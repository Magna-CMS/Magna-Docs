# Changelog

All notable changes to Magna Docs are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-11-24

### Added

- **The whole docs site follows the reader's language.** A reader who picks a language now
  gets everything in it, not just the page body: the sidebar (page titles and collection
  names), breadcrumb, Previous/Next, the browser tab title, meta tags, search results and the
  site's own labels ("On this page", "Was this page helpful?", copy buttons, the footer…).
  Anything not translated falls back to English.
- **The language choice is remembered.** Picking a language (`?lang=xx`) sets a `docs_lang`
  cookie for a year, so plain sidebar, Previous/Next and search links stay in that language.
  The menu's English item links to `?lang=en`, which switches back. A page is only shown in a
  language it has; otherwise it falls back to English.
- **Language names in their own script** in the public menu: हिन्दी, বাংলা, ગુજરાતી, ಕನ್ನಡ,
  മലയാളം, मराठी, தமிழ், తెలుగు, English (and so on for every supported language). The admin
  shows both names, e.g. "മലയാളം (Malayalam)", and its language search matches either.
- **Translated summary and SEO.** A page translation can have its own excerpt, meta title and
  meta description (a new "Translation summary & SEO" section in the page editor while a
  translation is open). A translated page never shows the English excerpt.
- **Translated collection names** — a new Translations section on the collection form.
- **Share image.** A new "Share image" upload in Docs settings (Branding; PNG, JPG or WebP,
  1200×630 recommended) is used as `og:image` / `twitter:image` for pages without a featured
  image of their own. Pages with an image get a large Twitter card. The setting travels with a
  settings export and import.
- **Multilingual SEO tags:** `<link rel="alternate" hreflang>` for every language a page has
  plus `x-default`, `og:locale` / `og:locale:alternate`, and a translated view's canonical is its
  own `?lang=xx` address. `<html lang>` is the language the page renders in.
- **UI strings are translatable** through a `docs::` translation namespace
  (`resources/lang/<locale>/docs.php`). English ships complete; `hi`, `bn`, `gu`, `kn`, `ml`,
  `mr`, `ta` and `te` ship with every key, still in English, ready to translate.

### Import / export

- Still `magna-docs-export` version 1, and older archives import unchanged. New, optional:
  page `translations[]` entries carry `excerpt`, `meta_title` and `meta_description`;
  `collections.ndjson` lines carry `translations: [{locale, title, description}]` (`name` is
  accepted for `title`), upserted on (collection, locale). The exporter writes both, and the
  manifest counts `collection_translations`.

### Fixed

- Reading time counts words in any script (a Malayalam or Hindi page always read "1 min").
- An archive translation in a three-letter locale (`fil`, which the editor offers) was
  rejected on import.
- An archive listing the same locale twice for one page no longer fails its upsert batch.

### Changed

- The sidebar tree is cached per language and is no longer rebuilt several times per request;
  building it is linear in the number of pages (it was quadratic).
- Docs pages send `Vary: Cookie`, since the `docs_lang` cookie decides their language. Nothing
  in Magna caches them (they leave as `private, no-cache`); this keeps a CDN in front of a docs
  site from serving one reader's language to another.
- The featured image is not used as the share image when the page hides it.

## [1.2.0] - 2026-11-23

### Added

- **The footer credit can be turned off.** A new "Show the Made with Magna Docs credit"
  switch in Docs settings (Footer) controls the link on the right of every public docs
  page's footer. It is on by default, so existing sites look the same after updating; turn it
  off and the footer keeps only your own copyright text. The setting (`show_credit`) also
  travels with a settings export and import.
