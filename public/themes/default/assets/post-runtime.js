(function () {
    var script = document.currentScript;
    if (!(script instanceof HTMLScriptElement)) {
        return;
    }

    var root = document.querySelector("#singular-content");
    if (!(root instanceof HTMLElement)) {
        return;
    }

    var blocks = Array.from(root.querySelectorAll("pre > code"));
    if (blocks.length === 0) {
        return;
    }

    var highlightEnabled = script.dataset.highlightEnabled === "true";
    var mermaidEnabled = script.dataset.mermaidEnabled === "true";
    var highlightSrc = script.dataset.highlightSrc || "";
    var mermaidSrc = script.dataset.mermaidSrc || "";

    var isMermaidBlock = function (block) {
        return Array.from(block.classList).some(function (className) {
            return className.toLowerCase() === "language-mermaid";
        });
    };

    var hasLanguageBlock = blocks.some(function (block) {
        return Array.from(block.classList).some(function (className) {
            return className.toLowerCase().indexOf("language-") === 0;
        });
    });
    var hasMermaid = blocks.some(isMermaidBlock);

    var runHighlight = function () {
        if (!window.hljs) {
            return;
        }
        blocks.forEach(function (block) {
            if (!isMermaidBlock(block)) {
                window.hljs.highlightElement(block);
            }
        });
    };

    if (highlightEnabled && hasLanguageBlock && highlightSrc) {
        if (window.hljs) {
            runHighlight();
        } else {
            var highlightScript = document.createElement("script");
            highlightScript.src = highlightSrc;
            highlightScript.onload = runHighlight;
            document.body.appendChild(highlightScript);
        }
    }

    if (mermaidEnabled && hasMermaid && mermaidSrc) {
        var mermaidScript = document.createElement("script");
        mermaidScript.type = "module";
        mermaidScript.src = mermaidSrc;
        document.body.appendChild(mermaidScript);
    }
})();
