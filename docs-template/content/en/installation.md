# Installation

No build step required — this is plain static HTML/CSS/JS. A small local
server is enough to make the `fetch()` calls (config, markdown, plugins)
work correctly (the `file://` protocol blocks these requests in most browsers).

## Quick start

```bash
./start.sh
```

The script auto-detects Python or Node and starts a static server at
`http://localhost:4000`.

:::callout{type="warning" title="Why not file:// directly?"}
Browsers block `fetch()` requests to local files for security reasons
(CORS). Without a server, config, markdown and plugins won't load.
:::

## Project structure

```text
docs-template/
├── index.html
├── start.sh
├── css/
│   ├── root.css      -> theme variables only
│   └── core.css       -> layout + components (imports root.css)
├── js/                -> app.js, nav.js, theme.js, i18n.js, search.js, markdown.js...
├── config/
│   ├── site.json      -> brand, languages, themes, search, footer
│   └── nav.json        -> pages, categories, prev/next order
├── content/
│   ├── fr/*.md
│   └── en/*.md
└── plugins/
    └── <name>/{plugin.json, index.js, index.css}
```
