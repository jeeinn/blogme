// Markdown <-> Editor.js blocks adapter for blogme admin editor.
// Storage contract: the post body is stored as STANDARD MARKDOWN (Tunalog compatible).
// Editor.js works with structured blocks, so we translate at the boundary:
//   - load:  markdown string  -> Editor.js blocks (fed to editor.render)
//   - save:  Editor.js blocks -> markdown string (written back to #content)
//
// mermaid is represented as a ```mermaid fenced code block in markdown, which the
// public site already renders via mermaid-page.js (matches `pre > code.language-mermaid`).

import MarkdownIt from "markdown-it";

const md = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: false,
});

const MERMAID_LANG = "mermaid";

// ---------------------------------------------------------------------------
// Markdown -> blocks
// ---------------------------------------------------------------------------

function renderInline(inlineToken) {
  if (!inlineToken) return "";
  return md.renderer.render([inlineToken], md.options, {}).trim();
}

function plain(inlineToken) {
  return inlineToken ? (inlineToken.content || "").trim() : "";
}

function isStandaloneImage(html) {
  if (!html) return null;
  let doc;
  try {
    doc = new DOMParser().parseFromString(html, "text/html");
  } catch (e) {
    return null;
  }
  const imgs = doc.querySelectorAll("img");
  if (imgs.length !== 1) return null;
  if ((doc.body.textContent || "").trim() !== "") return null;
  const img = imgs[0];
  return {
    type: "image",
    data: {
      url: img.getAttribute("src") || "",
      caption: img.getAttribute("alt") || "",
      withBorder: false,
      stretched: false,
      withBackground: false,
    },
  };
}

function buildList(tokens, start, env, out) {
  const open = tokens[start];
  const style = open.type === "ordered_list_open" ? "ordered" : "unordered";
  const items = [];
  let i = start + 1;
  const closeType =
    style === "ordered" ? "ordered_list_close" : "bullet_list_close";
  while (i < tokens.length && tokens[i].type !== closeType) {
    if (tokens[i].type === "list_item_open") {
      const res = buildListItem(tokens, i, env);
      items.push(res.item);
      i = res.next;
    } else {
      i++;
    }
  }
  out.push({ type: "list", data: { style, items } });
  return i + 1;
}

// Editor.js List tool v2 stores every item as { content, meta, items }
// where `items` holds nested list items. Keep this shape in sync with
// @editorjs/list or rendering/saving breaks.
function buildListItem(tokens, start, env) {
  let i = start + 1;
  const parts = [];
  const children = [];
  while (i < tokens.length && tokens[i].type !== "list_item_close") {
    const t = tokens[i];
    if (t.type === "paragraph_open") {
      parts.push(renderInline(tokens[i + 1]));
      i += 3;
    } else if (
      t.type === "bullet_list_open" ||
      t.type === "ordered_list_open"
    ) {
      const nested = [];
      i = buildList(tokens, i, env, nested);
      nested.forEach((block) => {
        if (block.type === "list" && block.data) {
          children.push(...(block.data.items || []));
        }
      });
    } else {
      i++;
    }
  }
  return {
    item: { content: parts.join(" "), meta: {}, items: children },
    next: i + 1,
  };
}

function buildBlockquote(tokens, start, env, out) {
  let i = start + 1;
  const parts = [];
  while (i < tokens.length && tokens[i].type !== "blockquote_close") {
    const t = tokens[i];
    if (t.type === "paragraph_open") {
      parts.push("<p>" + renderInline(tokens[i + 1]) + "</p>");
      i += 3;
    } else if (
      t.type === "bullet_list_open" ||
      t.type === "ordered_list_open"
    ) {
      const nested = [];
      i = buildList(tokens, i, env, nested);
      nested.forEach((block) => {
        if (block.type === "list") {
          parts.push(renderListBlock(block.data));
        }
      });
    } else {
      i++;
    }
  }
  out.push({ type: "quote", data: { text: parts.join(""), caption: "" } });
  return i + 1;
}

function buildTable(tokens, start, out) {
  let i = start + 1;
  const rows = [];
  let current = null;
  while (i < tokens.length && tokens[i].type !== "table_close") {
    const t = tokens[i];
    if (t.type === "tr_open") {
      current = [];
      i++;
    } else if (t.type === "td_open" || t.type === "th_open") {
      current.push(plain(tokens[i + 1]));
      i += 3;
    } else if (t.type === "tr_close") {
      rows.push(current);
      current = null;
      i++;
    } else {
      i++;
    }
  }
  out.push({
    type: "table",
    data: { withHeadings: rows.length > 0, content: rows },
  });
  return i + 1;
}

