#!/usr/bin/env python3
"""Traduction automatique de la documentation.

La langue source est le français (``docs/fr``). Chaque langue cible est écrite
dans ``docs/<code>`` en conservant l'arborescence et les noms de fichiers
(``docs/fr/typing.md`` -> ``docs/en/typing.md``).

Le script fonctionne avec n'importe quelle API compatible OpenAI
(DeepSeek, OpenAI, Mistral, Ollama...) ou sans réseau grâce au mode
``--provider copy``, utile pour amorcer une nouvelle langue.

Exemples
--------
Traduire ce qui manque ou ce qui a changé pour l'anglais ::

    export LLM_API_KEY=...
    python scripts/translate.py --lang en

Tout retraduire pour toutes les langues déclarées ::

    python scripts/translate.py --lang all --force

Vérifier en CI qu'aucune traduction n'est manquante (ne nécessite pas de clé) ::

    python scripts/translate.py --lang all --check

Amorcer une nouvelle langue sans API (copie du français) ::

    python scripts/translate.py --lang es --provider copy
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOCS = ROOT / "docs"
SOURCE_LANG = "fr"
SOURCE_DIR = DOCS / SOURCE_LANG
LANGUAGES_FILE = DOCS / "language_names.yml"
PROMPTS_DIR = Path(__file__).resolve().parent / "prompts"
MANIFEST_NAME = ".manifest.json"
MAX_ATTEMPTS = 3


# ---------------------------------------------------------------------------
# Langues
# ---------------------------------------------------------------------------

@dataclass(frozen=True)
class Language:
    code: str
    name: str
    default: bool = False


def load_languages(path: Path = LANGUAGES_FILE) -> list[Language]:
    """Analyse ``docs/language_names.yml`` (format volontairement simple)."""
    if not path.is_file():
        raise SystemExit(f"Fichier de langues introuvable : {path}")

    languages: list[Language] = []
    current: dict[str, object] | None = None

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.split("#", 1)[0].rstrip()
        if not line.strip():
            continue

        match = re.match(r"^([a-z]{2}(?:-[A-Z]{2})?):\s*$", line)
        if match:
            if current is not None:
                languages.append(Language(**current))  # type: ignore[arg-type]
            current = {"code": match.group(1), "name": match.group(1), "default": False}
            continue

        name = re.match(r"^\s+name:\s*(.+?)\s*$", line)
        if name and current is not None:
            current["name"] = name.group(1).strip().strip("'\"")
            continue

        default = re.match(r"^\s+default:\s*(true|false)\s*$", line)
        if default and current is not None:
            current["default"] = default.group(1) == "true"

    if current is not None:
        languages.append(Language(**current))  # type: ignore[arg-type]

    return languages


def resolve_languages(requested: str) -> list[Language]:
    languages = [lang for lang in load_languages() if lang.code != SOURCE_LANG]

    if requested == "all":
        return languages

    selected = [lang for lang in languages if lang.code == requested]
    if not selected:
        known = ", ".join(lang.code for lang in languages) or "aucune"
        raise SystemExit(f"Langue inconnue : {requested} (disponibles : {known})")

    return selected


# ---------------------------------------------------------------------------
# Fichiers source
# ---------------------------------------------------------------------------

def sources() -> list[Path]:
    """Tous les fichiers Markdown à traduire, triés, en relatif à docs/fr."""
    if not SOURCE_DIR.is_dir():
        raise SystemExit(f"Dossier source introuvable : {SOURCE_DIR}")
    return sorted(path.relative_to(SOURCE_DIR) for path in SOURCE_DIR.rglob("*.md"))


def source_hash(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read_manifest(target_dir: Path) -> dict[str, dict[str, str]]:
    manifest = target_dir / MANIFEST_NAME
    if not manifest.is_file():
        return {}
    try:
        return json.loads(manifest.read_text(encoding="utf-8")).get("files", {})
    except json.JSONDecodeError:
        return {}


def write_manifest(target_dir: Path, files: dict[str, dict[str, str]]) -> None:
    target_dir.mkdir(parents=True, exist_ok=True)
    payload = {"source_language": SOURCE_LANG, "files": dict(sorted(files.items()))}
    (target_dir / MANIFEST_NAME).write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def needs_translation(
    relative: Path,
    language: Language,
    manifest: dict[str, dict[str, str]],
    force: bool,
) -> str | None:
    """Retourne la raison de traduire, ou ``None`` si la traduction est à jour."""
    target = DOCS / language.code / relative

    if force:
        return "demandé (--force)"
    if not target.is_file():
        return "fichier manquant"
    if relative.as_posix() not in manifest:
        return "absent du manifeste"
    if manifest[relative.as_posix()].get("source_hash") != source_hash(SOURCE_DIR / relative):
        return "source modifiée"

    return None


# ---------------------------------------------------------------------------
# Traduction
# ---------------------------------------------------------------------------

def load_prompt_template() -> str:
    path = PROMPTS_DIR / "translate.md"
    if not path.is_file():
        raise SystemExit(f"Prompt introuvable : {path}")
    return path.read_text(encoding="utf-8")


def load_glossary() -> str:
    path = PROMPTS_DIR / "glossary.md"
    return path.read_text(encoding="utf-8") if path.is_file() else ""


def build_prompt(template: str, glossary: str, language: Language, source: str) -> str:
    return (
        template.replace("{{source_language}}", "French")
        .replace("{{target_language}}", language.name)
        .replace("{{target_code}}", language.code)
        .replace("{{glossary}}", glossary)
        .replace("{{source}}", source)
    )


def call_llm(prompt: str) -> str:
    api_key = os.environ.get("LLM_API_KEY") or os.environ.get("OPENAI_API_KEY")
    if not api_key:
        raise SystemExit(
            "Aucune clé API : définissez LLM_API_KEY (ou OPENAI_API_KEY), "
            "ou utilisez --provider copy / --dry-run."
        )

    base_url = os.environ.get("LLM_BASE_URL", "https://api.deepseek.com/v1").rstrip("/")
    model = os.environ.get("LLM_MODEL", "deepseek-chat")

    payload = json.dumps(
        {
            "model": model,
            "temperature": 0.2,
            "messages": [{"role": "user", "content": prompt}],
        }
    ).encode("utf-8")

    request = urllib.request.Request(
        f"{base_url}/chat/completions",
        data=payload,
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}",
        },
        method="POST",
    )

    try:
        with urllib.request.urlopen(request, timeout=600) as response:
            data = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:  # pragma: no cover - réseau
        detail = error.read().decode("utf-8", errors="replace")
        raise SystemExit(f"Erreur API ({error.code}) : {detail}") from error
    except urllib.error.URLError as error:  # pragma: no cover - réseau
        raise SystemExit(f"API injoignable : {error.reason}") from error

    try:
        content = data["choices"][0]["message"]["content"]
    except (KeyError, IndexError) as error:  # pragma: no cover - réponse inattendue
        raise SystemExit(f"Réponse API inattendue : {data}") from error

    return str(content).strip() + "\n"


# ---------------------------------------------------------------------------
# Contrôles de qualité
# ---------------------------------------------------------------------------

def count_headings(text: str) -> list[str]:
    return [line for line in text.splitlines() if line.startswith("#")]


def check_translation(source: str, translated: str) -> list[str]:
    """Contrôles structurels : le code et la structure Markdown doivent survivre."""
    errors: list[str] = []

    if source.count("```") != translated.count("```"):
        errors.append(
            f"blocs de code déséquilibrés (source {source.count('```')}, "
            f"traduction {translated.count('```')})"
        )

    if len(count_headings(source)) != len(count_headings(translated)):
        errors.append(
            f"nombre de titres différent (source {len(count_headings(source))}, "
            f"traduction {len(count_headings(translated))})"
        )

    if source.count("!!!") != translated.count("!!!"):
        errors.append("admonitions (!!! note/tip/warning/example) manquantes")

    if "{{" in translated or "%%%" in translated:
        errors.append("placeholders du prompt laissés dans la sortie")

    if len(translated.strip()) < len(source.strip()) * 0.5:
        errors.append("traduction anormalement courte")

    return errors


def translate_file(relative: Path, language: Language, provider: str) -> tuple[str, list[str]]:
    source_path = SOURCE_DIR / relative
    source = source_path.read_text(encoding="utf-8")

    if provider == "copy":
        return source, []

    template = load_prompt_template()
    glossary = load_glossary()
    problems: list[str] = []
    translated = ""

    for attempt in range(1, MAX_ATTEMPTS + 1):
        prompt = build_prompt(template, glossary, language, source)
        if problems:
            prompt += (
                "\n\nIMPORTANT: la tentative précédente a échoué sur ces contrôles : "
                + "; ".join(problems)
                + ". Corrige-les tout en traduisant l'intégralité du document."
            )

        translated = call_llm(prompt)
        problems = check_translation(source, translated)

        if not problems:
            return translated, []

        print(f"    tentative {attempt}/{MAX_ATTEMPTS} invalide : {', '.join(problems)}", file=sys.stderr)
        time.sleep(1)

    return translated, problems


# ---------------------------------------------------------------------------
# Commandes
# ---------------------------------------------------------------------------

def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Traduit docs/fr vers les langues cibles.")
    parser.add_argument("--lang", default="en", help="code de langue, ou 'all'")
    parser.add_argument("--force", action="store_true", help="retraduire même si à jour")
    parser.add_argument("--check", action="store_true", help="vérifier sans rien écrire")
    parser.add_argument("--dry-run", action="store_true", help="afficher le plan sans écrire")
    parser.add_argument(
        "--provider",
        choices=["api", "copy"],
        default="api",
        help="'api' : modèle via LLM_API_KEY ; 'copy' : copie la source (amorçage)",
    )
    parser.add_argument("--only", default=None, help="ne traiter qu'un fichier (ex. typing.md)")
    parser.add_argument(
        "--adopt",
        action="store_true",
        help="enregistrer les traductions existantes dans le manifeste sans appeler l'API",
    )
    args = parser.parse_args(argv)

    languages = resolve_languages(args.lang)
    paths = sources()
    if args.only:
        paths = [path for path in paths if path.as_posix() == args.only]
        if not paths:
            raise SystemExit(f"Fichier source introuvable dans docs/{SOURCE_LANG} : {args.only}")

    exit_code = 0
    failures: list[str] = []

    for language in languages:
        target_dir = DOCS / language.code
        manifest = read_manifest(target_dir)

        if args.adopt:
            adopted = 0
            for relative in paths:
                target = target_dir / relative
                if not target.is_file():
                    continue
                manifest[relative.as_posix()] = {
                    "source_hash": source_hash(SOURCE_DIR / relative),
                    "translated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                }
                adopted += 1
            write_manifest(target_dir, manifest)
            print(f"[{language.code}] {adopted} traduction(s) enregistrée(s) dans le manifeste.")
            continue

        todo = []
        for relative in paths:
            reason = needs_translation(relative, language, manifest, args.force)
            if reason:
                todo.append((relative, reason))

        if args.check:
            for relative, reason in todo:
                print(f"[{language.code}] {relative} : {reason}")
            if todo:
                exit_code = 1
            else:
                print(f"[{language.code}] à jour ({len(paths)} fichiers).")
            continue

        if not todo:
            print(f"[{language.code}] à jour ({len(paths)} fichiers).")
            continue

        print(f"[{language.code}] {len(todo)} fichier(s) à traduire.")

        for relative, reason in todo:
            print(f"  -> {relative} ({reason})")

            if args.dry_run:
                continue

            translated, problems = translate_file(relative, language, args.provider)
            target = target_dir / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(translated, encoding="utf-8", newline="\n")

            if problems:
                failures.append(f"{language.code}/{relative} : {', '.join(problems)}")
                print(f"    ⚠ contrôles en échec : {', '.join(problems)}")

            manifest[relative.as_posix()] = {
                "source_hash": source_hash(SOURCE_DIR / relative),
                "translated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            }
            write_manifest(target_dir, manifest)

    if failures:
        print("\nTraductions à relire :", file=sys.stderr)
        for failure in failures:
            print(f"  - {failure}", file=sys.stderr)
        exit_code = 1

    return exit_code


if __name__ == "__main__":
    sys.exit(main())
