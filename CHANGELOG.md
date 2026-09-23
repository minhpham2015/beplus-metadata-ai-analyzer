# Changelog

All notable changes to this project are documented here (dev-facing —
see `readme.txt` for the user-facing WordPress.org changelog).
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.1.3] - 2026-09-23

### Changed
- Assign To search now explains its two-character minimum, caches identical
  per-user searches for 60 seconds, and limits uncached searches to 10 per
  user per minute while retaining nonce and capability checks.
- CI now explicitly activates the plugin against installable WordPress 7.1.1.

## [1.1.0] - 2026-09-16

### Added
- **"Schemas" custom post type** (`sso_schema`, admin-only, no public
  archive/single template) — replaces the old Settings > Schema tables
  (Schema per Post Type / by Page Template / by Category-Tag, all removed).
  Each entry has an "Assign To" box: specific pages/posts (multi-select),
  every post of one post type, or **"Whole site"** (new) as a site-wide
  fallback. See `includes/class-sso-schema-cpt.php`.
- **"Settings" submenu** under the Beplus Smart SEO admin menu for quicker
  access to the main settings screen (`SSO_Settings::add_settings_page()`).
- **Bulk assign for schema rules.** Each of the 3 schema rule tables in the
  Schema tab (Post Type, Page Template, Category/Tag) now has a "Bulk
  assign" toolbar above it: pick a schema type, click "Apply to all enabled
  rows" to set every already-enabled row in that table to the chosen type
  in one click, or tick "Also enable every row first" to enable + assign in
  one pass. Purely client-side (`initSchemaBulkAssign()` in
  `admin-script.js`) — it only sets the existing per-row `<select>`/
  checkbox values before the normal settings form submit, so it reuses the
  exact same `sso_settings[schema][...]` fields and `sanitize_group()` path
  as a manual per-row edit; no new AJAX endpoint or sanitization code.
  Deliberately never turns a rule on unless the admin explicitly asked it to
  (the "enable every row first" checkbox is opt-in, unchecked by default).
  (Note: this bulk-assign UI is now dead code for the 3 removed tables above
  — kept only insofar as it still applies to nothing; scheduled for removal
  in a future cleanup pass since the tables themselves no longer exist.)

### Changed
- `resolve_post_schema_type()` in `class-sso-schema.php` now resolves
  through 2 priority tiers instead of 4 (most specific wins, statically
  cached per post_id per request):
  1. Per-post override (`_sso_schema_type` post meta — unchanged).
  2. A Schemas CPT entry: specific-post assignment checked first, then
     post-type-wide, then the new site-wide ("Whole site") fallback last.
- `SSO_Meta_Box::render_schema_fields_only()` / `save_schema_fields_only()`
  are now shared between the per-post meta box's Schema tab AND
  `SSO_Schema_CPT::render_fields_box()` / `save()` so the two never drift
  apart (identical `_sso_schema_*` meta keys, different post IDs).
- **All JSON-LD output moved from `wp_footer` to `wp_head`**
  (`output_global_schema` priority 19, `output_post_schema` priority 20,
  `output_breadcrumb_schema` priority 21) — `<script type="application/
  ld+json">` tags now land in `<head>` instead of the page footer.

### Fixed
- `SSO_Schema_CPT::render_fields_box()` was passing a truthy (non-bool)
  2nd argument to `render_schema_fields_only()`, which silently enabled the
  per-post-only "Auto" dropdown option inside the CPT screen. A new entry
  left on that default option saved an EMPTY `_sso_schema_type`, so the
  entry produced zero JSON-LD output with no visible error — this is what
  the "Whole site" entry appeared to do nothing when first tested.
