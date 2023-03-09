<?php
/*
Plugin Name: Mspecs Mäklarsystem - API
Plugin URI: https://www.mspecs.se/
Description: 
Author: Mspecs
Version: 1.0.0
*/

define( 'MSPECS_PLUGIN_FILE', __FILE__ );
define( 'MSPECS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
//For local development add_filter('https_ssl_verify', '__return_false');

require_once( MSPECS_PLUGIN_DIR . 'inc/helpers.php' );

require_once( MSPECS_PLUGIN_DIR . 'vendor/wp-background-processing/wp-background-processing.php' );

require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-admin.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-store.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-syncer.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-sync-manager.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-webhook.php' );
require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-error-handler.php' );
require_once( MSPECS_PLUGIN_DIR . 'public-api.php' );

add_action( 'init', array( 'Mspecs', 'init' ) );
add_action( 'plugins_loaded', array( 'Mspecs', 'plugins_loaded' ) );

function mspecs_test(){
    mspecs_log('Test');
}