<?php

class Mspecs_Sync_Manager {
    public static function init(){
        add_action('update_option_mspecs_settings', array('Mspecs_Sync_Manager', 'maybe_run_initial_sync'));
    }

    public static function maybe_run_initial_sync(){
        $has_subscriber_details = get_option('mspecs_has_subscriber_details', 0);

        if(!$has_subscriber_details){
            self::download_subscriber_details();
        }
    }

    public static function full_resync(){
        self::download_subscriber_details(false);
        self::delete_all_deals(false);
        self::sync_all_deals(false);

        self::dispatch();
    }

    public static function delete_all_deals($dispatch = true){
        $deals = mspecs_get_deals(array(
            'post_status' => get_post_stati(),
        ));

        foreach($deals as $deal){
            self::delete_deal(mspecs_get_deal_meta('mspecs_id', $deal), false);
        }

        if($dispatch) self::dispatch();
    }

    public static function delete_deal($deal_id, $dispatch = true){
        Mspecs::$syncer->push_to_queue(array(
            'action' => 'delete_deal',
            'deal' => $deal_id,
        ));
        
        if($dispatch) self::dispatch();
    }

    public static function sync_all_deals($dispatch = true){
        $api_client = Mspecs::get_api_client();
        $deals = $api_client->get_all_deals();

        if(is_wp_error($deals)){
            mspecs_log($deals);
            Mspecs_Error_Handler::set_latest_error($deals->get_error_message());

            return $deals;
        }

        foreach($deals as $deal){
            self::sync_deal($deal['id'], false);
        }

        if($dispatch) self::dispatch();
    }

    public static function sync_deal($deal_id, $dispatch = true){
        Mspecs::$syncer->push_to_queue(array(
            'action' => 'download_deal',
            'deal' => $deal_id,
        ));
        
        if($dispatch) self::dispatch();
    }

    public static function download_subscriber_details($dispatch = true){
        Mspecs::$syncer->push_to_queue(array(
            'action' => 'download_subscriber_details',
        ));
        
        if($dispatch) self::dispatch();
    }

    public static function delete_subscriber_details($dispatch = true){
        Mspecs::$syncer->push_to_queue(array(
            'action' => 'delete_subscriber_details',
        ));
        
        if($dispatch) self::dispatch();
    }

    public static function dispatch(){
        Mspecs::$syncer->save()->dispatch();
    }
}