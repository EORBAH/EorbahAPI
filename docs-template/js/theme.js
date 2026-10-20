/* =========================================================================
   theme.js — bascule [data-theme] sur <html>, persiste le choix.
   Les thèmes eux-mêmes (couleurs) sont définis dans css/root.css ;
   la liste affichée dans le dropdown vient de config/site.json → themes.
   ========================================================================= */

window.DocsTheme = (() => {
  const STORAGE_KEY = "docs-theme";

  function apply(id) {
    document.documentElement.setAttribute("data-theme", id);
  }

  function current() {
    return document.documentElement.getAttribute("data-theme");
  }

  function init(themes, defaultTheme) {
    const saved = localStorage.getItem(STORAGE_KEY);
    apply(saved && themes.some(t => t.id === saved) ? saved : defaultTheme);

    const menu = document.getElementById("themeMenu");
    if (!menu) return;

    menu.innerHTML = themes.map(t => `
      <button class="docs-dropdown-item" data-theme-id="${t.id}"
              role="menuitemradio" aria-checked="${t.id === current()}">
        <span class="swatch" style="background:${t.swatch}"></span>
        <span>${t.label}</span>
        <i class="ti ti-check check"></i>
      </button>
    `).join("");

    updateActive(menu);

    menu.querySelectorAll("[data-theme-id]").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = btn.dataset.themeId;
        apply(id);
        localStorage.setItem(STORAGE_KEY, id);
        updateActive(menu);
      });
    });
  }

  function updateActive(menu) {
    menu.querySelectorAll("[data-theme-id]").forEach(btn => {
      const isActive = btn.dataset.themeId === current();
      btn.classList.toggle("is-active", isActive);
      btn.setAttribute("aria-checked", String(isActive));
    });
  }

  return { init, apply, current };
})();