export function markdownToBlocks(source) {
  if (typeof source !== "string" || source.trim() === "") {
    return [];
  }
  try {
    if (!source.trim()) {
      return [{ type: "paragraph", data: { text: "" } }];
    }
    const tokens = md.parse(source, {});
    const blocks = [];
    let i = 0;
    while (i < tokens.length) {
      const token = tokens[i];
      switch (token.type) {
        case "heading_open": {
          const level = Number((token.tag || "p").slice(1)) || 2;
          blocks.push({
            type: "header",
            data: { text: plain(tokens[i + 1]), level },
          });
          i += 3;
          break;
        }
        case "paragraph_open": {
          const html = renderInline(tokens[i + 1]);
          const img = isStandaloneImage(html);
          if (img) {
            blocks.push(img);
          } else {
            blocks.push({ type: "paragraph", data: { text: html } });
          }
          i += 3;
          break;
        }
        case "bullet_list_open":
        case "ordered_list_open":
          i = buildList(tokens, i, {}, blocks);
          break;
        case "blockquote_open":
          i = buildBlockquote(tokens, i, {}, blocks);
          break;
        case "fence": {
          const info = (token.info || "").trim().toLowerCase();
          if (info === MERMAID_LANG) {
            blocks.push({ type: "mermaid", data: { source: token.content } });
          } else {
            blocks.push({
              type: "code",
              data: { code: token.content, language: (token.info || "").trim() },
            });
          }
          i += 1;
          break;
        }
        case "hr":
          blocks.push({ type: "delimiter", data: {} });
          i += 1;
          break;
        case "table_open":
          i = buildTable(tokens, i, blocks);
          break;
        default:
          i += 1;
      }
    }
    return blocks.length ? blocks : [{ type: "paragraph", data: { text: "" } }];
  } catch (e) {
    if (typeof console !== "undefined" && console.error) {
      console.error("[markdownToBlocks] caught:", e && e.stack ? e.stack : e);
    }
    // Fallback: never lose content. Store raw markdown as a single paragraph.
    return [
      {
        type: "paragraph",
        data: { text: escapeHtmlAsText(source) },
      },
    ];
  }
}

