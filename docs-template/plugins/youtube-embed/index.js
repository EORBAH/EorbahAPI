/* Plugin: youtube-embed
   Syntaxe markdown : ::youtube{id="VIDEO_ID" title="Titre optionnel"}
   "inline" -> pas de contenu entre balises, juste des attributs. */
DocsPlugins.register("youtube", {
  kind: "inline",
  render(attrs) {
    const id = (attrs.id || "").replace(/[^a-zA-Z0-9_-]/g, "");
    if (!id) {
      return `<div class="docs-plugin-unknown">Plugin youtube : attribut "id" manquant.</div>`;
    }
    const title = attrs.title || "Vidéo YouTube";
    return `
      <div class="docs-plugin-block docs-plugin-youtube">
        <div class="docs-plugin-youtube-frame">
          <iframe
            src="https://www.youtube-nocookie.com/embed/${id}"
            title="${DocsPlugins.escapeHtml(title)}"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen>
          </iframe>
        </div>
        <p class="docs-plugin-youtube-caption">${DocsPlugins.escapeHtml(title)}</p>
      </div>`;
  }
});
