import { Crepe } from "@milkdown/crepe";
import "./editor-crepe-theme.css";
import "@milkdown/crepe/theme/frame.css";

const buildUnsavedHandler = () => event => {
    event.preventDefault();
    event.returnValue = "";
};

const uploadImage = async (file, uploadUrl, csrfToken) => {
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
    return path;
};

export const initBlogmeCrepe = async (options = {}) => {
    const form = document.querySelector(options.formSelector ?? "form");
    const contentInput = document.querySelector(options.contentSelector ?? "#content");
    const holder = document.querySelector(options.holderSelector ?? "#block-editor");
    const uploadUrl = (options.uploadUrl ?? "").trim();
    const csrfToken = options.csrfToken ?? "";

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

    const imageUpload = async file => uploadImage(file, uploadUrl, csrfToken);
    const featureConfigs = uploadUrl === ""
        ? undefined
        : {
            [Crepe.Feature.ImageBlock]: {
                onUpload: imageUpload,
                inlineOnUpload: imageUpload,
                blockOnUpload: imageUpload,
            },
        };

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
