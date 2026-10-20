# Scripts

Outillage du dépôt : génération du site de documentation et traduction.

## `build_docs_site.py`

Convertit le cours (`docs/fr`, navigation `mkdocs.yml`) en site statique dans
`docs-template/` :

- copie des pages vers `docs-template/content/<langue>/` ;
- conversion du Markdown Material : `!!! note "Titre"` → plugin `callout`,
  liens `page.md` → routes `#/langue/page` ;
- génération de `docs-template/config/nav.json` (groupes, icônes, ordre) ;
- personnalisation de `docs-template/config/site.json` (marque, langues, footer) ;
- manifeste `.docs-build.json` (SHA-256) pour détecter les dérives.

```bash
python scripts/build_docs_site.py            # génère / met à jour
python scripts/build_docs_site.py --check    # code retour 1 si dérive (CI)
python scripts/build_docs_site.py --dry-run  # affiche sans écrire
python scripts/build_docs_site.py --no-prune # garde les pages obsolètes
```

## `docs_start.sh`

Génère le site puis le sert localement (PHP, Python 3 ou `npx serve`) :

```bash
./scripts/docs_start.sh            # http://localhost:4000
./scripts/docs_start.sh 8080       # autre port
./scripts/docs_start.sh --no-build # sert le site déjà généré
./scripts/docs_start.sh --check    # vérifie que le site est à jour
```

## Workflows

| Fichier | Rôle |
| --- | --- |
| `.github/workflows/docs.yml` | génère le site, le publie sur GitHub Pages et propose une PR avec `docs-template/` |
| `.github/workflows/translate.yml` | traduit `docs/fr` vers les autres langues (LLM) |
| `.github/workflows/tests.yml` | `composer test` |

**GitHub Pages** — activez Pages dans *Settings → Pages → Source : GitHub
Actions*. Le site est publié par le job `deploy` du workflow
`Documentation site`, à partir de l'artefact `_site` (copie de
`docs-template/`).

## `translate.py`

Traduit la documentation de `docs/fr` vers les langues déclarées dans
`docs/language_names.yml` (`docs/en`, puis les langues ajoutées au besoin).

```bash
# Traduire ce qui manque ou ce qui a changé
LLM_API_KEY=sk-... python scripts/translate.py --lang en

# Toutes les langues, tout retraduire
LLM_API_KEY=sk-... python scripts/translate.py --lang all --force

# Vérifier sans clé API (code retour 1 si une page manque)
python scripts/translate.py --lang all --check

# Voir le plan sans rien écrire
python scripts/translate.py --lang en --dry-run

# Amorcer une nouvelle langue sans API (copie du français)
python scripts/translate.py --lang es --provider copy
```

### Variables d'environnement

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `LLM_API_KEY` (ou `OPENAI_API_KEY`) | — | Clé de l'API compatible OpenAI |
| `LLM_BASE_URL` | `https://api.deepseek.com/v1` | Endpoint (`.../chat/completions`) |
| `LLM_MODEL` | `deepseek-chat` | Modèle utilisé |

Fonctionne avec DeepSeek, OpenAI, Mistral, Groq, Ollama en local, etc. : tout
service exposant `POST /chat/completions`.

### Fonctionnement

1. lecture de `docs/fr/**/*.md` ;
2. comparaison avec `docs/<lang>/.manifest.json` (empreinte SHA-256 de la source) ;
3. pour chaque page manquante ou modifiée : appel du modèle avec
   `scripts/prompts/translate.md` ;
4. contrôles automatiques (blocs de code, titres, admonitions, longueur) avec
   jusqu'à 3 tentatives ;
5. écriture de `docs/<lang>/<page>.md` et mise à jour du manifeste.

Le prompt impose de **copier les blocs de code à l'identique** : ils
correspondent aux fichiers de `docs_src/`, eux-mêmes exécutés par
`tests/ExamplesTest.php`.

### Ajouter une langue

1. ajoutez le code dans `docs/language_names.yml` (et dans `mkdocs.yml`) ;
2. ajoutez éventuellement sa colonne dans `scripts/prompts/glossary.md` ;
3. lancez `python scripts/translate.py --lang <code>`.

## Prompts

- `prompts/translate.md` : prompt de traduction (règles, structure, style) ;
- `prompts/glossary.md` : terminologie de référence, injectée via `{{glossary}}`.

## Documentation

Le cours français est la source de vérité : `docs/fr/`. Le site est construit
avec MkDocs Material :

```bash
pip install mkdocs-material mkdocs-static-i18n
mkdocs serve
```

## Tests

Les exemples de `docs_src/` sont exécutés par la suite de tests :

```bash
composer test          # phpunit
./vendor/bin/phpunit --filter ExamplesTest
```
