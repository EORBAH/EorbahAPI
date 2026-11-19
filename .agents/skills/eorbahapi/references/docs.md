# Documentation — conventions

## Langue et structure

- **Le français est la source** : tout est écrit dans `docs/fr/`. Les autres
  langues (`docs/en/`, …) sont générées par `scripts/translate.py`.
- Le site est un **cours progressif** inspiré de la documentation de FastAPI.
- Chaque page suit un plan proche de FastAPI :

  1. `# Titre`
  2. `## Ce que vous allez apprendre`
  3. étapes `##`/`###` avec du code réel
  4. tableaux « Requête | Statut | Corps » quand c'est utile
  5. 1 à 3 encadrés Material (`!!! tip`, `!!! note`, `!!! warning`)
  6. `## Récapitulatif`
  7. `[Suivant : …](page.md){ .md-button }`

- Vouvoiement, phrases courtes, présent de l'indicatif.
- Pas d'emoji, pas de marketing.

## Le code des pages est autonome

Contrairement à l'ancienne convention, **les pages ne recopient plus les classes
de `docs_src/`** :

- snippets réutilisables directement (fichier complet `<?php` + `require
  vendor/autoload.php` + `use`, ou extrait ciblé) ;
- pas de `final class XExample extends DocsExample`, pas de
  `DocsExample::scenario()` ;
- les attributs et signatures doivent correspondre au code réel (vérifier
  `src/`) ;
- `docs_src/` reste la suite de régression exécutée par `tests/ExamplesTest.php`
  (fixtures), mais n'apparaît plus dans le cours.

## Couverture

Le cours doit couvrir toute la surface publique : routage, paramètres, corps,
validation, réponses, fichiers, formulaires, sécurité, dépendances,
middlewares, contrôleurs, fichiers statiques, templates, OpenAPI, cache,
contrats/adaptateurs, WebSockets, tâches d'arrière-plan, journalisation,
utilitaires, JSON-RPC, tests, runners, erreurs.

## Ajouter une page

1. écrire `docs/fr/<page>.md` ;
2. ajouter l'entrée dans le `nav` de `mkdocs.yml` et sa traduction
   (`nav_translations` pour `en`) ;
3. vérifier les liens internes et `mkdocs build`.

## Traduction

```bash
python scripts/translate.py --lang all --check    # CI, sans clé
python scripts/translate.py --lang en             # LLM_API_KEY requis
```

- Ne jamais éditer `docs/en/*.md` à la main : la prochaine traduction l'écraserait.
- Le prompt (`scripts/prompts/translate.md`) impose de **copier les blocs de code
  à l'identique**.
- Manifeste par langue : `docs/<lang>/.manifest.json` (SHA-256 de la source).

## Nommage

| Élément | Convention |
| --- | --- |
| pages | `kebab-case.md` (`query-params.md`, `background-tasks.md`) |
| exemples exécutés | `PascalCaseExample.php` dans `docs_src/` |
| namespace des exemples | `Eorbahapi\Examples` (PSR-4 → `docs_src/`) |

## Vérifications avant commit

```bash
./vendor/bin/phpunit                       # toute la suite
python scripts/translate.py --lang all --check
find src docs_src -name '*.php' -exec php -l {} \;
mkdocs build
```
