# CLAUDE.md — Beplus Metadata AI Analyzer

Guidance for Claude Code (or any AI agent) working in this repository.

## What this is

A lightweight WordPress SEO plugin: meta tags, XML sitemap, Open Graph/Twitter
Cards, focus keyword analysis, breadcrumbs, canonical URLs, robots control,
and Schema.org JSON-LD — published on WordPress.org as
`beplus-metadata-ai-analyzer`.

## Architecture

- `beplus-metadata-ai-analyzer.php` — plugin bootstrap, defines `SSO_VERSION`,
  loads all `includes/class-sso-*.php` files.
- `includes/class-sso-settings.php` — central options store. All settings
  live under one `sso_settings` option, grouped by tab
  (`general`/`social`/`schema`/`sitemap`/`breadcrumbs`/`advanced`).
  `SSO_Settings::get($group, $key, $default)` is the only sanctioned way to
  read a setting — never call `get_option('sso_settings')` directly elsewhere.
- `includes/class-sso-meta-box.php` — per-post SEO meta box (General/Social/
  Schema tabs), save handler with nonce + capability checks.
- `includes/class-sso-sitemap.php` — virtual `/sitemap.xml` (rewrite rule,
  never a real file), 12h transient cache, invalidated on save/delete post
  or settings change. Above `MAX_URLS_PER_PAGE` (2000) URLs it auto-switches
  to a `<sitemapindex>` linking `/sitemap-N.xml` chunks (added 1.0.3); the
  shared URL list is cached once (`URLS_CACHE_KEY`) and sliced per chunk. Any
  new rewrite rule added here needs a one-time `flush_rewrite_rules()` on the
  version-bump path in the main plugin file, or in-place upgrades 404. Also
  owns the `robots_txt` filter that must always point at OUR sitemap, not
  WordPress core's `/wp-sitemap.xml`.
- `includes/class-sso-schema.php` — JSON-LD builders (Article, Product,
  FAQPage, HowTo, Event, Recipe, JobPosting, Course, Review, LocalBusiness,
  BreadcrumbList, site-wide Organization/WebSite graph).
- `includes/class-sso-llms-txt.php` — `/llms.txt` per llmstxt.org, gated by
  `advanced.llms_txt_enabled`.
- `includes/class-sso-opengraph.php`, `class-sso-canonical.php`,
  `class-sso-robots.php`, `class-sso-breadcrumbs.php`,
  `class-sso-post-columns.php`, `class-sso-analyzer.php` — one concern per
  file, singleton pattern throughout (`Class::instance()`).

## Hard rules — do not violate

1. **Every settings tab that is a single lone checkbox MUST have a hidden
   `value="0"` input immediately before it.** An unchecked checkbox is
   omitted from `$_POST` entirely, and `SSO_Settings::sanitize()`
   deliberately only processes groups present in the submission (so
   switching tabs never wipes other tabs). Without the hidden fallback, a
   lone checkbox can never be turned off — this exact bug shipped in 1.0.1
   and was fixed in 1.0.2. When adding a new all-checkbox tab, copy the
   pattern from `render_sitemap_tab()`.
2. **Any code that touches `robots.txt` must not let WordPress core's
   `/wp-sitemap.xml` line leak through unless our own sitemap is
   disabled.** See `SSO_Sitemap::filter_robots_txt()` — reuse it, don't
   duplicate.
3. **Never rename a public template tag or shortcode without also updating
   `readme.txt`.** The 1.0.1 → 1.0.2 fix existed because `bpmaa_breadcrumbs()`
   was renamed to `sso_breadcrumbs()` in code but readme.txt kept the old
   name — public docs and code must be grepped together before every
   release (`grep -rn "bpmaa_\|sso_" readme.txt includes/`).
4. **Version must be bumped in exactly two places, and they must match:**
   `beplus-metadata-ai-analyzer.php` (`Version:` header + `SSO_VERSION`
   constant) and `readme.txt` (`Stable tag:`). CI enforces this
   (`.github/workflows/ci.yml` → `plugin-check` job) but check it yourself
   before opening a PR.
5. **Sanitize/escape discipline:** every settings field sanitized in
   `SSO_Settings::sanitize_group()` by explicit type (`sanitize_text_field`,
   `absint`, `esc_url_raw`, `wp_kses_post` for the FAQ answer field only).
   Every output escaped at the point of echo (`esc_attr`/`esc_html`/`esc_url`).
   Follow the existing pattern in the tab you're editing — don't invent a
   new sanitization style.
6. **Nonce + capability check on every save path**, `current_user_can`
   after `check_admin_referer`/nonce verify (WP-recommended order: nonce
   first, capability second). This is already consistent across the
   codebase — do not skip it "just this once" for a small feature.

## Testing before every PR

There is no PHPUnit suite yet (small plugin, manual + Docker WP testing has
been the practice so far). Minimum bar before opening a PR:

```bash
# 1. Syntax check every touched file
php -l includes/class-sso-whatever.php

# 2. Spin up a throwaway WordPress + MySQL via Docker, mount the plugin,
#    activate it, and click through every settings tab checking for
#    Fatal error / Warning / Notice in the page body.
# 3. If you touched sitemap/robots/llms.txt: curl the endpoint directly
#    (not just through the browser — HTTP caching can mask a real 404/200
#    flip). Toggle the relevant setting off AND on, re-check both states.
# 4. If you touched schema: view-source a real single post and grep for
#    <script type="application/ld+json">, verify the JSON parses and has
#    the expected @type.
```

## Release procedure

See `docs/RELEASE.md`. Short version: GitHub `main` is source of truth →
sync to WordPress.org SVN trunk → `svn copy trunk tags/X.Y.Z` → commit both.
Never hand-edit the SVN copy — always regenerate it from a fresh `git clone`
of `main` so SVN can never drift from what's actually in the reviewed
GitHub history.

**Dev-only files never ship to WordPress.org:** `.github/`, `CLAUDE.md`,
`CHANGELOG.md`, `docs/`, `composer.json`/`composer.lock`, `phpcs.xml.dist`,
`.gitignore` are repo infrastructure only. The `rsync` command in
`docs/RELEASE.md` explicitly excludes all of them — if you add a new
dev-only file/folder at the repo root, add it to that exclude list too, or
it will accidentally get published to every WordPress site running this
plugin.

## Things NOT to do

- Don't add a database table or custom `$wpdb->query()` — this plugin
  intentionally has zero non-core-API database access (verified in the
  1.0.2 security review). Keep it that way; use `wp_options`/post meta.
- Don't add an AJAX "AI" call to a third-party LLM API without discussing
  it with the maintainer first — the plugin name says "AI Analyzer" but the
  focus-keyword scoring is deliberately rule-based/client-side JS today;
  adding a real API call is a scope change with cost/privacy implications,
  not a routine fix.
- Don't touch `uninstall.php`'s data-removal list without checking it still
  matches every option/meta key the plugin actually writes — an incomplete
  uninstall leaves orphaned data, and an over-broad one can delete data a
  user expected to keep.
