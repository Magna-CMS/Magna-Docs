<p align="center">
  <img src="screenshots/magna-logo.svg" alt="Magna Docs" width="90">
</p>

<h1 align="center">Magna Docs</h1>

<p align="center">
  <strong>Magna Docs is a documentation and knowledge-base plugin for <a href="https://github.com/Magna-CMS/Magna">Magna CMS</a> — live publishing, no build step, no Node.js.</strong><br>
  Write, organise, and publish developer documentation directly inside your Magna CMS admin panel.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-v1.1.1-6366f1?style=for-the-badge" alt="Version v1.1.1">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Filament-5-f59e0b?style=for-the-badge" alt="Filament 5">
  <img src="https://img.shields.io/badge/license-MIT-22c55e?style=for-the-badge" alt="License: MIT">
</p>

---

<table>
  <tr>
    <td><img src="screenshots/01-frontend-light.png" alt="Magna Docs — frontend light mode" /></td>
    <td><img src="screenshots/Screenshot%202026-07-12%20162556.png" alt="Magna Docs — frontend dark mode" /></td>
    <td><img src="screenshots/07-admin-edit-page.png" alt="Magna Docs — full-screen Markdown editor" /></td>
  </tr>
  <tr>
    <td align="center"><sub>Public docs — light mode</sub></td>
    <td align="center"><sub>Public docs — dark mode</sub></td>
    <td align="center"><sub>Full-screen Markdown editor</sub></td>
  </tr>
  <tr>
    <td><img src="screenshots/05-admin-pages-list.png" alt="Magna Docs — admin pages list" /></td>
    <td><img src="screenshots/06-admin-collections.png" alt="Magna Docs — collections admin" /></td>
    <td><img src="screenshots/10-frontend-mobile.png" alt="Magna Docs — mobile view" /></td>
  </tr>
  <tr>
    <td align="center"><sub>Admin — Doc Pages list</sub></td>
    <td align="center"><sub>Admin — Collections</sub></td>
    <td align="center"><sub>Mobile responsive view</sub></td>
  </tr>
</table>

---

## Why Magna Docs?

Most documentation platforms force you to maintain a completely separate website alongside your application.

Whether you use **GitBook**, **VitePress**, **Docusaurus**, **MkDocs**, or **Docsify**, you end up managing:

- a separate repository
- Node.js dependencies and build pipelines
- separate deployment workflows
- a synchronization problem between your app and its docs

Magna Docs takes a different approach. It lives **inside your Laravel application** as a native plugin. Every page is stored in your database, rendered with Blade, cached automatically, and live on the next page refresh the instant you hit Publish.

There is nothing extra to build, deploy, or maintain.

---

## Built For

Magna Docs is the right tool for any team that wants documentation without a separate platform:

- 📚 Software & open-source project documentation
- 🔌 API references and developer portals
- 📖 Product manuals and user guides
- 🏢 Internal company wikis and SOPs
- 🎓 Training and onboarding documentation
- ❓ Customer help centres and knowledge bases
- 📒 SaaS platform documentation
- 📗 Installation guides and technical references

---

## ⭐ Key Features

- Full-screen distraction-free Markdown editor with toolbar
- Live publishing — edit, click Publish, refresh. Done.
- Collections and unlimited nested page hierarchy
- Automatic sidebar generation from your page tree
- Automatic Table of Contents from `h2` / `h3` headings
- Reading time estimation and "last updated" display
- Previous / Next page navigation
- Breadcrumb navigation
- Featured images from upload or media library
- Dark mode / light mode toggle (remembered in the browser)
- Built-in SEO — meta description, canonical URL, Open Graph, Twitter Card, JSON-LD, XML sitemap
- Built-in search across page titles, excerpts and content
- REST API for headless or mobile consumption
- Multi-language / localization — per-page translations with locale switching
- Role-based editor permissions (via Magna CMS roles)
- Shared Magna media library
- Custom logo, favicon, site name and footer text
- Import / export of a whole docs site as a portable `.zip` archive
- Server-side rendering — no React, no Vue in the browser
- Rendered Markdown cached per page, refreshed automatically when a page is saved
- Fully self-hosted, MIT licensed

