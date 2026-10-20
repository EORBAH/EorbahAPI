/* =========================================================================
   nav.js
   -------------------------------------------------------------------------
   config/nav.json est la SEULE source de vérité pour :
     - la sidebar contextuelle (.docs-sidebar)      -> groupes inSidebar=true
     - le menu complet du site (.docs-site-menu)     -> tous les groupes
     - l'ordre précédent/suivant (.docs-pager)       -> liste "aplatie"
     - l'index de recherche globale (js/search.js)   -> même liste aplatie
   ========================================================================= */

window.DocsNav = (() => {

  function flatten(groups) {
    const out = [];
    (groups || []).forEach(group => {
      (group.items || []).forEach(item => {
        out.push({ ...item, groupId: group.id, groupTitle: group.title });
      });
    });
    return out;
  }

  function findItem(groups, pageId) {
    return flatten(groups).find(i => i.id === pageId) || null;
  }

  function neighbors(groups, pageId) {
    const list = flatten(groups);
    const index = list.findIndex(i => i.id === pageId);
    if (index === -1) return { prev: null, next: null };
    return { prev: list[index - 1] || null, next: list[index + 1] || null };
  }

  function renderSidebar(container, groups, lang, activePageId) {
    const visible = (groups || []).filter(g => g.inSidebar);
    container.innerHTML = visible.map(group => `
      <div class="docs-nav-section">
        <div class="docs-nav-title">${group.title}</div>
        ${group.items.map(item => `
          <a class="docs-nav-link ${item.id === activePageId ? "is-active" : ""}"
             href="#/${lang}/${item.id}" data-page-id="${item.id}">
            <i class="${item.icon || "ti ti-file-text"}"></i>
            <span>${item.title}</span>
          </a>
        `).join("")}
      </div>
    `).join("");
  }

  function renderSiteMenu(container, groups, lang, activePageId) {
    container.innerHTML = (groups || []).map(group => `
      <div class="docs-menu-group">
        <div class="docs-menu-group-title">
          <i class="${group.icon || "ti ti-folder"}"></i>
          <span>${group.title}</span>
        </div>
        ${group.items.map(item => `
          <a class="docs-menu-link ${item.id === activePageId ? "is-active" : ""}"
             href="#/${lang}/${item.id}" data-page-id="${item.id}">
            <i class="${item.icon || "ti ti-file-text"}"></i>
            <span>${item.title}</span>
            ${item.badge ? `<span class="badge">${item.badge}</span>` : ""}
          </a>
        `).join("")}
      </div>
    `).join("");
  }

  function renderPager(container, groups, lang, activePageId) {
    const { prev, next } = neighbors(groups, activePageId);
    if (!prev && !next) { container.innerHTML = ""; return; }

    container.innerHTML = `
      ${prev ? `
        <a class="docs-pager-link is-prev" href="#/${lang}/${prev.id}">
          <span class="docs-pager-label"><i class="ti ti-arrow-left"></i> Précédent</span>
          <span class="docs-pager-title">${prev.title}</span>
        </a>` : "<span></span>"}
      ${next ? `
        <a class="docs-pager-link is-next" href="#/${lang}/${next.id}">
          <span class="docs-pager-label">Suivant <i class="ti ti-arrow-right"></i></span>
          <span class="docs-pager-title">${next.title}</span>
        </a>` : "<span></span>"}
    `;
  }

  function renderBreadcrumb(container, groupTitle, pageTitle) {
    container.innerHTML = `
      <span>Docs</span>
      <i class="ti ti-chevron-right"></i>
      <span>${groupTitle}</span>
      <i class="ti ti-chevron-right"></i>
      <strong>${pageTitle}</strong>
    `;
  }

  function renderToc(container, tocItems) {
    const items = (tocItems || []).filter(t => t.level <= 3);
    if (!items.length) {
      container.innerHTML = `<div class="docs-toc-title">Sur cette page</div><div class="docs-toc-empty">Pas de sous-section.</div>`;
      return;
    }
    container.innerHTML = `
      <div class="docs-toc-title">Sur cette page</div>
      ${items.map(t => `<a href="#${t.id}" style="padding-left:${(t.level - 1) * 12}px">${t.text}</a>`).join("")}
    `;
  }

  return { flatten, findItem, neighbors, renderSidebar, renderSiteMenu, renderPager, renderBreadcrumb, renderToc };
})();
