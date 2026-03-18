# Mermaid V2 Plan

## Goal

Implement Mermaid support with minimal, maintainable changes:

- Frontend article pages render Mermaid diagrams.
- Admin editor supports inserting Mermaid code blocks and previewing them.
- Keep Markdown storage format unchanged.
- Avoid DOM hacks, polling, duplicate runtimes, and unsafe HTML insertion.

## Branch Strategy

- Base work on `master`.
- Implement on `feature/mermaid-v2`.
- Do not continue from the existing `feature/mermaid` implementation.

## Core Decisions

### 1. Keep Markdown format unchanged

Do not add a custom PHP Mermaid parser.

Posts continue to store Mermaid as standard fenced code blocks:

```md
```mermaid
graph TD
    A --> B
```
```

Server-side Markdown rendering stays unchanged and outputs normal code blocks such as:

```html
<pre><code class="language-mermaid">...</code></pre>
```

### 2. Frontend uses progressive enhancement

Only article detail pages should load Mermaid assets.

Frontend script behavior:

- Find `pre > code.language-mermaid`.
- Read source via `textContent`.
- Render with Mermaid.
- Replace the surrounding `<pre>` with rendered output on success.
- Keep original code block visible on failure.
- Show error text safely with text nodes, not raw `innerHTML`.

Do not load Mermaid on index/list pages because excerpts do not contain rendered Mermaid content.

### 3. Admin editor uses Crepe's native code block preview path

Current dependency is `@milkdown/crepe 7.18.0`.

Use existing Crepe extension points instead of custom wrapper scripts:

- `CodeMirror.renderPreview`
- `BlockEdit.buildMenu`
- optionally `Toolbar.buildToolbar`

Admin behavior:

- Add a slash-menu item for Mermaid.
- Insert a code block with language `mermaid`.
- Reuse Crepe code-block preview for Mermaid blocks only.
- Leave other code block behavior unchanged.

Do not implement:

- MutationObserver-based DOM scanning
- delayed auto-initialization via `setTimeout`
- global `window.blogmeMermaid*` APIs
- custom textarea replacement UI
- fake syntax highlighting systems

### 4. Security defaults

- Mermaid config should use `securityLevel: 'strict'`.
- Backend preview should rely on Milkdown's preview sanitization path.
- Frontend rendered output should be sanitized before insertion.
- Error messages must be inserted as plain text.

## Expected Dependency Changes

Add:

- `mermaid`
- `dompurify`

Use local bundled assets only. Do not load Mermaid from CDN.

## Expected File Changes

### Backend / Admin

- `package.json`
- `package-lock.json`
- `public/admin/assets/editor-crepe-src.js`
- `public/admin/assets/editor-crepe.js`
- `resources/templates/admin_post_create.html`
- `resources/templates/admin_post_edit.html`

### Frontend

- `resources/templates/admin_appearances.html`
- `app/Controllers/AdminController.php`
- `public/themes/default/singular.html`
- new frontend Mermaid source asset
- bundled frontend Mermaid asset

### Tests / Validation

- add or update tests where behavior changed

## Implementation Steps

1. Add Mermaid-related npm dependencies.
2. Extend `editor-crepe-src.js`:
   - add Mermaid insert helper
   - wire Mermaid slash-menu entry
   - wire Mermaid code-block preview renderer
3. Rebuild admin editor bundle.
4. Add frontend Mermaid page script and bundle it locally.
5. Load frontend Mermaid assets only on singular article pages.
6. Add admin appearance toggle for Mermaid and persist config.
7. Validate that disabled mode falls back to plain code blocks.
8. Run PHPUnit and frontend build verification.

## Non-Goals

- Do not introduce a custom PHP Mermaid parser.
- Do not alter the post storage model.
- Do not replace Crepe with a custom editor integration.
- Do not implement advanced in-editor diagram widgets in v2.

## Acceptance Criteria

- Admin can insert Mermaid blocks quickly.
- Admin can preview Mermaid blocks directly in the editor.
- Frontend article pages render Mermaid diagrams correctly.
- When Mermaid is disabled or rendering fails, raw code blocks still remain usable.
- No CDN dependency is required.
- No polling or DOM-guessing initialization path exists.
- Tests and build pass.
