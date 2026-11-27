<?php

// Run with: php tests/run.php. All HTTP, queue, media and storage boundaries are offline.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('MSPECS_PLUGIN_DIR', dirname(__DIR__) . '/');
define('MSPECS_DEAL_CPT', 'mspecs_deal');

class WP_Error {
    public function __construct(public $code, public $message, public $data = null){}
    public function get_error_message(){ return $this->message; }
}

class Mspecs_WP_Background_Process {
    public $queue = array();
    public $dispatches = 0;
    public function __construct(){}
    public function push_to_queue($item){ $this->queue[] = $item; return $this; }
    public function save(){ return $this; }
    public function dispatch(){ $this->dispatches++; return $this; }
}

class Mspecs {
    public static $syncer;
    public static $api_client;
    public static function get_api_client(){ return self::$api_client; }
}

class Mspecs_Error_Handler {
    public static $latest_error;
    public static function set_latest_error($message){ self::$latest_error = $message; }
}

function apply_filters($name, $value, ...$args){ return $value; }
function do_action(...$args){}
function is_wp_error($value){ return $value instanceof WP_Error; }
function wp_remote_retrieve_body($response){ return $response['body']; }
function wp_remote_retrieve_response_code($response){ return $response['status']; }
function wp_remote_request($url, $args){
    global $http, $requests;
    $path = parse_url($url, PHP_URL_PATH);
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    $requests[] = array('method' => $args['method'], 'path' => $path, 'query' => $query);
    if($args['method'] === 'PUT') return response(array());
    return $http($path, $query);
}
function wp_insert_post($args, $return_error){
    global $stored_meta, $inserted;
    $inserted[] = $args;
    $stored_meta = array_merge($stored_meta, $args['meta_input']);
    return $args['ID'] ?? 1;
}
function mspecs_get_deal($id){ global $old_deal; return $old_deal; }
function mspecs_get_deals($args){ global $old_deal; return $old_deal ? array($old_deal) : array(); }
function mspecs_get_deal_meta($key, $deal){ global $stored_meta; return $stored_meta[$key] ?? ''; }
function mspecs_prefixed_post_metas(){ return array('id'); }
function get_post_stati(){ return array('publish', 'draft'); }
function wp_list_pluck($input, $field){
    return is_array($input) ? array_map(function($item) use ($field){ return $item[$field]; }, $input) : array();
}

require MSPECS_PLUGIN_DIR . 'inc/helpers.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-api-client.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-sync-manager.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-syncer.php';
require MSPECS_PLUGIN_DIR . 'inc/class.mspecs-store.php';

class Test_Syncer extends Mspecs_Syncer {
    public $deleted_files = array();
    public function download_image($image){ return $image; }
    public function download_file($file){ return $file; }
    public function delete_file($file){ $this->deleted_files[] = $file; }
}