---

## Feature Comparison

| Feature | **Magna Docs** | GitBook | Docusaurus | VitePress | MkDocs | BookStack |
|---------|:-----------:|:-------:|:----------:|:---------:|:------:|:---------:|
| Self-hosted | ✅ | ⚠️ paid | ✅ | ✅ | ✅ | ✅ |
| Laravel / PHP native | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Filament admin integration | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Live publishing (no rebuild) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Requires Node.js | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Database-driven | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Full-screen writing mode | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Markdown support | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| JSON-LD structured data | ✅ | ❌ | ⚠️ | ⚠️ | ❌ | ❌ |
| Automatic XML sitemap | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ❌ |
| REST API | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Role-based permissions | ✅ | ✅ paid | ❌ | ❌ | ❌ | ✅ |
| Shared media library | ✅ | ❌ | ❌ | ❌ | ❌ | ⚠️ |
| Automatic breadcrumbs | ✅ | ❌ | ⚠️ | ⚠️ | ⚠️ | ✅ |
| Reading time | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Prev / Next navigation | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ |
| Multi-language translations | ✅ | ✅ paid | ⚠️ | ⚠️ | ⚠️ | ❌ |
| Mobile responsive | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Open source | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ |

---

## Features

### Full-Screen Markdown Editor

<p align="center">
  <img src="screenshots/07-admin-edit-page.png" alt="Magna Docs full-screen Markdown editor" width="80%" />
</p>

The editor takes over the entire screen — Filament chrome disappears, leaving nothing but your content.

- Rich toolbar: bold, italic, strikethrough, links, headings, lists, blockquotes, code blocks, tables, file attachments
- **Dark / light mode toggle** for the editor, remembered in your browser
- **Save Draft** saves silently without redirecting; **Publish / Update** goes live immediately
- Slug auto-generated from the title while you type — override any time

### Draft / Publish Workflow

- Every page starts as a **draft** — invisible to visitors until published
- Status badge in the sidebar: Draft / Published / Archived
- Publish date recorded automatically the moment a page goes live
- Topbar button switches to **Update** once a page is already published so you always know what you're doing

### Collections & Nested Pages

<p align="center">
  <img src="screenshots/06-admin-collections.png" alt="Magna Docs collections management" width="80%" />
</p>

- **Collections** group related pages (Getting Started, API Reference, etc.) — each has a title, slug, description, icon, colour, and sort order
- **Nested pages** — any page can be a child of another via `parent_id`, giving unlimited depth
- The public sidebar is built automatically from this tree; no manual menu editing ever

### Auto Table of Contents

- Every `h2` and `h3` in the rendered page is extracted and displayed as a sticky anchor list in the right column
- All headings get `#` permalink anchors via CommonMark's heading-permalink extension

### SEO — Zero Config

<p align="center">
  <img src="screenshots/09-admin-settings.png" alt="Magna Docs settings page" width="80%" />
</p>

Every published page ships with:

- `<title>` and `<meta name="description">` (per-page SEO title/description overrides, falling back to the page title and excerpt)
- Canonical URL
- Open Graph tags (`og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`)
- Twitter Card metadata (`summary`)
- JSON-LD structured data (`TechArticle`)
- Auto-generated `sitemap.xml` at `/docs/sitemap.xml` with `<lastmod>` per page

No extra plugins or config files needed.

### Server-Rendered, No Build Step

The public frontend is Blade + pre-built CSS, with a small amount of vanilla JavaScript (theme toggle, search, copy buttons) and Prism for code highlighting. No JavaScript framework is shipped to the browser.

- **No `npm run build`** after content changes — the plugin's CSS/JS ships pre-built
- **Instant live pages** — published content is visible on the next refresh
- **Automatic caching** of each page's rendered Markdown, keyed on page ID, locale and `updated_at`, so saving a page serves the new version immediately
- No client-side hydration step

