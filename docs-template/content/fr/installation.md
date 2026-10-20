# Installation

Aucune dépendance de build : c'est du HTML/CSS/JS statique. Un petit serveur
local suffit pour que les `fetch()` (config, markdown, plugins) fonctionnent
correctement (le protocole `file://` bloque ces requêtes dans la plupart des
navigateurs).

## Démarrage rapide

```bash
./start.sh
```

Le script détecte automatiquement Python ou Node et démarre un serveur
statique sur `http://localhost:4000`.

:::callout{type="warning" title="Pourquoi pas file:// directement ?"}
Les navigateurs bloquent les requêtes `fetch()` vers des fichiers locaux
par sécurité (CORS). Sans serveur, la config, le markdown et les plugins
ne pourront pas être chargés.
:::

## Structure du projet

```text
docs-template/
├── index.html
├── start.sh
├── css/
│   ├── root.css      -> variables de thème uniquement
│   └── core.css       -> layout + composants (importe root.css)
├── js/                -> app.js, nav.js, theme.js, i18n.js, search.js, markdown.js...
├── config/
│   ├── site.json      -> marque, langues, thèmes, recherche, footer
│   └── nav.json        -> pages, catégories, ordre précédent/suivant
├── content/
│   ├── fr/*.md
│   └── en/*.md
└── plugins/
    └── <nom>/{plugin.json, index.js, index.css}
```
