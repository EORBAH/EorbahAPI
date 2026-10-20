/* =========================================================================
   search.js
   -------------------------------------------------------------------------
   Deux modes, pilotés par config/site.json → search.scope :

   - "global" (par défaut) : indexe TOUTES les pages listées dans
     config/nav.json pour la langue active (titres de page + titres de
     sous-sections), en récupérant paresseusement le markdown de chaque
     page la première fois que la recherche s'ouvre. C'est cohérent avec
     le bouton "menu" qui montre lui aussi le site entier.

   - "page" : n'indexe que les titres visibles dans la page actuellement
     affichée (comportement "TOC de la page").
   ========================================================================= */

window.DocsSearch = (() => {
  let opts = {};
  let context = { lang: "fr", pageId: null, groups: [] };
  let globalIndexCache = {}; // { [lang]: [ {title, sub, href} ] }
  let indexBuilding = null;

  let overlay, input, resultsBox, scopeLabel;

  function init(options) {
    opts = Object.assign({ scope: "global", maxResults: 8, placeholder: "Rechercher..." }, options);

    overlay = document.getElementById("docsSearch");
    input = document.getElementById("docsSearchInput");
    resultsBox = document.getElementById("docsSearchResults");
    scopeLabel = document.getElementById("docsSearchScope");

    if (input) input.placeholder = opts.placeholder;
    if (scopeLabel) scopeLabel.textContent = opts.scope === "global" ? "Toute la doc" : "Cette page";

    input?.addEventListener("input", e => renderResults(e.target.value));
    document.getElementById("closeSearch")?.addEventListener("click", close);
    overlay?.addEventListener("click", e => { if (e.target === overlay) close(); });
  }

  function setContext(lang, pageId, groups) {
    context = { lang, pageId, groups };
  }

  function open() {
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    DocsScrollLock.lock();
    requestAnimationFrame(() => input?.focus());
    renderResults("");
    if (opts.scope === "global") ensureGlobalIndex();
  }

  function close() {
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    if (input) input.value = "";
    DocsScrollLock.unlock();
  }

  function isOpen() { return overlay?.classList.contains("is-open"); }

  async function ensureGlobalIndex() {
    const lang = context.lang;
    if (globalIndexCache[lang] || indexBuilding === lang) return;
    indexBuilding = lang;

    const items = DocsNav.flatten(context.groups);
    const results = [];

    for (const item of items) {
      try {
        const raw = await fetch(`content/${lang}/${item.file}`).then(r => r.text());
        const { toc } = DocsMarkdown.render(raw);
        results.push({ title: item.title, sub: item.groupTitle, href: `#/${lang}/${item.id}` });
        toc.filter(t => t.level > 1).forEach(t => {
          results.push({ title: t.text, sub: item.title, href: `#/${lang}/${item.id}/${t.id}` });
        });
      } catch (_) { /* fichier manquant : ignoré silencieusement */ }
    }

    globalIndexCache[lang] = results;
    indexBuilding = null;
    if (isOpen() && input) renderResults(input.value);
  }

  function pageIndex() {
    return [...document.querySelectorAll("#conteneur-docs h1, #conteneur-docs h2, #conteneur-docs h3")]
      .map(el => ({ title: el.textContent.trim(), sub: null, href: `#${el.id}` }))
      .filter(r => r.title);
  }

  function renderResults(query) {
    const q = query.trim().toLowerCase();
    const pool = opts.scope === "global" ? (globalIndexCache[context.lang] || []) : pageIndex();
    const loading = opts.scope === "global" && !globalIndexCache[context.lang];

    const filtered = pool
      .filter(r => !q || r.title.toLowerCase().includes(q))
      .slice(0, opts.maxResults);

    if (loading) {
      resultsBox.innerHTML = `<div class="docs-search-result"><span>Indexation en cours...</span></div>`;
      return;
    }

    resultsBox.innerHTML = filtered.length
      ? filtered.map(r => `
          <a class="docs-search-result" href="${r.href}" data-search-link>
            <strong>${DocsMarkdown.escapeHtml(r.title)}</strong>
            <span>${r.sub ? DocsMarkdown.escapeHtml(r.sub) : "Page"}</span>
          </a>
        `).join("")
      : `<div class="docs-search-result"><span>Aucun résultat.</span></div>`;

    resultsBox.querySelectorAll("[data-search-link]").forEach(link => {
      link.addEventListener("click", close);
    });
  }

  return { init, setContext, open, close, isOpen };
})();