### Frontend — Light & Dark Mode

<p align="center">
  <img src="screenshots/01-frontend-light.png" alt="Magna Docs public documentation light mode" width="49%" />
  <img src="screenshots/Screenshot%202026-07-12%20162556.png" alt="Magna Docs public documentation dark mode" width="49%" />
</p>

### Mobile

<p align="center">
  <img src="screenshots/10-frontend-mobile.png" alt="Magna Docs mobile responsive view" width="35%" />
</p>

Fully responsive — the sidebar collapses on mobile, and the layout adapts to any screen width.

### Additional Features

- **Featured image** — upload directly or pick from the Magna media library; toggle display per page
- **Breadcrumb navigation** — built automatically from the `parent_id` hierarchy
- **Prev / Next navigation** — cards at the bottom of each page, ordered by sidebar position
- **Reading time** — calculated from word count (~200 wpm), shown in the page footer
- **Last updated** — rendered from `updated_at`, always accurate
- **Page feedback widget** — "Was this page helpful? 👍 👎" counts are stored in your application's cache; no third-party service
- **Search** — the header search box queries page titles, excerpts and content (`GET /docs/search?q=…`)
- **Import / Export** — **Magna Docs → Import / Export** (or the `magna:docs:export` / `magna:docs:import` commands) moves collections, pages, translations and media between installs as a `.zip`

### REST API

Every published page is available as JSON for headless or mobile consumption. The endpoints use Magna's API authentication, so send a Magna API key (a read-only **Delivery** key is enough) as a bearer token:

```bash
curl -H "Authorization: Bearer <your-magna-api-key>" https://your-site.com/api/v1/docs/tree
```

| Endpoint | Description |
|---|---|
| `GET /api/v1/docs/tree` | Nested sidebar tree (collections → pages) |
| `GET /api/v1/docs/pages` | Flat list of all published pages |
| `GET /api/v1/docs/pages/{slug}` | Full Markdown content + breadcrumb for one page |

Example response for `/api/v1/docs/tree`:

```json
{
  "data": [
    {
      "title": "Getting Started",
      "icon": "book-open",
      "color": "#6366f1",
      "children": [
        { "title": "Installation", "slug": "installation", "children": [] },
        { "title": "Configuration", "slug": "configuration", "children": [] }
      ]
    }
  ]
}
```

Useful for Next.js frontends, mobile apps, search indexing, and AI assistants.

---

## Magna Docs vs GitBook

GitBook is one of the most popular documentation platforms today. It offers an excellent writing experience — but it is primarily a **cloud-hosted SaaS product**.

| | Magna Docs | GitBook |
|---|---|---|
| Hosting | Self-hosted inside your app | Cloud SaaS (paid for teams) |
| Data ownership | Your database, your server | GitBook's servers |
| Authentication | Shared with your Laravel app | Separate GitBook account |
| Monthly cost | Free (open source) | Paid after free tier |
| Laravel integration | Native | None |
| Admin panel | Filament 5 inside your app | Separate GitBook UI |
| REST API | Built-in | Paid plans |

If your application already runs on Laravel, Magna Docs eliminates the need to manage documentation on a separate platform with a separate login, separate billing, and separate deployment.

---

## Magna Docs vs Docusaurus

Docusaurus is an excellent static site generator from Meta, widely used for open-source project documentation.

The fundamental difference: every documentation change in Docusaurus requires a **rebuild and redeploy**.

**Docusaurus workflow:**
1. Edit Markdown file
2. Commit to Git
3. Push to trigger CI
4. Build (`npm run build`) — takes minutes
5. Deploy to hosting
6. Wait for CDN propagation

**Magna Docs workflow:**
1. Edit page in admin
2. Click **Publish**
3. Refresh browser

No Node.js. No build. No deployment. No waiting.

