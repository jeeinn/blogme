import DOMPurify from "dompurify";
import mermaid from "mermaid";

let mermaidInitialized = false;
let mermaidRenderIndex = 0;

const ensureMermaid = () => {
    if (mermaidInitialized) {
        return;
    }

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: "strict",
        theme: "default",
        fontFamily: "inherit",
        htmlLabels: false,
    });
    mermaidInitialized = true;
};

const sanitizeSvg = svg => DOMPurify.sanitize(svg, {
    USE_PROFILES: {
        svg: true,
        svgFilters: true,
    },
});

const isMermaidCodeBlock = codeBlock => Array.from(codeBlock.classList)
    .some(className => className.toLowerCase() === "language-mermaid");

const renderDiagram = async (codeBlock, index) => {
    const container = document.createElement("div");
    container.className = "mermaid-diagram";

    const source = (codeBlock.textContent ?? "").trim();
    if (source === "") {
        return;
    }

    try {
        const diagramId = `blogme-post-mermaid-${index}-${mermaidRenderIndex++}`;
        const { svg, bindFunctions } = await mermaid.render(diagramId, source);
        container.innerHTML = sanitizeSvg(svg);
        bindFunctions?.(container);
        codeBlock.parentElement?.replaceWith(container);
    } catch (error) {
        console.error("Failed to render Mermaid diagram.", error);
    }
};

const renderMermaidBlocks = async () => {
    const root = document.querySelector("#singular-content");
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const codeBlocks = Array.from(root.querySelectorAll("pre > code"))
        .filter(isMermaidCodeBlock);
    if (codeBlocks.length === 0) {
        return;
    }

    ensureMermaid();
    for (const [index, codeBlock] of codeBlocks.entries()) {
        await renderDiagram(codeBlock, index);
    }
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        void renderMermaidBlocks();
    }, { once: true });
} else {
    void renderMermaidBlocks();
}
