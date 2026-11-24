@php
    $isEdit          = $this->isEditMode;
    $docRecord       = $isEdit ? $this->record : null;
    $previewUrl      = $isEdit ? route('docs.web.show', $docRecord->slug) : null;
    $publishBtnLabel = ($isEdit && $docRecord?->status === 'published') ? 'Update' : 'Publish page';
    $statusLabel     = match($this->editorStatus) {
        'draft_saved' => 'Draft saved',
        'published'   => 'Published',
        default       => '',
    };
@endphp

<x-filament-panels::page>

@push('styles')
<style>
/* ─────────────────────────────────────────────────────────────────────────
   DESIGN TOKENS
   Dark-mode tokens live on html.de-dark (NOT on .doc-editor-root) so
   Livewire DOM morphing never strips them.
───────────────────────────────────────────────────────────────────────── */
:root {
    --de-bg:         #f8fafc;
    --de-bg-panel:   #ffffff;
    --de-bg-main:    #ffffff;
    --de-border:     #e2e8f0;
    --de-text:       #0f172a;
    --de-text-muted: #64748b;
    --de-text-ph:    #94a3b8;
    --de-brand:      #6366f1;
    --de-brand-hov:  #4f46e5;
    --de-danger:     #dc2626;
}
html.de-dark {
    --de-bg:         #020617;
    --de-bg-panel:   #0f172a;
    --de-bg-main:    rgba(15,23,42,.55);
    --de-border:     #1e293b;
    --de-text:       #f1f5f9;
    --de-text-muted: #94a3b8;
    --de-text-ph:    #475569;
    --de-brand-hov:  #818cf8;
}

/* ─────────────────────────────────────────────────────────────────────────
   FULL-VIEWPORT TAKEOVER
───────────────────────────────────────────────────────────────────────── */
.doc-editor-root {
    position: fixed !important; inset: 0 !important; z-index: 500 !important;
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--de-bg); color: var(--de-text);
    font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    -webkit-font-smoothing: antialiased;
}
[x-data*="modal"], [x-data*="dialog"], [x-data*="notification"],
.fi-modal, .fi-notification, .fi-notifications,
.fi-modal-container, .fi-notification-container,
#filament-modals, .filament-notifications-container { z-index: 1000 !important; }

/* ─────────────────────────────────────────────────────────────────────────
   TOPBAR
───────────────────────────────────────────────────────────────────────── */
.de-topbar {
    height: 56px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1rem; gap: .75rem;
    background: var(--de-bg-panel); border-bottom: 1px solid var(--de-border);
}
.de-topbar-left, .de-topbar-right { display: flex; align-items: center; gap: .5rem; }

.de-back-btn {
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border-radius: 7px; color: var(--de-text-muted);
    cursor: pointer; background: transparent; border: 0;
    transition: background .15s, color .15s; text-decoration: none;
}
.de-back-btn:hover { background: var(--de-border); color: var(--de-text); }
.de-back-btn svg { width: 16px; height: 16px; }

.de-sep { width: 1px; height: 18px; background: var(--de-border); flex-shrink: 0; margin: 0 .125rem; }

