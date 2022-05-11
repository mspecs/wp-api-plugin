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
    // $client = Mspecs::get_api_client();
    // mspecs_log($client->get_all_services());

    // mspecs_log(wp_get_schedules());

    // $api_client = Mspecs::get_api_client();
    // mspecs_log(Mspecs_Sync_Manager::sync_deal('MDI5N3wwMDAwMDAwMDAwNHw1OA..'));

    // mspecs_log(mspecs_get_mspecs_meta(mspecs_get_deal('MDI5N3wwMDAwMDAwMDAwNHw1OA..')));

    // mspecs_log(mspecs_get_bids(mspecs_get_deal('MDI5N3wwMDAwMDAwMDAwN3w1OA..')));

    // Mspecs_Sync_Manager::download_subscriber_details();
    // Mspecs_Sync_Manager::sync_deal('MDI5N3wwMDAwMDAwMDAwNHw1OA..');
    // mspecs_log(mspecs_get_deal_files(mspecs_get_deal('MDI5N3wwMDAwMDAwMDAwNHw1OA..')));
    // mspecs_log(mspecs_get_deals_by_office('MDI5N3wwMDAwMDAwMDAwMnw0NA..'));

    // mspecs_log(mspecs_get_offices(), mspecs_get_users(), mspecs_get_organization(), mspecs_get_deals());

    // mspecs_log(Mspecs_Webhook::get_webhook_url());

    // mspecs_log(mspecs_get_deal_images(mspecs_get_deal('MDI5N3wwMDAwMDAwMDAwMXw1OA..')));
}