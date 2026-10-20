# Exemples

Cette page illustre les composants et plugins disponibles.

## Bloc de code avec bouton copier

```js
DocsPlugins.register("mon-plugin", {
  kind: "inline",
  render(attrs) {
    return `<div>Bonjour ${attrs.name}</div>`;
  }
});
```

## Callouts

:::callout{type="info" title="Info"}
Un simple encart d'information.
:::

:::callout{type="success" title="Succès"}
Tout s'est bien passé.
:::

:::callout{type="danger" title="Danger"}
Action irréversible, à utiliser avec précaution.
:::

## Vidéo YouTube

::youtube{id="dQw4w9WgXcQ"}

## Avis

:::review{rating="4" author="Mamadou D." role="CTO, startup Conakry"}
Facile à personnaliser, et le système de plugins couvre exactement nos
besoins (vidéos + témoignages clients) sans dépendance lourde.
:::

## Cartes

<div class="docs-cards">
  <article class="docs-card">
    <i class="ti ti-palette"></i>
    <h3>Thème</h3>
    <p>Piloté par variables CSS.</p>
  </article>
  <article class="docs-card">
    <i class="ti ti-plug-connected"></i>
    <h3>Plugins</h3>
    <p>Étend le markdown sans toucher au cœur.</p>
  </article>
</div>
