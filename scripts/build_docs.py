#!/usr/bin/env python3
"""Construit le site de documentation MkDocs : `docs/` → `build/`.

Le site est construit par [MkDocs](https://www.mkdocs.org/) avec le thème
Material et le plugin de traduction (`mkdocs-static-i18n`). Le résultat est un
site statique autonome, publié tel quel sur GitHub Pages.

Le script :

1. vérifie que MkDocs est disponible (interpréteur courant, puis `.venv-docs/`) ;
2. nettoie `build/` ;
3. lance `mkdocs build --site-dir build` ;
4. vérifie que `build/index.html` existe et résume la sortie.

Exemples
--------
Construire le site ::

    python scripts/build_docs.py

Construire en mode strict (un avertissement = échec, pour la CI) ::

    python scripts/build_docs.py --strict

Installer les dépendances dans un environnement local `.venv-docs/` puis
construire ::

    python scripts/build_docs.py --install

Surveiller les changements et servir le site ::

    ./scripts/docs_start.sh            # http://localhost:4000
"""

from __future__ import annotations

import argparse
import os
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONFIG = ROOT / "mkdocs.yml"
DOCS = ROOT / "docs"
DEFAULT_OUTPUT = ROOT / "build"
VENV = ROOT / ".venv-docs"

REQUIREMENTS_FILE = ROOT / "requirements-docs.txt"
REQUIREMENTS = ("mkdocs-material", "mkdocs-static-i18n")


# ---------------------------------------------------------------------------
# Environnement Python
# ---------------------------------------------------------------------------

def mkdocs_command(python: str) -> list[str]:
    return [python, "-m", "mkdocs"]


def module_available(python: str) -> bool:
    """Vrai si `mkdocs` (et le thème) sont importables par cet interpréteur."""
    try:
        result = subprocess.run(
            [python, "-c", "import mkdocs, material, mkdocs_static_i18n"],
            capture_output=True,
            text=True,
        )
    except FileNotFoundError:
        return False

    return result.returncode == 0


def pick_python(install: bool) -> str:
    """Choisit l'interpréteur qui sait faire tourner MkDocs."""
    candidates = [sys.executable]

    if VENV.is_dir():
        candidates.insert(0, str(VENV / "bin" / "python"))

    for python in candidates:
        if module_available(python):
            return python

    if not install:
        raise SystemExit(
            "MkDocs n'est pas installé.\n"
            "  python scripts/build_docs.py --install    # crée .venv-docs/ et installe les dépendances\n"
            "ou\n"
            f"  python3 -m pip install -r {REQUIREMENTS_FILE.name}"
        )

    create_venv()
    return str(VENV / "bin" / "python")


def create_venv() -> None:
    if not (VENV / "bin" / "python").exists():
        print(f"→ création de l'environnement {VENV.relative_to(ROOT)}/")
        subprocess.run([sys.executable, "-m", "venv", str(VENV)], check=True)

    target = [f"-r{REQUIREMENTS_FILE}"] if REQUIREMENTS_FILE.is_file() else list(REQUIREMENTS)
    print("→ installation de " + " ".join(target))
    subprocess.run(
        [str(VENV / "bin" / "python"), "-m", "pip", "install", "--upgrade", "pip", "-q"],
        check=True,
    )
    subprocess.run(
        [str(VENV / "bin" / "python"), "-m", "pip", "install", *target, "-q"],
        check=True,
    )


# ---------------------------------------------------------------------------
# Construction
# ---------------------------------------------------------------------------

def check_inputs() -> None:
    if not CONFIG.is_file():
        raise SystemExit(f"Configuration introuvable : {CONFIG}")
    if not DOCS.is_dir():
        raise SystemExit(f"Dossier de documentation introuvable : {DOCS}")
    if not any((DOCS / SOURCE).is_dir() for SOURCE in ("fr", "en")):
        raise SystemExit("Aucune page : docs/fr/ (ou docs/en/) est absent")


def summarize(output: Path) -> None:
    index = output / "index.html"
    if not index.is_file():
        raise SystemExit(f"Échec : {index.relative_to(ROOT)} n'a pas été généré")

    files = [path for path in output.rglob("*") if path.is_file()]
    pages = [path for path in output.rglob("*.html")]
    size = sum(path.stat().st_size for path in files) / 1024

    print()
    print(f"Site généré : {output.relative_to(ROOT)}/")
    print(f"  {len(pages)} pages HTML, {len(files)} fichiers, {size:.0f} Ko")
    print(f"  entrée : {index.relative_to(ROOT)}")


def build(strict: bool = False, output: Path = DEFAULT_OUTPUT, clean: bool = True, install: bool = False) -> int:
    check_inputs()
    python = pick_python(install)

    if clean and output.exists():
        shutil.rmtree(output)

    command = mkdocs_command(python) + ["build", "--site-dir", str(output)]

    if strict:
        command.append("--strict")

    print("→ " + " ".join(command))

    env = dict(os.environ)
    env.setdefault("PYTHONUTF8", "1")

    result = subprocess.run(command, cwd=ROOT, env=env)

    if result.returncode != 0:
        return result.returncode

    summarize(output)

    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Construit le site MkDocs (docs/ → build/).")
    parser.add_argument("--strict", action="store_true", help="un avertissement MkDocs devient une erreur (CI)")
    parser.add_argument("--install", action="store_true", help="crée .venv-docs/ et installe MkDocs si nécessaire")
    parser.add_argument("--dir", default=str(DEFAULT_OUTPUT), help="dossier de sortie (défaut : build)")
    parser.add_argument("--no-clean", action="store_true", help="ne supprime pas le dossier de sortie")
    args = parser.parse_args(argv)

    try:
        return build(
            strict=args.strict,
            output=Path(args.dir).resolve(),
            clean=not args.no_clean,
            install=args.install,
        )
    except subprocess.CalledProcessError as error:
        print(f"Échec de la commande : {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
