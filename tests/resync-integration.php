<?php

// Exercise the real client initialization, settings hook, and background queue.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MSPECS_PLUGIN_DIR', dirname(__DIR__) . '/');
define('MSPECS_DEAL_CPT', 'mspecs_deal');
define('DAY_IN_SECONDS', 86400);

$hooks = $options = $saved = $dispatched = array();
$mode = 'sold-error';
function add_action($name, $callback, ...$unused){ global $hooks; $hooks[$name][] = $callback; }
function add_filter(...$args){}
function do_action($name, ...$args){
    global $hooks;
    foreach($hooks[$name] ?? array() as $callback) $callback(...$args);
}
function apply_filters($name, $value, ...$args){ return $value; }
function get_option($key, $default = false){ global $options; return $options[$key] ?? $default; }
function update_option($key, $value){
    global $options;
    $old = $options[$key] ?? null;
    $options[$key] = $value;
    if($old !== $value) do_action('update_option_' . $key, $old, $value, $key);
    return true;
}
function update_site_option($key, $value){ global $saved; $saved[] = $value; return true; }
function wp_next_scheduled($key){ return false; }
function wp_schedule_event(...$args){ return true; }
function wp_create_nonce(...$args){ return 'test-nonce'; }
function admin_url($path){ return 'https://wordpress.invalid/' . $path; }
function add_query_arg($query, $url){ return $url . '?' . http_build_query($query); }
function esc_url_raw($url){ return $url; }
function wp_remote_post($url, $args){ global $dispatched; $dispatched[] = $args['body']; return array(); }
function get_post_stati(){ return array('publish', 'draft'); }
function wp_parse_args($args, $defaults){ return array_merge($defaults, $args); }
function get_posts($args){ return array((object) array('ID' => 1, 'post_type' => MSPECS_DEAL_CPT)); }
function get_post_meta($id, $key, $single){ return $key === 'mspecs_id' ? 'active-1' : ''; }
class WP_Error {
    public function __construct(public $code, public $message, public $data = null){}
    public function get_error_message(){ return $this->message; }
}
function is_wp_error($value){ return $value instanceof WP_Error; }
function wp_remote_retrieve_body($response){ return $response['body']; }
function wp_remote_retrieve_response_code($response){ return $response['status']; }
function wp_remote_request($url, $args){
    global $mode, $saved, $dispatched;
    // No destructive work may be persisted or dispatched while fetching lists.
    foreach(array_merge($saved, $dispatched) as $batch){
        foreach($batch as $item){
            if($item['action'] === 'delete_deal') throw new RuntimeException('Deletion dispatched before all deal lists were fetched');
        }
    }
    $path = parse_url($url, PHP_URL_PATH);
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    $status = 200;
    if($path === '/api/provider/subscribers'){
        $body = array(array('subscriberId' => 'test-subscriber'));
    }elseif($path === '/api/marketing/deals'){
        $body = array(array('id' => 'active-1'));
        if($mode === 'published-error'){
            $status = 503;
            $body = array('message' => 'Published list unavailable');
        }
    }elseif($path === '/api/marketing/sold/deals'){
        $body = array('deals' => array(array('id' => 'sold-1')));
        if($mode === 'sold-error' || ($mode === 'later-page-error' && isset($query['next']))){
            $status = 503;
            $body = array('message' => 'Sold list unavailable');
        }elseif($mode === 'later-page-error'){
            $body['next'] = 'page-2';
        }
    }else{
        throw new RuntimeException('Unexpected request: ' . $url);
    }
    return array('status' => $status, 'body' => json_encode($body));
}

require MSPECS_PLUGIN_DIR . 'inc/helpers.php';
require MSPECS_PLUGIN_DIR . 'vendor/wp-background-processing/classes/wp-async-request.php';
require MSPECS_PLUGIN_DIR . 'vendor/wp-background-processing/classes/wp-background-process.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-api-client.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-syncer.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-sync-manager.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-store.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-error-handler.php';
require MSPECS_PLUGIN_DIR . 'public-api.php';

foreach(array('sold-error', 'later-page-error', 'published-error', 'success') as $mode){
    $hooks = $saved = $dispatched = array();
    $options = array(
        'mspecs_settings' => array(
            'api_username' => 'test-user',
            'api_password' => 'test-password',
            'api_domain' => 'example.invalid',
            'api_secret' => 'already-set',
            // Deliberately omit api_subscriber to trigger the real settings hook.
        ),
        'mspecs_has_subscriber_details' => 1,
    );
    Mspecs::plugins_loaded();
    Mspecs_Sync_Manager::init();
    $result = Mspecs_Sync_Manager::full_resync();
    if(($options['mspecs_settings']['api_subscriber'] ?? '') !== 'test-subscriber'){
        throw new RuntimeException('The subscriber autodetection hook did not run');
    }
    if($mode !== 'success'){
        if(!is_wp_error($result) || $saved !== array()){
            throw new RuntimeException('Failed resync saved destructive work: ' . json_encode($saved));
        }
        foreach($dispatched as $batch){
            if($batch !== array()) throw new RuntimeException('Failed resync dispatched work');
        }
    }else{
        $expected = array(
            array('action' => 'download_subscriber_details'),
            array('action' => 'delete_deal', 'deal' => 'active-1'),
            array('action' => 'download_deal', 'deal' => 'active-1'),
            array('action' => 'download_deal', 'deal' => 'sold-1'),
        );
        if($saved !== array($expected) || end($dispatched) !== $expected){
            throw new RuntimeException('Successful resync did not persist the complete restoration batch');
        }
    }
}
echo 'Real settings-hook and background-queue regression scenarios passed' . PHP_EOL;
