<?php

$GLOBALS['sso_test'] = array(
	'user_id'       => 7,
	'can_edit'      => true,
	'nonce_checked' => false,
	'posts_calls'   => 0,
	'cache'         => array(),
	'now'           => 1000,
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
function current_user_can() { return $GLOBALS['sso_test']['can_edit']; }
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
function get_posts() {
	++$GLOBALS['sso_test']['posts_calls'];
	return array( (object) array( 'ID' => 42, 'post_title' => 'Alpha', 'post_type' => 'post' ) );
}
function get_post_type_object() { return (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ) ); }
function wp_cache_get( $key, $group, $force = false, &$found = null ) {
	$full  = $group . ':' . $key;
	$found = isset( $GLOBALS['sso_test']['cache'][ $full ] ) && $GLOBALS['sso_test']['cache'][ $full ]['expires'] > $GLOBALS['sso_test']['now'];
	return $found ? $GLOBALS['sso_test']['cache'][ $full ]['value'] : false;
}
function wp_cache_set( $key, $value, $group, $ttl ) {
	$GLOBALS['sso_test']['cache'][ $group . ':' . $key ] = array( 'value' => $value, 'expires' => $GLOBALS['sso_test']['now'] + $ttl );
	return true;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once dirname( __DIR__ ) . '/includes/class-sso-schema-cpt.php';
