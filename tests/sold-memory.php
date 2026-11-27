<?php

// Called in a separate PHP process with memory_limit=128M by tests/run.php.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__) . '/inc/helpers.php';
require dirname(__DIR__) . '/inc/class.mspecs-api-client.php';
require dirname(__DIR__) . '/inc/class.mspecs-sync-manager.php';
class Mspecs {
    public static $syncer;
    public static $api_client;
    public static function get_api_client(){ return self::$api_client; }
}
class Memory_Test_Queue {
    public $items = array();
    public function push_to_queue($item){ $this->items[] = $item; return $this; }
}
function apply_filters($name, $value, ...$args){ return $value; }
function is_wp_error($value){ return false; }
function wp_remote_retrieve_body($response){ return $response['body']; }
function wp_remote_retrieve_response_code($response){ return $response['status']; }
$pages = 0;
function wp_remote_request($url, $args){
    global $pages;
    if(parse_url($url, PHP_URL_PATH) === '/api/marketing/deals'){
        return array('status' => 200, 'body' => json_encode(array(array('id' => 'active-1'))));
    }
    if(parse_url($url, PHP_URL_PATH) !== '/api/marketing/sold/deals'){
        throw new RuntimeException('Unexpected request: ' . $url);
    }
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    if(($query['next'] ?? null) !== ($pages ? base64_encode('cursor-' . $pages) : null)){
        throw new RuntimeException('Incorrect pagination cursor');
    }
    $pages++;
    $deals = array();
    for($i = 0; $i < 1000; $i++){
        $id = ($pages - 1) * 1000 + $i;
        $images = array();
        for($j = 0; $j < 10; $j++){
            $images[] = array(
                'id' => 'image-' . $id . '-' . $j,
                'title' => 'Interior ' . $j,
                'originalUrl' => 'https://media.example.com/' . $id . '/' . $j . '/' . str_repeat('a', 60) . '.jpg',
                'viewUrl' => 'https://media.example.com/' . $id . '/' . $j . '/' . str_repeat('b', 60) . '.jpg',
                'thumbnailUrl' => 'https://media.example.com/' . $id . '/' . $j . '/' . str_repeat('c', 60) . '.jpg',
                'originalResolution' => '2000x1500',
                'viewResolution' => '1000x750',
                'thumbnailResolution' => '300x225',
            );
        }
        $deals[] = array(
            'id' => 'sold-' . $id,
            'objectType' => array('constant' => 'OBJECT_TYPE_HOUSE', 'translation' => 'Villa'),
            'isNewDevelopment' => false,
            'publishingInformation' => array(
                'status' => 'ENUM_DEAL_MARKETING_PLACES_STATUS_PUBLISHED',
                'updated' => '2026-11-27T12:00:00Z',
                'saleStatus' => 'ENUMS_SALE_STATUS_SOLD',
            ),
            'sellingTexts' => array('sellingTextShort' => str_repeat('Example house description. ', 40)),
            'images' => $images,
        );
    }
    $body = array('deals' => $deals);
    if($pages < 20) $body['next'] = base64_encode('cursor-' . $pages);
    return array('status' => 200, 'body' => json_encode($body));
}
Mspecs::$syncer = new Memory_Test_Queue();
Mspecs::$api_client = new Mspecs_Api_Client('', '', 'test-token', 'test-subscriber', 'example.invalid');
Mspecs_Sync_Manager::sync_all_deals(false);
$ids = array_column(Mspecs::$syncer->items, 'deal');
if($pages !== 20 || count($ids) !== 20001 || count(array_unique($ids)) !== 20001
    || $ids[0] !== 'active-1' || end($ids) !== 'sold-19999'){
    throw new RuntimeException('Large-history sync skipped or duplicated objects');
}
echo '20,000 sold objects queued; peak memory ' . round(memory_get_peak_usage(true) / 1048576, 1) . ' MiB' . PHP_EOL;
