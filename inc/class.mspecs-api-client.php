<?php

class Mspecs_Api_Client {
    private $token;
    private $tokenPrefix;
    private $url;
    private $subscriberId;

    public static function init(){
        $subscriberId = mspecs_settings('api_subscriber');

        // Automatically set subscriber ID if missing
        if(empty($subscriberId)){
            $client = new Mspecs_Api_Client(
                mspecs_settings('api_username'),
                mspecs_settings('api_password'),
                mspecs_settings('api_accessToken'),
                '',
                mspecs_settings('api_domain')
            );

            $subscriberId = mspecs_get($client->get_all_subscribers(), '0.subscriberId', '');

            if(!empty($subscriberId)){
                mspecs_update_setting('api_subscriber', $subscriberId);
            }
        }

        $client = new Mspecs_Api_Client(
            mspecs_settings('api_username'),
            mspecs_settings('api_password'),
            mspecs_settings('api_accessToken'),
            $subscriberId,
            mspecs_settings('api_domain')
        );

        return $client;
    }

    public function __construct($username, $password, $token, $subscriberId, $domain){
        $this->url = 'https://'.rtrim(trim($domain), '').'/api/';

        if(empty($token)) {
            $this->token = base64_encode($username.':'.$password);
            $this->tokenPrefix = 'Basic';
        } else {
            $this->token = $token;
            $this->tokenPrefix = 'Bearer';
        }

        $this->subscriberId = $subscriberId;
    }
    
    /**
     * request
     *
     * @param  string $method
     * @param  string $path
     * @param  array $data
     * @return array|WP_Error
     */
    public function request($method, $path, $data = null){
        $url = $this->url.$path;
        $args = array(
            'headers' => array(
                'Authorization' => $this->tokenPrefix.' '.$this->token,
                'Content-Type' => 'application/json',
                'subscriber-id' => $this->subscriberId,
            ),
            'method' => $method
        );

        if(!is_null($data)){
            $args['body'] = json_encode($data);
        }

        /**
         * Filter the URL used to make the Mspecs API request
         */
        $url = apply_filters('mspecs_api_request_url', $url, $args, $method, $path, $data);

        /**
         * Filter the arguments used to make the Mspecs API request
         */
        $args = apply_filters('mspecs_api_request_args', $args, $url, $method, $path, $data);

        $response = wp_remote_request($url, $args);

        if(is_wp_error($response)){
            $response->add('mspecs_network_error', 'Network error');
            return $response;
        }

        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);
        $status = wp_remote_retrieve_response_code($response);

        if(is_null($body)){
            $error = new WP_Error('mspecs_api_parse_error', 'Invalid response from API', array(
                'raw_body' => $raw_body,
                'status' => $status,
                'url' => $url,
                'data' => $data,
            ));

            return $error;
        }

        // Parse Mspecs error response
        if($status >= 400){
            $error = $this->format_response_error($body, $status, $url, $data);

            return $error;
        }

        return $body;
    }
    
    /**
     * format_response_error
     *
     * @param  array $body
     * @param  int $status
     * @return WP_Error
     */
    public function format_response_error($body, $status, $url, $data){
        $message = 'Unknown error format';
        if(isset($body['message'])){
            $message = $body['message'];
        }elseif(isset($body[0]) && isset($body[0]['message'])){
            $message = $body[0]['message'];
        }

        return new WP_Error('mspecs_api_error', $message, array(
            'body' => $body,
            'status' => $status,
            'url' => $url,
            'data' => $data,
        ));
    }

    public function get_all_subscribers(){
        return $this->request('GET', 'provider/subscribers');
    }

    public function get_subscriber_details(){
        return $this->request('GET', 'provider/subscriber');
    }
    
    /**
     * get_deal
     *
     * @param  mixed $id
     * @return array|WP_Error
     */
    public function get_deal($id){
        return $this->request('GET', 'marketing/deals/'.rawurlencode($id));
    }
    
    /**
     * get_all_deals
     *
     * @return array|WP_Error
     */
    public function get_all_deals(){
        return $this->request('GET', 'marketing/deals');
    }

    public function get_all_services(){
        return $this->request('GET', 'provider/services');
    }

    public function create_service($data){
        return $this->request('POST', 'provider/services', $data);
    }

    public function update_service($id, $data){
        return $this->request('PUT', 'provider/services/'.rawurlencode($id), $data);
    }

    public function set_deal_status($id, $status){
        return $this->request('PUT', 'marketing/deals/'.rawurlencode($id).'/status', array('status' => $status));
    }
    public function set_deal_status_published($id){
        return $this->set_deal_status($id, 'ENUM_DEAL_MARKETING_PLACES_STATUS_PUBLISHED');
    }
    public function set_deal_status_error($id){
        return $this->set_deal_status($id, 'ENUM_DEAL_MARKETING_PLACES_STATUS_ERROR');
    }

    /*
     *  Post data
     */

    public function add_prospective_buyer($deal_id, $buyer_details){
        return $this->request('POST', 'marketing/deals/'.rawurlencode($deal_id).'/prospectiveBuyer', $buyer_details);
    }
    
    public function add_buyer_to_viewing($deal_id, $viewing_id, $buyer_details){
        return $this->request('POST', 'marketing/deals/'.rawurlencode($deal_id).'/externalViewer/'.rawurlencode($viewing_id), $buyer_details);
    }

    public function add_buyer_to_viewing_slot($deal_id, $viewing_id, $slot_id, $buyer_details){
        return $this->request('POST', 'marketing/deals/'.rawurlencode($deal_id).'/externalViewer/'.rawurlencode($viewing_id).'/slot/'.rawurlencode($slot_id), $buyer_details);
    }

    public function generate_webhook_secret(){
        return $this->request('PUT', 'provider/webhookSecret');
    }
}