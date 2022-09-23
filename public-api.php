<?php

/*
 *  Template functions
 */

/**
 * Get meta data from a Mspecs deal
 *
 * @param  string $meta_key
 * @param  WP_Post|null $deal
 * @return mixed
 */
function mspecs_get_deal_meta($meta_key, $deal = null){
    $deal = mspecs_get_the_deal($deal);
    if(!$deal) return null;

    return get_post_meta($deal->ID, $meta_key, true);
}

/**
 * Get all Mspecs specific meta from a post
 *
 * @param  WP_Post $post
 * @return array
 */
function mspecs_get_mspecs_meta($post){
    $metas = wp_list_pluck(get_post_meta($post->ID), '0');
    $metas = array_map('maybe_unserialize', $metas);
    $mspecs_keys = isset($metas['mspecs_meta_keys']) ? $metas['mspecs_meta_keys'] : array();

    return array_intersect_key($metas, array_flip($mspecs_keys));
}

/**
 * Get an array of deal images, also makes sure the image urls are correct
 *
 * @param  WP_Post|null $deal
 * @return array|null
 */
function mspecs_get_deal_images($deal = null){
    $images = mspecs_get_deal_meta('images', $deal);
    if(!$images) return [];

    // Make sure the image urls are correct
    $sizes = mspecs_image_sizes();
    foreach($images as $i => $image){
        foreach($sizes as $size){
            $path = mspecs_get($image, $size.'Path');
            if(!empty($path)){
                $image[$size . 'Url'] = mspecs_file_dir_url() . '/' . $path;
            }
        }

        $images[$i] = $image;
    }

    return $images;
}

/**
 * Get an array of deal files, also makes sure the file urls are correct
 *
 * @param  WP_Post|null $deal
 * @return array
 */
function mspecs_get_deal_files($deal = null){
    $files = mspecs_get_deal_meta('files', $deal);
    if(!$files) return [];

    // Make sure the file urls are correct
    foreach($files as $i => $file){
        $path = mspecs_get($file, 'path');
        if(!empty($path)){
            $file['url'] = mspecs_file_dir_url() . '/' . $path;
        }

        $files[$i] = $file;
    }

    return $files;
}

/**
 * Get an array of bids on a deal, ordered by ascending date then ascending amount
 *
 * @param  WP_Post|null $deal
 * @return array
 */
function mspecs_get_bids($deal = null){
    $bidding = mspecs_get_deal_meta('bidding', $deal);
    $bids = mspecs_get($bidding, 'bids', array());

    // Generate unix timestamps
    $bids = array_map(function($bid){
        $bid['timestamp'] = strtotime(mspecs_get($bid, 'date'));
        return $bid;
    }, $bids);

    return wp_list_sort($bids, array(
        'timestamp' => 'ASC',
        'amount' => 'ASC',
    ));
}

/**
 * Get an array of viewings on a deal
 *
 * @param  WP_Post|null $deal
 * @return array
 */
function mspecs_get_viewings($deal = null){
    $value = mspecs_get_deal_meta('viewings', $deal);
    return $value ? $value : array();
}

/**
 * Returns the current global $post object if it is a Mspecs Deal
 * If the $deal parameter is passed, it will instead return the WP_Post object if it is an Mspecs Deal or deal ID
 *  
 * @param  WP_Post|string|null $deal
 * 
 * @return WP_Post|null
 */
function mspecs_get_the_deal($deal = null){
    if(is_null($deal)){
        $post = get_post();
        return mspecs_is_deal($post) ? $post : null;
    }else{
        $deal = mspecs_get_deal($deal);
        return $deal === false ? null : $deal;
    }
}

/**
 * Check if a WP_Post is a Mspecs Deal
 *
 * @param  WP_Post $deal
 * @return bool
 */
function mspecs_is_deal($deal){
    return $deal && isset($deal->post_type) && $deal->post_type === 'mspecs_deal';
}

/*
 *  Query functions
 */