| | Magna Docs | Docusaurus |
|---|---|---|
| Requires Node.js | ❌ | ✅ |
| Build step on every edit | ❌ | ✅ |
| Deploy after edits | ❌ | ✅ |
| Live publishing | ✅ | ❌ |
| Edit inside admin panel | ✅ | ❌ |
| Database-backed content | ✅ | ❌ |
| Shared media library | ✅ | ❌ |

---

## Magna Docs vs VitePress

VitePress is lightweight and fast, but it still depends on Node.js and static site generation. Every content update requires running the build and redeploying.

| Task | Magna Docs | VitePress |
|---|---|---|
| Requires Node.js | ❌ | ✅ |
| `npm install` on setup | ❌ | ✅ |
| Build after editing | ❌ | ✅ |
| Deploy after editing | ❌ | ✅ |
| Edit inside admin panel | ✅ | ❌ |
| Database storage | ✅ | ❌ |
| Role-based editor access | ✅ | ❌ |
| Shared app authentication | ✅ | ❌ |

For Laravel teams already using Filament, Magna Docs provides a far more integrated experience — no separate toolchain, no separate user accounts, no separate deployment.

---

## Magna Docs vs MkDocs

MkDocs is popular for Python projects and technical documentation. Like Docusaurus and VitePress, it generates **static HTML at build time**.

Magna Docs focuses on **dynamic documentation management** — editors update content without touching Git repositories or running build commands. The content team and the engineering team share one admin panel, one media library, and one permission system.

---

## Magna Docs vs BookStack

BookStack is a solid self-hosted knowledge management platform. It serves a slightly different audience:

- **BookStack** is optimised for company-wide knowledge sharing, internal wikis, and team information
- **Magna Docs** is optimised for **public-facing developer documentation** — product docs, API references, installation guides, SaaS documentation

Magna Docs also integrates natively with Magna CMS and Filament, giving you a single admin panel for your entire application.

---

## Architecture

```
Visitor Request
       │
       ▼
  Laravel Router
       │
       ▼
  DocsPageController
       │
       ▼
  DocPage model (Eloquent)
       │
       ▼
  CommonMark parser ──→ HTML cached per page + locale + updated_at
       │                (cache hit skips the Markdown conversion)
       ▼
  Blade templates (layout, sidebar, TOC, prev/next)
       │
       ▼
  Visitor receives fully rendered HTML
```

Unlike JavaScript documentation frameworks, there is no client-side rendering, no hydration, and no JavaScript framework shipped to the visitor.

---

## Performance

- **Server-side rendering** — pages are Blade-rendered HTML; no React or Vue runtime in the browser
- **Automatic per-page caching** — each page's Markdown-to-HTML output is cached after the first render
- **No manual cache clearing** — saving a page changes its cache key, so the new version is served on the next request and other pages' cache entries are untouched
- **Lightweight frontend** — Blade, pre-built CSS, Prism for code highlighting, and a small amount of vanilla JS
- **No client-side rendering** — no hydration step and no framework runtime to download

---

## Security

Magna Docs inherits Laravel's built-in security model:

- CSRF protection on all forms
- Authentication and authorization policies
- Role-based permissions (shared with the rest of the CMS)
- Request validation
- Escaped Blade templates
- Raw HTML in Markdown is stripped and unsafe links (e.g. `javascript:`) are rejected before rendering
- Admin screens gated by the plugin's `docs.pages.view` / `docs.pages.manage` permissions
- Throttled feedback endpoint that only accepts votes for published pages

No third-party authentication service required.

---

## Requirements

