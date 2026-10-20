# Configuration

Le thème repose entièrement sur des variables CSS définies dans `css/root.css`.
Aucune valeur codée en dur ailleurs : `core.css` ne fait que consommer
`var(--docs-...)`.

## Changer les couleurs

```css
[data-theme="dark"] {
  --docs-accent-strong: #ff6b4a;
  --docs-bg: #0c0c0c;
}
```

## Ajouter un thème

1. Choisis un identifiant, ex. `ocean`.
2. Copie un bloc `[data-theme="..."]` dans `css/root.css` et adapte les valeurs.
3. Ajoute `{ "id": "ocean", "label": "Océan", "swatch": "#0b3d5c" }` dans
   `config/site.json → themes`.

Le sélecteur de thème (icône palette dans le header) se régénère automatiquement.

## Dimensions et rythme

Les largeurs de sidebar, la hauteur du header, le rayon des cartes, etc. sont
eux aussi des variables (`--docs-sidebar-w`, `--docs-header-h`, `--docs-radius`...),
partagées par tous les thèmes sauf si un thème choisit de les redéfinir.

:::callout{type="success" title="Astuce"}
Pour surcharger ponctuellement sans toucher au cœur du template, utilise le
bloc `<style id="custom-css">` dans `index.html`.
:::