function response($body, $status = 200){
    return array('status' => $status, 'body' => json_encode($body));
}
function expect($expected, $actual){
    if($expected !== $actual){
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function queued_ids(){ return array_column(Mspecs::$syncer->queue, 'deal'); }
function sold_deal(){
    return array(
        'id' => 'sold-1',
        'shortId' => 'SOLD 1',
        'images' => array(),
        'publishingInformation' => array('saleStatus' => 'ENUMS_SALE_STATUS_SOLD'),
    );
}
function reset_test(){
    global $http, $requests, $stored_meta, $inserted, $old_deal;
    $http = function($path, $query){ throw new RuntimeException('Unexpected request: ' . $path); };
    $requests = $stored_meta = $inserted = array();
    $old_deal = false;
    Mspecs_Error_Handler::$latest_error = null;
    Mspecs::$syncer = new Test_Syncer();
    Mspecs::$api_client = new Mspecs_Api_Client('', '', 'test-token', 'test-subscriber', 'example.invalid');
}
function respond_with_deal($deal){
    global $http;
    $http = function($path, $query) use ($deal){
        expect('/api/marketing/deals/sold-1', $path);
        return response($deal);
    };
}
function run_isolated_test($filename, $memory_limit = '128M'){
    $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('memory_limit=' . $memory_limit)
        . ' ' . escapeshellarg(__DIR__ . '/' . $filename) . ' 2>&1';
    exec($command, $output, $status);
    if($status !== 0) throw new RuntimeException(implode(PHP_EOL, $output));
    echo implode(PHP_EOL, $output) . PHP_EOL;
}

$tests = array();
$tests['full sync fetches every sold page and queues each deal once'] = function(){
    global $http, $requests;
    $cursor = 'next&cursor+/= value';
    $http = function($path, $query) use ($cursor){
        if($path === '/api/marketing/deals') return response(array(array('id' => 'active-1')));
        expect('/api/marketing/sold/deals', $path);
        if(!isset($query['next'])) return response(array('deals' => array(array('id' => 'sold-1')), 'next' => $cursor));
        expect($cursor, $query['next']);
        return response(array('deals' => array(array('id' => 'sold-2'), array('id' => 'sold-1'))));
    };
    Mspecs_Sync_Manager::sync_all_deals();
    expect(array('active-1', 'sold-1', 'sold-2'), queued_ids());
    expect(3, count($requests));
    expect(1, Mspecs::$syncer->dispatches);
};
$tests['sold pagination follows cursors even when a page is empty'] = function(){
    global $http;
    $http = function($path, $query){
        if(!isset($query['next'])) return response(array('deals' => array(), 'next' => 'page-2'));
        expect('page-2', $query['next']);
        return response(array('deals' => array(array('id' => 'sold-1')), 'next' => null));
    };
    expect(array('sold-1'), Mspecs::$api_client->get_all_sold_deal_ids());
};
$tests['empty sold results preserve published deals and dispatch preference'] = function(){
    global $http;
    $http = function($path, $query){
        return response($path === '/api/marketing/deals' ? array(array('id' => 'active-1')) : array('deals' => array(), 'next' => ''));
    };
    Mspecs_Sync_Manager::sync_all_deals(false);
    expect(array('active-1'), queued_ids());
    expect(0, Mspecs::$syncer->dispatches);
};
$tests['failed sold pages report an error without queuing a partial import'] = function(){
    global $http;
    $http = function($path, $query){
        if($path === '/api/marketing/deals') return response(array(array('id' => 'active-1')));
        if(!isset($query['next'])) return response(array('deals' => array(array('id' => 'sold-1')), 'next' => 'page-2'));
        return response(array('message' => 'Sold page unavailable'), 503);
    };
    $result = Mspecs_Sync_Manager::sync_all_deals();
    expect(true, is_wp_error($result));
    expect('Sold page unavailable', Mspecs_Error_Handler::$latest_error);
    expect(array(), Mspecs::$syncer->queue);
    expect(0, Mspecs::$syncer->dispatches);
};
$tests['failed published requests stop before fetching sold deals'] = function(){
    global $http, $requests;
    $http = function($path, $query){ return response(array('message' => 'Published deals unavailable'), 503); };
    expect(true, is_wp_error(Mspecs_Sync_Manager::sync_all_deals()));
    expect(1, count($requests));
    expect(array(), Mspecs::$syncer->queue);
    expect(0, Mspecs::$syncer->dispatches);
};
$tests['failed full resync does not dispatch deletions'] = function(){
    global $http, $old_deal, $stored_meta;
    $old_deal = (object) array('ID' => 1);
    $stored_meta['mspecs_id'] = 'sold-1';
    $http = function($path, $query){
        if($path === '/api/marketing/deals') return response(array());
        return response(array('message' => 'Sold deals unavailable'), 503);
    };
    expect(true, is_wp_error(Mspecs_Sync_Manager::full_resync()));
    expect(0, Mspecs::$syncer->dispatches);
};
$tests['successful full resync restores published and sold deals after deletion'] = function(){
    global $http, $old_deal, $stored_meta;
    $old_deal = (object) array('ID' => 1);
    $stored_meta['mspecs_id'] = 'sold-1';
    $http = function($path, $query){
        return response($path === '/api/marketing/deals' ? array(array('id' => 'active-1')) : array('deals' => array(array('id' => 'sold-1'))));
    };
    Mspecs_Sync_Manager::full_resync();
    expect(array(
        array('action' => 'download_subscriber_details'),
        array('action' => 'delete_deal', 'deal' => 'sold-1'),
        array('action' => 'download_deal', 'deal' => 'active-1'),
        array('action' => 'download_deal', 'deal' => 'sold-1'),
    ), Mspecs::$syncer->queue);
    expect(1, Mspecs::$syncer->dispatches);
};
$tests['new sold deals without files store an empty file list'] = function(){
    global $stored_meta;
    respond_with_deal(sold_deal());
    expect(false, Mspecs::$syncer->download_deal('sold-1'));
    expect(array(), $stored_meta['files']);
    expect('ENUMS_SALE_STATUS_SOLD', $stored_meta['saleStatus']);
};
$tests['existing sold deals tolerate missing stored and incoming files'] = function(){
    global $old_deal, $stored_meta;
    $old_deal = (object) array('ID' => 1);
    $stored_meta['images'] = array();
    respond_with_deal(sold_deal());
    expect(false, Mspecs::$syncer->download_deal('sold-1'));
    expect(array(), $stored_meta['files']);
};
$tests['omitted or empty incoming files remove obsolete files and metadata'] = function(){
    global $old_deal, $stored_meta;
    foreach(array('omitted', 'empty', 'null') as $kind){
        $old_deal = (object) array('ID' => 1);
        $old_file = array('path' => 'old/brochure.pdf');
        $stored_meta = array('images' => array(), 'files' => array($old_file));
        Mspecs::$syncer->deleted_files = array();
        $deal = sold_deal();
        if($kind !== 'omitted') $deal['files'] = $kind === 'empty' ? array() : null;
        respond_with_deal($deal);
        expect(false, Mspecs::$syncer->download_deal('sold-1'));
        expect(array($old_file), Mspecs::$syncer->deleted_files);
        expect(array(), $stored_meta['files']);
    }
};
$tests['non-array stored file metadata is handled safely'] = function(){
    global $old_deal, $stored_meta;
    foreach(array('', null, false) as $old_files){
        $old_deal = (object) array('ID' => 1);
        $stored_meta = array('images' => array(), 'files' => $old_files);
        respond_with_deal(sold_deal());
        expect(false, Mspecs::$syncer->download_deal('sold-1'));
        expect(array(), $stored_meta['files']);
    }
};
$tests['present files are retained while removed files are deleted'] = function(){
    global $old_deal, $stored_meta;
    $old_deal = (object) array('ID' => 1);
    $kept = array('path' => 'kept/brochure.pdf');
    $removed = array('path' => 'removed/brochure.pdf');
    $stored_meta = array('images' => array(), 'files' => array($kept, $removed));
    $deal = sold_deal();
    $deal['files'] = array($kept);
    respond_with_deal($deal);
    expect(false, Mspecs::$syncer->download_deal('sold-1'));
    expect(array($removed), Mspecs::$syncer->deleted_files);
    expect(array($kept), $stored_meta['files']);
};
$tests['subscriber autodetection cannot dispatch deletions before resync validation'] = function(){
    run_isolated_test('resync-integration.php');
};
$tests['20,000 sold objects sync within a 128 MB memory limit'] = function(){
    run_isolated_test('sold-memory.php');
};

$failed = 0;
foreach($tests as $name => $test){
    reset_test();
    try{
        $test();
        echo 'PASS ' . $name . PHP_EOL;
    }catch(Throwable $error){
        $failed++;
        echo 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL;
    }
}
echo count($tests) . ' tests, ' . $failed . ' failed' . PHP_EOL;
exit($failed ? 1 : 0);
