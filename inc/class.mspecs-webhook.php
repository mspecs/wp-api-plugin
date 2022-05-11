<?php

class Mspecs_Webhook {
    public static function init(){
        add_action('wp_ajax_nopriv_mspecs_webhook', array('Mspecs_Webhook', 'handle_webhook'));
    }

    public static function handle_webhook(){
        $data = json_decode(file_get_contents('php://input'), true);
        $subscriberId = mspecs_get($data, 'subscriberId');

        if($subscriberId === mspecs_settings('api_subscriber')){
            $eventType = mspecs_get($data, 'eventType');
            $dealId = mspecs_get($data, 'dealId');
            $deal = $dealId ? mspecs_get_deal($dealId) : false;

            switch($eventType){
                case 'BIDDING':
                    if($deal){
                        update_post_meta($deal->ID, 'bidding', mspecs_get($data, 'eventData.data.bidding'));
                    }
                    break;

                case 'VIEWING':
                    if($deal){
                        update_post_meta($deal->ID, 'viewings', mspecs_get($data, 'eventData.data.viewings'));
                    }
                    break;

                // TODO: Handle UN_PUBLISH?
                
                default:
                    if($dealId){
                        Mspecs_Sync_Manager::sync_deal($dealId);
                    }

                    break;
            }
    
            wp_send_json_success(array());
            wp_die();
        }else{
            wp_send_json_error('Invalid subscriber', 401);
            wp_die();
        }
    }

    public static function get_webhook_url(){
        return apply_filters('mspecs_webhook_url', add_query_arg(array(
            'action' => 'mspecs_webhook',
        ), admin_url( 'admin-ajax.php' )));
    }

    // TODO: Maybe remove installation functions

    public static function get_service_definition(){
        return apply_filters('mspecs_service_definition', array(
            'type' => 'ENUMS_PROVIDER_SERVICE_TYPE_MARKETING',
            'header' => 'Mspecs WordPress Plugin ('.get_bloginfo('name').')',
            'body' => __('Publish to WordPress website', 'mspecs'),
            'publicStatus' => 'ENUMS_PROVIDER_SERVICE_PUBLICATION_STATUS_PRIVATE',
            'publishUrl' => self::get_webhook_url(),
            'clientEndpointPresentation' => 'ENUMS_PROVIDER_SERVICE_CLIENT_ENDPOINT_PRESENTATION_STANDALONE', // TODO
            'clientEndpointUrl' => add_query_arg(array('token' => '{token}'), get_home_url()), // TODO
        ));
    }

    public static function install(){
        $url = self::get_webhook_url();
        $api_client = Mspecs::get_api_client();

        $services = $api_client->get_all_services();

        $service_id = null;

        foreach($services as $services){
            if($services['publishUrl'] === $url){
                $service_id = $services['id'];
                break;
            }
        }

        $data = self::get_service_definition();

        if(!$service_id){
            $api_client->create_service($data);
        }else{
            $api_client->update_service($service_id, $data);
        }
    }
}