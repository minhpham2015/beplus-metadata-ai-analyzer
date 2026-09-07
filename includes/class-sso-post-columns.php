<?php
/**
 * SEO Score column for WordPress post list tables.
 *
 * Adds an SEO Score column to every public post type, with sorting and
 * filter dropdown support. Scores are stored as post meta and recalculated
 * on save_post.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSO_Post_Columns
 */
class SSO_Post_Columns {

	/**
	 * Constructor — register all hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_hooks' ) );
		add_action( 'save_post', array( $this, 'recalculate_score' ), 20 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_score' ), 999 );
		add_action( 'admin_init', array( $this, 'handle_recalc_request' ) );
		add_action( 'wp_head', array( $this, 'admin_bar_styles' ) );
		add_action( 'admin_head', array( $this, 'admin_bar_styles' ) );
	}

	/**
	 * Register per-post-type column hooks after admin_init so get_post_types()
	 * returns the full list of registered types.
	 */
	public function register_hooks() {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_seo_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_seo_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'sortable_seo_column' ) );
		}
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_sort_and_filter' ) );
	}

	/**
	 * Insert the SEO Score column immediately after the Title column.
	 *
	 * @param  array $columns Existing columns.
	 * @return array
	 */
	public function add_seo_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['sso_seo_score'] = '<span class="dashicons dashicons-chart-bar" title="SEO Score"></span> SEO';
			}
		}
		return $new;
	}

	/**
	 * Render the SEO Score cell as a coloured circular badge.
	 *
	 * @param string $column  Current column name.
	 * @param int    $post_id Current post ID.
	 */
	public function render_seo_column( $column, $post_id ) {
		if ( 'sso_seo_score' !== $column ) {
			return;
		}

		$calculated = get_post_meta( $post_id, '_sso_seo_score_calculated', true );
		$score      = $calculated
			? (int) get_post_meta( $post_id, '_sso_seo_score', true )
			: self::calculate_score( $post_id );

		if ( $score >= 75 ) {
			$color = '#00a32a';
			$label = __( 'Good', 'beplus-metadata-ai-analyzer' );
		} elseif ( $score >= 50 ) {
			$color = '#dba617';
			$label = __( 'Average', 'beplus-metadata-ai-analyzer' );
		} else {
			$color = '#d63638';
			$label = __( 'Weak', 'beplus-metadata-ai-analyzer' );
		}

		printf(
			'<div style="display:inline-flex;align-items:center;gap:5px;">
			  <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%%;background:%s;color:#fff;font-weight:700;font-size:12px;">%d</span>
			  <span style="font-size:11px;color:%s;">%s</span>
			</div>',
			esc_attr( $color ),
			absint( $score ),
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * Make the SEO Score column sortable.
	 *
	 * @param  array $columns Existing sortable columns.
	 * @return array
	 */
	public function sortable_seo_column( $columns ) {
		$columns['sso_seo_score'] = 'sso_seo_score';
		return $columns;
	}

	/**
	 * Output a filter dropdown above the post list.
	 */
	public function filter_dropdown() {
		global $post_type;

		if ( ! in_array( $post_type, get_post_types( array( 'public' => true ), 'names' ), true ) ) {
			return;
		}

		$selected = isset( $_GET['sso_score_filter'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['sso_score_filter'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		echo '<select name="sso_score_filter">';
		echo '<option value="">— SEO Score —</option>';

		$options = array(
			'good'   => __( 'Good (75+)', 'beplus-metadata-ai-analyzer' ),
			'medium' => __( 'Average (50–74)', 'beplus-metadata-ai-analyzer' ),
			'weak'   => __( 'Weak (<50)', 'beplus-metadata-ai-analyzer' ),
		);
		foreach ( $options as $val => $lbl ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $val ),
				selected( $selected, $val, false ),
				esc_html( $lbl )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply sorting and filtering to the main query on admin post list screens.
	 *
	 * @param WP_Query $query Current query.
	 */
	public function handle_sort_and_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Sorting by SEO Score.
		if ( 'sso_seo_score' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_sso_seo_score' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		// Filtering by score band.
		$filter = isset( $_GET['sso_score_filter'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['sso_score_filter'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( $filter ) {
			$meta_query = $query->get( 'meta_query' ) ? $query->get( 'meta_query' ) : array();

			if ( 'good' === $filter ) {
				$meta_query[] = array(
					'key'     => '_sso_seo_score',
					'value'   => 75,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			} elseif ( 'medium' === $filter ) {
				$meta_query[] = array(
					'key'     => '_sso_seo_score',
					'value'   => array( 50, 74 ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				);
			} elseif ( 'weak' === $filter ) {
				$meta_query[] = array(
					'key'     => '_sso_seo_score',
					'value'   => 50,
					'compare' => '<',
					'type'    => 'NUMERIC',
				);
			}

			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Calculate and persist the SEO score for a post.
	 *
	 * Scoring breakdown (max 100):
	 *   10  — post title always present
	 *   20  — meta title (10 for existing, +10 for optimal 30–60 char length)
	 *   20  — meta description (10 for existing, +10 for optimal 120–160 char length)
	 *   15  — focus keyword (5 for existing, +5 in title, +5 in content)
	 *   15  — content length (≥300 words = 15, ≥100 = 8, >0 = 3)
	 *   10  — schema type set and not "none"
	 *   10  — featured image
	 *
	 * @param  int $post_id Post ID.
	 * @return int Score 0–100.
	 */
	public static function calculate_score( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'auto-draft' === $post->post_status ) {
			return 0;
		}

		$score = 0;

		$meta_title = get_post_meta( $post_id, '_sso_meta_title', true );
		$meta_desc  = get_post_meta( $post_id, '_sso_meta_description', true );
		$focus_kw   = get_post_meta( $post_id, '_sso_focus_keyword', true );
		$schema     = get_post_meta( $post_id, '_sso_schema_type', true );

		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		$title_len  = mb_strlen( $meta_title );
		$desc_len   = mb_strlen( $meta_desc );

		// Post title is always present (10 pts).
		$score += 10;

		// Meta Title (20 pts).
		if ( $meta_title ) {
			$score += 10;
			if ( $title_len >= 30 && $title_len <= 60 ) {
				$score += 10;
			} elseif ( $title_len > 0 ) {
				$score += 5;
			}
		}

		// Meta Description (20 pts).
		if ( $meta_desc ) {
			$score += 10;
			if ( $desc_len >= 120 && $desc_len <= 160 ) {
				$score += 10;
			} elseif ( $desc_len > 0 ) {
				$score += 5;
			}
		}

		// Focus Keyword (15 pts).
		if ( $focus_kw ) {
			$score += 5;
			if ( $meta_title && false !== mb_stripos( $meta_title, $focus_kw ) ) {
				$score += 5;
			}
			if ( false !== mb_stripos( $content, $focus_kw ) ) {
				$score += 5;
			}
		}

		// Content length (15 pts).
		if ( $word_count >= 300 ) {
			$score += 15;
		} elseif ( $word_count >= 100 ) {
			$score += 8;
		} elseif ( $word_count > 0 ) {
			$score += 3;
		}

		// Schema type (10 pts).
		if ( $schema && 'none' !== $schema ) {
			$score += 10;
		}

		// Featured image (10 pts).
		if ( has_post_thumbnail( $post_id ) ) {
			$score += 10;
		}

		$score = min( 100, max( 0, $score ) );

		update_post_meta( $post_id, '_sso_seo_score', $score );
		update_post_meta( $post_id, '_sso_seo_score_calculated', 1 );

		return $score;
	}

	/**
	 * Recalculate and persist the SEO score whenever a post is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public function recalculate_score( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::calculate_score( $post_id );
	}

	/**
	 * Add SEO Score node to the WordPress Admin Bar when viewing or editing a post/page.
	 * On non-singular contexts (homepage, archives, dashboard, post list, etc.) a
	 * standalone "Beplus Smart SEO" node is shown instead, linking to plugin settings.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_score( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_id = 0;

		if ( is_admin() ) {
			// Backend edit screen only.
			$screen = get_current_screen();
			if ( $screen && 'post' === $screen->base ) {
				$post_id = get_the_ID();
			}
		} elseif ( is_singular() ) {
			// Frontend single post/page.
			$post_id = get_the_ID();
		}

		// No singular post context: homepage, archives, admin dashboard, post list, etc.
		// Show a standalone top-level "Beplus Smart SEO" node linking to plugin settings.
		if ( ! $post_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				$wp_admin_bar->add_node(
					array(
						'id'    => 'sso-settings-bar',
						'title' => '<span class="ab-icon dashicons dashicons-chart-area" style="font:400 20px/1 dashicons;margin-top:2px;"></span> ' .
								__( 'Beplus Smart SEO', 'beplus-metadata-ai-analyzer' ),
						'href'  => esc_url( admin_url( 'admin.php?page=sso-settings' ) ),
						'meta'  => array( 'title' => __( 'Beplus Smart SEO Settings', 'beplus-metadata-ai-analyzer' ) ),
					)
				);
			}
			return;
		}

		$calculated = get_post_meta( $post_id, '_sso_seo_score_calculated', true );
		$score      = $calculated
			? (int) get_post_meta( $post_id, '_sso_seo_score', true )
			: self::calculate_score( $post_id );

		if ( $score >= 75 ) {
			$color = '#00a32a';
			$label = __( 'Good', 'beplus-metadata-ai-analyzer' );
		} elseif ( $score >= 50 ) {
			$color = '#dba617';
			$label = __( 'Average', 'beplus-metadata-ai-analyzer' );
		} else {
			$color = '#d63638';
			$label = __( 'Weak', 'beplus-metadata-ai-analyzer' );
		}

		// Main node.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'sso-seo-score',
				'title' => sprintf(
					'<span style="display:inline-flex;align-items:center;gap:6px;">
					<span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%%;background:%s;color:#fff;font-weight:700;font-size:11px;line-height:1;">%d</span>
					<span style="color:#eee;font-size:12px;">SEO: <strong style="color:%s;">%s</strong></span>
				</span>',
					esc_attr( $color ),
					$score,
					esc_attr( $color ),
					esc_html( $label )
				),
				'href'  => esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit#sso-meta-box' ) ),
				'meta'  => array(
					'title' => sprintf(
						/* translators: %d: SEO score out of 100 */
						__( 'SEO Score: %d/100', 'beplus-metadata-ai-analyzer' ),
						$score
					),
				),
			)
		);

		// Sub-nodes: breakdown hints.
		$meta_title = get_post_meta( $post_id, '_sso_meta_title', true );
		$meta_desc  = get_post_meta( $post_id, '_sso_meta_description', true );
		$focus_kw   = get_post_meta( $post_id, '_sso_focus_keyword', true );
		$edit_link  = esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit#sso-meta-box' ) );

		$wp_admin_bar->add_node(
			array(
				'parent' => 'sso-seo-score',
				'id'     => 'sso-score-title',
				'title'  => $meta_title
					? '&#x2705; ' . __( 'Meta Title set', 'beplus-metadata-ai-analyzer' )
					: '&#x274C; ' . __( 'Meta Title missing', 'beplus-metadata-ai-analyzer' ),
				'href'   => $edit_link,
			)
		);
		$wp_admin_bar->add_node(
			array(
				'parent' => 'sso-seo-score',
				'id'     => 'sso-score-desc',
				'title'  => $meta_desc
					? '&#x2705; ' . __( 'Meta Description set', 'beplus-metadata-ai-analyzer' )
					: '&#x274C; ' . __( 'Meta Description missing', 'beplus-metadata-ai-analyzer' ),
				'href'   => $edit_link,
			)
		);
		$wp_admin_bar->add_node(
			array(
				'parent' => 'sso-seo-score',
				'id'     => 'sso-score-kw',
				'title'  => $focus_kw
					? '&#x2705; ' . __( 'Focus Keyword set', 'beplus-metadata-ai-analyzer' )
					: '&#x274C; ' . __( 'Focus Keyword missing', 'beplus-metadata-ai-analyzer' ),
				'href'   => $edit_link,
			)
		);
		$wp_admin_bar->add_node(
			array(
				'parent' => 'sso-seo-score',
				'id'     => 'sso-score-recalc',
				'title'  => '&#x21BB; ' . __( 'Recalculate Score', 'beplus-metadata-ai-analyzer' ),
				'href'   => esc_url(
					add_query_arg(
						array(
							'sso_recalc' => $post_id,
							'sso_nonce'  => wp_create_nonce( 'sso_recalc_' . $post_id ),
						),
						admin_url( 'post.php?post=' . $post_id . '&action=edit' )
					)
				),
			)
		);

		// Sub-node: "⚙ SEO Settings" → post's own SEO meta box.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'sso-seo-score',
				'id'     => 'sso-settings-link',
				'title'  => '&#9881; ' . __( 'SEO Settings', 'beplus-metadata-ai-analyzer' ),
				'href'   => $edit_link,
				'meta'   => array( 'title' => __( 'Edit SEO settings for this post', 'beplus-metadata-ai-analyzer' ) ),
			)
		);
	}

	/**
	 * Handle the "Recalculate Score" link from the admin bar.
	 * Verifies nonce + capability, recalculates, then redirects back.
	 */
	public function handle_recalc_request() {
		if ( ! isset( $_GET['sso_recalc'], $_GET['sso_nonce'] ) ) {
			return;
		}
		$post_id = absint( $_GET['sso_recalc'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['sso_nonce'] ) ), 'sso_recalc_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		self::calculate_score( $post_id );
		wp_safe_redirect( remove_query_arg( array( 'sso_recalc', 'sso_nonce' ) ) );
		exit;
	}

	/**
	 * Output minimal inline CSS for the admin bar SEO Score node.
	 * Hooked to both wp_head and admin_head.
	 */
	public function admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$css = '#wpadminbar #wp-admin-bar-sso-seo-score > .ab-item { padding-top: 3px; }
			#wpadminbar #wp-admin-bar-sso-seo-score .ab-sub-wrapper { min-width: 200px; }';
		wp_add_inline_style( 'admin-bar', $css );
	}
}