/**
 * Get Mspecs Deals by sale status
 *
 * @param  string{'ENUMS_SALE_STATUS_FOR_SALE'|'ENUMS_SALE_STATUS_COMING'|'ENUMS_SALE_STATUS_SOLD'|'ENUMS_SALE_STATUS_RESERVED'} $status
 * @return WP_Post[]
 */
function mspecs_get_deals_by_status($status){
    return mspecs_get_deals_by_meta('saleStatus', $status);
}

 /**
 * Get Mspecs Deals by office
 *
 * @param  string $officeId
 * @return WP_Post[]
 */
function mspecs_get_deals_by_office($office_id){
    return mspecs_get_deals_by_meta('officeId', $office_id);
}

 /**
 * Get Mspecs Deals by object sub type
 *
 * @param  string $type
 * @return WP_Post[]
 */
function mspecs_get_deals_by_sub_type($sub_type){
    return mspecs_get_deals_by_meta('objectSubTypeConstant', $sub_type);
}

/**
 * Get Mspecs Deals by object type
 *
 * @param  string{'OBJECT_TYPE_CONDOMINIUM'|'OBJECT_TYPE_FARMING'|'OBJECT_TYPE_HOUSE'|'OBJECT_TYPE_HOUSING_DEVELOPMENT'|'OBJECT_TYPE_LAND'|'OBJECT_TYPE_PREMISE'|'OBJECT_TYPE_RECREATIONAL_HOUSE'|'OBJECT_TYPE_TENANT_OWNERSHIP'} $type
 * @return WP_Post[]
 */
function mspecs_get_deals_by_type($type){
    return mspecs_get_deals_by_meta('objectTypeConstant', $type);
}

function mspecs_get_deals_by_meta($key, $value){
    return mspecs_get_deals(array(
        'meta_query' => array(
            array(
                'key' => $key,
                'value' => $value,
            ),
        ),
    ));
}

/**
 * Get a deal by its Mspecs ID
 * If a deal is provided instead of an ID, it will simply be returned
 *
 * @param WP_Post|string $mspecs_id
 * @return WP_Post|false
 */
function mspecs_get_deal($mspecs_id){
    if(!is_string($mspecs_id)){
        return mspecs_is_deal($mspecs_id) ? $mspecs_id : false;
    }

    return Mspecs_Store::get_deal($mspecs_id); 
}

/**
 * Query Mspecs deals
 *
 * @see get_posts
 *
 * @param array $args
 * 
 * @return WP_Post[]|int[] Array of post objects or post IDs.
 */
function mspecs_get_deals($args = array()){
    return Mspecs_Store::get_deals($args);
}

/*
 *  Post data functions
 */

 
/**
 * Add a new prospective buyer to a deal. An error will be returned if the buyer is already added.
 *
 * @param  WP_Post|string $deal
 * @param  array $buyer_details
 * @return array|WP_Error
 */
function mspecs_add_prospective_buyer($deal, $buyer_details){
    $deal = mspecs_get_deal($deal);
    if(!$deal) return null;

    return Mspecs::get_api_client()->add_prospective_buyer($deal->ID, $buyer_details);
}

/**
 * Add a buyer to a viewing, the buyer can be a new buyer or an existing one.
 *
 * @param  WP_Post|string $deal
 * @param  string $viewing_id
 * @param  array $buyer_details
 * @return array|WP_Error
 */
function mspecs_add_buyer_to_viewing($deal, $viewing_id, $buyer_details){
    $deal = mspecs_get_deal($deal);
    if(!$deal) return null;

    return Mspecs::get_api_client()->add_buyer_to_viewing($deal->ID, $viewing_id, $buyer_details);
}

/**
 * Add a buyer to a viewing slot, the buyer can be a new buyer or an existing one.
 *
 * @param  WP_Post|string $deal
 * @param  string $viewing_id
 * @param  string $slot_id
 * @param  array $buyer_details
 * @return array|WP_Error
 */
function mspecs_add_buyer_to_viewing_slot($deal, $viewing_id, $slot_id, $buyer_details){
    $deal = mspecs_get_deal($deal);
    if(!$deal) return null;

    return Mspecs::get_api_client()->add_buyer_to_viewing_slot($deal->ID, $viewing_id, $slot_id, $buyer_details);
}

