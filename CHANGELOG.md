# Changelog

All notable changes to this project are documented here (dev-facing —
see `readme.txt` for the user-facing WordPress.org changelog).
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
