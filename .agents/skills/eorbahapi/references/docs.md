# Documentation — conventions

## Langue et structure

- **Le français est la source** : tout est écrit dans `docs/fr/`. Les autres
  langues (`docs/en/`, …) sont générées par `scripts/translate.py`.
- Le site est un **cours progressif** inspiré de la documentation de FastAPI.
- Chaque page suit le même plan :

  1. `# Titre`
  2. `## Ce que vous allez apprendre`
  3. `## Le code` (bloc ```php)
  4. `## Ce qui se passe` + tableau `Requête | Statut | Corps`
  5. 1 ou 2 encadrés Material (`!!! tip`, `!!! note`, `!!! warning`) — informatifs, jamais décoratifs
  6. `## Récapitulatif` (liste à puces)
  7. `[Suivant : …](page.md){ .md-button }`

- Vouvoiement, phrases courtes, présent de l'indicatif.
- Pas d'emoji, pas de marketing.

## Les exemples sont exécutés

Toute page cite un fichier réel de `docs_src/` :

```markdown
`docs_src/QueryParamsExample.php` :

```php
// extrait fidèle du fichier
```
```

Règles :

- le code montré est **copié** du fichier d'exemple (pas réécrit de mémoire) ;
- le tableau « Ce qui se passe » reprend les `scenarios()` du fichier ;
- `./vendor/bin/phpunit --filter ExamplesTest` doit rester vert.

## Ajouter une page

1. écrire `docs/fr/<page>.md` ;
2. ajouter l'entrée dans le `nav` de `mkdocs.yml` ;
3. créer ou réutiliser l'exemple `docs_src/<Brique>Example.php` et l'inscrire dans
   `docs_src/registry.php` ;
4. lancer les tests puis `mkdocs serve` pour vérifier le rendu.

## Traduction

```bash
python scripts/translate.py --lang all --check    # CI, sans clé
python scripts/translate.py --lang en             # LLM_API_KEY requis
```

- Ne jamais éditer `docs/en/*.md` à la main : la prochaine traduction l'écraserait.
- Le prompt (`scripts/prompts/translate.md`) impose de **copier les blocs de code
  à l'identique** — ils correspondent aux fichiers de `docs_src/`.
- Le glossaire (`scripts/prompts/glossary.md`) fige la terminologie et la liste
  des identifiants à ne pas traduire.
- Manifeste par langue : `docs/<lang>/.manifest.json` (SHA-256 de la source).

## Nommage

| Élément | Convention |
| --- | --- |
| pages | `kebab-case.md` (`query-params.md`, `static-files.md`) |
| exemples | `PascalCaseExample.php` (`QueryParamsExample.php`) |
| namespace des exemples | `Eorbahapi\Examples` (PSR-4 → `docs_src/`) |
| encadrés d'exemple | `` `docs_src/QueryParamsExample.php` `` en tête de chapitre |

## Vérifications avant commit

```bash
./vendor/bin/phpunit                       # toute la suite
python scripts/translate.py --lang all --check
find src docs_src -name '*.php' -exec php -l {} \;
```