/*
 *  Utility functions
 */

/**
 * Converts Mspecs Short ID to standard Mspecs ID
 *
 * @param  string $mspecs_short_id
 * @return string $mspecs_id
 */
function mspecs_deal_id_from_short_id($mspecs_short_id){
    $deals = mspecs_get_deals_by_meta('shortId', $mspecs_short_id);

    if(count($deals) > 0){
        return mspecs_get_deal_meta('mspecs_id', $deals[0]);
    }else{
        return false;
    }
}

/**
 * Converts WP Post ID to Mspecs ID
 *
 * @param  int $post_id
 * @return string $mspecs_id
 */
function mspecs_deal_id_from_post_id($post_id){
    $post = get_post($post_id);

    if(mspecs_is_deal($post)){
        return mspecs_get_deal_meta('mspecs_id', $post);
    }else{
        return false;
    }
}

/*
 *  Subscriber details
 */

/**
 * Gets the organization data
 *
 * @return WP_Post|false
 */
function mspecs_get_organization(){
    return Mspecs_Store::get_organization();
}

/**
 * Get an office by its Mspecs ID
 *
 * @param  string $mspecs_id
 * @return WP_Post|false
 */
function mspecs_get_office($mspecs_id){
    return Mspecs_Store::get_office($mspecs_id); 
}

/**
 * Query Mspecs offices
 *
 * @see get_posts
 *
 * @param array $args
 * 
 * @return WP_Post[]|int[] Array of post objects or post IDs.
 */
function mspecs_get_offices($args = array()){
    return Mspecs_Store::get_offices($args);
}

/**
 * Get a user by its Mspecs ID
 *
 * @param  string $mspecs_id
 * @return WP_Post|false
 */
function mspecs_get_user($mspecs_id){
    return Mspecs_Store::get_user($mspecs_id); 
}

/**
 * Query Mspecs users
 *
 * @see get_posts
 *
 * @param array $args
 * 
 * @return WP_Post[]|int[] Array of post objects or post IDs.
 */
function mspecs_get_users($args = array()){
    return Mspecs_Store::get_users($args);
}

/*
 *  Configuration functions
 */

function mspecs_admin_capability(){
    /**
     * Filters the capability required to access the Mspecs settings and tools
     */
    return apply_filters('mspecs_admin_capability', 'manage_options');
}

function mspecs_settings($key = null){
    $settings = get_option('mspecs_settings');
    $settings = apply_filters('mspecs_settings', $settings, $key);

    if(!is_null($key)){
        $settings = isset($settings[$key]) ? $settings[$key] : '';
    }

    return $settings;
}

function mspecs_update_setting($key, $value){
    $settings = get_option('mspecs_settings');
    $settings[$key] = $value;

    update_option('mspecs_settings', $settings);
}

// TODO: Evaluate
function mspecs_cache_key($key_object){
    $hash = hash('md5', json_encode($key_object));

    return apply_filters('mspecs_cache_key', 'mspecs_'.$hash, $key_object);
}

function mspecs_file_dir(){
    $upload_dir = wp_upload_dir();
    $image_dir = apply_filters('mspecs_file_dir', $upload_dir['basedir'].'/mspecs');

    if ( !file_exists( $image_dir ) ) {
        wp_mkdir_p( $image_dir );
    }

    return $image_dir;
}

function mspecs_file_dir_url(){
    $upload_dir = wp_upload_dir();
    $image_dir = apply_filters('mspecs_file_dir_url', $upload_dir['baseurl'].'/mspecs');

    return $image_dir;
}

function mspecs_image_sizes(){
    /**
     * Filters images sizes provided by Mspecs
     */
    return apply_filters('mspecs_image_sizes', array('original', 'view', 'thumbnail'));
}

function mspecs_prefixed_post_metas(){
    /**
     * Filters the Mspecs meta keys that may conflict with other WordPress meta keys
     */
    return apply_filters('mspecs_prefixed_post_metas', array('id'));
}