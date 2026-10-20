# Configuration

The theme relies entirely on CSS variables defined in `css/root.css`.
No hard-coded values anywhere else: `core.css` only ever consumes
`var(--docs-...)`.

## Changing colors

```css
[data-theme="dark"] {
  --docs-accent-strong: #ff6b4a;
  --docs-bg: #0c0c0c;
}
```

## Adding a theme

1. Pick an id, e.g. `ocean`.
2. Copy a `[data-theme="..."]` block in `css/root.css` and adjust values.
3. Add `{ "id": "ocean", "label": "Ocean", "swatch": "#0b3d5c" }` to
   `config/site.json → themes`.

The theme switcher (palette icon in the header) rebuilds itself automatically.

:::callout{type="success" title="Tip"}
For one-off overrides without touching the core template, use the
`<style id="custom-css">` block in `index.html`.
:::