- A "Whole site" Schemas entry previously never rendered anywhere, because
  `output_post_schema()` bailed out entirely on any non-`is_singular()`
  request (home, archive, search, 404) — exactly the pages "whole site" is
  supposed to cover. It now falls back to
  `SSO_Schema_CPT::get_site_wide_entry_id()` on those views and builds the
  schema from the entry's own fields, recursively rewriting any nested
  value that equals the entry's internal (non-public) permalink/title —
  `offers.url`, `mainEntityOfPage.@id`, `sameAs`, etc. — to the site's real
  home URL / site name (`replace_recursive()`).

## [1.0.5] - 2026-09-09

### Added
- **Schema type by Page Template or Category/Tag.** `resolve_post_schema_type()`
  in `class-sso-schema.php` now resolves through 4 priority tiers (most
  specific wins, statically cached per post_id per request):
  1. Per-post override (`_sso_schema_type` post meta — unchanged).
  2. **New:** Page Template rule (`schema.template_rules`), keyed by
     `get_page_template_slug()`, with `'default'` representing the theme's
     default template (WP returns `''` for it).
  3. **New:** Taxonomy rule (`schema.taxonomy_rules`) — category terms
     checked before tag terms; within a taxonomy, the lowest matching
     term_id with an *enabled* rule wins (deterministic tiebreak).
  4. Per-post-type default (`schema.post_types` — unchanged).
  A disabled rule at any tier falls through to the next tier rather than
  blocking resolution. Two new tables added to Settings > Schema ("Schema
  by Page Template", "Schema by Category / Tag"), following the existing
  hidden-checkbox fallback pattern so a lone unchecked box can be turned
  off. Sanitization added in `sanitize_group('schema', ...)` for both new
  option keys (`template_rules`, `taxonomy_rules`).

## [1.0.4] - 2026-09-06

### Added
- **Sitemap `<priority>` values.** `get_urls()` now tags every URL with a
  `priority` field via new `calc_priority()`: 1.0 for the front page and
  `page` post type, 0.7 for everything else (posts/CPTs), 0.5 for taxonomy
  term archives. Filterable per-URL via `sso_sitemap_url_priority`. Purely
  conventional (Google publicly ignores `<priority>` since ~2020) but
  standard practice among SEO plugins and still read by some non-Google
  tools/crawlers.
- **`/sitemap.xsl` readable stylesheet.** New virtual endpoint (rewrite rule
  + `XSL_QUERY_VAR`, never a real file — same pattern as the sitemap itself)
  serving a static XSLT document. Every `<?xml version...?>` sitemap output
  now includes a `<?xml-stylesheet type="text/xsl" href=".../sitemap.xsl"?>`
  processing instruction, so a human opening `/sitemap.xml` in a browser
  sees a styled HTML table (URL/lastmod/priority, or the sitemap index list)
  instead of raw XML. Purely cosmetic — search engines ignore the PI and
  parse the underlying `<urlset>`/`<sitemapindex>` exactly as before, and no
  build step / real file changed.

### Changed
- `Requires PHP` raised 7.4 → 8.1. 7.4 reached end-of-life 2022-11-28 (no
  security patches for ~4 years); CI matrix now tests 8.1/8.2/8.3 only.
  `phpcs.xml.dist` `testVersion` updated to match.
- Fixed a CI bug (not a plugin bug): the "Version Consistency Check" job's
  `grep -oP '(?<=Version:\s{0,20})\S+'` used a variable-length lookbehind,
  which GNU grep 3.11 (current Ubuntu Actions runner) rejects outright
  ("lookbehind assertion is not fixed length"), failing the job on every run
  regardless of whether versions actually matched. Replaced with the
  fixed-width `Version:\s*\K\S+` (`\K` resets the match start instead of
  requiring a lookbehind). Verified both readme.txt/plugin-header versions
  were already in sync before this fix — this was a CI false-negative, not a
  real version mismatch.

## [1.0.3] - 2026-09-05

### Added
- **Paginated sitemap index for large sites.** `SSO_Sitemap::get_sitemap_xml()`
  now takes a `$page` argument. At or below `MAX_URLS_PER_PAGE` (2000) the root
  `/sitemap.xml` stays a single `<urlset>` (fully back-compatible — small sites
  are byte-for-byte unchanged). Above 2000 total URLs, `/sitemap.xml` returns a
  `<sitemapindex>` linking `/sitemap-1.xml` … `/sitemap-N.xml`, each a 2000-URL
  `<urlset>` chunk. New rewrite rule `^sitemap-([0-9]+)\.xml$` +
  `sso_sitemap_page` query var; out-of-range chunk requests return 404.
  Motivation: `posts_per_page => -1` gathered every URL into one in-memory
  document, which risks memory/timeout on sites with tens of thousands of
  posts. The gather now runs once per cache window (shared `URLS_CACHE_KEY`
  transient) and is sliced per chunk.
- Split XML building into `build_urlset_xml()` / `build_index_xml()` helpers;
  added `get_cached_urls()` (shared URL-list cache) and `MAX_CACHED_PAGES`
  (bounds the invalidation loop in `clear_sitemap_cache()`, which now clears
  the index, every chunk, and the URL-list transient).
- One-time `flush_rewrite_rules()` on the version-bump path in
  `beplus-metadata-ai-analyzer.php` so in-place upgrades (not just
  deactivate/reactivate) register the new `/sitemap-N.xml` rule.

### Changed
- `Tested up to: 7.1` — verified on a live WP 7.1 / PHP 8.3 Docker site:
  sitemap (single + index + chunks + 404), cache invalidation on post
  delete, meta tags, Open Graph, and JSON-LD schema all render correctly with
  no PHP Fatal/Warning/Notice.

## [1.0.2] - 2026-09-04

### Fixed
- **Security/correctness:** `robots.txt` now points to this plugin's own
  `/sitemap.xml` instead of WordPress core's default `/wp-sitemap.xml`.
  Search engines were discovering a sitemap that doesn't respect this
  plugin's noindex/exclude settings.
- **Bug:** the "Enable XML sitemap" and "Enable llms.txt" checkboxes could
  never be turned off. Root cause: both settings tabs consist of a single
  lone checkbox; per standard HTML behavior an unchecked checkbox submits
  no data for that field at all, so the entire settings group was missing
  from `$_POST` and `SSO_Settings::sanitize()` (by design) skips groups not
  present in the submission — silently keeping the old "enabled" value.
  Fixed by adding a hidden `value="0"` fallback input before each lone
  checkbox.
- **Docs:** `readme.txt` referenced the stale `bpmaa_breadcrumbs()` /
  `[bpmaa_breadcrumb]` names from before the plugin was renamed; corrected
  to the actual `sso_breadcrumbs()` / `[sso_breadcrumb]` names shipped in
  code.

## [1.0.1] - 2026-08-xx

### Fixed
- "Noindex" archive checkboxes (tag/author/date) in Settings → General now
  persist correctly when unchecked, instead of silently reverting to noindex.
- XML sitemap now excludes posts noindexed via a post type's site-wide
  default, not just posts noindexed individually.
- Canonical URLs for paginated archives (blog index, category/tag/tax, post
  type archives, author archives) now point at the current page instead of
  always page 1.
- The "Choose Image" media picker (OG image, default OG image, schema logo)
  now only allows selecting images.

## [1.0.0] - 2026-08-xx

### Added
- Initial release: meta tags management with Google snippet preview, virtual
  XML sitemap, Open Graph/Twitter Cards, AI/LLM meta tags (Dublin Core,
  citation, llm:summary/topics), `/llms.txt` generator, client-side focus
  keyword analysis, breadcrumbs with BreadcrumbList JSON-LD, canonical URLs,
  robots meta control, and Schema.org JSON-LD (Organization/WebSite graph +
  12 per-post-type schema builders including Product, FAQPage, Event,
  Recipe, JobPosting, Course, Review, LocalBusiness).
