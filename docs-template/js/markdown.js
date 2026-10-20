/* =========================================================================
   markdown.js
   -------------------------------------------------------------------------
   Mini-moteur Markdown maison (pas de dépendance externe) + support des
   "shortcodes" plugins :

     Bloc (avec contenu)  :::tag{attr="val" attr2="val2"}
                            contenu markdown...
                           :::

     Inline (sans contenu) ::tag{attr="val"}

   Chaque plugin est déclaré en JS via DocsPlugins.register(tag, {...})
   (voir js/plugin-loader.js et plugins/<nom>/index.js).
   ========================================================================= */

window.DocsMarkdown = (() => {
  function parseFrontMatter(raw) {
    const match = /^---\s*\n([\s\S]*?)\n---\s*\n?/.exec(raw);
    if (!match) return { meta: {}, body: raw };

    const meta = {};
    match[1].split("\n").forEach((line) => {
      const m = /^([A-Za-z0-9_]+):\s*(.*)$/.exec(line.trim());
      if (m) meta[m[1]] = m[2].replace(/^["']|["']$/g, "");
    });

    return { meta, body: raw.slice(match[0].length) };
  }

  function escapeHtml(str) {
    return String(str).replace(
      /[&<>"']/g,
      (ch) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[ch],
    );
  }

  function parseAttrs(str) {
    const attrs = {};
    if (!str) return attrs;
    const re = /([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/g;
    let m;
    while ((m = re.exec(str))) attrs[m[1]] = m[2];
    return attrs;
  }

  function slugify(text) {
    return text
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "");
  }

  /* ---- Placeholders : on sort le contenu "dangereux" (code, plugins) du
     flux avant le traitement ligne par ligne, puis on le réinjecte. ---- */
  function makeStash() {
    const store = [];
    return {
      save(html) {
        const key = `\u0000STASH${store.length}\u0000`;
        store.push(html);
        return key;
      },
      restore(html) {
        return html.replace(/\u0000STASH(\d+)\u0000/g, (_, i) => store[+i]);
      },
    };
  }

  function renderInlinePlugin(tag, attrsStr) {
    const plugin = window.DocsPlugins?.get(tag);
    if (!plugin) {
      return `<div class="docs-plugin-unknown">Plugin inconnu : "${escapeHtml(tag)}". Active-le dans plugins/plugins.json.</div>`;
    }
    try {
      return plugin.render(parseAttrs(attrsStr));
    } catch (err) {
      return `<div class="docs-plugin-unknown">Erreur dans le plugin "${escapeHtml(tag)}" : ${escapeHtml(err.message)}</div>`;
    }
  }

  function renderBlockPlugin(tag, attrsStr, innerMarkdown) {
    const plugin = window.DocsPlugins?.get(tag);
    if (!plugin) {
      return `<div class="docs-plugin-unknown">Plugin inconnu : "${escapeHtml(tag)}". Active-le dans plugins/plugins.json.</div>`;
    }
    try {
      return plugin.render(
        parseAttrs(attrsStr),
        paragraphize(innerMarkdown.trim()),
      );
    } catch (err) {
      return `<div class="docs-plugin-unknown">Erreur dans le plugin "${escapeHtml(tag)}" : ${escapeHtml(err.message)}</div>`;
    }
  }

  function inlineFormat(text) {
    let out = escapeHtml(text);
    out = out.replace(/`([^`]+)`/g, (_, code) => `<code>${code}</code>`);
    out = out.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    out = out.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, "<em>$1</em>");
    out = out.replace(
      /\[([^\]]+)\]\(([^)]+)\)/g,
      (_, label, url) =>
        `<a href="${url}" ${/^https?:\/\//.test(url) ? 'target="_blank" rel="noopener"' : ""}>${label}</a>`,
    );
    return out;
  }

  function paragraphize(md) {
    // Découpe un bloc en paragraphes séparés par ligne vide, en gérant
    // listes simples (-, *, 1.) sans nid imbriqué.
    const blocks = md.split(/\n{2,}/);
    return blocks
      .map((block) => {
        const lines = block
          .split("\n")
          .map((l) => l.trim())
          .filter(Boolean);
        if (!lines.length) return "";

        if (lines.every((l) => /^[-*]\s+/.test(l))) {
          return `<ul>${lines.map((l) => `<li>${inlineFormat(l.replace(/^[-*]\s+/, ""))}</li>`).join("")}</ul>`;
        }
        if (lines.every((l) => /^\d+\.\s+/.test(l))) {
          return `<ol>${lines.map((l) => `<li>${inlineFormat(l.replace(/^\d+\.\s+/, ""))}</li>`).join("")}</ol>`;
        }
        return `<p>${inlineFormat(lines.join(" "))}</p>`;
      })
      .join("\n");
  }
  function render(rawMarkdown) {
    console.log("[DocsMarkdown] render() appelé");

    // ---- Vérification de Prism ----
    if (typeof Prism === "undefined") {
      console.warn(
        "[DocsMarkdown] Prism n'est pas chargé ! La coloration syntaxique ne fonctionnera pas.",
      );
    } else {
      console.log(
        "[DocsMarkdown] Prism est chargé (version :",
        Prism.version || "inconnue",
        ")",
      );
    }

    const { meta, body } = parseFrontMatter(rawMarkdown);
    const stash = makeStash();
    const toc = [];
    let src = body;

    // 1) Blocs de code
    src = src.replace(
      /```([a-zA-Z0-9_+-]*)\n([\s\S]*?)```/g,
      (_, lang, code) => {
        const trimmed = code.replace(/\n$/, "");
        const langClass = lang || "text";
        console.log(
          `[DocsMarkdown] Bloc de code détecté, langage : "${langClass}"`,
        );

        const html = `
      <div class="docs-code">
        <div class="docs-code-head">
          <span>${escapeHtml(langClass)}</span>
          <button class="docs-copy" data-copy="${escapeHtml(trimmed)}" title="Copier">
            <i class="ti ti-copy"></i>
          </button>
        </div>
        <pre><code class="language-${escapeHtml(langClass)}">${escapeHtml(trimmed)}</code></pre>
      </div>`;
        return stash.save(html);
      },
    );

    // 2) Blocs plugins :::tag{attrs} ... :::  -> stash
    src = src.replace(
      /^:::([a-zA-Z0-9_-]+)(\{[^}]*\})?\s*\n([\s\S]*?)\n:::\s*$/gm,
      (_, tag, attrsRaw, inner) => {
        const attrsStr = attrsRaw ? attrsRaw.slice(1, -1) : "";
        return stash.save(
          `<div>${renderBlockPlugin(tag, attrsStr, inner)}</div>`,
        );
      },
    );

    // 3) Plugins inline ::tag{attrs} -> stash
    src = src.replace(/::([a-zA-Z0-9_-]+)(\{[^}]*\})?/g, (_, tag, attrsRaw) => {
      const attrsStr = attrsRaw ? attrsRaw.slice(1, -1) : "";
      return stash.save(renderInlinePlugin(tag, attrsStr));
    });

    // 4) Découpage en blocs (lignes vides = séparateur)
    const lines = src.split("\n");
    const htmlParts = []; // <-- DÉCLARATION OBLIGATOIRE
    let listBuffer = null;
    let paraBuffer = [];

    function flushPara() {
      if (paraBuffer.length) {
        if (/^</.test(paraBuffer[0].trim())) {
          htmlParts.push(paraBuffer.join("\n"));
        } else {
          htmlParts.push(`<p>${inlineFormat(paraBuffer.join(" "))}</p>`);
        }
        paraBuffer = [];
      }
    }
    function flushList() {
      if (listBuffer) {
        const tag = listBuffer.type;
        htmlParts.push(
          `<${tag}>${listBuffer.items.map((i) => `<li>${inlineFormat(i)}</li>`).join("")}</${tag}>`,
        );
        listBuffer = null;
      }
    }

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];

      // Tables markdown
      if (
        /^\s*\|.*\|\s*$/.test(line) &&
        /^\s*\|[\s:|-]+\|\s*$/.test(lines[i + 1] || "")
      ) {
        flushPara();
        flushList();
        const headerCells = line
          .trim()
          .slice(1, -1)
          .split("|")
          .map((c) => c.trim());
        const rows = [];
        let j = i + 2;
        while (j < lines.length && /^\s*\|.*\|\s*$/.test(lines[j])) {
          rows.push(
            lines[j]
              .trim()
              .slice(1, -1)
              .split("|")
              .map((c) => c.trim()),
          );
          j++;
        }
        htmlParts.push(`
        <table class="docs-table">
          <thead><tr>${headerCells.map((c) => `<th>${inlineFormat(c)}</th>`).join("")}</tr></thead>
          <tbody>${rows.map((r) => `<tr>${r.map((c) => `<td>${inlineFormat(c)}</td>`).join("")}</tr>`).join("")}</tbody>
        </table>`);
        i = j - 1;
        continue;
      }

      if (/^\u0000STASH\d+\u0000$/.test(line.trim())) {
        flushPara();
        flushList();
        htmlParts.push(line.trim());
        continue;
      }

      const h = /^(#{1,4})\s+(.*)$/.exec(line);
      if (h) {
        flushPara();
        flushList();
        const level = h[1].length;
        const text = h[2].trim();
        const id = slugify(text);
        if (level <= 3) toc.push({ level, text, id });
        htmlParts.push(
          `<h${level} id="${id}">${inlineFormat(text)}</h${level}>`,
        );
        continue;
      }

      if (/^---+$/.test(line.trim())) {
        flushPara();
        flushList();
        htmlParts.push("<hr>");
        continue;
      }

      const ul = /^[-*]\s+(.*)$/.exec(line);
      if (ul) {
        flushPara();
        if (!listBuffer || listBuffer.type !== "ul") {
          flushList();
          listBuffer = { type: "ul", items: [] };
        }
        listBuffer.items.push(ul[1]);
        continue;
      }

      const ol = /^\d+\.\s+(.*)$/.exec(line);
      if (ol) {
        flushPara();
        if (!listBuffer || listBuffer.type !== "ol") {
          flushList();
          listBuffer = { type: "ol", items: [] };
        }
        listBuffer.items.push(ol[1]);
        continue;
      }

      const bq = /^>\s?(.*)$/.exec(line);
      if (bq) {
        flushPara();
        flushList();
        htmlParts.push(
          `<blockquote><p>${inlineFormat(bq[1])}</p></blockquote>`,
        );
        continue;
      }

      if (line.trim() === "") {
        flushPara();
        flushList();
        continue;
      }

      flushList();
      paraBuffer.push(line.trim());
    }
    flushPara();
    flushList();

    let html = htmlParts.join("\n");
    html = stash.restore(html);

    console.log(
      "[DocsMarkdown] HTML final généré (extrait) :",
      html.substring(0, 300) + "...",
    );

    // ---- On ne fait PAS appel à Prism ici, car le DOM n'est pas encore mis à jour ----
    // L'appel sera fait par le code qui injecte le HTML (dans app.js, par exemple)

    return { html, meta, toc };
  }

  return { render, slugify, escapeHtml };
})();
