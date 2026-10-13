<?php
/**
 * Llms.txt generator: serves a machine-readable Markdown summary of the site
 * at /llms.txt, following the llmstxt.org standard so LLMs can understand
 * the site's structure and content.
 *
 * @see     https://llmstxt.org
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Llms_Txt
 */
class SSO_Llms_Txt {

	/**
	 * Transient holding the generated file.
	 */
	const CACHE_KEY = 'sso_llms_txt_cache';

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Llms_Txt|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Llms_Txt
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );

		// Keep the cached output fresh.
		add_action( 'save_post', array( $this, 'maybe_clear_cache_on_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'clear_cache' ) );
		add_action( 'updated_option', array( $this, 'on_option_updated' ), 10, 1 );
		add_action( 'added_option', array( $this, 'on_option_updated' ), 10, 1 );
		add_action( 'created_term', array( $this, 'clear_cache' ) );
		add_action( 'edited_term', array( $this, 'clear_cache' ) );
		add_action( 'delete_term', array( $this, 'clear_cache' ) );
	}

	/**
	 * Delete the cached llms.txt.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear the cache on save_post, ignoring revisions, autosaves and auto-drafts.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public function maybe_clear_cache_on_save( $post_id, $post = null ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $post instanceof WP_Post && 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->clear_cache();
	}

	/**
	 * Clear the cache when the plugin settings or the custom llms.txt content change.
	 *
	 * @param string $option Option name.
	 */
	public function on_option_updated( $option ) {
		if ( in_array( $option, array( SSO_Settings::OPTION_KEY, 'sso_llms_custom_content', 'show_on_front', 'page_on_front', 'page_for_posts' ), true ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Register the /llms.txt rewrite rule.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?sso_llms_txt=1', 'top' );
	}

	/**
	 * Expose the custom query var to WordPress.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'sso_llms_txt';
		return $vars;
	}

	/**
	 * Serve the llms.txt file when the rewrite rule is triggered.
	 */
	public function serve_llms_txt() {
		if ( ! get_query_var( 'sso_llms_txt' ) ) {
			return;
		}

		// Only serve when the feature is enabled in Settings.
		if ( ! SSO_Settings::get( 'advanced', 'llms_txt_enabled', 0 ) ) {
			status_header( 404 );
			exit;
		}

		$content = get_transient( self::CACHE_KEY );
		if ( false === $content ) {
			$content = $this->generate_content();
			set_transient( self::CACHE_KEY, $content, 12 * HOUR_IN_SECONDS );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text file, not HTML; every dynamic value is sanitised in generate_content().
		exit;
	}

	/**
	 * Build the full llms.txt content string.
	 *
	 * @return string
	 */
	public function generate_content() {
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$site_url  = home_url();
		$language  = get_bloginfo( 'language' );

		$lines = array();

		// --- Header ---
		$lines[] = '# ' . $site_name;
		$lines[] = '';
		if ( $site_desc ) {
			$lines[] = '> ' . $site_desc;
			$lines[] = '';
		}

		// --- About ---
		$lines[] = '## About';
		$lines[] = 'Site URL: ' . $site_url;
		$lines[] = 'Language: ' . $language;
		$lines[] = 'Generated: ' . gmdate( 'Y-m-d' );
		$lines[] = '';

		// --- Recent content: posts first, then every other public post type ---
		$post_types = array_diff( array_keys( get_post_types( array( 'public' => true ) ) ), array( 'page', 'attachment', 'post' ) );
		array_unshift( $post_types, 'post' );
		foreach ( $post_types as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				continue;
			}

			// Over-fetch, then drop anything the sitemap would hide (noindex, excluded, password).
			$candidates = get_posts(
				array(
					'post_type'    => $post_type,
					'numberposts'  => 60,
					'post_status'  => 'publish',
					'has_password' => false,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			$items      = array();
			foreach ( $candidates as $post ) {
				if ( SSO_Sitemap::is_post_listable( $post ) ) {
					$items[] = $post;
				}
				if ( count( $items ) >= 20 ) {
					break;
				}
			}
			if ( ! $items ) {
				continue;
			}

			$lines[] = '## ' . ( 'post' === $post_type ? 'Recent Posts' : $post_type_obj->labels->name );
			foreach ( $items as $post ) {
				$meta_desc = get_post_meta( $post->ID, '_sso_meta_description', true );
				$desc      = $meta_desc ? $meta_desc : wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
				$desc      = str_replace( array( "\r", "\n" ), ' ', $desc );
				$lines[]   = sprintf( '- [%s](%s): %s', $this->link_text( $post ), get_permalink( $post ), $desc );
			}
			$lines[] = '';
		}

		// --- Pages ---
		$pages = get_pages(
			array(
				'sort_column' => 'menu_order',
				'post_status' => 'publish',
				'number'      => 200,
			)
		);
		$items = array();
		foreach ( $pages as $page ) {
			if ( SSO_Sitemap::is_post_listable( $page ) ) {
				$items[] = $page;
			}
		}
		if ( $items ) {
			$lines[] = '## Pages';
			foreach ( $items as $page ) {
				$meta_desc = get_post_meta( $page->ID, '_sso_meta_description', true );
				if ( $meta_desc ) {
					$lines[] = sprintf( '- [%s](%s): %s', $this->link_text( $page ), get_permalink( $page ), str_replace( array( "\r", "\n" ), ' ', $meta_desc ) );
				} else {
					$lines[] = sprintf( '- [%s](%s)', $this->link_text( $page ), get_permalink( $page ) );
				}
			}
			$lines[] = '';
		}

		// --- Categories (top-level) ---
		$categories = get_categories(
			array(
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => 20,
			)
		);
		if ( $categories && ! is_wp_error( $categories ) ) {
			$lines[] = '## Categories';
			foreach ( $categories as $cat ) {
				$lines[] = sprintf( '- [%s](%s): %d posts', $cat->name, get_category_link( $cat->term_id ), $cat->count );
			}
			$lines[] = '';
		}

		// --- Sitemap reference ---
		$lines[] = '## Sitemap';
		$lines[] = 'XML Sitemap: ' . home_url( '/sitemap.xml' );
		$lines[] = '';

		// --- Optional custom content from Settings ---
		$custom = get_option( 'sso_llms_custom_content', '' );
		if ( $custom ) {
			$lines[] = '## Additional Information';
			$lines[] = sanitize_textarea_field( $custom );
			$lines[] = '';
		}

		$lines[] = '---';
		$lines[] = 'Generated by Beplus Metadata AI Analyzer';

		return implode( "\n", $lines );
	}

	/**
	 * Markdown-safe link text for a post: decoded, tag-free, brackets removed.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function link_text( $post ) {
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );
		return trim( str_replace( array( '[', ']' ), array( '(', ')' ), $title ) );
	}

	/**
	 * Flush rewrite rules so the /llms.txt endpoint is immediately accessible.
	 * Called on plugin activation and when the llms.txt setting is saved.
	 */
	public function flush_rewrite() {
		$this->add_rewrite_rule();
		flush_rewrite_rules();
	}
}
