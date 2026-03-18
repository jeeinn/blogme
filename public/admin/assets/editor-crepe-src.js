import { Crepe } from "@milkdown/crepe";
import { LanguageDescription, LanguageSupport, StreamLanguage } from "@codemirror/language";
import { commandsCtx } from "@milkdown/kit/core";
import {
    clearTextInCurrentBlockCommand,
    codeBlockSchema,
    setBlockTypeCommand,
} from "@milkdown/kit/preset/commonmark";
import mermaid from "mermaid";
import "./editor-crepe-theme.css";
import "@milkdown/crepe/theme/frame.css";

const MERMAID_ICON = `
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <path d="M4 17V7l4 5 4-5v10" />
  <path d="M14 17V7l3 3 3-3v10" />
</svg>
`;

const mermaidCodeMirrorSupport = new LanguageSupport(
    StreamLanguage.define({
        name: "Mermaid",
        token: stream => {
            stream.skipToEnd();
            return null;
        },
    })
);

const mermaidLanguage = LanguageDescription.of({
    name: "Mermaid",
    alias: ["mermaid", "mmd"],
    extensions: ["mmd"],
    support: mermaidCodeMirrorSupport,
});

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

const renderMermaidSvg = async source => {
    ensureMermaid();
    const diagramId = `blogme-mermaid-${mermaidRenderIndex++}`;
    const { svg } = await mermaid.render(diagramId, source);
    return svg;
};

const insertMermaidBlock = ctx => {
    const commands = ctx.get(commandsCtx);
    const codeBlock = codeBlockSchema.type(ctx);

    commands.call(clearTextInCurrentBlockCommand.key);
    commands.call(setBlockTypeCommand.key, {
        nodeType: codeBlock,
        attrs: {
            language: "mermaid",
        },
    });
};

const buildUnsavedHandler = () => event => {
    event.preventDefault();
    event.returnValue = "";
};

const normalizeUploadPath = (path, urlRoot) => {
    if (/^(?:[a-z]+:)?\/\//i.test(path) || path.startsWith("data:") || path.startsWith("blob:")) {
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

export const initBlogmeCrepe = async (options = {}) => {
    const form = document.querySelector(options.formSelector ?? "form");
    const contentInput = document.querySelector(options.contentSelector ?? "#content");
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
    const unsavedHandler = buildUnsavedHandler();

    const setDirtyState = dirty => {
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

    const imageUpload = async file => uploadImage(file, uploadUrl, csrfToken, urlRoot);
    const featureConfigs = {
        [Crepe.Feature.CodeMirror]: {
            languages: [mermaidLanguage],
            renderPreview: (language, content, applyPreview) => {
                if (language.trim().toLowerCase() !== "mermaid") {
                    return null;
                }

                const source = content.trim();
                if (source === "") {
                    return null;
                }

                void renderMermaidSvg(source)
                    .then(applyPreview)
                    .catch(() => {
                        applyPreview(null);
                    });
                return undefined;
            },
        },
        [Crepe.Feature.BlockEdit]: {
            buildMenu: builder => {
                builder.getGroup("advanced").addItem("mermaid", {
                    label: "Mermaid",
                    icon: MERMAID_ICON,
                    onRun: insertMermaidBlock,
                });
            },
        },
    };

    if (uploadUrl !== "") {
        featureConfigs[Crepe.Feature.ImageBlock] = {
            onUpload: imageUpload,
            inlineOnUpload: imageUpload,
            blockOnUpload: imageUpload,
        };
    }

    const crepe = new Crepe({
        root: holder,
        defaultValue: initialMarkdown,
        features: {
            [Crepe.Feature.Latex]: false,
        },
        featureConfigs,
    });

    crepe.on(listener => {
        listener.markdownUpdated((_ctx, markdown) => {
            latestMarkdown = markdown;
            contentInput.value = markdown;
            setDirtyState(markdown !== initialMarkdown);
        });
    });

    await crepe.create();
    latestMarkdown = crepe.getMarkdown();
    contentInput.value = latestMarkdown;

    form.addEventListener("submit", () => {
        contentInput.value = latestMarkdown || crepe.getMarkdown();
        setDirtyState(false);
    });

    return crepe;
};
