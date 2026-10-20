#!/usr/bin/env python3
"""Génère le site de documentation (`docs-template/`) à partir de `docs/fr`.

Le site statique de `docs-template/` est un template HTML/CSS/JS sans build :
il lit `config/site.json`, `config/nav.json` et `content/<langue>/*.md` via
`fetch()`. Ce script fait le pont entre le cours (sources Markdown `docs/fr`,
navigation `mkdocs.yml`) et ce dossier :

1. copie chaque page de `docs/<langue>/` dans `docs-template/content/<langue>/` ;
2. convertit le Markdown Material vers le Markdown du template
   (admonitions `!!! note` → plugin `callout`, liens `.md` → routes `#/langue/id`) ;
3. génère `docs-template/config/nav.json` depuis le `nav` de `mkdocs.yml` ;
4. personnalise `docs-template/config/site.json` (marque, langues, footer) ;
5. écrit un manifeste `.docs-build.json` (empreintes SHA-256) pour détecter
   les dérives.

Exemples
--------
Synchroniser le site (ce que fait la CI avant de publier) ::

    python scripts/build_docs_site.py

Vérifier sans écrire (code retour 1 si le site n'est plus à jour) ::

    python scripts/build_docs_site.py --check

Voir le détail sans toucher aux fichiers ::

    python scripts/build_docs_site.py --dry-run
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOCS = ROOT / "docs"
TEMPLATE = ROOT / "docs-template"
MKDOCS = ROOT / "mkdocs.yml"
MANIFEST_NAME = ".docs-build.json"

SOURCE_LANG = "fr"
# Langues gérées : la source (fr) puis les traductions présentes dans docs/.
KNOWN_LANGS = ("fr", "en")

REPO_URL = "https://github.com/EORBAH/EorbahAPI"
REPO_BRANCH = "develop"
SITE_URL = "https://eorbah.github.io/EorbahAPI/"

LANG_META = {
    "fr": {"label": "Français", "flag": "🇫🇷"},
    "en": {"label": "English", "flag": "🇬🇧"},
    "es": {"label": "Español", "flag": "🇪🇸"},
    "pt": {"label": "Português", "flag": "🇵🇹"},
    "de": {"label": "Deutsch", "flag": "🇩🇪"},
    "ko": {"label": "한국어", "flag": "🇰🇷"},
}

# `!!! <type>` (Material) → type de callout du template.
ADMONITION_TYPES = {
    "note": "info",
    "info": "info",
    "abstract": "info",
    "summary": "info",
    "tip": "success",
    "hint": "success",
    "important": "info",
    "success": "success",
    "check": "success",
    "question": "info",
    "warning": "warning",
    "caution": "warning",
    "attention": "warning",
    "danger": "danger",
    "failure": "danger",
    "fail": "danger",
    "bug": "danger",
    "error": "danger",
    "example": "info",
    "quote": "info",
}

# Groupes écrits avant le `nav` de mkdocs.yml (pages de tête sans section).
INTRO_GROUP = {
    "id": "accueil",
    "titles": {"fr": "Accueil", "en": "Home"},
    "icon": "ti ti-home",
}

GROUP_ICONS = {
    "accueil": "ti ti-home",
    "guide-du-cours": "ti ti-book-2",
    "aller-plus-loin": "ti ti-rocket",
    "reference": "ti ti-api",
    "ressources": "ti ti-lifebuoy",
}

# Un titre de page de tête contenant ce mot devient son propre groupe.
REFERENCE_KEYWORDS = ("référence", "reference")

EN_GROUP_TITLES = {
    "Accueil": "Home",
    "Guide du cours": "Course guide",
    "Aller plus loin": "Going further",
    "Référence": "Reference",
    "Ressources": "Resources",
}

PAGE_ICONS = {
    "index": "ti ti-home",
    "installation": "ti ti-download",
    "reference": "ti ti-api",
    "first-steps": "ti ti-flag-3",
}


# ---------------------------------------------------------------------------
# Utilitaires
# ---------------------------------------------------------------------------

def slugify(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[àâä]", "a", text)
    text = re.sub(r"[éèêë]", "e", text)
    text = re.sub(r"[îï]", "i", text)
    text = re.sub(r"[ôö]", "o", text)
    text = re.sub(r"[ùûü]", "u", text)
    text = re.sub(r"[ç]", "c", text)
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def digest(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def available_languages() -> list[str]:
    return [lang for lang in KNOWN_LANGS if (DOCS / lang).is_dir()]


# ---------------------------------------------------------------------------
# Lecture du `nav` de mkdocs.yml
# ---------------------------------------------------------------------------

def parse_mkdocs_nav(path: Path) -> list[dict]:
    """Retourne la liste des entrées du `nav` : pages et groupes imbriqués."""
    if not path.is_file():
        raise SystemExit(f"Fichier introuvable : {path}")

    lines = path.read_text(encoding="utf-8").splitlines()
    entries: list[dict] = []
    stack: list[tuple[int, dict]] = []
    in_nav = False

    for raw in lines:
        if re.match(r"^nav:\s*$", raw):
            in_nav = True
            continue

        if not in_nav:
            continue

        if not raw.strip():
            continue

        if not raw.startswith((" ", "-")):
            break  # fin de la section nav

        match = re.match(r"^(\s*)-\s+(.+?)\s*$", raw)
        if not match:
            continue

        indent = len(match.group(1))
        payload = match.group(2)

        page = re.match(r"^(.+?):\s+(\S+\.md)\s*$", payload)
        section = re.match(r"^(.+?):\s*$", payload)

        if page:
            entry = {"title": page.group(1).strip("'\""), "file": page.group(2)}
        elif section:
            entry = {"title": section.group(1).strip("'\""), "items": []}
        else:
            continue

        while stack and stack[-1][0] >= indent:
            stack.pop()

        if stack:
            stack[-1][1]["items"].append(entry)
        else:
            entries.append(entry)

        if "items" in entry:
            stack.append((indent, entry))

    if not entries:
        raise SystemExit(f"Aucune entrée `nav` lisible dans {path}")

    return entries


def group_id(title: str) -> str:
    return slugify(title)


def group_title(title: str, lang: str) -> str:
    if lang == "en":
        return EN_GROUP_TITLES.get(title, title)
    return title


# ---------------------------------------------------------------------------
# Génération du nav
# ---------------------------------------------------------------------------

def build_nav(entries: list[dict], lang: str, files: set[str]) -> list[dict]:
    """Transforme le `nav` MkDocs en groupes `nav.json` du template."""
    intro = {
        "id": INTRO_GROUP["id"],
        "title": INTRO_GROUP["titles"].get(lang, INTRO_GROUP["titles"][SOURCE_LANG]),
        "icon": INTRO_GROUP["icon"],
        "inSidebar": True,
        "items": [],
    }
    groups: list[dict] = [intro]

    for entry in entries:
        if "file" in entry:
            item = make_item(entry, lang, files)

            if any(word in entry["title"].lower() for word in REFERENCE_KEYWORDS):
                groups.append({
                    "id": "reference",
                    "title": group_title("Référence", lang),
                    "icon": GROUP_ICONS["reference"],
                    "inSidebar": True,
                    "items": [item],
                })
                continue

            intro["items"].append(item)
            continue

        gid = group_id(entry["title"])
        group = {
            "id": gid,
            "title": group_title(entry["title"], lang),
            "icon": GROUP_ICONS.get(gid, "ti ti-folder"),
            "inSidebar": True,
            "items": [make_item(item, lang, files) for item in entry["items"]],
        }

        if not group["items"]:
            continue

        groups.append(group)

    groups = [group for group in groups if group["items"]]

    if not groups:
        raise SystemExit("Aucune page dans le nav : vérifiez docs/fr et mkdocs.yml")

    return groups


def make_item(entry: dict, lang: str, files: set[str]) -> dict:
    filename = entry["file"]
    if filename not in files:
        raise SystemExit(
            f"{lang} : la page '{filename}' est référencée par mkdocs.yml mais absente de docs/{lang}/"
        )

    page_id = Path(filename).stem
    title = entry["title"]
    if lang == "en":
        title = EN_GROUP_TITLES.get(title, title)

    item = {
        "id": page_id,
        "title": title,
        "icon": PAGE_ICONS.get(page_id, "ti ti-file-text"),
        "file": filename,
    }

    return item


# ---------------------------------------------------------------------------
# Conversion du Markdown
# ---------------------------------------------------------------------------

def convert_admonitions(text: str) -> str:
    """`!!! note "Titre"` + corps indenté → `:::callout{type=... title=...}`."""
    lines = text.splitlines()
    out: list[str] = []
    index = 0

    while index < len(lines):
        match = re.match(r'^!!!\s+(\w+)(?:\s+"(.*)")?\s*$', lines[index])

        if not match:
            out.append(lines[index])
            index += 1
            continue

        kind = ADMONITION_TYPES.get(match.group(1).lower(), "info")
        title = match.group(2)
        index += 1

        body: list[str] = []
        while index < len(lines):
            line = lines[index]

            if line.strip() == "":
                lookahead = index
                while lookahead < len(lines) and lines[lookahead].strip() == "":
                    lookahead += 1

                if lookahead < len(lines) and lines[lookahead].startswith("    "):
                    body.append("")
                    index += 1
                    continue
                break

            if not line.startswith("    "):
                break

            body.append(line[4:])
            index += 1

        attributes = f'type="{kind}"'
        if title:
            attributes += f' title="{title}"'

        out.append(f":::callout{{{attributes}}}")
        out.extend(body)
        out.append(":::")

    return "\n".join(out) + ("\n" if text.endswith("\n") else "")


def convert_links(text: str, lang: str) -> str:
    """Liens `.md` → routes du SPA ; boutons Material → liens simples."""
    text = re.sub(r"\{\.md-button\}", "", text)
    text = re.sub(r"\{\s*\.md-button\s*\}", "", text)

    def to_route(match: re.Match[str]) -> str:
        target, anchor = match.group(1), match.group(2) or ""
        page_id = Path(target).stem

        if not anchor:
            return f"](#/{lang}/{page_id})"

        return f"](#/{lang}/{page_id}/{anchor.lstrip('#')})"

    text = re.sub(r"\]\(([\w./-]+\.md)(#[^)\s]+)?\)", to_route, text)

    # Liens vers le dépôt (fichiers d'exemple, sources).
    text = re.sub(
        r"\]\(((?:\.\./)*(?:docs_src|src|tests)/[^)\s]+)\)",
        lambda m: f"]({REPO_URL}/blob/{REPO_BRANCH}/{m.group(1).replace('../', '')})",
        text,
    )

    return text


def convert_markdown(text: str, lang: str) -> str:
    return convert_links(convert_admonitions(text), lang)


# ---------------------------------------------------------------------------
# Construction
# ---------------------------------------------------------------------------

def build_pages(lang: str) -> dict[str, str]:
    """Retourne {nom_fichier: contenu converti} pour une langue."""
    source = DOCS / lang
    if not source.is_dir():
        return {}

    pages: dict[str, str] = {}
    for path in sorted(source.glob("*.md")):
        pages[path.name] = convert_markdown(path.read_text(encoding="utf-8"), lang)

    return pages


def patch_site_config(config: dict, languages: list[str]) -> dict:
    """Personnalise le site pour EorbahAPI (idempotent)."""
    config.setdefault("brand", {})
    config["brand"]["name"] = "EorbahAPI"
    config["brand"]["logo"] = config["brand"].get("logo", "")

    config["defaultLanguage"] = languages[0] if languages else SOURCE_LANG
    config["languages"] = [
        {"id": lang, **LANG_META.get(lang, {"label": lang, "flag": ""})} for lang in languages
    ]

    # Référencement : valeurs par défaut statiques de index.html + js/seo.js.
    seo = config.setdefault("seo", {})
    seo["siteUrl"] = seo.get("siteUrl") or SITE_URL
    seo["titleTemplate"] = "{title} — EorbahAPI"
    seo["titleDefault"] = "EorbahAPI — Documentation"
    seo["description"] = (
        "Cours complet du framework web PHP EorbahAPI : routage, paramètres "
        "validés par Svinx, sécurité, middlewares, tests et déploiement."
    )
    seo["keywords"] = [
        "EorbahAPI", "PHP", "framework", "API", "routage", "validation",
        "Svinx", "middlewares", "tests", "documentation",
    ]
    seo["author"] = "EORBAH"
    seo["locale"] = {"fr": "fr_FR", "en": "en_US"}.get(config.get("defaultLanguage", "fr"), "fr_FR")
    seo["robots"] = "index,follow"
    seo["repo"] = REPO_URL
    seo.setdefault("ogImage", "")
    seo.setdefault("twitter", "")

    footer = config.setdefault("footer", {})
    footer["tagline"] = "Framework web PHP pour construire des API : routes déclaratives, paramètres validés, pipeline testable."

    social = list(footer.get("social", []))
    if social:
        social[0] = {"icon": "ti ti-brand-github", "url": REPO_URL, "label": "GitHub"}
    else:
        social = [{"icon": "ti ti-brand-github", "url": REPO_URL, "label": "GitHub"}]
    footer["social"] = social

    footer["corporate"] = {
        "name": "EORBAH",
        "line": "© 2026 EORBAH. Documentation sous licence MIT.",
    }

    return config


def build(dry_run: bool = False, check: bool = False, prune: bool = True) -> int:
    languages = available_languages()
    if not languages:
        raise SystemExit("Aucune documentation trouvée dans docs/ (dossier docs/fr absent ?)")

    if not TEMPLATE.is_dir():
        raise SystemExit(f"Template introuvable : {TEMPLATE}")

    entries = parse_mkdocs_nav(MKDOCS)
    planned: dict[Path, str] = {}
    manifest: dict[str, dict] = {"languages": languages, "pages": {}}
    nav_all: dict[str, list] = {}

    for lang in languages:
        pages = build_pages(lang)
        if not pages:
            continue

        nav_all[lang] = build_nav(entries, lang, set(pages))

        for name, content in pages.items():
            planned[TEMPLATE / "content" / lang / name] = content
            manifest["pages"][f"{lang}/{name}"] = {
                "source": f"docs/{lang}/{name}",
                "sha256": digest(content),
            }

    site_path = TEMPLATE / "config" / "site.json"
    site = json.loads(site_path.read_text(encoding="utf-8"))
    site = patch_site_config(site, languages)
    planned[site_path] = json.dumps(site, ensure_ascii=False, indent=2) + "\n"

    # nav.json : structure à plusieurs langues attendue par le template.
    planned[TEMPLATE / "config" / "nav.json"] = json.dumps(nav_all, ensure_ascii=False, indent=2) + "\n"

    # Référencement : robots.txt + sitemap.xml (une URL par page et par langue).
    base = str(site.get("seo", {}).get("siteUrl") or SITE_URL).rstrip("/")
    urls: list[str] = []
    for lang, groups in nav_all.items():
        for group in groups:
            for item in group["items"]:
                if item["id"] == "index":
                    urls.append(f"{base}/#/{lang}/{item['id']}")
                else:
                    urls.append(f"{base}/#/{lang}/{item['id']}")

    sitemap = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ]
    for url in urls:
        sitemap.append(f"  <url><loc>{url}</loc></url>")
    sitemap.append("</urlset>")

    planned[TEMPLATE / "sitemap.xml"] = "\n".join(sitemap) + "\n"
    planned[TEMPLATE / "robots.txt"] = (
        "User-agent: *\n"
        "Allow: /\n"
        f"Sitemap: {base}/sitemap.xml\n"
    )

    planned[Path(TEMPLATE / MANIFEST_NAME)] = json.dumps(manifest, ensure_ascii=False, indent=2) + "\n"

    # Fichiers obsolètes à supprimer (pages disparues, nav.<lang>.json temporaires).
    stale: list[Path] = []

    # Langues gérées : pages non régénérées = obsolètes.
    for lang in KNOWN_LANGS:
        directory = TEMPLATE / "content" / lang
        if not directory.is_dir():
            continue

        if lang not in languages:
            # Langue déclarée mais pas encore traduite : on retire les pages du
            # modèle pour ne pas publier de contenu d'exemple.
            stale.extend(sorted(directory.glob("*.md")))
            continue

        for existing in directory.glob("*.md"):
            if existing not in planned:
                stale.append(existing)

    errors = 0
    if check:
        for path, content in planned.items():
            if not path.is_file():
                print(f"[check] manquant : {path.relative_to(ROOT)}")
                errors += 1
            elif path.read_text(encoding="utf-8") != content:
                print(f"[check] obsolète : {path.relative_to(ROOT)}")
                errors += 1

        for path in stale:
            print(f"[check] orphelin : {path.relative_to(ROOT)}")
            errors += 1

        if errors:
            print(f"\n{errors} fichier(s) à régénérer : lancez python scripts/build_docs_site.py")
            return 1

        print("Documentation à jour.")
        return 0

    for path, content in planned.items():
        if dry_run:
            print(f"[dry-run] écrirait {path.relative_to(ROOT)}")
            continue

        path.parent.mkdir(parents=True, exist_ok=True)
        if path.is_file() and path.read_text(encoding="utf-8") == content:
            continue
        path.write_text(content, encoding="utf-8")
        print(f"écrit   {path.relative_to(ROOT)}")

    if prune and not dry_run:
        for path in stale:
            path.unlink()
            print(f"supprimé {path.relative_to(ROOT)}")

    print(f"\nSite prêt : docs-template ({', '.join(languages)})")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Génère le site de documentation (docs-template) depuis docs/fr.",
    )
    parser.add_argument("--check", action="store_true", help="vérifie sans écrire (code retour 1 si dérive)")
    parser.add_argument("--dry-run", action="store_true", help="affiche les fichiers sans rien écrire")
    parser.add_argument("--no-prune", action="store_true", help="conserve les pages obsolètes")
    args = parser.parse_args(argv)

    return build(dry_run=args.dry_run, check=args.check, prune=not args.no_prune)


if __name__ == "__main__":
    sys.exit(main())