function escapeHtmlAsText(text) {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

// ---------------------------------------------------------------------------
// blocks -> Markdown
// ---------------------------------------------------------------------------

function htmlToMarkdown(html) {
  if (typeof html !== "string") {
    if (html && typeof html === "object") {
      // Defensive: never stringify objects into markdown (e.g. List v2 items).
      return htmlToMarkdown(html.content ?? "");
    }
    return "";
  }
  if (!html) return "";
  let doc;
  try {
    doc = new DOMParser().parseFromString(html, "text/html");
  } catch (e) {
    return String(html);
  }
  const root = doc.body || doc;
  return convertChildren(root, 0).replace(/\n{3,}/g, "\n\n").trim();
}

function convertChildren(node, depth) {
  let out = "";
  Array.from(node.childNodes).forEach((child) => {
    out += convertNode(child, depth);
  });
  return out;
}

function convertNode(node, depth) {
  if (node.nodeType === 3) {
    // text
    return (node.textContent || "").replace(/\s+/g, " ");
  }
  const tag = node.tagName ? node.tagName.toLowerCase() : "";
  switch (tag) {
    case "br":
      return "  \n";
    case "strong":
    case "b":
      return `**${convertChildren(node, depth)}**`;
    case "em":
    case "i":
      return `*${convertChildren(node, depth)}*`;
    case "code":
      return "`" + (node.textContent || "") + "`";
    case "del":
    case "s":
      return `~~${convertChildren(node, depth)}~~`;
    case "a":
      return `[${convertChildren(node, depth)}](${node.getAttribute("href") || ""})`;
    case "img": {
      const alt = node.getAttribute("alt") || "";
      const src = node.getAttribute("src") || "";
      const title = node.getAttribute("title");
      return `![${alt}](${src}${title ? ' "' + title + '"' : ""})`;
    }
    case "p":
      return convertChildren(node, depth).trim() + "\n\n";
    case "ul":
    case "ol":
      return renderListHtml(node, depth) + "\n\n";
    case "blockquote": {
      const inner = convertChildren(node, depth).replace(/\n+$/, "");
      return (
        inner
          .split("\n")
          .map((l) => (l.trim() === "" ? ">" : "> " + l))
          .join("\n") + "\n\n"
      );
    }
    case "pre": {
      const code = node.querySelector("code");
      const cls = code ? code.className || "" : "";
      const lang = (cls.match(/language-([\w-]+)/) || [])[1] || "";
      const text = code ? code.textContent || "" : node.textContent || "";
      return "```" + lang + "\n" + text + "\n```\n\n";
    }
    case "h1":
    case "h2":
    case "h3":
    case "h4":
    case "h5":
    case "h6": {
      const lvl = Number(tag[1]);
      return "#".repeat(lvl) + " " + convertChildren(node, depth).trim() + "\n\n";
    }
    case "hr":
      return "---\n\n";
    case "table":
      return renderTableHtml(node) + "\n\n";
    case "div":
    case "span":
    case "section":
    case "article":
      return convertChildren(node, depth);
    default:
      return convertChildren(node, depth);
  }
}

function renderListHtml(listNode, depth) {
  const ordered = listNode.tagName.toLowerCase() === "ol";
  const items = Array.from(listNode.children).filter(
    (c) => c.tagName && c.tagName.toLowerCase() === "li",
  );
  const indent = "  ".repeat(depth);
  const lines = [];
  items.forEach((li, idx) => {
    const marker = ordered ? idx + 1 + ". " : "- ";
    const markerPad = " ".repeat(marker.length);
    const childMd = convertChildren(li, depth + 1);
    const subLines = childMd.split("\n");
    let first = true;
    for (const ln of subLines) {
      if (ln.trim() === "") {
        lines.push("");
        continue;
      }
      if (first) {
        lines.push(indent + marker + ln);
        first = false;
      } else {
        lines.push(indent + markerPad + ln);
      }
    }
  });
  return lines.join("\n");
}

function renderTableHtml(tableNode) {
  const rows = Array.from(tableNode.querySelectorAll("tr"));
  if (!rows.length) return "";
  const cellText = (tr) =>
    Array.from(tr.querySelectorAll("th, td")).map(
      (c) => (c.textContent || "").trim().replace(/\|/g, "\\|"),
    );
  const lines = rows.map((tr) => "| " + cellText(tr).join(" | ") + " |");
  if (lines.length > 1) {
    const cols = lines[0].split("|").length - 2;
    const sep = "| " + Array(cols).fill("---").join(" | ") + " |";
    lines.splice(1, 0, sep);
  }
  return lines.join("\n");
}

function renderListBlock(data, depth = 0) {
  const ordered = data.style === "ordered";
  const items = data.items || [];
  const lines = [];
  const indent = "  ".repeat(depth);
  items.forEach((item, idx) => {
    // List tool v2 saves items as objects ({ content, items }); older data
    // (and our own adapter fallbacks) may still use plain strings.
    const itemHtml = typeof item === "string" ? item : item && item.content;
    const nestedItems =
      item && typeof item === "object" && Array.isArray(item.items)
        ? item.items
        : [];
    const marker = ordered ? idx + 1 + ". " : "- ";
    const markerPad = " ".repeat(marker.length);
    const childMd = htmlToMarkdown(itemHtml);
    const subLines = childMd.split("\n");
    let first = true;
    for (const ln of subLines) {
      if (ln.trim() === "") {
        lines.push("");
        continue;
      }
      if (first) {
        lines.push(indent + marker + ln);
        first = false;
      } else {
        lines.push(indent + markerPad + ln);
      }
    }
    if (nestedItems.length) {
      lines.push(renderListBlock({ style: data.style, items: nestedItems }, depth + 1));
    }
  });
  return lines.join("\n");
}

function renderTableBlock(data) {
  const rows = data.content || [];
  if (!rows.length) return "";
  const lines = rows.map((r) => "| " + (r || []).join(" | ") + " |");
  if (lines.length > 1) {
    const cols = (rows[0] || []).length;
    const sep = "| " + Array(Math.max(cols, 1)).fill("---").join(" | ") + " |";
    lines.splice(1, 0, sep);
  }
  return lines.join("\n");
}

function escapeHeading(text) {
  return (text || "").replace(/^(\s{0,3}#{1,6}\s)/, (m) => m.replace(/#/g, "\\#"));
}

export function blocksToMarkdown(blocks) {
  if (!Array.isArray(blocks) || !blocks.length) return "";
  try {
    const lines = [];
    for (const block of blocks) {
      const data = block.data || {};
      switch (block.type) {
        case "header":
          lines.push(
            "#".repeat(data.level || 2) + " " + escapeHeading(data.text || ""),
          );
          break;
        case "paragraph":
          lines.push(htmlToMarkdown(data.text || ""));
          break;
        case "list":
          lines.push(renderListBlock(data));
          break;
        case "quote": {
          const q = htmlToMarkdown(data.text || "")
            .split("\n")
            .map((l) => (l.trim() === "" ? ">" : "> " + l))
            .join("\n");
          lines.push(q);
          if (data.caption) lines.push("> — " + data.caption);
          break;
        }
        case "code":
          lines.push(
            "```" +
              (data.language || "") +
              "\n" +
              (data.code || "").replace(/\s+$/, "") +
              "\n```",
          );
          break;
        case "mermaid":
          lines.push(
            "```mermaid\n" + (data.source || "").replace(/\s+$/, "") + "\n```",
          );
          break;
        case "image":
          lines.push(
            "![" + (data.caption || "") + "](" + (data.url || "") + ")",
          );
          break;
        case "delimiter":
          lines.push("---");
          break;
        case "table":
          lines.push(renderTableBlock(data));
          break;
        default:
          if (data.text) lines.push(htmlToMarkdown(data.text));
      }
      lines.push("");
    }
    return lines.join("\n").replace(/\n{3,}/g, "\n\n").trim() + "\n";
  } catch (e) {
    if (typeof console !== "undefined" && console.error) {
      console.error("[blocksToMarkdown] caught:", e && e.stack ? e.stack : e);
    }
    // Fallback: concatenate any readable text so nothing is destroyed.
    return blocks
      .map((b) => (b.data && (b.data.text || b.data.code || b.data.source)) || "")
      .join("\n\n")
      .trim();
  }
}
