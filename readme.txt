=== Beplus Metadata AI Analyzer ===
Contributors: bearsthemes, minhphamit
Tags: seo, xml sitemap, open graph, schema, structured data
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SEO toolkit: meta tags, XML sitemap, Open Graph, focus keyword analysis, breadcrumbs, canonical URLs, robots control & Schema.org JSON-LD.

== Description ==

Beplus Metadata AI Analyzer gives every public post type a dedicated SEO meta
box and adds the technical SEO foundations most sites need, without the
bloat:

* **Meta Tags Management** – custom meta title & description per post with a
  live Google search snippet preview and character counters.
* **XML Sitemap** – a virtual sitemap served at `/sitemap.xml`, automatically
  pinged to Google on publish/update.
* **Open Graph & Twitter Cards** – og:title, og:description, og:image, og:locale,
  article:published_time, twitter:card and friends, with a dedicated OG image
  field and sensible fallbacks (featured image, then site default, then logo).
* **AI/LLM Meta Tags** – Dublin Core (DC.*), Citation meta (citation_title, etc.),
  AI-specific tags (llm:summary, llm:topics), and robots max-snippet hints —
  all optional and individually toggleable from Settings.
* **llms.txt Generator** – serves a machine-readable Markdown summary of your
  site at `/llms.txt` following the llmstxt.org standard, so LLMs can
  understand your site structure and content.
* **Focus Keyword Analysis** – instant, client-side scoring of your focus
  keyword against the title, description, slug, headings and content.
* **Breadcrumbs** – a `bpmaa_breadcrumbs()` template tag and a
  `[bpmaa_breadcrumb]` shortcode, with full sub-category, CPT archive and child
  page hierarchy support. BreadcrumbList JSON-LD is output automatically.
* **Canonical URLs** – automatic canonical tags with a per-post manual
  override for duplicate content.
* **Robots Meta Control** – noindex/nofollow per post, plus site-wide
  defaults per post type and archive.
* **Schema.org (JSON-LD)** – a site-wide Organization/Person + WebSite graph,
  plus per-post-type Article, BlogPosting, WebPage, Product (WooCommerce-aware),
  FAQPage, HowTo, Event, VideoObject, Recipe, JobPosting, Course, Review or
  LocalBusiness structured data, with manual overrides per post and a live
  JSON-LD preview in Settings.

= About BePlus =

This plugin is developed and maintained by BePlus,
a WordPress and Shopify development studio with 10+
years of experience building themes and plugins for
nonprofits and eCommerce brands.

Learn more at [beplusthemes.com](https://beplusthemes.com/).

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it
   through the Plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Metadata AI Analyzer** to configure General, Social,
   Schema, Sitemap and Breadcrumbs options.
4. Edit any post or page to fill in its SEO meta box (General / Social /
   Schema tabs).

== Frequently Asked Questions ==

= Does this plugin create an actual sitemap.xml file? =

No. The sitemap is generated on the fly through a rewrite rule, so it always
reflects your current published content with no extra file to manage.

= Is this compatible with the Block Editor (Gutenberg)? =

Yes. The meta box and the focus keyword analyzer work with both the Block
Editor and the Classic Editor.

= Does the Product schema require WooCommerce? =

WooCommerce is optional. Without it, Product schema still outputs the basic
name/image/description; price, SKU, stock and rating are added automatically
when WooCommerce is active.

= What happens to my data when I delete the plugin? =

Deleting the plugin (not just deactivating it) removes all of its options and
post meta from the database via `uninstall.php`.

= Who develops this plugin? =
This plugin is developed and maintained by BePlus, a WordPress and Shopify development studio. You can learn more about our work at [beplusthemes.com](https://beplusthemes.com/).

== Screenshots ==

1. SEO meta box with Google snippet preview and focus keyword analysis.
2. Schema settings tab with a live JSON-LD preview.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== External Services ==

This plugin makes the following external requests:

= Google Sitemap Ping =
When a post is published or updated, the plugin optionally pings Google to notify it of the updated sitemap. This request is sent to:

    https://www.google.com/ping?sitemap=<your-sitemap-url>

This ping is rate-limited to at most once every five minutes. No personal data is sent — only your public sitemap URL. Pinging can be disabled by turning off the XML sitemap in **Settings > Metadata AI Analyzer > Sitemap**.

* Google's Privacy Policy: https://policies.google.com/privacy
* Google's Terms of Service: https://policies.google.com/terms
