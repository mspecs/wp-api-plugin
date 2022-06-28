<?php

class Mspecs_Admin {
    public static function init() {
        add_action('admin_init', array('Mspecs_Admin', 'init_settings'));
        add_action('admin_menu', array('Mspecs_Admin', 'admin_menu'));
        add_action('admin_enqueue_scripts', array('Mspecs_Admin', 'enqueue_scripts'));
        add_action('wp_ajax_mspecs_admin_action', array('Mspecs_Admin', 'mspecs_admin_action_request'));

        add_action('add_meta_boxes', array('Mspecs_Admin', 'add_meta_boxes'));

        add_action('admin_notices', array('Mspecs_Admin', 'admin_notices'));
	}

    public static function admin_notices(){
        $screen = get_current_screen();

        if($screen->id === 'settings_page_mspecs'){
            Mspecs_Error_Handler::maybe_display_admin_notices();
        }
    }

    public static function enqueue_scripts($hook){
        if(
            $hook === 'settings_page_mspecs'
            || ($hook === 'post.php' && in_array(get_post_type(), array(
                'mspecs_deal',
                'mspecs_organization',
                'mspecs_office',
                'mspecs_user',
            )))
        ){
            // TODO: Minimize resources
            wp_enqueue_script('mspecs-admin-js', plugins_url('resources/mspecs-admin.js', MSPECS_PLUGIN_FILE), array('jquery'));
            wp_enqueue_style('mspecs-admin-css', plugins_url('resources/mspecs-admin.css', MSPECS_PLUGIN_FILE));

            wp_localize_script('mspecs-admin-js', 'mspecs_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
            ));
        }
    }

    /*
     *  Settings
     */

    public static function init_settings(){
        register_setting('mspecs', 'mspecs_settings', array(
            'default' => array(
                'api_username' => '',
                'api_password' => '',
                'api_subscriber' => '',
                'api_domain' => 'test-integration.mspecs.se', // TODO: Change to production domain
            )
        ));

        add_settings_section('mspecs_api_settings', __('API Settings', 'mspecs'), array('Mspecs_Admin', 'api_settings_section_callback'), 'mspecs');

        add_settings_field('mspecs_api_username', __('Username', 'mspecs'), array('Mspecs_Admin', 'api_username_callback'), 'mspecs', 'mspecs_api_settings');
        add_settings_field('mspecs_api_password', __('Password', 'mspecs'), array('Mspecs_Admin', 'api_password_callback'), 'mspecs', 'mspecs_api_settings');
        add_settings_field('mspecs_api_subscriber', __('Subscriber ID', 'mspecs'), array('Mspecs_Admin', 'api_subscriber_callback'), 'mspecs', 'mspecs_api_settings');
        add_settings_field('mspecs_api_domain', __('Domain', 'mspecs'), array('Mspecs_Admin', 'api_domain_callback'), 'mspecs', 'mspecs_api_settings');
    }

    public static function admin_menu(){
        add_options_page('Mspecs', 'Mspecs', mspecs_admin_capability(), 'mspecs', array('Mspecs_Admin', 'settings_page'));
    }

    public static function settings_page(){
        include(MSPECS_PLUGIN_DIR . 'views/admin-settings.php');
    }

    public static function api_settings_section_callback(){
        echo '<p>TODO: instructions</p>';
    }

    public static function api_username_callback(){
        self::display_settings_field('api_username');
    }
    public static function api_password_callback(){
        self::display_settings_field('api_password'); // TODO: Change to password field
    }
    public static function api_subscriber_callback(){
        self::display_settings_field('api_subscriber');
    }
    public static function api_domain_callback(){
        self::display_settings_field('api_domain');
    }

    private static function display_settings_field($key, $type = 'text'){
        $settings = get_option('mspecs_settings');
        $value = isset($settings[$key]) ? $settings[$key] : '';
        $full_key = 'mspecs_settings[' . $key . ']';
        ?>
        <input class="regular-text" type="<?= esc_attr($type) ?>" name="<?= esc_attr($full_key) ?>" value="<?= esc_attr( $value ) ?>">
        <?php
    }

    /*
     *  Actions
     */
    public static function get_admin_actions(){
        $actions = array(
            'full_sync' => array(
                'label' => __('Run a full sync', 'mspecs'),
                'callback' => array('Mspecs_Sync_Manager', 'sync_all_deals')
            ),
            'full_resync' => array(
                'label' => __('Delete all data and run sync', 'mspecs'),
                'callback' => array('Mspecs_Sync_Manager', 'full_resync')
            ),
            'delete_all' => array(
                'label' => __('Delete all deals from website', 'mspecs'),
                'callback' => array('Mspecs_Sync_Manager', 'delete_all_deals')
            ),
            'subscriber' => array(
                'label' => __('Sync subscriber details', 'mspecs'),
                'callback' => array('Mspecs_Sync_Manager', 'download_subscriber_details')
            ),
            'delete_subscriber' => array(
                'label' => __('Delete subscriber details', 'mspecs'),
                'callback' => array('Mspecs_Sync_Manager', 'delete_subscriber_details')
            ),
            'test' => array(
                'label' => __('Test', 'mspecs'),
                'callback' => 'mspecs_test',
            ),
        );

        return $actions;
    }

    public static function mspecs_admin_action_request(){
        $action = isset($_POST['actionId']) ? $_POST['actionId'] : false;

        if(
            !current_user_can(mspecs_admin_capability())
            || !$action
            || !wp_verify_nonce($_POST['nonce'], 'mspecs-action-'.$action)
        ){
            wp_send_json_error(null, 403);
            wp_die();
        }

        $actions = self::get_admin_actions();
        if(!isset($actions[$action])){
            wp_send_json_error(null, 400);
            wp_die();
        }

        $response = call_user_func($actions[$action]['callback']);

        if(is_wp_error($response)){
            wp_send_json_error($response, 500);
        }else{
            wp_send_json_success($response, 200);
        }
        wp_die();
    }

    /*
     * Post screen
     */
    public static function add_meta_boxes(){
        add_meta_box( 'mspecs-post-meta', __('Mspecs data', 'mspecs'), array('Mspecs_Admin', 'display_post_meta_box'), array(
            'mspecs_deal',
            'mspecs_organization',
            'mspecs_office',
            'mspecs_user',
        ) );
    }
    public static function display_post_meta_box(){
        include(MSPECS_PLUGIN_DIR . 'views/admin-post-meta.php');
    }
}