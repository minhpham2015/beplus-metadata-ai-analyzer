<?php
/**
 * "Schemas" custom post type: lets an admin define a reusable Schema.org
 * JSON-LD payload and attach it either to specific posts/pages or to every
 * post of a given post type — replacing the old Settings-tab tiers
 * (Schema per Post Type / by Page Template / by Category-Tag).
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Schema_CPT
 */
class SSO_Schema_CPT {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Schema_CPT|null
	 */
	private static $instance = null;

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'sso_schema';

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Schema_CPT
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Register the "Schemas" custom post type. Admin-only (no public archive/
	 * single template — these entries are never visited directly, only read
	 * by SSO_Schema when deciding what to output on the target page).
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Schemas', 'beplus-metadata-ai-analyzer' ),
					'singular_name'      => __( 'Schema', 'beplus-metadata-ai-analyzer' ),
					'add_new'            => __( 'Add New Schema', 'beplus-metadata-ai-analyzer' ),
					'add_new_item'       => __( 'Add New Schema', 'beplus-metadata-ai-analyzer' ),
					'edit_item'          => __( 'Edit Schema', 'beplus-metadata-ai-analyzer' ),
					'new_item'           => __( 'New Schema', 'beplus-metadata-ai-analyzer' ),
					'view_item'          => __( 'View Schema', 'beplus-metadata-ai-analyzer' ),
					'search_items'       => __( 'Search Schemas', 'beplus-metadata-ai-analyzer' ),
					'not_found'          => __( 'No schemas found.', 'beplus-metadata-ai-analyzer' ),
					'not_found_in_trash' => __( 'No schemas found in Trash.', 'beplus-metadata-ai-analyzer' ),
					'all_items'          => __( 'Schemas', 'beplus-metadata-ai-analyzer' ),
				),
				'public'               => false,
				'show_ui'              => true,
				'show_in_menu'         => 'sso-settings',
				'show_in_admin_bar'    => false,
				'show_in_nav_menus'    => false,
				'show_in_rest'         => false,
				'exclude_from_search'  => true,
				'publicly_queryable'   => false,
				'has_archive'          => false,
				'rewrite'              => false,
				'query_var'            => false,
				'capability_type'      => 'post',
				'map_meta_cap'         => true,
				'hierarchical'         => false,
				'supports'             => array( 'title' ),
				'menu_icon'            => 'dashicons-editor-code',
			)
		);
	}

	/**
	 * Public post types eligible as a schema-assignment target (mirrors
	 * SSO_Meta_Box::get_public_post_types(), attachments excluded, and this
	 * CPT itself excluded so a Schema entry can never target other Schema
	 * entries).
	 *
	 * @return WP_Post_Type[]
	 */
	private function get_target_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'], $post_types[ self::POST_TYPE ] );
		return $post_types;
	}

	/**
	 * Register the meta box for the assignment target + schema type/fields.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'sso_schema_assign',
			__( 'Assign To', 'beplus-metadata-ai-analyzer' ),
			array( $this, 'render_assign_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			'sso_schema_fields',
			__( 'Schema Data', 'beplus-metadata-ai-analyzer' ),
			array( $this, 'render_fields_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue the shared admin JS/CSS (same schema-type conditional field
	 * toggling + FAQ/HowTo repeaters used by the per-post meta box) plus a
	 * small inline script for the assign-target radio toggle.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'sso-admin-style', SSO_PLUGIN_URL . 'admin/css/admin-style.css', array(), SSO_VERSION );
		wp_enqueue_script( 'sso-admin-script', SSO_PLUGIN_URL . 'admin/js/admin-script.js', array( 'jquery' ), SSO_VERSION, true );

		wp_localize_script(
			'sso-admin-script',
			'ssoAdmin',
			array(
				'context' => 'schema-cpt',
				'i18n'    => array(
					'question' => __( 'Question', 'beplus-metadata-ai-analyzer' ),
					'answer'   => __( 'Answer', 'beplus-metadata-ai-analyzer' ),
					'remove'   => __( 'Remove', 'beplus-metadata-ai-analyzer' ),
				),
			)
		);
	}

	/**
	 * Render the "Assign To" side meta box: radio between a specific
	 * post/page picker (multi-select) and a post-type picker.
	 *
	 * @param WP_Post $post Current post object (a sso_schema entry).
	 */
	public function render_assign_box( $post ) {
		wp_nonce_field( 'sso_save_schema_cpt', 'sso_schema_cpt_nonce' );

		$target_mode  = get_post_meta( $post->ID, '_sso_schema_target_mode', true );
		$target_mode  = in_array( $target_mode, array( 'posts', 'post_type', 'site' ), true ) ? $target_mode : 'posts';
		$target_posts = get_post_meta( $post->ID, '_sso_schema_target_posts', true );
		$target_posts = is_array( $target_posts ) ? $target_posts : array();
		$target_type  = get_post_meta( $post->ID, '_sso_schema_target_post_type', true );
		?>
		<p>
			<label>
				<input type="radio" name="sso_schema_target_mode" value="posts" <?php checked( 'posts', $target_mode ); ?> />
				<?php esc_html_e( 'Specific pages/posts', 'beplus-metadata-ai-analyzer' ); ?>
			</label>
		</p>
		<div id="sso-schema-target-posts" style="<?php echo 'posts' === $target_mode ? '' : 'display:none;'; ?>">
			<select id="sso_schema_target_posts_select" name="sso_schema_target_posts[]" multiple="multiple" class="widefat" style="min-height:120px;">
				<?php
				$selected_posts = array();
				if ( $target_posts ) {
					$selected_posts = get_posts(
						array(
							'post__in'       => array_map( 'absint', $target_posts ),
							'post_type'      => array_keys( $this->get_target_post_types() ),
							'post_status'    => 'any',
							'posts_per_page' => -1,
							'orderby'        => 'post__in',
						)
					);
				}
				foreach ( $selected_posts as $selected_post ) :
					?>
					<option value="<?php echo esc_attr( $selected_post->ID ); ?>" selected="selected">
						<?php echo esc_html( $selected_post->post_title . ' (' . get_post_type_object( $selected_post->post_type )->labels->singular_name . ')' ); ?>
					</option>
					<?php
				endforeach;
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Start typing a title to search and add pages or posts.', 'beplus-metadata-ai-analyzer' ); ?></p>
		</div>
		<p>
			<label>
				<input type="radio" name="sso_schema_target_mode" value="post_type" <?php checked( 'post_type', $target_mode ); ?> />
				<?php esc_html_e( 'Every post of a post type', 'beplus-metadata-ai-analyzer' ); ?>
			</label>
		</p>
		<div id="sso-schema-target-post-type" style="<?php echo 'post_type' === $target_mode ? '' : 'display:none;'; ?>">
			<select name="sso_schema_target_post_type" class="widefat">
				<option value=""><?php esc_html_e( '— Select —', 'beplus-metadata-ai-analyzer' ); ?></option>
				<?php foreach ( $this->get_target_post_types() as $post_type ) : ?>
					<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $target_type, $post_type->name ); ?>>
						<?php echo esc_html( $post_type->label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Applies to ALL posts of this type unless a specific-post Schema entry or a per-post override (meta box on the post itself) takes priority.', 'beplus-metadata-ai-analyzer' ); ?></p>
		</div>
		<p>
			<label>
				<input type="radio" name="sso_schema_target_mode" value="site" <?php checked( 'site', $target_mode ); ?> />
				<?php esc_html_e( 'Whole site', 'beplus-metadata-ai-analyzer' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Applies as a site-wide fallback: used only when no specific-post entry and no post-type entry apply to the current page.', 'beplus-metadata-ai-analyzer' ); ?></p>
		</p>
		<script>
		( function ( $ ) {
			$( function () {
				$( 'input[name="sso_schema_target_mode"]' ).on( 'change', function () {
					var mode = $( this ).val();
					$( '#sso-schema-target-posts' ).toggle( 'posts' === mode );
					$( '#sso-schema-target-post-type' ).toggle( 'post_type' === mode );
				} );
				if ( $.fn.select2 ) {
					$( '#sso_schema_target_posts_select' ).select2( {
						width: '100%',
						ajax: {
							url: ajaxurl,
							dataType: 'json',
							delay: 300,
							data: function ( params ) {
								return {
									action: 'sso_search_target_posts',
									q: params.term,
									nonce: '<?php echo esc_js( wp_create_nonce( 'sso_search_target_posts' ) ); ?>',
								};
							},
							processResults: function ( data ) {
								return { results: data.data || [] };
							},
						},
						minimumInputLength: 2,
					} );
				}
			} );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Render the "Schema Data" main meta box: schema-type select + the same
	 * conditional field groups as the per-post meta box (Article/FAQPage/
	 * HowTo/Event/etc.), reading from THIS entry's own post meta.
	 *
	 * @param WP_Post $post Current post object (a sso_schema entry).
	 */
	public function render_fields_box( $post ) {
		// Delegate the actual field markup to the shared renderer so the two
		// contexts (per-post meta box vs. this CPT) never drift apart.
		// $include_auto = false: a Schemas entry IS the source of truth for
		// its own type — there is no "Auto" fallback concept here (unlike
		// the per-post meta box, which can fall back to a matching entry).
		SSO_Meta_Box::instance()->render_schema_fields_only( $post->ID, false );
	}

	/**
	 * Persist the assignment target + delegate schema-field saving to the
	 * shared save routine.
	 *
	 * @param int $post_id Post ID being saved (a sso_schema entry).
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['sso_schema_cpt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sso_schema_cpt_nonce'] ) ), 'sso_save_schema_cpt' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$mode = isset( $_POST['sso_schema_target_mode'] ) ? sanitize_key( wp_unslash( $_POST['sso_schema_target_mode'] ) ) : 'posts';
		$mode = in_array( $mode, array( 'posts', 'post_type', 'site' ), true ) ? $mode : 'posts';
		update_post_meta( $post_id, '_sso_schema_target_mode', $mode );

		if ( 'posts' === $mode ) {
			$ids = isset( $_POST['sso_schema_target_posts'] ) ? array_map( 'absint', (array) $_POST['sso_schema_target_posts'] ) : array();
			$ids = array_values( array_filter( $ids ) );
			if ( $ids ) {
				update_post_meta( $post_id, '_sso_schema_target_posts', $ids );
			} else {
				delete_post_meta( $post_id, '_sso_schema_target_posts' );
			}
			delete_post_meta( $post_id, '_sso_schema_target_post_type' );
		} elseif ( 'post_type' === $mode ) {
			$type = isset( $_POST['sso_schema_target_post_type'] ) ? sanitize_key( wp_unslash( $_POST['sso_schema_target_post_type'] ) ) : '';
			if ( $type ) {
				update_post_meta( $post_id, '_sso_schema_target_post_type', $type );
			} else {
				delete_post_meta( $post_id, '_sso_schema_target_post_type' );
			}
			delete_post_meta( $post_id, '_sso_schema_target_posts' );
		} else {
			// Whole site — no specific-post or post-type target needed.
			delete_post_meta( $post_id, '_sso_schema_target_posts' );
			delete_post_meta( $post_id, '_sso_schema_target_post_type' );
		}

		// Delegate the actual _sso_schema_* field saving to the shared routine
		// (identical $_POST field names/sanitization as the per-post meta box).
		SSO_Meta_Box::instance()->save_schema_fields_only( $post_id );
	}

	/**
	 * List table columns: show the assignment target and schema type at a glance.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['sso_schema_type']   = __( 'Schema Type', 'beplus-metadata-ai-analyzer' );
				$new['sso_schema_target'] = __( 'Assigned To', 'beplus-metadata-ai-analyzer' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'sso_schema_type' === $column ) {
			$type = get_post_meta( $post_id, '_sso_schema_type', true );
			echo esc_html( $type ? $type : '—' );
			return;
		}
		if ( 'sso_schema_target' === $column ) {
			$mode = get_post_meta( $post_id, '_sso_schema_target_mode', true );
			if ( 'site' === $mode ) {
				esc_html_e( 'Whole site', 'beplus-metadata-ai-analyzer' );
			} elseif ( 'post_type' === $mode ) {
				$type_name   = get_post_meta( $post_id, '_sso_schema_target_post_type', true );
				$type_object = $type_name ? get_post_type_object( $type_name ) : null;
				echo esc_html(
					sprintf(
						/* translators: %s: post type label. */
						__( 'All %s', 'beplus-metadata-ai-analyzer' ),
						$type_object ? $type_object->label : $type_name
					)
				);
			} else {
				$ids = get_post_meta( $post_id, '_sso_schema_target_posts', true );
				$ids = is_array( $ids ) ? $ids : array();
				echo esc_html(
					sprintf(
						/* translators: %d: number of assigned posts/pages. */
						_n( '%d page/post', '%d pages/posts', count( $ids ), 'beplus-metadata-ai-analyzer' ),
						count( $ids )
					)
				);
			}
		}
	}

	/**
	 * Resolve the Schema entry (if any) assigned to a given target post,
	 * checking specific-post assignment, then post-type-wide assignment,
	 * then finally the site-wide fallback (most specific wins).
	 *
	 * @param int $post_id Target post ID (the page/post being viewed).
	 * @return int 0 when no Schema entry applies, else the Schema entry's post ID.
	 */
	public static function resolve_schema_entry_id( $post_id ) {
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		// Tier: specific-post assignment.
		$specific = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small internal CPT, no realistic scale concern.
					array(
						'key'     => '_sso_schema_target_posts',
						'value'   => '"' . $post_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);
		if ( $specific ) {
			// Confirm exact ID membership (LIKE above is a fast pre-filter,
			// not authoritative — serialized-array LIKE can false-positive
			// on ID substrings, e.g. searching "12" would also match "112").
			foreach ( $specific as $entry_id ) {
				$ids = get_post_meta( $entry_id, '_sso_schema_target_posts', true );
				if ( is_array( $ids ) && in_array( (int) $post_id, array_map( 'intval', $ids ), true ) ) {
					$cache[ $post_id ] = (int) $entry_id;
					return $cache[ $post_id ];
				}
			}
		}

		// Tier: post-type-wide assignment.
		$post_type = get_post_type( $post_id );
		$by_type   = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small internal CPT.
					array(
						'key'   => '_sso_schema_target_post_type',
						'value' => $post_type,
					),
				),
			)
		);
		if ( $by_type ) {
			$cache[ $post_id ] = (int) $by_type[0];
			return $cache[ $post_id ];
		}

		// Tier: whole-site fallback — used only when nothing more specific matched.
		$site_wide = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small internal CPT.
					array(
						'key'   => '_sso_schema_target_mode',
						'value' => 'site',
					),
				),
			)
		);
		$resolved          = $site_wide ? (int) $site_wide[0] : 0;
		$cache[ $post_id ] = $resolved;
		return $resolved;
	}

	/**
	 * Resolve the site-wide "Whole site" Schemas entry, independent of any
	 * specific post — used as the ultimate fallback on non-singular views
	 * (home, archives, search, 404) where there is no post to check
	 * specific-post/post-type assignment against.
	 *
	 * @return int 0 when no site-wide entry exists, else its post ID.
	 */
	public static function get_site_wide_entry_id() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$site_wide = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small internal CPT.
					array(
						'key'   => '_sso_schema_target_mode',
						'value' => 'site',
					),
				),
			)
		);
		$cache = $site_wide ? (int) $site_wide[0] : 0;
		return $cache;
	}
}
