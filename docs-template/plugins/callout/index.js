/* Plugin: callout
   Syntaxe markdown :
   :::callout{type="warning" title="Attention"}
   Texte de l'encadré.
   ::: */
const CALLOUT_ICONS = {
  info: "ti-info-circle",
  success: "ti-circle-check",
  warning: "ti-alert-triangle",
  danger: "ti-alert-octagon"
};

DocsPlugins.register("callout", {
  kind: "block",
  render(attrs, innerHtml) {
    const type = CALLOUT_ICONS[attrs.type] ? attrs.type : "info";
    const title = attrs.title || type.charAt(0).toUpperCase() + type.slice(1);

    return `
      <div class="docs-plugin-block docs-plugin-callout is-${type}">
        <i class="ti ${CALLOUT_ICONS[type]}"></i>
        <div>
          <strong>${DocsPlugins.escapeHtml(title)}</strong>
          <div>${innerHtml}</div>
        </div>
      </div>`;
  }
});
