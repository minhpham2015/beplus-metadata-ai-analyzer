# Changelog

All notable changes to this project are documented here (dev-facing —
see `readme.txt` for the user-facing WordPress.org changelog).
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
