/* =========================================================================
   scroll-lock.js
   -------------------------------------------------------------------------
   Empêche le body de scroller pendant qu'une overlay est ouverte (menu
   mobile, menu du site, recherche, assistant) SANS que la page ne "saute"
   horizontalement quand la scrollbar disparaît.

   Deux protections cumulées :
   1) `scrollbar-gutter: stable` sur <html> (css/core.css) — gère la
      majorité des navigateurs modernes à elle seule.
   2) Compensation JS par padding-right, pour les navigateurs qui ne
      supportent pas scrollbar-gutter, ou une scrollbar déjà présente.

   Compte les verrous ouverts (plusieurs overlays pourraient théoriquement
   se superposer) pour ne déverrouiller qu'au dernier `unlock()`.
   ========================================================================= */

window.DocsScrollLock = (() => {
  let count = 0;

  function scrollbarWidth() {
    return window.innerWidth - document.documentElement.clientWidth;
  }

  function lock() {
    count++;
    if (count > 1) return;
    const sw = scrollbarWidth();
    if (sw > 0) document.body.style.paddingRight = `${sw}px`;
    document.body.classList.add("docs-scroll-locked");
  }

  function unlock() {
    count = Math.max(0, count - 1);
    if (count > 0) return;
    document.body.style.paddingRight = "";
    document.body.classList.remove("docs-scroll-locked");
  }

  return { lock, unlock };
})();
