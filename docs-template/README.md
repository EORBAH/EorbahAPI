# Docs Template

Base de documentation statique, réutilisable, thémable et extensible via
plugins — inspirée de Mintlify. Aucune dépendance de build (pas de npm
install obligatoire), juste HTML/CSS/JS.

## Démarrer

```bash
./start.sh          # http://localhost:4000
./start.sh 8080      # sur un autre port
```

Un serveur est nécessaire : `index.html` charge sa config, ses pages
markdown et ses plugins via `fetch()`, ce qui ne fonctionne pas en
ouverture directe (`file://`).

## Ce qui différencie "nav" et "menu"

- **`.docs-sidebar`** (gauche) = navigation **contextuelle** : les pages des
  catégories marquées `"inSidebar": true` dans `config/nav.json`
  (typiquement Guide, Référence). Ce sont des ancres `#introduction` etc.
- **Bouton "menu"** (header, à côté de Recherche et Assistant) = plan
  **complet** du site : absolument tous les groupes, y compris ceux qui ne
  sont pas dans la sidebar (`"inSidebar": false`), comme **Ressources /
  Changelog / Releases**. C'est l'endroit dédié pour tout ce qui est
  "méta" plutôt que "guide de la page en cours".

Les deux sont générés depuis la **même** source (`config/nav.json`), donc
tu ne mets à jour qu'un seul fichier.

## Recherche : page ou site entier ?

Réglé dans `config/site.json → search.scope` :

- `"global"` (par défaut) : indexe toutes les pages listées dans
  `nav.json`, chargées à la demande (première ouverture de la recherche).
  Cohérent avec le bouton "menu" qui montre lui aussi tout le site.
- `"page"` : n'indexe que les titres visibles sur la page affichée.

## Thèmes

Toutes les couleurs/dimensions sont des variables CSS dans `css/root.css`.
`css/core.css` ne fait que les consommer — jamais de couleur en dur.
3 thèmes livrés : `dark`, `light`, `midnight`. Pour en ajouter un, voir
`content/*/configuration.md` (qui documente la procédure) ou directement
les commentaires en tête de `css/root.css`.

## Langues

`config/site.json → languages` liste les langues disponibles (bouton dans
le header). Chaque langue a son propre `content/<lang>/*.md` et sa propre
section dans `config/nav.json`. Changer de langue recharge la page
courante dans la nouvelle langue (ou retombe sur la première page si
l'identifiant n'existe pas encore dans cette langue).

## Plugins — étendre le Markdown

Chaque plugin est un dossier `plugins/<nom>/` avec :

```
plugins/<nom>/
├── plugin.json   -> métadonnées (tag, kind, entry, style)
├── index.js      -> DocsPlugins.register(tag, { kind, render(attrs, innerHtml) })
└── index.css     -> styles du plugin (chargé automatiquement)
```

Puis on l'active dans `plugins/plugins.json` :

```json
{ "enabled": ["callout", "youtube-embed", "review", "mon-plugin"] }
```

Deux syntaxes disponibles dans les fichiers markdown :

```md
<!-- Inline (kind: "inline"), sans contenu -->
::youtube{id="dQw4w9WgXcQ" title="Titre optionnel"}

<!-- Bloc (kind: "block"), avec du contenu markdown à l'intérieur -->
:::review{rating="5" author="Nom"}
Texte de l'avis, peut contenir du **markdown**.
:::
```

3 plugins d'exemple sont fournis : `callout` (encarts info/succès/attention/
danger), `youtube-embed` (vidéo intégrée) et `review` (avis/témoignage
avec note sur 5).

## Ajouter une page

1. Crée `content/<lang>/ma-page.md`.
2. Ajoute-la dans `config/nav.json`, dans le bon groupe :
   ```json
   { "id": "ma-page", "title": "Ma page", "icon": "ti ti-file-text", "file": "ma-page.md" }
   ```
3. Elle apparaît automatiquement dans la sidebar (si le groupe est
   `inSidebar: true`), dans le menu complet, dans la recherche, et dans la
   chaîne précédent/suivant.

## Footer

Entièrement piloté par `config/site.json → footer` : tagline, réseaux
sociaux, colonnes de liens (Solutions, Contact, etc.), mention légale
corporate, liens légaux. Aucun HTML à toucher.

## Le body ne bouge jamais

Toutes les overlays (menu mobile, menu du site, recherche, assistant)
utilisent `js/scroll-lock.js`, qui compense la largeur de la scrollbar
retirée par un `padding-right` calculé dynamiquement, en plus de
`scrollbar-gutter: stable` sur `<html>`. Résultat : ouvrir/fermer une
overlay ne décale jamais le contenu horizontalement.
