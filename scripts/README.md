# Scripts

Outillage du dépôt : construction du site de documentation (MkDocs) et
traduction.

## `build_docs.py`

Construit le site **MkDocs** (`docs/` → `build/`) avec le thème Material :

- vérifie que MkDocs est disponible (interpréteur courant, puis `.venv-docs/`) ;
- nettoie `build/` ;
- lance `mkdocs build --site-dir build` et vérifie `build/index.html`.

```bash
python scripts/build_docs.py              # construit build/
python scripts/build_docs.py --strict     # un avertissement = échec (CI)
python scripts/build_docs.py --install    # crée .venv-docs/ et installe MkDocs
python scripts/build_docs.py --no-clean   # ne vide pas build/ avant
```

Dépendances Python : `requirements-docs.txt`
(`pip install -r requirements-docs.txt`).

## `docs_start.sh`

Sert la documentation en local avec `mkdocs serve` (rechargement à chaud) :

```bash
./scripts/docs_start.sh            # http://localhost:4000
./scripts/docs_start.sh 8080       # autre port
./scripts/docs_start.sh --build    # construit build/ puis quitte
./scripts/docs_start.sh --check    # construit en mode strict
```

## Workflows

| Fichier | Rôle |
| --- | --- |
| `.github/workflows/docs.yml` | construit le site MkDocs et le publie sur GitHub Pages |
| `.github/workflows/translate.yml` | traduit `docs/fr` vers les autres langues (LLM) |
| `.github/workflows/tests.yml` | `composer test` |

**GitHub Pages** — activez Pages dans *Settings → Pages → Source : GitHub
Actions*. Le job `deploy` publie `build/` à chaque push sur `main`.

## `translate.py` — traduction locale du cours

Traduit `docs/fr/` vers les langues déclarées dans `docs/language_names.yml`
(`docs/en`, puis les langues ajoutées au besoin). Le script tourne **en local** :
il n'est pas lancé à chaque push, vous décidez quand traduire.

```bash
# Traduire l'anglais (crée docs/en/ et docs/en/.manifest.json)
LLM_API_KEY=sk-... python scripts/translate.py --lang en

# Traduire toutes les langues déclarées
LLM_API_KEY=sk-... python scripts/translate.py --lang all

# Retraduire même les pages à jour
LLM_API_KEY=sk-... python scripts/translate.py --lang en --force

# Une seule page (incrémental)
LLM_API_KEY=sk-... python scripts/translate.py --lang en --only routing.md

# Vérifier sans clé API (code retour 1 si une page manque)
python scripts/translate.py --lang all --check

# Voir le plan sans rien écrire
python scripts/translate.py --lang en --dry-run

# Amorcer une langue sans API (copie le français, à retraduire ensuite)
python scripts/translate.py --lang en --provider copy
```

### Variables d'environnement

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `LLM_API_KEY` (ou `OPENAI_API_KEY`) | — | Clé de l'API compatible OpenAI |
| `LLM_BASE_URL` | `https://api.deepseek.com/v1` | Endpoint (`.../chat/completions`) |
| `LLM_MODEL` | `deepseek-chat` | Modèle utilisé |

Fonctionne avec DeepSeek, OpenAI, Mistral, Groq, un Ollama local, etc. : tout
service exposant `POST /chat/completions`.

### Fonctionnement

1. lecture de `docs/fr/**/*.md` (25 pages) ;
2. comparaison avec `docs/<lang>/.manifest.json` (empreinte SHA-256 de la source) ;
3. pour chaque page manquante ou modifiée : appel du modèle avec
   `scripts/prompts/translate.md` ;
4. contrôles automatiques (blocs de code, titres, admonitions, longueur) avec
   jusqu'à 3 tentatives ;
5. écriture de `docs/<lang>/<page>.md` et mise à jour du manifeste.

Le prompt impose de **copier les blocs de code à l'identique** : ils
correspondent aux fichiers de `docs_src/`, eux-mêmes exécutés par
`tests/ExamplesTest.php`. Les admonitions `!!! note`, les liens `.md` relatifs et
les boutons `.md-button` sont conservés : c'est MkDocs qui les rend.

### Publier la langue traduite

Le script affiche à la fin le bloc à ajouter à `mkdocs.yml`. Décommentez
`- locale: en` dans `plugins → i18n → languages`, puis :

```bash
python scripts/build_docs.py --strict     # ou ./scripts/docs_start.sh
```

Le site contient alors `/en/…` et le sélecteur de langue du thème Material
fonctionne. Tant que `docs/en/` n'existe pas, laissez `en` commenté : la
traduction anglaise n'est pas publiée.

### Ajouter une langue

1. ajoutez le code dans `docs/language_names.yml` ;
2. ajoutez éventuellement sa colonne dans `scripts/prompts/glossary.md` ;
3. lancez `python scripts/translate.py --lang <code>` ;
4. décommentez/ajoutez la langue dans `mkdocs.yml` (plugins → i18n).

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
