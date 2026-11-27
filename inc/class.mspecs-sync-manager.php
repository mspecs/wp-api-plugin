<?php

class Mspecs_Sync_Manager {
    public static function init(){
        add_action('update_option_mspecs_settings', array('Mspecs_Sync_Manager', 'maybe_run_initial_sync'));
    }

    public static function maybe_run_initial_sync(){
        $has_subscriber_details = get_option('mspecs_has_subscriber_details', 0);

        if(!$has_subscriber_details){
            self::download_subscriber_details(false);
        }

        $has_webhook_secret = !empty(trim(mspecs_settings('api_secret')));
        if(!$has_webhook_secret){
            self::set_webhook_secret(false);
        }

        self::dispatch();
    }

    public static function full_resync(){
        // Client initialization can trigger a settings hook that dispatches the queue.
        // Finish fetching both lists before adding any destructive work to it.
        $deal_ids = self::get_all_deal_ids();
        if(is_wp_error($deal_ids)) return $deal_ids;

        self::download_subscriber_details(false);
        self::delete_all_deals(false);
        foreach($deal_ids as $deal_id){
            self::sync_deal($deal_id, false);
        }

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
        $deal_ids = self::get_all_deal_ids();
        if(is_wp_error($deal_ids)) return $deal_ids;

        foreach($deal_ids as $deal_id){
            self::sync_deal($deal_id, false);
        }

        if($dispatch) self::dispatch();
    }

    private static function get_all_deal_ids(){
        $api_client = Mspecs::get_api_client();
        $deals = $api_client->get_all_deals();

        if(is_wp_error($deals)){
            mspecs_log($deals);
            Mspecs_Error_Handler::set_latest_error($deals->get_error_message());

            return $deals;
        }

        $deal_ids = array_column($deals, 'id');
        unset($deals);
        $sold_deal_ids = $api_client->get_all_sold_deal_ids();

        if(is_wp_error($sold_deal_ids)){
            mspecs_log($sold_deal_ids);
            Mspecs_Error_Handler::set_latest_error($sold_deal_ids->get_error_message());

            return $sold_deal_ids;
        }

        return array_unique(array_merge($deal_ids, $sold_deal_ids));
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

    public static function set_webhook_secret($dispatch = true){
        Mspecs::$syncer->push_to_queue(array(
            'action' => 'set_webhook_secret',
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