| Requirement | Version |
|---|---|
| [Magna CMS](https://github.com/Magna-CMS/Magna) | 1.x (the plugin manifest declares `^1.0`) |
| PHP | 8.3 or higher |
| Laravel | 13.x (ships with Magna CMS) |
| Filament | 5.x (ships with Magna CMS) |
| Composer | Required for the Composer install path |

---

## Installation

You need a running [Magna CMS](https://github.com/Magna-CMS/Magna) instance. Magna Docs is a **plugin** for Magna CMS — it is not a standalone application.

On a site with marketplace installs available, you can install **Magna Docs** from the **Plugins** screen in the admin panel. Otherwise, use Composer:

### 1. Require the package

From the root of your Magna CMS installation:

```bash
composer require magna-cms/docs
```

### 2. Enable the plugin

Go to **Admin → Plugins**, find **Magna Docs**, and click **Enable** — or from the terminal:

```bash
php artisan magna:plugin:enable magna-cms/docs
```

Enabling the plugin automatically runs its database migrations and registers its routes, admin resources, and permissions. No `php artisan migrate` needed.

### Working on the plugin locally (optional)

To develop against a local checkout instead, clone it into `plugins-dev/{vendor}/{package}` and wire it in as a Composer path repository:

```bash
# From the root of your Magna CMS installation
git clone https://github.com/Magna-CMS/magna-docs.git plugins-dev/magna-cms/docs
composer config repositories.magna-docs path plugins-dev/magna-cms/docs
composer require magna-cms/docs:@dev
php artisan magna:plugin:enable magna-cms/docs
```

Magna only discovers plugins under `plugins-dev/` outside the `production` environment; production installs should use the Composer package above.

---

## Usage

### Creating your first page

1. In the admin sidebar, go to **Magna Docs → Pages → Create page**
2. Type your title — the slug is auto-generated
3. Write content in Markdown using the full-screen editor
4. Optionally assign to a **Collection** and set a **parent page** for nesting
5. Click **Publish page** — it's live at `/docs/{slug}` immediately

### Organising with Collections

1. Go to **Magna Docs → Collections** and create a collection (e.g. "Getting Started")
2. When editing a page, pick the collection in the **Organisation** sidebar panel
3. Pages are grouped by collection in the public sidebar, ordered by the `order` field

### Setting up branding

Go to **Magna Docs → Settings** to set site name, logo, and favicon. These affect only the docs section and do not change anything else in your CMS.

### Restricting editor access

Access to the admin screens is controlled by the plugin's two permissions, which you grant to roles in Magna CMS's role management:

- `docs.pages.view` — see the Magna Docs pages and collections
- `docs.pages.manage` — create, edit and delete pages and collections, and use Import / Export

### Import, export and sync

```bash
# Export everything to storage/app/magna-docs-exports (see --help for filters)
php artisan magna:docs:export

# Import an archive produced by magna:docs:export
php artisan magna:docs:import path/to/archive.zip --dry-run
php artisan magna:docs:import path/to/archive.zip --conflict=update   # or skip / duplicate

# Sync a folder of Markdown files (one sub-folder per collection) into the docs site
php artisan magna:docs:sync --source=path/to/markdown --dry-run
```

The same import/export is available in the admin under **Magna Docs → Import / Export**.

---

## Routes

| Route | Description |
|---|---|
| `GET /docs` | Landing — renders the first published page in sidebar order |
| `GET /docs/{slug}` | Doc page with sidebar, TOC, breadcrumb, SEO meta |
| `GET /docs/sitemap.xml` | XML sitemap for all published pages |
| `GET /docs/search?q=…` | JSON — search results (up to 8) |
| `POST /docs/feedback` | Records a "Was this page helpful?" vote (throttled) |
| `GET /api/v1/docs/tree` | JSON — nested sidebar tree (API key required) |
| `GET /api/v1/docs/pages` | JSON — flat list of published pages (API key required) |
| `GET /api/v1/docs/pages/{slug}` | JSON — single page content + breadcrumb (API key required) |

Append `?lang={locale}` to a page URL to render one of its translations.

---

## Database Structure

### `doc_collections`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `title` | string | Collection title |
| `slug` | string | URL slug |
| `description` | text | Short description |
| `icon` | string | Icon identifier |
| `color` | string | Sidebar accent colour |
| `order` | integer | Display order |
| `is_public` | boolean | Show the collection in the public sidebar / tree |

### `docs_pages`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `collection_id` | bigint | Parent collection |
| `parent_id` | bigint | Parent page (nullable) |
| `title` | string | Page title |
| `slug` | string | URL slug |
| `excerpt` | string | Short summary |
| `content` | longtext | Markdown content |
| `featured_image` | string | Hero image path |
| `show_featured_image` | boolean | Show image on frontend |
| `meta_title` | string | SEO title override |
| `meta_description` | string(500) | SEO description override |
| `status` | string | `draft` / `published` / `archived` |
| `order` | integer | Sidebar order |
| `is_published` | boolean | Published flag |
| `published_at` | timestamp | Publish date |

### `docs_page_translations`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `doc_page_id` | bigint | Translated page |
| `locale` | string(10) | Locale code (unique per page) |
| `title` | string | Translated title |
| `content` | longtext | Translated Markdown content |

---

## Frequently Asked Questions

**Does Magna Docs require Node.js?**
No. Everything runs inside Laravel. There is no npm, no Vite, and no build step.

**Does it generate static HTML?**
No. Pages are rendered dynamically by Blade on every request (or served from cache). Publishing is instant — no rebuild, no deployment.

**Does it support Markdown?**
Yes. Markdown is parsed by [league/commonmark](https://commonmark.thephpleague.com/) — a full CommonMark implementation with extensions.

**Can I use it for API documentation?**
Absolutely. Magna Docs is ideal for REST API references, SDK docs, and developer portals.

**Can I use it as a knowledge base or internal wiki?**
Yes. Collections and nested pages make it easy to structure any type of documentation project. Note that every published page is publicly readable at `/docs/{slug}` — there is no login-protected docs mode, and hiding a collection only removes it from the sidebar — so don't publish confidential content.

**Is it SEO friendly?**
Yes. Every page automatically gets meta tags, Open Graph, Twitter Cards, JSON-LD structured data, canonical URLs, and a sitemap.

**Does it support multiple languages?**
Yes. Magna Docs ships with a built-in translation system — each page can have a translated `title` and `content` per locale, stored in a dedicated `docs_page_translations` table. Visitors switch language with the locale switcher (`?lang={locale}`).

**Can I serve docs on my own domain (e.g. docs.myapp.com)?**
Docs are served under `/docs` on your Magna CMS site. The settings page has a **Custom domain** field, but the plugin's routes do not use it yet, so serving docs from a separate domain is not supported out of the box.

**Can I restrict who edits documentation?**
Yes. Grant the `docs.pages.manage` permission only to the Magna CMS roles that should edit documentation.

**Do I need to run `php artisan migrate` manually?**
No. Migrations run automatically when you enable the plugin from the admin panel.

**Is Magna Docs open source?**
Yes. Released under the MIT License.

---

## Roadmap

Planned for future releases:

- [ ] Full-text search (a basic title/excerpt/content search is built in today)
- [ ] Versioned documentation (e.g. v1, v2)
- [ ] Import directly from a GitHub repository (a local Markdown folder can already be synced with `magna:docs:sync`)
- [ ] Export to PDF
- [ ] Export to static HTML (ZIP export/import of the docs site is already available)
- [ ] Page revision history
- [ ] Scheduled publishing
- [ ] Comments on pages
- [ ] Page analytics
- [ ] Custom themes
- [ ] Documentation templates
- [ ] AI writing assistant integration
- [ ] One-click install via Magna CMS plugin marketplace

---

## Contributing

Contributions are welcome. You can help by:

- Reporting bugs with clear reproduction steps
- Suggesting features via GitHub Issues
- Submitting pull requests (please follow the existing code style)
- Improving documentation

Please include your PHP version, Laravel version, Magna CMS version, and plugin version in any bug report.

---

## License

Magna Docs is open-source software released under the **MIT License** — see [LICENSE](LICENSE) for full details.

---

<p align="center">
  <strong>Made with ❤️ for the Laravel & Magna CMS community.</strong><br>
  If Magna Docs saves you from maintaining a separate documentation platform, consider giving the repo a ⭐
</p>
