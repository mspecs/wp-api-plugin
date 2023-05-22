<?php

class Mspecs {
    public static $syncer;

    public static function plugins_loaded(){
        self::$syncer = new Mspecs_Syncer();
    }

    public static function init() {
        Mspecs_Admin::init();
        Mspecs_Store::init();
        Mspecs_Sync_Manager::init();
        Mspecs_Webhook::init();
        Mspecs_Error_Handler::init();
        Mspecs_Rest_Api::init();

        self::register_post_types();
    }

    public static function register_post_types(){
        register_post_type('mspecs_deal', apply_filters('mspecs_deal_post_type', array(
            'labels' => array(
                'name' => __('Deals - Mspecs', 'mspecs'),
                'singular_name' => __('Deal', 'mspecs'),
                'add_new' => __('Add New', 'mspecs'),
                'add_new_item' => __('Add New Deal', 'mspecs'),
                'edit_item' => __('Edit Deal', 'mspecs'),
                'new_item' => __('New Deal', 'mspecs'),
                'view_item' => __('View Deal', 'mspecs'),
                'search_items' => __('Search Deals', 'mspecs'),
                'not_found' => __('No deals found', 'mspecs'),
                'not_found_in_trash' => __('No deals found in Trash', 'mspecs'),
                'all_items' => __('All Deals', 'mspecs'),
                'archives' => __('Deal Archives', 'mspecs'),
                'attributes' => __('Deal Attributes', 'mspecs'),
                'filter_items_list' => __('Filter deals list', 'mspecs'),
                'items_list_navigation' => __('Deals list navigation', 'mspecs'),
                'items_list' => __('Deals list', 'mspecs'),
                'item_published' => __('Deal published.', 'mspecs'),
                'item_published_privately' => __('Deal published privately.', 'mspecs'),
                'item_reverted_to_draft' => __('Deal reverted to draft.', 'mspecs'),
                'item_scheduled' => __('Deal scheduled.', 'mspecs'),
                'item_updated' => __('Deal updated.', 'mspecs'),
                'item_link' => __('Deal link', 'mspecs'),
                'item_link_description' => __('A link to a deal.', 'mspecs'),
            ),
            'public' => false,
            'hierarchical' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-admin-home',
            'capability_type' => 'post', // TODO: Change?
            'supports' => array('title', 'editor'),       
        )));

        register_post_type('mspecs_office', apply_filters('mspecs_office_post_type', array(
            'labels' => array(
                'name' => __('Offices - Mspecs', 'mspecs'),
                'singular_name' => __('Office', 'mspecs'),
                'add_new' => __('Add New', 'mspecs'),
                'add_new_item' => __('Add New Office', 'mspecs'),
                'edit_item' => __('Edit Office', 'mspecs'),
                'new_item' => __('New Office', 'mspecs'),
                'view_item' => __('View Office', 'mspecs'),
                'search_items' => __('Search Offices', 'mspecs'),
                'not_found' => __('No offices found', 'mspecs'),
                'not_found_in_trash' => __('No offices found in Trash', 'mspecs'),
                'all_items' => __('All Offices', 'mspecs'),
                'archives' => __('Office Archives', 'mspecs'),
                'attributes' => __('Office Attributes', 'mspecs'),
                'filter_items_list' => __('Filter offices list', 'mspecs'),
                'items_list_navigation' => __('Offices list navigation', 'mspecs'),
                'items_list' => __('Offices list', 'mspecs'),
                'item_published' => __('Office published.', 'mspecs'),
                'item_published_privately' => __('Office published privately.', 'mspecs'),
                'item_reverted_to_draft' => __('Office reverted to draft.', 'mspecs'),
                'item_scheduled' => __('Office scheduled.', 'mspecs'),
                'item_updated' => __('Office updated.', 'mspecs'),
                'item_link' => __('Office link', 'mspecs'),
                'item_link_description' => __('A link to an office.', 'mspecs'),
            ),
            'public' => false,
            'hierarchical' => false,
            'show_ui' => true, // TODO: Switch off
            'menu_icon' => 'dashicons-location',
            'capability_type' => 'post', // TODO: Change?  
            'supports' => array('title'),          
        )));

        register_post_type('mspecs_user', apply_filters('mspecs_user_post_type', array(
            'labels' => array(
                'name' => __('Users - Mspecs', 'mspecs'),
                'singular_name' => __('User', 'mspecs'),
                'add_new' => __('Add New', 'mspecs'),
                'add_new_item' => __('Add New User', 'mspecs'),
                'edit_item' => __('Edit User', 'mspecs'),
                'new_item' => __('New User', 'mspecs'),
                'view_item' => __('View User', 'mspecs'),
                'search_items' => __('Search Users', 'mspecs'),
                'not_found' => __('No users found', 'mspecs'),
                'not_found_in_trash' => __('No users found in Trash', 'mspecs'),
                'all_items' => __('All Users', 'mspecs'),
                'archives' => __('User Archives', 'mspecs'),
                'attributes' => __('User Attributes', 'mspecs'),
                'filter_items_list' => __('Filter users list', 'mspecs'),
                'items_list_navigation' => __('Users list navigation', 'mspecs'),
                'items_list' => __('Users list', 'mspecs'),
                'item_published' => __('User published.', 'mspecs'),
                'item_published_privately' => __('User published privately.', 'mspecs'),
                'item_reverted_to_draft' => __('User reverted to draft.', 'mspecs'),
                'item_scheduled' => __('User scheduled.', 'mspecs'),
                'item_updated' => __('User updated.', 'mspecs'),
                'item_link' => __('User link', 'mspecs'),
                'item_link_description' => __('A link to a user.', 'mspecs'),
            ),
            'public' => false,
            'hierarchical' => false,
            'show_ui' => true, // TODO: Switch off
            'menu_icon' => 'dashicons-id-alt',
            'capability_type' => 'post', // TODO: Change?    
            'supports' => array('title'),        
        )));

        register_post_type('mspecs_organization', apply_filters('mspecs_organization_post_type', array(
            'labels' => array(
                'name' => __('Organizations - Mspecs', 'mspecs'),
                'singular_name' => __('Organization', 'mspecs'),
                'add_new' => __('Add New', 'mspecs'),
                'add_new_item' => __('Add New Organization', 'mspecs'),
                'edit_item' => __('Edit Organization', 'mspecs'),
                'new_item' => __('New Organization', 'mspecs'),
                'view_item' => __('View Organization', 'mspecs'),
                'search_items' => __('Search Organizations', 'mspecs'),
                'not_found' => __('No organizations found', 'mspecs'),
                'not_found_in_trash' => __('No organizations found in Trash', 'mspecs'),
                'all_items' => __('All Organizations', 'mspecs'),
                'archives' => __('Organization Archives', 'mspecs'),
                'attributes' => __('Organization Attributes', 'mspecs'),
                'filter_items_list' => __('Filter organizations list', 'mspecs'),
                'items_list_navigation' => __('Organizations list navigation', 'mspecs'),
                'items_list' => __('Organizations list', 'mspecs'),
                'item_published' => __('Organization published.', 'mspecs'),
                'item_published_privately' => __('Organization published privately.', 'mspecs'),
                'item_reverted_to_draft' => __('Organization reverted to draft.', 'mspecs'),
                'item_scheduled' => __('Organization scheduled.', 'mspecs'),
                'item_updated' => __('Organization updated.', 'mspecs'),
                'item_link' => __('Organization link', 'mspecs'),
                'item_link_description' => __('A link to an organization.', 'mspecs'),
            ),
            'public' => false,
            'hierarchical' => false,
            'show_ui' => true, // TODO: Switch off
            'menu_icon' => 'dashicons-store',
            'capability_type' => 'post', // TODO: Change?
            'supports' => array('title'),         
        )));
    }

    public static function get_api_client(){
        require_once( MSPECS_PLUGIN_DIR . 'inc/class.mspecs-api-client.php' );
        $api_client = Mspecs_Api_Client::init();

        return $api_client;
    }
}
