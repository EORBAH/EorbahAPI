/* =========================================================================
   plugin-loader.js
   -------------------------------------------------------------------------
   Registre global des plugins + chargement dynamique de
   plugins/<nom>/{plugin.json, index.js, index.css}.

   Un plugin s'enregistre lui-même en appelant, dans son index.js :

     DocsPlugins.register("montag", {
       kind: "inline" | "block",
       render(attrs, innerHtml) { return "<div>...</div>"; }
     });

   Puis on l'utilise dans un fichier markdown :
     inline -> ::montag{cle="valeur"}
     block  -> :::montag{cle="valeur"}\n contenu \n:::
   ========================================================================= */

window.DocsPlugins = (() => {
  const registry = new Map();

  function register(tag, definition) {
    if (!tag || typeof definition.render !== "function") {
      console.warn("[DocsPlugins] plugin invalide, ignoré :", tag);
      return;
    }
    registry.set(tag, definition);
  }

  function get(tag) { return registry.get(tag); }
  function has(tag) { return registry.has(tag); }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, ch => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[ch]));
  }

  function loadStyle(href) {
    if (document.querySelector(`link[data-plugin-style="${href}"]`)) return;
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.dataset.pluginStyle = href;
    document.head.appendChild(link);
  }

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      if (document.querySelector(`script[data-plugin-script="${src}"]`)) return resolve();
      const script = document.createElement("script");
      script.src = src;
      script.dataset.pluginScript = src;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`Impossible de charger ${src}`));
      document.body.appendChild(script);
    });
  }

  async function loadAll(baseUrl = "plugins/") {
    try {
      const manifest = await fetch(`${baseUrl}plugins.json`).then(r => r.json());
      for (const name of manifest.enabled || []) {
        try {
          const meta = await fetch(`${baseUrl}${name}/plugin.json`).then(r => r.json());
          if (meta.style) loadStyle(`${baseUrl}${name}/${meta.style}`);
          if (meta.entry) await loadScript(`${baseUrl}${name}/${meta.entry}`);
        } catch (err) {
          console.warn(`[DocsPlugins] échec de chargement du plugin "${name}"`, err);
        }
      }
    } catch (err) {
      console.warn("[DocsPlugins] plugins.json introuvable ou invalide", err);
    }
  }

  return { register, get, has, escapeHtml, loadAll };
})();
