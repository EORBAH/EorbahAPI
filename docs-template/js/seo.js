/* =============================================================================
   seo.js — métadonnées SEO du site de documentation.

   Les balises statiques de index.html servent de valeurs par défaut ; ce module
   les met à jour à chaque changement de page (le contenu est rendu côté client,
   donc les crawlers reçoivent le HTML initial et les utilisateurs/robots qui
   exécutent le JS obtiennent la version exacte de la page affichée).

   Il met à jour :
     - <title>, meta description / keywords / author / robots ;
     - Open Graph (og:*) et Twitter Card ;
     - <link rel="canonical"> ;
     - JSON-LD : WebSite (+ SearchAction), TechArticle, BreadcrumbList.

   Configuration : config/site.json → seo {
     siteUrl, titleTemplate, titleDefault, description, keywords,
     author, locale, robots, twitter, ogImage
   }
   ============================================================================= */
(function () {
  "use strict";

  const META = {
    name: (key, content) => upsert("meta", "name", key, content),
    property: (key, content) => upsert("meta", "property", key, content),
  };

  function upsert(tag, attr, key, content) {
    if (content === null || content === undefined || content === "") return;
    let el = document.head.querySelector(`${tag}[${attr}="${cssEscape(key)}"]`);
    if (!el) {
      el = document.createElement(tag);
      el.setAttribute(attr, key);
      document.head.appendChild(el);
    }
    el.setAttribute("content", String(content));
  }

  function cssEscape(value) {
    return String(value).replace(/["\\]/g, "\\$&");
  }

  function setLink(rel, href) {
    if (!href) return;
    let el = document.head.querySelector(`link[rel="${rel}"]`);
    if (!el) {
      el = document.createElement("link");
      el.setAttribute("rel", rel);
      document.head.appendChild(el);
    }
    el.setAttribute("href", href);
  }

  function setJsonLd(id, data) {
    let script = document.head.querySelector(`script[type="application/ld+json"][data-seo="${id}"]`);
    if (!script) {
      script = document.createElement("script");
      script.type = "application/ld+json";
      script.setAttribute("data-seo", id);
      document.head.appendChild(script);
    }
    script.textContent = JSON.stringify(data);
  }

  /** Premier paragraphe du contenu rendu, utilisé comme description. */
  function firstParagraph(html) {
    if (!html) return "";
    try {
      const doc = new DOMParser().parseFromString(html, "text/html");
      const el = doc.querySelector(".docs-content p, p");
      return el ? el.textContent.replace(/\s+/g, " ").trim() : "";
    } catch (_) {
      return "";
    }
  }

  function normalizeUrl(siteUrl) {
    const fallback = window.location.origin + window.location.pathname;
    return String(siteUrl || fallback).replace(/\/+$/, "");
  }

  function buildTitle(seo, site, item) {
    const template = seo.titleTemplate || "{title} — {site}";
    return template
      .replace(/\{title\}/g, item.title || "")
      .replace(/\{site\}/g, site.brand?.name || "")
      .replace(/\s+—\s+$/, "")
      .trim() || (seo.titleDefault || site.brand?.name || "Documentation");
  }

  /**
   * Met à jour toutes les métadonnées de la page courante.
   *
   * @param {object} ctx
   * @param {object} ctx.site   config/site.json
   * @param {object} ctx.item   page affichée (id, title, file, groupTitle…)
   * @param {string} ctx.lang   langue courante
   * @param {string} ctx.html   HTML rendu du contenu
   * @param {object} [ctx.meta]  frontmatter de la page (description, title…)
   * @param {string} [ctx.headingId] ancre éventuelle
   */
  function update({ site, item, lang, html, meta, headingId }) {
    const seo = site.seo || {};
    const base = normalizeUrl(seo.siteUrl);
    const url = `${base}/#/${lang}/${item.id}${headingId ? `/${headingId}` : ""}`;
    const title = buildTitle(seo, site, item);
    const description = (
      (meta && meta.description) ||
      item.description ||
      firstParagraph(html) ||
      seo.description ||
      ""
    ).slice(0, 300);
    const image = seo.ogImage ? `${base}/${String(seo.ogImage).replace(/^\/+/, "")}` : "";
    const author = seo.author || site.brand?.name || "";

    document.title = title;

    META.name("description", description);
    META.name("keywords", (seo.keywords || []).join(", "));
    META.name("author", author);
    META.name("robots", seo.robots || "index,follow");
    META.name("theme-color", site.defaultTheme === "light" ? "#ffffff" : "#101110");

    setLink("canonical", url);

    META.property("og:type", "article");
    META.property("og:site_name", site.brand?.name || "");
    META.property("og:title", title);
    META.property("og:description", description);
    META.property("og:url", url);
    META.property("og:locale", seo.locale || "fr_FR");
    META.property("og:image", image);

    META.name("twitter:card", image ? "summary_large_image" : "summary");
    META.name("twitter:title", title);
    META.name("twitter:description", description);
    META.name("twitter:site", seo.twitter || "");
    META.name("twitter:image", image);

    const organization = {
      "@type": "Organization",
      name: author || site.brand?.name || "EORBAH",
      url: seo.repo || base,
    };

    setJsonLd("website", {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: site.brand?.name || "Documentation",
      url: `${base}/`,
      description: seo.description || description,
      inLanguage: lang,
      publisher: organization,
      potentialAction: {
        "@type": "SearchAction",
        target: `${base}/#/${lang}/{search_term_string}`,
        "query-input": "required name=search_term_string",
      },
    });

    setJsonLd("page", {
      "@context": "https://schema.org",
      "@type": "TechArticle",
      headline: item.title || "",
      description,
      url,
      inLanguage: lang,
      isPartOf: {
        "@type": "WebSite",
        name: site.brand?.name || "Documentation",
        url: `${base}/`,
      },
      author: organization,
      publisher: organization,
    });

    const items = [
      { "@type": "ListItem", position: 1, name: "Accueil", item: `${base}/#/${lang}/${item.id}` },
    ];

    if (item.groupTitle) {
      items.push({
        "@type": "ListItem",
        position: 2,
        name: item.groupTitle,
        item: `${base}/#/${lang}/${item.id}`,
      });
    }

    items.push({
      "@type": "ListItem",
      position: items.length + 1,
      name: item.title || "",
      item: url,
    });

    setJsonLd("breadcrumb", {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: items,
    });
  }

  window.DocsSeo = { update, firstParagraph };
})();
