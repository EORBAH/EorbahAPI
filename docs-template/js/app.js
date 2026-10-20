/* =========================================================================
   app.js — point d'entrée. Assemble tout : config, nav, thème, langue,
   recherche, plugins, routage par hash, rendu markdown.

   Routage :
     #/<lang>/<pageId>             -> charge une page
     #/<lang>/<pageId>/<headingId> -> charge une page puis scrolle à l'ancre
     #<headingId>                  -> simple scroll dans la page déjà chargée
   ========================================================================= */

(async function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  const state = { site: null, nav: null, lang: null, pageId: null };

  /* ---------------------------------------------------------------------
     1) Config
     --------------------------------------------------------------------- */
  const [site, nav] = await Promise.all([
    fetch("config/site.json").then((r) => r.json()),
    fetch("config/nav.json").then((r) => r.json()),
  ]);
  state.site = site;
  state.nav = nav;

  document.title = site.brand.name;
  $("[data-docs-brand]").textContent = site.brand.name;
  $("#brandMark").textContent = site.brand.logo || "✳";

  /* ---------------------------------------------------------------------
     2) Plugins (chargés une seule fois, avant le premier rendu markdown)
     --------------------------------------------------------------------- */
  await DocsPlugins.loadAll("plugins/");

  /* ---------------------------------------------------------------------
     3) Thème
     --------------------------------------------------------------------- */
  DocsTheme.init(site.themes, site.defaultTheme);

  /* ---------------------------------------------------------------------
     4) Footer (statique, une fois — vient entièrement de site.json)
     --------------------------------------------------------------------- */
  renderFooter(site.footer, site.brand);

  /* ---------------------------------------------------------------------
     5) Recherche
     --------------------------------------------------------------------- */
  DocsSearch.init(site.search);

  /* ---------------------------------------------------------------------
     6) Routage
     --------------------------------------------------------------------- */
  function parseHash() {
    const raw = location.hash.replace(/^#/, "");
    if (!raw.startsWith("/")) return null; // simple ancre in-page, pas une route
    const [, lang, pageId, headingId] = raw.split("/");
    return { lang, pageId, headingId };
  }

  function defaultRoute() {
    const lang = site.defaultLanguage;
    const first = DocsNav.flatten(nav[lang])[0];
    return { lang, pageId: first?.id };
  }

  async function handleHashChange() {
    const raw = location.hash.replace(/^#/, "");
    if (!raw.startsWith("/")) {
      // simple ancre (#ma-section) : scroll uniquement, pas de rechargement
      const el = raw && document.getElementById(raw);
      if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }

    const route = parseHash() || defaultRoute();
    const lang = nav[route.lang] ? route.lang : site.defaultLanguage;
    const groups = nav[lang];
    const item =
      DocsNav.findItem(groups, route.pageId) || DocsNav.flatten(groups)[0];

    await loadPage(lang, item.id, route.headingId);
  }

  window.addEventListener("hashchange", handleHashChange);

  /* ---------------------------------------------------------------------
     7) Chargement d'une page
     --------------------------------------------------------------------- */
  async function loadPage(lang, pageId, headingId) {
    const groups = nav[lang];
    const found = DocsNav.findItem(groups, pageId);
    if (!found) return;

    state.lang = lang;
    state.pageId = pageId;
    document.documentElement.lang = lang;

    let raw = "";
    try {
      raw = await fetch(`content/${lang}/${found.file}`).then((r) => {
        if (!r.ok) throw new Error("404");
        return r.text();
      });
    } catch (_) {
      raw = `# ${found.title}\n\nCette page n'a pas encore de contenu (\`content/${lang}/${found.file}\` introuvable).`;
    }

    const { html, meta, toc } = DocsMarkdown.render(raw);

    const container = $("#conteneur-docs");
    container.innerHTML = `
      <div class="docs-breadcrumb" id="docsBreadcrumb"></div>
      <div class="docs-content">${html}</div>
      <nav class="docs-pager" id="docsPager" aria-label="Navigation entre pages"></nav>
    `;

    document.title = `${found.title} — ${site.brand.name}`;

    DocsNav.renderBreadcrumb(
      $("#docsBreadcrumb"),
      found.groupTitle,
      found.title,
    );
    DocsNav.renderSidebar($("#docsSidebar"), groups, lang, pageId);
    DocsNav.renderSiteMenu($("#siteMenuList"), groups, lang, pageId);
    DocsNav.renderPager($("#docsPager"), groups, lang, pageId);
    DocsNav.renderToc($("#docsToc"), toc);

    // Métadonnées SEO de la page (title, description, OG, canonical, JSON-LD).
    if (window.DocsSeo) {
      window.DocsSeo.update({ site, item: found, lang, html, meta, headingId });
    }

    DocsSearch.setContext(lang, pageId, groups);

    // Ferme les overlays mobiles après navigation
    closeSidebar();
    closeSiteMenu();

    window.scrollTo({
      top: 0,
      behavior: "instant" in window ? "instant" : "auto",
    });

    if (headingId) {
      requestAnimationFrame(() => {
        document
          .getElementById(headingId)
          ?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }

    setupCopyButtons();
    setupTocScrollSpy(toc);
    if (typeof Prism !== "undefined") {
      Prism.highlightAll();
      console.log("[app.js] Prism.highlightAll() exécuté après injection");
    } else {
      console.warn("[app.js] Prism n'est pas disponible");
    }
  }

  /* ---------------------------------------------------------------------
     8) Copier le code (délégation, car le contenu est régénéré à chaque page)
     --------------------------------------------------------------------- */
  function setupCopyButtons() {
    $$("[data-copy]").forEach((button) => {
      button.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(button.dataset.copy || "");
          const original = button.innerHTML;
          button.innerHTML = '<i class="ti ti-check"></i>';
          setTimeout(() => (button.innerHTML = original), 1200);
        } catch (_) {
          /* clipboard indisponible : ignoré */
        }
      });
    });
  }

  /* ---------------------------------------------------------------------
     9) Scroll-spy simple pour la TOC de droite
     --------------------------------------------------------------------- */
  let spyObserver = null;
  function setupTocScrollSpy(toc) {
    spyObserver?.disconnect();
    if (!toc.length) return;
    spyObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          $$(".docs-toc a").forEach((a) => a.classList.remove("is-active"));
          $(`.docs-toc a[href="#${entry.target.id}"]`)?.classList.add(
            "is-active",
          );
        });
      },
      { rootMargin: "-20% 0px -70% 0px" },
    );
    toc.forEach((t) => {
      const el = document.getElementById(t.id);
      if (el) spyObserver.observe(el);
    });
  }

  /* ---------------------------------------------------------------------
     10) Footer
     --------------------------------------------------------------------- */
  function renderFooter(footer, brand) {
    $("#footerBrandName").textContent = brand.name;
    $("#footerTagline").textContent = footer.tagline || "";

    $("#footerSocial").innerHTML = (footer.social || [])
      .map(
        (s) => `
      <a href="${s.url}" target="_blank" rel="noopener" aria-label="${s.label}" title="${s.label}">
        <i class="${s.icon}"></i>
      </a>
    `,
      )
      .join("");

    $("#footerColumns").innerHTML = (footer.columns || [])
      .map(
        (col) => `
      <div class="docs-footer-col">
        <div class="docs-footer-col-title">${col.title}</div>
        ${col.links.map((l) => `<a href="${l.url}">${l.label}</a>`).join("")}
      </div>
    `,
      )
      .join("");

    $("#footerCorporate").textContent = footer.corporate?.line || "";
    $("#footerLegal").innerHTML = (footer.legalLinks || [])
      .map((l) => `<a href="${l.url}">${l.label}</a>`)
      .join("");
  }

  /* ---------------------------------------------------------------------
     11) Langue
     --------------------------------------------------------------------- */
  DocsI18n.init(site.languages, site.defaultLanguage, async (langId) => {
    closeDropdowns();
    const groups = nav[langId];
    const sameId = DocsNav.findItem(groups, state.pageId);
    const target = sameId ? state.pageId : DocsNav.flatten(groups)[0]?.id;
    location.hash = `#/${langId}/${target}`;
  });

  /* ---------------------------------------------------------------------
     12) Overlays : sidebar mobile / menu du site / recherche / assistant
     Toutes utilisent DocsScrollLock pour ne jamais faire "sauter" le body.
     --------------------------------------------------------------------- */
  const sidebar = $("#docsSidebar");
  const backdrop = $("#docsBackdrop");
  const siteMenu = $("#siteMenu");

  function openSidebar() {
    sidebar.classList.add("is-open");
    backdrop.classList.add("is-open");
    DocsScrollLock.lock();
    $("#menuButtonMobile")?.setAttribute("aria-expanded", "true");
  }
  function closeSidebar() {
    if (!sidebar.classList.contains("is-open")) return;
    sidebar.classList.remove("is-open");
    backdrop.classList.remove("is-open");
    DocsScrollLock.unlock();
    $("#menuButtonMobile")?.setAttribute("aria-expanded", "false");
  }

  function openSiteMenu() {
    siteMenu.classList.add("is-open");
    siteMenu.setAttribute("aria-hidden", "false");
    DocsScrollLock.lock();
  }
  function closeSiteMenu() {
    if (!siteMenu.classList.contains("is-open")) return;
    siteMenu.classList.remove("is-open");
    siteMenu.setAttribute("aria-hidden", "true");
    DocsScrollLock.unlock();
  }

  $("#menuButtonMobile")?.addEventListener("click", () => {
    sidebar.classList.contains("is-open") ? closeSidebar() : openSidebar();
  });
  backdrop?.addEventListener("click", closeSidebar);

  $("#siteMenuButton")?.addEventListener("click", () => {
    siteMenu.classList.contains("is-open") ? closeSiteMenu() : openSiteMenu();
  });
  $("#closeSiteMenu")?.addEventListener("click", closeSiteMenu);
  siteMenu?.addEventListener("click", (e) => {
    if (e.target === siteMenu) closeSiteMenu();
  });

  $("#searchButton")?.addEventListener("click", DocsSearch.open);
  $("#searchTrigger")?.addEventListener("click", DocsSearch.open);

  // Assistant (UI seule — branche ton backend dans sendAssistantMessage)
  const assistant = $("#docsAssistant");
  function openAssistant() {
    assistant.classList.add("is-open");
    assistant.setAttribute("aria-hidden", "false");
    DocsScrollLock.lock();
    requestAnimationFrame(() => $("#assistantInput")?.focus());
  }
  function closeAssistant() {
    if (!assistant.classList.contains("is-open")) return;
    assistant.classList.remove("is-open");
    assistant.setAttribute("aria-hidden", "true");
    DocsScrollLock.unlock();
  }
  $("#assistantButton")?.addEventListener("click", openAssistant);
  $("#closeAssistant")?.addEventListener("click", closeAssistant);
  assistant?.addEventListener("click", (e) => {
    if (e.target === assistant) closeAssistant();
  });

  function addAssistantMessage(text, role) {
    const wrap = document.createElement("div");
    wrap.style.cssText = `
      margin:10px 0; padding:12px 14px; border:1px solid var(--docs-border);
      border-radius:12px; color:var(--docs-text-2);
      background:${role === "user" ? "var(--docs-surface-3)" : "var(--docs-surface-2)"};`;
    wrap.textContent = text;
    $("#assistantBody").appendChild(wrap);
    wrap.scrollIntoView({ behavior: "smooth", block: "end" });
  }
  async function sendAssistantMessage(text) {
    // Point d'extension : branche ton API ici, puis appelle addAssistantMessage(reponse, "assistant")
    addAssistantMessage("Assistant : " + text, "assistant");
  }
  $("#assistantForm")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const input = $("#assistantInput");
    const text = input.value.trim();
    if (!text) return;
    addAssistantMessage(text, "user");
    input.value = "";
    await sendAssistantMessage(text);
  });
  $$(".docs-assistant-example").forEach((btn) => {
    btn.addEventListener("click", async () => {
      addAssistantMessage(btn.textContent.trim(), "user");
      await sendAssistantMessage(btn.textContent.trim());
    });
  });

  /* ---------------------------------------------------------------------
     13) Dropdowns génériques (thème / langue)
     --------------------------------------------------------------------- */
  function closeDropdowns() {
    $$(".docs-dropdown-panel.is-open").forEach((p) =>
      p.classList.remove("is-open"),
    );
  }
  $$(".docs-dropdown").forEach((dd) => {
    const trigger = $(".docs-icon-btn", dd);
    const panel = $(".docs-dropdown-panel", dd);
    trigger?.addEventListener("click", (e) => {
      e.stopPropagation();
      const willOpen = !panel.classList.contains("is-open");
      closeDropdowns();
      panel.classList.toggle("is-open", willOpen);
    });
  });
  document.addEventListener("click", closeDropdowns);

  /* ---------------------------------------------------------------------
     14) Raccourcis clavier
     --------------------------------------------------------------------- */
  document.addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      DocsSearch.open();
    }
    if (e.key === "Escape") {
      DocsSearch.close();
      closeAssistant();
      closeSidebar();
      closeSiteMenu();
      closeDropdowns();
    }
  });

  /* ---------------------------------------------------------------------
     15) Démarrage
     --------------------------------------------------------------------- */
  if (!location.hash.startsWith("#/")) {
    const d = defaultRoute();
    location.hash = `#/${d.lang}/${d.pageId}`;
  } else {
    await handleHashChange();
  }
})();
