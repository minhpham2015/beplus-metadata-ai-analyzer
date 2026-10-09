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

function add_action() {}
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

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
require_once dirname( __DIR__ ) . '/includes/class-sso-schema-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-sso-meta-box.php';
