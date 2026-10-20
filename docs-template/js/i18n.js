/* =========================================================================
   i18n.js — dropdown des langues disponibles (bouton dans le header).
   Ne recharge pas le contenu lui-même : délègue à onSelect(langId),
   fourni par app.js, qui sait recharger la page courante dans la
   nouvelle langue (avec repli sur la première page si l'id n'existe
   pas dans cette langue).
   ========================================================================= */

window.DocsI18n = (() => {
  function init(languages, currentLang, onSelect) {
    const menu = document.getElementById("langMenu");
    const trigger = document.getElementById("langButton");
    if (!menu) return;

    function render(activeLang) {
      menu.innerHTML = languages.map(l => `
        <button class="docs-dropdown-item ${l.id === activeLang ? "is-active" : ""}" data-lang-id="${l.id}">
          <span>${l.flag || ""}</span>
          <span>${l.label}</span>
          <i class="ti ti-check check"></i>
        </button>
      `).join("");

      menu.querySelectorAll("[data-lang-id]").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.dataset.langId;
          onSelect(id);
        });
      });

      if (trigger) {
        const active = languages.find(l => l.id === activeLang);
        trigger.setAttribute("title", `Langue : ${active ? active.label : activeLang}`);
      }
    }

    render(currentLang);
    return { refresh: render };
  }

  return { init };
})();
