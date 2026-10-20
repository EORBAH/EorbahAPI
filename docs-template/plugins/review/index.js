/* Plugin: review
   Syntaxe markdown :
   :::review{rating="5" author="Nom" role="Fonction"}
   Texte libre de l'avis, peut contenir du **markdown**.
   :::
   "block" -> le contenu entre les balises est fourni déjà rendu en HTML. */
DocsPlugins.register("review", {
  kind: "block",
  render(attrs, innerHtml) {
    const rating = Math.max(0, Math.min(5, parseInt(attrs.rating, 10) || 5));
    const author = attrs.author || "Anonyme";
    const role = attrs.role || "";

    const stars = Array.from({ length: 5 }, (_, i) =>
      `<i class="ti ${i < rating ? "ti-star-filled" : "ti-star"}"></i>`
    ).join("");

    return `
      <figure class="docs-plugin-block docs-plugin-review">
        <div class="docs-plugin-review-stars" aria-label="${rating} sur 5">${stars}</div>
        <blockquote>${innerHtml}</blockquote>
        <figcaption>
          <strong>${DocsPlugins.escapeHtml(author)}</strong>
          ${role ? `<span>${DocsPlugins.escapeHtml(role)}</span>` : ""}
        </figcaption>
      </figure>`;
  }
});
