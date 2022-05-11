<?php

class Mspecs_Store {
    public static function init(){
        
    }

    /**
     * get_organization
     *
     * @param string $mspecs_id
     * @return WP_Post|false
     */
    public static function get_organization(){
        $args = array(
            'orderby' => 'date',
            'post_type' => 'mspecs_organization',
        );

        $organizations = self::get_posts(apply_filters('mspecs_get_organization_args', $args));
        $organization = count($organizations) >= 1 ? $organizations[0] : false;


        return apply_filters('mspecs_get_organization', $organization);
    }

    /**
     * get_user
     *
     * @param string $mspecs_id
     * @return WP_Post|false
     */
    public static function get_user($mspecs_id){
        if(empty($mspecs_id)){
            return false;
        }

        $users = mspecs_get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'mspecs_id',
                    'value' => $mspecs_id,
                ),
            ),
            'post_status' => get_post_stati(),
        ));

        if(count($users) > 0){
            return $users[0];
        }else{
            return false;
        }
    }
    
    /**
     * get_users
     *
     * @see get_posts
     *
     * @param array $args
     * 
     * @return WP_Post[]|int[] Array of post objects or post IDs.
     */
    public static function get_users($args = array()){
        $args = wp_parse_args($args, array(
            'orderby' => 'title',
            'order' => 'ASC',
            'post_type' => 'mspecs_user',
        ));

        $users = self::get_posts(apply_filters('mspecs_get_users_args', $args));

        return apply_filters('mspecs_get_users', $users);
    }

    /**
     * get_office
     *
     * @param string $mspecs_id
     * @return WP_Post|false
     */
    public static function get_office($mspecs_id){
        if(empty($mspecs_id)){
            return false;
        }

        $offices = mspecs_get_offices(array(
            'meta_query' => array(
                array(
                    'key' => 'mspecs_id',
                    'value' => $mspecs_id,
                ),
            ),
            'post_status' => get_post_stati(),
        ));

        if(count($offices) > 0){
            return $offices[0];
        }else{
            return false;
        }
    }
    
    /**
     * get_offices
     *
     * @see get_posts
     *
     * @param array $args
     * 
     * @return WP_Post[]|int[] Array of post objects or post IDs.
     */
    public static function get_offices($args = array()){
        $args = wp_parse_args($args, array(
            'orderby' => 'title',
            'order' => 'ASC',
            'post_type' => 'mspecs_office',
        ));

        $offices = self::get_posts(apply_filters('mspecs_get_offices_args', $args));

        return apply_filters('mspecs_get_offices', $offices);
    }

    /**
     * get_deal
     *
     * @see mspecs_get_deal
     * 
     */
    public static function get_deal($mspecs_id){
        if(empty($mspecs_id)){
            return false;
        }

        $deals = mspecs_get_deals(array(
            'meta_query' => array(
                array(
                    'key' => 'mspecs_id',
                    'value' => $mspecs_id,
                ),
            ),
            'post_status' => get_post_stati(),
        ));

        if(count($deals) > 0){
            return $deals[0];
        }else{
            return false;
        }
    }
    
    /**
     * get_deals
     *
     * @see gmspecs_get_deals
     * 
     */
    public static function get_deals($args = array()){
        $args = wp_parse_args($args, array(
            'orderby' => 'date',
            'order' => 'DESC',
            'post_type' => 'mspecs_deal',
        ));

        $deals = self::get_posts(apply_filters('mspecs_get_deals_args', $args));

        return apply_filters('mspecs_get_deals', $deals);
    }

    public static function get_posts($args){
        $args = wp_parse_args($args, array(
            'numberposts' => -1,
            'suppress_filters' => false,
        ));

        $posts = get_posts(apply_filters('mspecs_get_posts_args', $args));

        return apply_filters('mspecs_get_posts', $posts);
    }

    public static function insert_post($args){
        $args = apply_filters('mspecs_insert_post_args', $args);

        return wp_insert_post($args, true);
    }

    public static function get_unique_meta_values($meta_key){
        global $wpdb;

        $sql = $wpdb->prepare("SELECT DISTINCT meta_value FROM $wpdb->postmeta pm, $wpdb->posts p WHERE meta_key = %s and pm.post_id=p.ID  and p.post_type='mspecs_deal'", $meta_key);
        $values = $wpdb->get_results($sql, ARRAY_A);

        $values = array_map(function($value){
            return maybe_unserialize($value['meta_value']);
        }, $values);

        return $values;
    }
}