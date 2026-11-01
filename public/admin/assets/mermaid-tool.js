// Custom Editor.js block tool for Mermaid diagrams.
// Stored data shape: { source: "<mermaid source>" }
// On the markdown boundary it is serialized as a ```mermaid fenced block, which the
// public site renders via mermaid-page.js (it matches `pre > code.language-mermaid`).

const ICON = `
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <path d="M4 17V7l4 5 4-5v10" />
  <path d="M14 17V7l3 3 3-3v10" />
</svg>`;

export class MermaidTool {
  static get isReadOnlySupported() {
    return true;
  }

  static get toolbox() {
    return {
      title: "Mermaid 图表",
      icon: ICON,
    };
  }

  // `source` is plain text; do not let Editor.js sanitize it as HTML.
  static get sanitize() {
    return {
      source: false,
    };
  }

  constructor({ data }) {
    this.data = data && typeof data.source === "string" ? data : { source: "" };
    this._textarea = null;
  }

  render() {
    const container = document.createElement("div");
    container.classList.add("blogme-mermaid-block");

    const label = document.createElement("div");
    label.className = "blogme-mermaid-block__label";
    label.innerHTML = `${ICON}<span>Mermaid 图表</span>`;

    const textarea = document.createElement("textarea");
    textarea.className = "blogme-mermaid-block__input";
    textarea.placeholder = "在此粘贴 Mermaid 源码（保存后将在文章页自动渲染）…";
    textarea.value = this.data.source || "";

    container.appendChild(label);
    container.appendChild(textarea);

    this._textarea = textarea;
    return container;
  }

  save() {
    const source = this._textarea
      ? this._textarea.value
      : this.data.source || "";
    return { source };
  }
}
