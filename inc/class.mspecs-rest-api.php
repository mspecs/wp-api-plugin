<?php

class Mspecs_Rest_Api {
    public static function init(){
        add_action('rest_api_init', function(){
            register_rest_route('mspecs', '/deal/', array(
                'methods' => 'GET',
                'callback' => array('Mspecs_Rest_Api', 'handle_redirect'),
            ));
        });
    }

    public static function handle_redirect(){
        $token = $_GET['token'];
        $token_parts = explode('.', $token);
        $payload = $token_parts[1];
        $payload = base64_decode($payload);

        $payload = json_decode($payload, true);

        if(!$payload){
            echo 'Error decoding payload: '.json_last_error_msg();
            return;
        }

        do_action( 'redirect_to_deal', $payload);
    }
}
