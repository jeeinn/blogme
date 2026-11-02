// blogme admin editor — Editor.js build (replaces Milkdown Crepe).
//
// Design contract (must stay compatible with blogme admin templates):
//   - framework-agnostic (blogme is native PHP + Alpine, no React/Vue runtime)
//   - Markdown IN / Markdown OUT: post body is stored as standard markdown
//     (Tunalog compatible; Parsedown renders it server-side, zero JS cost)
//   - image upload hooks into existing POST /admin/photos/api (returns {path})
//   - editor is loaded via dynamic import() in the template so the heavy bundle
//     never blocks first paint / cold-cache open on a different machine
//
// mermaid: authored as a dedicated Mermaid block; on save it becomes a
// ```mermaid fenced code block in the markdown, which mermaid-page.js renders
// on the public post page. No mermaid runtime is bundled into the editor.

import EditorJS from "@editorjs/editorjs";
import Header from "@editorjs/header";
import List from "@editorjs/list";
import Image from "@editorjs/image";
import Quote from "@editorjs/quote";
import CodeTool from "@editorjs/code";
import Delimiter from "@editorjs/delimiter";
import Table from "@editorjs/table";
import { MermaidTool } from "./mermaid-tool.js";
import { markdownToBlocks, blocksToMarkdown } from "./markdown-adapter.js";
import "./editor-ejs-theme.css";

const normalizeUploadPath = (path, urlRoot) => {
  if (
    /^(?:[a-z]+:)?\/\//i.test(path) ||
    path.startsWith("data:") ||
    path.startsWith("blob:")
  ) {
    return path;
  }
  if (path.startsWith("/")) {
    return path;
  }
  const safeRoot = (urlRoot ?? "/").trim();
  const normalizedRoot = safeRoot === "" ? "/" : safeRoot;
  const rootWithOneTrailingSlash = normalizedRoot.replace(/\/+$/, "/");
  const normalizedPath = path.replace(/^\/+/, "");
  return `${rootWithOneTrailingSlash}${normalizedPath}`;
};

const uploadImage = async (file, uploadUrl, csrfToken, urlRoot) => {
  const formData = new FormData();
  if (csrfToken) {
    formData.append("_csrf", csrfToken);
  }
  formData.append("photo_file", file);

  const response = await fetch(uploadUrl, {
    method: "POST",
    body: formData,
  });
  if (!response.ok) {
    throw new Error("Image upload failed.");
  }

  const payload = await response.json();
  const path = typeof payload.path === "string" ? payload.path.trim() : "";
  if (path === "") {
    throw new Error("Image upload returned an empty path.");
  }
  return normalizeUploadPath(path, urlRoot);
};

export const initBlogmeEditor = async (options = {}) => {
  const form = document.querySelector(options.formSelector ?? "form");
  const contentInput = document.querySelector(
    options.contentSelector ?? "#content",
  );
  const holder = document.querySelector(options.holderSelector ?? "#block-editor");
  const uploadUrl = (options.uploadUrl ?? "").trim();
  const csrfToken = options.csrfToken ?? "";
  const urlRoot = (options.urlRoot ?? "/").trim();

  if (!form || !contentInput || !holder) {
    return null;
  }

  const initialMarkdown = contentInput.value ?? "";
  let latestMarkdown = initialMarkdown;
  let isDirty = false;
  const unsavedHandler = (event) => {
    event.preventDefault();
    event.returnValue = "";
  };

  const setDirtyState = (dirty) => {
    if (dirty === isDirty) {
      return;
    }
    isDirty = dirty;
    if (isDirty) {
      window.addEventListener("beforeunload", unsavedHandler);
    } else {
      window.removeEventListener("beforeunload", unsavedHandler);
    }
  };

  const imageUploader = {
    uploadByFile: async (file) => {
      const url = await uploadImage(file, uploadUrl, csrfToken, urlRoot);
      return { success: 1, file: { url } };
    },
  };

  let editor;
  try {
    editor = new EditorJS({
      holder,
      tools: {
        header: { class: Header, config: { placeholder: "标题", levels: [2, 3, 4] } },
        list: { class: List, config: { defaultStyle: "unordered" } },
        image: {
          class: Image,
          config: {
            uploader: imageUploader,
            types: "image/*",
          },
        },
        quote: { class: Quote, config: { placeholder: "引用内容" } },
        code: { class: CodeTool, config: { placeholder: "代码" } },
        delimiter: Delimiter,
        table: { class: Table, config: { rows: 2, cols: 2 } },
        mermaid: MermaidTool,
      },
      data: { blocks: markdownToBlocks(initialMarkdown) },
      onChange: async () => {
        try {
          const output = await editor.save();
          latestMarkdown = blocksToMarkdown(output.blocks);
          contentInput.value = latestMarkdown;
          setDirtyState(latestMarkdown !== initialMarkdown);
        } catch (e) {
          // Keep the last good markdown so a transient save error can't wipe content.
          console.error("Editor.js save failed:", e);
        }
      },
    });

    await editor.isReady;
  } catch (e) {
    // Never leave the page without an editable body: fall back to the raw
    // markdown textarea so the article can still be edited and saved.
    console.error("Editor.js init failed, falling back to textarea:", e);
    holder.style.display = "none";
    contentInput.style.display = "";
    contentInput.style.minHeight = "420px";
    contentInput.value = initialMarkdown;
    form.addEventListener("submit", () => {
      contentInput.value = contentInput.value || initialMarkdown;
      setDirtyState(false);
    });
    return null;
  }

  // Establish the baseline after initial render.
  try {
    const output = await editor.save();
    latestMarkdown = blocksToMarkdown(output.blocks);
    contentInput.value = latestMarkdown;
  } catch (e) {
    latestMarkdown = initialMarkdown;
    contentInput.value = initialMarkdown;
  }

  form.addEventListener("submit", () => {
    // onChange keeps #content fresh; flush the latest baseline on submit.
    contentInput.value = latestMarkdown || initialMarkdown;
    setDirtyState(false);
  });

  return editor;
};
