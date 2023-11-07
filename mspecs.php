<?php
/*
Plugin Name: Mspecs Mäklarsystem - API
Plugin URI: https://www.mspecs.se/
Description:
Author: Mspecs
Version: 1.0.0
*/

if (!defined('MSPECS_DEAL_CPT')) {
	define('MSPECS_DEAL_CPT', 'mspecs_deal');
}
if (!defined('MSPECS_ORG_CPT')) {
	define('MSPECS_ORG_CPT', 'mspecs_organization');
}
if (!defined('MSPECS_USER_CPT')) {
	define('MSPECS_USER_CPT', 'mspecs_user');
}
if (!defined('MSPECS_OFFICE_CPT')) {
	define('MSPECS_OFFICE_CPT', 'mspecs_office');
}

define( 'MSPECS_PLUGIN_FILE', __FILE__ );
define( 'MSPECS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
//For local development add_filter('https_ssl_verify', '__return_false');

//Unallowed filetypes couse issues with builtiin WP functions, add custom filetypes here.
function custom_upload_mimes ( $mimes ) {
    $mimes['kml'] = 'text/xml';
    return $mimes;
}
add_filter('upload_mimes', 'custom_upload_mimes');

require_once( MSPECS_PLUGIN_DIR . 'inc/helpers.php' );

require_once( MSPECS_PLUGIN_DIR . 'vendor/wp-background-processing/wp-background-processing.php' );

require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-admin.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-store.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-syncer.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-sync-manager.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-webhook.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-error-handler.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-rest-api.php' );
require_once( MSPECS_PLUGIN_DIR . 'public-api.php' );

add_action( 'init', array( 'Mspecs', 'init' ) );
add_action( 'plugins_loaded', array( 'Mspecs', 'plugins_loaded' ) );

function mspecs_test(){
    mspecs_log('Test');
}
