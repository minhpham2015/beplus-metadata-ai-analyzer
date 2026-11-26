<?php

$GLOBALS['sso_test'] = array(
	'user_id'       => 7,
	'can_edit'      => true,
	'nonce_checked' => false,
	'posts_calls'   => 0,
	'cache'         => array(),
	'now'           => 1000,
	'posts_result'  => null, // Override for get_posts() (null = default single "Alpha" post).
	'posts_args'    => array(),
	'editable_ids'  => null,
	'meta'          => array(), // [ post_id => [ meta_key => value ] ] for get_post_meta().
	'options'       => array(),
	'filters'       => array(),
	'is_singular'   => false,
	'queried_id'    => 0,
	'actions'       => array(),
	'removed_actions' => array(),
);

class SSO_Test_Json_Response extends RuntimeException {
	public $success;
	public $data;
	public $status;
	public function __construct( $success, $data, $status = 200 ) {
		parent::__construct();
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}

function add_action( $hook, $callback = null ) { $GLOBALS['sso_test']['actions'][] = array( $hook, $callback ); }
function remove_action( $hook, $callback = null ) { $GLOBALS['sso_test']['removed_actions'][] = array( $hook, $callback ); }
function add_filter() {}
function __( $text ) { return $text; }
function check_ajax_referer() { $GLOBALS['sso_test']['nonce_checked'] = true; }
function current_user_can( $capability = '', $object_id = null ) {
	if ( 'edit_post' === $capability && null !== $GLOBALS['sso_test']['editable_ids'] ) {
		return in_array( (int) $object_id, $GLOBALS['sso_test']['editable_ids'], true );
	}
	return $GLOBALS['sso_test']['can_edit'];
}
function get_current_user_id() { return $GLOBALS['sso_test']['user_id']; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_send_json_success( $data = array(), $status = 200 ) { throw new SSO_Test_Json_Response( true, $data, $status ); }
function wp_send_json_error( $data = array(), $status = 400 ) { throw new SSO_Test_Json_Response( false, $data, $status ); }
function get_post_types() {
	return array(
		'post' => (object) array( 'name' => 'post' ),
		'page' => (object) array( 'name' => 'page' ),
	);
}
function get_posts( $args = array() ) {
	++$GLOBALS['sso_test']['posts_calls'];
	$GLOBALS['sso_test']['posts_args'][] = $args;
	if ( null !== $GLOBALS['sso_test']['posts_result'] ) {
		$posts    = $GLOBALS['sso_test']['posts_result'];
		$per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : count( $posts );
		$page     = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
		return array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
	}
	return array( (object) array( 'ID' => 42, 'post_title' => 'Alpha', 'post_type' => 'post' ) );
}
function get_post_type_object() { return (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ); }
function get_transient( $key ) {
	$entry = $GLOBALS['sso_test']['cache'][ $key ] ?? null;
	return ( $entry && $entry['expires'] > $GLOBALS['sso_test']['now'] ) ? $entry['value'] : false;
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['sso_test']['cache'][ $key ] = array( 'value' => $value, 'expires' => $GLOBALS['sso_test']['now'] + $ttl );
	return true;
}
function get_post_meta( $post_id, $key ) { return $GLOBALS['sso_test']['meta'][ $post_id ][ $key ] ?? ''; }
function get_post_type() { return 'post'; }
function esc_url_raw( $value ) { return trim( $value ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function get_option( $key, $default = false ) { return $GLOBALS['sso_test']['options'][ $key ] ?? $default; }
function get_bloginfo( $show = '' ) { return 'Test Site'; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function apply_filters( $hook, $value ) { return $GLOBALS['sso_test']['filters'][ $hook ] ?? $value; }
function is_admin() { return false; }
function is_singular() { return $GLOBALS['sso_test']['is_singular']; }
function get_queried_object_id() { return $GLOBALS['sso_test']['queried_id']; }
function get_query_var( $key, $default = '' ) { return $default; }
function current_theme_supports( $feature ) { return false; }
function get_the_title( $post_id ) { return 'Test Article'; }
function get_post_field( $field, $post_id ) { return 3; }
function get_the_author_meta( $field, $author_id ) { return 'Test Author'; }
function get_the_date( $format, $post_id ) { return '2026-01-01T00:00:00+00:00'; }
function get_the_modified_date( $format, $post_id ) { return '2026-01-02T00:00:00+00:00'; }
function get_author_posts_url( $author_id ) { return 'https://example.test/author/test'; }
function get_permalink( $post_id ) { return 'https://example.test/test-article/'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function has_post_thumbnail( $post_id ) { return false; }

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
require_once dirname( __DIR__ ) . '/includes/class-sso-schema-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-compat.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-sitemap.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-meta-box.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-canonical.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-opengraph.php';