/* Language / translations controls — sidebar panel */
.de-lang-panel {
    padding: .9rem 1rem; border-bottom: 1px solid var(--de-border);
    display: flex; flex-direction: column; gap: .5rem;
}
.de-lang-panel-title {
    font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
    color: var(--de-text-muted);
}
.de-lang-panel-actions { display: flex; align-items: center; gap: .35rem; }
.de-lang-static { font-size: .85rem; color: var(--de-text); font-weight: 500; }
.de-lang-static span { color: var(--de-text-muted); font-weight: 400; }
.de-lang-hint { font-size: .72rem; color: var(--de-text-muted); line-height: 1.4; }
.de-lang-select {
    width: 100%; height: 34px; border-radius: 7px; border: 1px solid var(--de-border);
    background: var(--de-bg-panel); color: var(--de-text); font-size: .82rem;
    padding: 0 .55rem; cursor: pointer;
}
html.de-dark .de-lang-select { background: #172033; border-color: #3c4a63; }
.de-lang-add { position: relative; flex: 1; }
.de-lang-add-btn { width: 100%; justify-content: center; }
.de-lang-add-btn, .de-lang-del {
    display: inline-flex; align-items: center; gap: .25rem; height: 32px; padding: 0 .7rem;
    border-radius: 7px; border: 1px solid var(--de-border); background: transparent;
    color: var(--de-text-muted); font-size: .78rem; font-weight: 500; cursor: pointer;
    transition: background .15s, color .15s; white-space: nowrap;
}
.de-lang-add-btn:hover { background: var(--de-border); color: var(--de-text); }
.de-lang-add-btn svg { width: 13px; height: 13px; }
.de-lang-del { color: var(--de-danger); }
.de-lang-del:hover { background: #fef2f2; }
html.de-dark .de-lang-del:hover { background: rgba(220,38,38,.12); }
.de-lang-menu {
    position: absolute; top: calc(100% + 5px); left: 0; z-index: 20; min-width: 210px;
    background: var(--de-bg-panel); border: 1px solid var(--de-border); border-radius: 9px;
    box-shadow: 0 12px 32px rgba(0,0,0,.18); padding: .3rem; display: flex; flex-direction: column; gap: .3rem;
}
.de-lang-search {
    width: 100%; height: 30px; padding: 0 .6rem; border-radius: 6px;
    border: 1px solid var(--de-border); background: var(--de-bg); color: var(--de-text); font-size: .78rem; outline: none;
}
.de-lang-search:focus { border-color: var(--de-brand); }
.de-lang-menu-list { display: flex; flex-direction: column; gap: 1px; max-height: 260px; overflow-y: auto; }
.de-lang-menu-list button {
    text-align: left; padding: .4rem .55rem; border: 0; background: transparent; border-radius: 6px;
    font-size: .8rem; color: var(--de-text); cursor: pointer;
}
.de-lang-menu-list button:hover { background: var(--de-border); }

.de-brand-wrap { display: flex; align-items: center; gap: .45rem; }
.de-brand-icon  { width: 24px; height: 24px; flex-shrink: 0; }
.de-brand-text  {
    font-size: .72rem; font-weight: 700; color: var(--de-text-muted);
    letter-spacing: .07em; text-transform: uppercase;
}

/* Magna animated logo paths */
.mgb-line {
    stroke-dasharray: 28 56; stroke-dashoffset: 84;
    animation: mgb-chase 2s cubic-bezier(0.25,1,0.5,1) infinite;
}
@keyframes mgb-chase {
    0%   { stroke-dasharray: 15 69; stroke-dashoffset: 84; }
    40%  { stroke-dasharray: 38 46; }
    100% { stroke-dasharray: 15 69; stroke-dashoffset: 0; }
}

/* Status badge after save */
.de-status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px; border-radius: 999px;
    font-size: .7rem; font-weight: 600; letter-spacing: .01em;
}
.de-status-badge.is-draft  { background: #f0fdf4; color: #15803d; }
.de-status-badge.is-pub    { background: #eef2ff; color: #4338ca; }
html.de-dark .de-status-badge.is-draft { background: rgba(22,101,52,.25); color: #4ade80; }
html.de-dark .de-status-badge.is-pub   { background: rgba(67,56,202,.25); color: #818cf8; }
.de-status-dot {
    width: 6px; height: 6px; border-radius: 50%; background: currentColor;
    animation: de-pulse 2s ease-in-out infinite;
}
@keyframes de-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

/* Topbar buttons */
.de-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .38rem .8rem; border-radius: 7px;
    font-size: .8rem; font-weight: 500; border: 0;
    cursor: pointer; transition: background .15s, color .15s, opacity .15s;
    white-space: nowrap; color: var(--de-text-muted); background: transparent;
    text-decoration: none; line-height: 1;
}
.de-btn:hover { background: var(--de-border); color: var(--de-text); text-decoration: none; }
.de-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.de-btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; }
.de-btn-draft {
    border: 1px solid var(--de-border); color: var(--de-text-muted); background: transparent;
}
.de-btn-draft:hover { background: var(--de-border); color: var(--de-text); }
.de-btn-publish {
    background: var(--de-brand); color: #fff; font-weight: 600;
    box-shadow: 0 1px 3px rgba(99,102,241,.25);
}
.de-btn-publish:hover { background: var(--de-brand-hov); box-shadow: 0 2px 8px rgba(99,102,241,.35); }
.de-btn-delete { color: var(--de-danger); border: 1px solid transparent; }
.de-btn-delete:hover { background: #fef2f2; border-color: #fecaca; }
html.de-dark .de-btn-delete:hover { background: rgba(220,38,38,.12); border-color: rgba(220,38,38,.3); }
.de-btn:disabled, .de-btn[disabled] { opacity: .5; pointer-events: none; }

/* ─────────────────────────────────────────────────────────────────────────
   BODY + CANVAS
───────────────────────────────────────────────────────────────────────── */
.de-body { flex: 1; display: flex; overflow: hidden; }

.de-canvas {
    flex: 1; min-width: 0; overflow-y: auto;
    background: var(--de-bg-main); padding: 3rem 5rem 6rem;
}
.de-canvas::-webkit-scrollbar { width: 5px; }
.de-canvas::-webkit-scrollbar-thumb { background: var(--de-border); border-radius: 99px; }
.de-canvas-inner { max-width: 780px; margin: 0 auto; }

/* Title + editor stacked vertically */
.de-canvas-inner .fi-form,
.de-canvas-inner .fi-fo-component-ctn,
.de-canvas-inner [class*="fi-fo-grid"] {
    display: flex !important; flex-direction: column !important; gap: 0 !important;
}

/* Title — matches the reference template: text-4xl, extrabold, tracking-tight,
   a bottom border that only appears on focus, and NO divider box beneath it. */
.de-canvas-inner input.doc-editor-title-input {
    font-size: 2.25rem !important; font-weight: 800 !important;
    letter-spacing: -.025em !important; line-height: 1.15 !important;
    color: var(--de-text) !important; background: transparent !important;
    border: 0 !important; border-bottom: 1px solid transparent !important;
    border-radius: 0 !important; box-shadow: none !important;
    padding: .25rem 0 .5rem !important; width: 100%; outline: none; display: block !important;
}
.de-canvas-inner input.doc-editor-title-input::placeholder {
    color: var(--de-text-ph) !important; font-weight: 800 !important; opacity: 1 !important;
}
.de-canvas-inner input.doc-editor-title-input:focus {
    border-bottom-color: var(--de-border) !important;
}
/* Strip Filament's input box (rounded-lg bg-white ring-1 shadow-sm) so the title
   is a bare input like the reference template. The real wrapper is .fi-input-wrp;
   the field root is .fi-fo-field. */
.doc-editor-root .de-canvas-inner .fi-input-wrp:has(input.doc-editor-title-input),
.doc-editor-root .de-canvas-inner .fi-input-wrp:has(input.doc-editor-title-input) .fi-input-wrp-content-ctn {
    background: transparent !important; background-color: transparent !important;
    border: none !important; box-shadow: none !important;
    padding: 0 !important; border-radius: 0 !important; --tw-ring-shadow: 0 0 !important;
}
.doc-editor-root .de-canvas-inner .fi-fo-field:has(input.doc-editor-title-input) {
    width: 100% !important; margin-bottom: 1rem !important;
}
.de-canvas-inner .doc-editor-content,
.de-canvas-inner .doc-editor-content > * { width: 100% !important; }
.de-canvas-inner .doc-editor-content .fi-fo-markdown-editor { min-height: 520px; }
.de-canvas-inner .doc-editor-content .fi-fo-markdown-editor textarea,
.de-canvas-inner .doc-editor-content .fi-fo-markdown-editor-content { min-height: 460px; }
.de-canvas-inner .fi-fo-field-wrp:has(.doc-editor-content) { width: 100% !important; }

/* ─────────────────────────────────────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────────────────────────────────────── */
.de-sidebar {
    width: 320px; flex-shrink: 0; overflow-y: auto;
    display: flex; flex-direction: column;
    background: var(--de-bg-panel); border-left: 1px solid var(--de-border);
}
.de-sidebar::-webkit-scrollbar { width: 4px; }
.de-sidebar::-webkit-scrollbar-thumb { background: var(--de-border); border-radius: 99px; }
.de-sidebar-tabs {
    display: flex; border-bottom: 1px solid var(--de-border);
    padding: .5rem .75rem; gap: .25rem; flex-shrink: 0;
}
.de-sidebar-tab {
    flex: 1; padding: .3rem .5rem; border-radius: 5px;
    font-size: .78rem; font-weight: 500; border: 0;
    text-align: center; background: var(--de-border); color: var(--de-text);
}
.de-sidebar-body { flex: 1; }
.de-sidebar-body .fi-section {
    border: none !important; border-radius: 0 !important;
    box-shadow: none !important; background: transparent !important;
    border-bottom: 1px solid var(--de-border) !important;
}
.de-sidebar-body .fi-section-header,
.de-sidebar-body button.fi-section-header,
.de-sidebar-body .fi-section-header-ctn { padding: .75rem 1rem !important; background: transparent !important; }
.de-sidebar-body .fi-section-header-heading {
    font-size: .68rem !important; font-weight: 700 !important;
    text-transform: uppercase !important; letter-spacing: .08em !important;
    color: var(--de-text-muted) !important;
}
.de-sidebar-body .fi-section-content,
.de-sidebar-body .fi-section-content-ctn { padding: .25rem 1rem .875rem !important; }
.de-sidebar-body label.fi-label,
.de-sidebar-body .fi-fo-field-wrp-label { font-size: .78rem !important; font-weight: 500 !important; }
.de-sidebar-body .fi-fo-helper-text,
.de-sidebar-body .fi-fo-field-wrp-helper-text { font-size: .7rem !important; }
.de-sidebar-body .fi-fo-placeholder { font-size: .82rem !important; color: var(--de-text) !important; }

/* Status text inside Summary section */
.de-status-text {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8rem; font-weight: 600;
}
.de-status-text::before {
    content: ''; display: inline-block; width: 7px; height: 7px;
    border-radius: 50%; background: currentColor; flex-shrink: 0;
}
.de-status-text--dft { color: #d97706; }
.de-status-text--pub { color: #16a34a; }
.de-status-text--arc { color: #64748b; }
html.de-dark .de-status-text--dft { color: #fbbf24; }
html.de-dark .de-status-text--pub { color: #4ade80; }
html.de-dark .de-status-text--arc { color: #94a3b8; }

/* ─────────────────────────────────────────────────────────────────────────
   DARK MODE — Filament form components
   Scope to html.de-dark so it's controlled from <html>, not component.
───────────────────────────────────────────────────────────────────────── */
/* Inner controls: fill + text only, NO own border — the wrapper draws the single
   1px border, so boxes don't look doubled/thick. */
html.de-dark .doc-editor-root .fi-input:not(.doc-editor-title-input),
html.de-dark .doc-editor-root .fi-select-input,
html.de-dark .doc-editor-root textarea {
    background: transparent !important; color: #f1f5f9 !important; border-color: transparent !important;
}
/* ONE box only: the outer, rounded .fi-input-wrp wrapper. */
html.de-dark .doc-editor-root .fi-input-wrp,
html.de-dark .doc-editor-root .fi-input-wrapper {
    background: #172033 !important; border: 1px solid #334155 !important; box-shadow: none !important;
}
/* Inner sub-containers (content/prefix/suffix) must NOT draw their own square box
   — that was the second, square box overlapping the rounded one. */
html.de-dark .doc-editor-root .fi-input-wrp-content-ctn,
html.de-dark .doc-editor-root .fi-input-wrp > [class*="fi-input-wrp-"],
html.de-dark .doc-editor-root .fi-input-wrp-prefix,
html.de-dark .doc-editor-root .fi-input-wrp-suffix {
    background: transparent !important; border: 0 !important; box-shadow: none !important; border-radius: 0 !important;
}
html.de-dark .doc-editor-root .fi-input-wrp:focus-within {
    border-color: var(--de-brand) !important;
}
html.de-dark .doc-editor-root .fi-fo-field-wrp-label,
html.de-dark .doc-editor-root label.fi-label { color: #94a3b8 !important; }
html.de-dark .doc-editor-root .fi-fo-helper-text { color: #475569 !important; }
html.de-dark .doc-editor-root .fi-section-header-heading { color: #94a3b8 !important; }
html.de-dark .doc-editor-root .fi-fo-markdown-editor { border-color: #1e293b !important; }
html.de-dark .doc-editor-root .fi-fo-markdown-editor textarea { background: #0f172a !important; color: #e2e8f0 !important; }
html.de-dark .doc-editor-root .fi-fo-markdown-editor-toolbar { background: #0a0f1e !important; border-color: #1e293b !important; }
html.de-dark .doc-editor-root .fi-fo-placeholder { color: #e2e8f0 !important; }
html.de-dark .doc-editor-root .de-sidebar-tab { background: #1e293b; color: #f1f5f9; }

/* Selects (Collection, Parent page): the searchable control is rendered by JS
   inside .fi-select-input, and Filament's own dark mode isn't active in this
   editor takeover — so the control stayed white. Give the wrapper the dark box,
   make every inner element transparent + light, then repaint the dropdown solid. */
/* The JS-rendered select control: transparent + light, no own border (wrapper
   handles it). */
html.de-dark .doc-editor-root .fi-select-input,
html.de-dark .doc-editor-root .fi-select-input * {
    background-color: transparent !important; color: #f1f5f9 !important; border-color: transparent !important;
}
html.de-dark .doc-editor-root .fi-select-input input::placeholder,
html.de-dark .doc-editor-root .fi-select-input [class*="placeholder"] { color: #94a3b8 !important; }
/* Options dropdown: exactly ONE box — the outer panel. Every element inside is
   forced transparent (no bg/border/shadow/radius) so no square box overlaps it. */
html.de-dark .doc-editor-root .fi-select-input [role="listbox"],
html.de-dark .doc-editor-root .fi-select-input [class*="dropdown"] {
    background-color: #172033 !important; border: 1px solid #334155 !important;
    border-radius: 10px !important; box-shadow: 0 10px 30px rgba(0,0,0,.5) !important; overflow: hidden !important;
}
html.de-dark .doc-editor-root .fi-select-input [role="listbox"] *,
html.de-dark .doc-editor-root .fi-select-input [class*="dropdown"] * {
    background-color: transparent !important; border-color: transparent !important;
    box-shadow: none !important; border-radius: 0 !important;
}
html.de-dark .doc-editor-root .fi-select-input [role="option"]:hover,
html.de-dark .doc-editor-root .fi-select-input [role="option"][aria-selected="true"] {
    background-color: #24304a !important;
}
/* Native <select> fallback (skip the hidden one behind non-native selects). */
html.de-dark .doc-editor-root select:not(.fi-hidden):not([class*="hidden"]) {
    background-color: #172033 !important; color: #f1f5f9 !important; border: 1px solid #334155 !important;
}
html.de-dark .doc-editor-root [class*="date-time-picker"] input {
    background-color: transparent !important; color: #f1f5f9 !important; border-color: transparent !important;
}

/* ─────────────────────────────────────────────────────────────────────────
   FEATURED IMAGE — "browse media library" rendered as a text link (not a
   button), centred under the drag-and-drop dropzone.
───────────────────────────────────────────────────────────────────────── */
/* Browse link is a normal in-flow button (in a Placeholder) so it sits directly
   below the drag-and-drop dropzone. */
.doc-editor-root .de-browse-link,
.doc-editor-root button.de-browse-link {
    display: flex; align-items: center; justify-content: center; gap: .35rem;
    width: 100%; margin: .55rem 0 0; padding: .45rem .6rem;
    background: transparent; border: 1px dashed var(--de-border); border-radius: 8px; box-shadow: none;
    color: var(--de-brand); font-size: .78rem; font-weight: 500; cursor: pointer; text-decoration: none;
    transition: border-color .15s, background .15s;
}
.doc-editor-root .de-browse-link:hover { border-color: var(--de-brand); background: rgba(99,102,241,.05); color: var(--de-brand-hov); }
.doc-editor-root .de-browse-link svg { width: 15px; height: 15px; }
html.de-dark .doc-editor-root .de-browse-link { color: var(--de-brand-hov); }

/* Preview shown in place of the dropzone after picking from the media library. */
.de-featured-preview {
    position: relative; border-radius: 10px; overflow: hidden;
    border: 1px solid var(--de-border); aspect-ratio: 16 / 9; background: var(--de-border);
}
.de-featured-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.de-featured-preview-remove {
    position: absolute; top: .45rem; right: .45rem;
    width: 26px; height: 26px; border-radius: 7px; border: 0;
    background: rgba(0,0,0,.6); color: #fff; cursor: pointer;
    font-size: 1.1rem; line-height: 1; display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.de-featured-preview-remove:hover { background: rgba(0,0,0,.82); }
.de-featured-preview-tag {
    position: absolute; bottom: 0; left: 0; right: 0; padding: .3rem .55rem;
    background: linear-gradient(to top, rgba(0,0,0,.6), transparent);
    color: #fff; font-size: .65rem; font-weight: 500;
}

/* Save Draft / Publish (Update) buttons: normal width, a little taller. */
.de-topbar .de-btn-draft,
.de-topbar .de-btn-publish { padding-top: .6rem; padding-bottom: .6rem; }

/* ─────────────────────────────────────────────────────────────────────────
   PUBLISHED TOAST
───────────────────────────────────────────────────────────────────────── */
.de-published-toast {
    position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
    z-index: 700;
    display: flex; align-items: center; gap: .625rem;
    padding: .75rem 1.25rem; border-radius: 10px;
    background: #16a34a; color: #fff;
    font-size: .875rem; font-weight: 600;
    box-shadow: 0 8px 32px rgba(22,163,74,.35);
    animation: de-toast-in .25s ease forwards;
}
.de-published-toast svg { width: 18px; height: 18px; flex-shrink: 0; }
[x-cloak] { display: none !important; }
@keyframes de-toast-in {
    from { opacity: 0; transform: translateX(-50%) translateY(12px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* ─────────────────────────────────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────────────────────────────────── */
@media (max-width: 900px) { .de-sidebar { display: none; } .de-canvas { padding: 2rem 1.5rem 4rem; } }
@media (max-width: 640px) { .de-canvas-inner input.doc-editor-title-input { font-size: 1.75rem !important; } .de-canvas { padding: 1.5rem 1rem 3rem; } }
</style>
@endpush

<script>
/**
 * Editor theme management.
 *
 * The dark class lives on <html> (as `html.de-dark`), NOT on .doc-editor-root.
 * Livewire morphs the component's DOM but never touches <html>, so the class
 * survives every save/publish/delete round-trip without needing re-application.
 */
(function () {
    var KEY = 'doc-editor-theme';

    function getStored() {
        return localStorage.getItem(KEY)
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    }

    function applyTheme(theme) {
        var html = document.documentElement;
        if (theme === 'dark') {
            html.classList.add('de-dark', 'dark');
        } else {
            html.classList.remove('de-dark', 'dark');
        }
        // Update toggle icon
        var moon = document.getElementById('de-icon-moon');
        var sun  = document.getElementById('de-icon-sun');
        if (moon) moon.style.display = theme === 'dark' ? 'none' : '';
        if (sun)  sun.style.display  = theme === 'dark' ? ''     : 'none';
    }

    function toggleTheme() {
        var next = document.documentElement.classList.contains('de-dark') ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        applyTheme(next);
    }

    function init() {
        applyTheme(getStored());
        // Attach toggle once — guard against duplicate event listeners
        var btn = document.getElementById('de-theme-toggle');
        if (btn && !btn._deInit) {
            btn._deInit = true;
            btn.addEventListener('click', toggleTheme);
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    // Re-sync icon state after Livewire re-renders (the <html> class is already correct,
    // but the toggle button's icon references may have been replaced in the new DOM).
    document.addEventListener('livewire:updated', function () {
        applyTheme(getStored());
    });

    document.addEventListener('livewire:navigated', init);
})();
</script>

<div class="doc-editor-root" id="docEditorRoot">

    {{-- ── TOPBAR ───────────────────────────────────────────────────────── --}}
    <header class="de-topbar">

        <div class="de-topbar-left">
            <a href="{{ $this->getResource()::getUrl('index') }}" class="de-back-btn" title="All pages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="de-sep"></div>

            {{-- Animated Magna logo --}}
            <div class="de-brand-wrap">
                <svg class="de-brand-icon" viewBox="0 0 34 34" fill="none"
                     xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                    <defs>
                        <linearGradient id="de-grad" x1="0" y1="0" x2="34" y2="34">
                            <stop stop-color="#6366f1"/>
                            <stop offset="1" stop-color="#8b5cf6"/>
                        </linearGradient>
                        <linearGradient id="de-neon" x1="0" y1="0" x2="34" y2="34">
                            <stop offset="0%" stop-color="#00f2fe"/>
                            <stop offset="50%" stop-color="#4facfe"/>
                            <stop offset="100%" stop-color="#f355da"/>
                        </linearGradient>
                    </defs>
                    <path d="M17 1 31 9v16l-14 8L3 25V9l14-8Z"
                          stroke="url(#de-grad)" stroke-width="2.4" fill="rgba(99,102,241,.06)"/>
                    <path class="mgb-line" d="M17 1 31 9v16l-14 8L3 25V9l14-8Z"
                          stroke="url(#de-neon)" stroke-width="2.6" stroke-linecap="round"/>
                    <path d="M10 23V11.5l7 6 7-6V23"
                          stroke="url(#de-grad)" stroke-width="2.4"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
                <span class="de-brand-text">Magna</span>
            </div>

            @if ($statusLabel)
                <div class="de-sep"></div>
                <span class="de-status-badge {{ $this->editorStatus === 'published' ? 'is-pub' : 'is-draft' }}"
                      role="status" aria-live="polite">
                    <span class="de-status-dot" aria-hidden="true"></span>
                    {{ $statusLabel }}
                </span>
            @endif

        </div>

        <div class="de-topbar-right">

            {{-- Preview — shown for any saved edit page --}}
            @if ($previewUrl)
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="de-btn" title="Preview page">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Preview
                </a>
                <div class="de-sep"></div>
            @endif

            {{-- Dark / light toggle --}}
            <button id="de-theme-toggle" class="de-btn de-btn-icon" type="button"
                    title="Toggle dark / light mode" aria-label="Toggle dark mode">
                <svg id="de-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
                <svg id="de-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     style="display:none" aria-hidden="true">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </button>

            <div class="de-sep"></div>

            {{-- Delete (edit pages only) --}}
            @if ($isEdit)
                <button class="de-btn de-btn-delete" type="button"
                        wire:click="deletePage"
                        wire:confirm="Delete this page permanently? This cannot be undone."
                        wire:loading.attr="disabled" wire:target="deletePage" title="Delete page">
                    <svg wire:loading.remove wire:target="deletePage" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
                    </svg>
                    <span wire:loading.remove wire:target="deletePage">Delete</span>
                    <span wire:loading wire:target="deletePage">Deleting…</span>
                </button>
                <div class="de-sep"></div>
            @endif

            {{-- Save draft --}}
            <button type="button" class="de-btn de-btn-draft"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,publish,deletePage">
                <span wire:loading.remove wire:target="saveDraft">Save Draft</span>
                <span wire:loading wire:target="saveDraft">Saving…</span>
            </button>

            {{-- Publish / Update --}}
            <button type="button" class="de-btn de-btn-publish"
                    wire:click="publish"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,publish,deletePage">
                <span wire:loading.remove wire:target="publish">{{ $publishBtnLabel }}</span>
                <span wire:loading wire:target="publish">Publishing…</span>
            </button>

        </div>
    </header>

    {{-- ── BODY ─────────────────────────────────────────────────────────── --}}
    <div class="de-body">

        <main class="de-canvas">
            <div class="de-canvas-inner">
                {{ $this->form }}
            </div>
        </main>

        <aside class="de-sidebar" aria-label="Page settings">
            <div class="de-sidebar-tabs" role="tablist">
                <span class="de-sidebar-tab" role="tab" aria-selected="true">Page</span>
            </div>
            <div class="de-sidebar-body">

                {{-- Language / translations --}}
                <div class="de-lang-panel">
                    <div class="de-lang-panel-title">Language</div>
                    @if ($isEdit)
                        <select class="de-lang-select" wire:model.live="editingLocale"
                                wire:loading.attr="disabled" wire:target="editingLocale,addLanguage,deleteLanguage"
                                title="Language you are editing">
                            @foreach ($this->availableLocales as $loc)
                                <option value="{{ $loc }}">{{ \Magna\Docs\Support\DocLocales::flag($loc) }} {{ \Magna\Docs\Support\DocLocales::adminLabel($loc) }}{{ $loc === 'en' ? ' · default' : '' }}</option>
                            @endforeach
                        </select>

                        <div class="de-lang-panel-actions">
                            @php $addable = array_values(array_diff(array_keys(\Magna\Docs\Support\DocLocales::LABELS), $this->availableLocales)); @endphp
                            @if (count($addable))
                                <div class="de-lang-add" x-data="{ open: false, q: '' }" @click.outside="open = false; q = ''">
                                    <button type="button" class="de-lang-add-btn" @click="open = !open; q = ''; $nextTick(() => open && $refs.langSearch && $refs.langSearch.focus())" title="Add a language translation">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        Add language
                                    </button>
                                    <div class="de-lang-menu" x-show="open" x-cloak>
                                        <input type="text" class="de-lang-search" x-model="q" x-ref="langSearch" @click.stop placeholder="Search languages…">
                                        <div class="de-lang-menu-list">
                                            @foreach ($addable as $loc)
                                                <button type="button" wire:click="addLanguage('{{ $loc }}')" @click="open = false; q = ''"
                                                        x-show="q === '' || @js(mb_strtolower(\Magna\Docs\Support\DocLocales::adminLabel($loc).' '.$loc)).includes(q.toLowerCase().trim())">
                                                    {{ \Magna\Docs\Support\DocLocales::flag($loc) }} {{ \Magna\Docs\Support\DocLocales::adminLabel($loc) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($this->editingLocale !== 'en')
                                <button type="button" class="de-lang-del" wire:click="deleteLanguage"
                                        wire:confirm="Delete this translation? The {{ \Magna\Docs\Support\DocLocales::englishName($this->editingLocale) }} version will be removed.">
                                    Remove
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="de-lang-static">🇬🇧 English <span>· default</span></div>
                        <p class="de-lang-hint">Save the page, then add translations from here.</p>
                    @endif
                </div>

                {{ $this->sidebarForm }}
            </div>
        </aside>

    </div>

    {{-- ── PUBLISHED TOAST ─────────────────────────────────────────────────
         Event-driven so it auto-dismisses. Tying it to $editorStatus kept it on
         screen forever (the status never resets) and re-flashed on every render;
         instead publish() dispatches 'doc-published' and this shows for ~3s. --}}
    <div class="de-published-toast" role="status" aria-live="polite"
         x-data="{ show: false, msg: 'Page published', t: null }"
         x-show="show" x-cloak style="display:none"
         x-on:doc-published.window="msg = ($event.detail && $event.detail.message) || 'Page published'; show = true; clearTimeout(t); t = setTimeout(() => show = false, 3200)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        <span x-text="msg">Page published</span>
    </div>

    {{-- Global media picker — opened by the "Browse media library" button via magna:open-media-picker --}}
    <livewire:magna-media-picker />

</div>{{-- /.doc-editor-root --}}

</x-filament-panels::page>
