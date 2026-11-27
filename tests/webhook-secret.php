<?php

// Run with: php tests/webhook-secret.php. WordPress and the Mspecs API are offline stubs.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});
ini_set('error_log', '/dev/null'); // mspecs_log() output
define('MSPECS_PLUGIN_DIR', dirname(__DIR__) . '/');

class WP_Error {
    public $error_data = array();
    public function __construct(public $code, public $message = '', $data = null){
        if(!is_null($data)) $this->error_data[$code] = $data;
    }
    public function get_error_message(){ return $this->message; }
    public function get_error_data($code){ return $this->error_data[$code] ?? null; }
}
class Mspecs_WP_Background_Process { public function __construct(){} }
class Mspecs_Error_Handler {
    public static $latest_error;
    public static function set_latest_error($message){ self::$latest_error = $message; }
}
class Mspecs {
    public static $response;
    public static function get_api_client(){
        return new class { public function generate_webhook_secret(){ return Mspecs::$response; } };
    }
}

$options = array();
function apply_filters($name, $value, ...$args){ return $value; }
function __($text, $domain = null){ return $text; }
function is_wp_error($value){ return $value instanceof WP_Error; }
function get_option($key, $default = false){ global $options; return $options[$key] ?? $default; }
function mspecs_update_setting($key, $value){ global $options; $options['mspecs_settings'][$key] = $value; }
function api_error($status){ return new WP_Error('mspecs_api_error', 'API says no', array('status' => $status)); }

require dirname(__DIR__) . '/inc/helpers.php';
require dirname(__DIR__) . '/inc/class.mspecs-admin.php';
require dirname(__DIR__) . '/inc/class.mspecs-syncer.php';

class Test_Syncer extends Mspecs_Syncer {
    public function run_task($item){ return $this->task($item); }
}

function expect($expected, $actual){
    if($expected !== $actual) throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

// Saving the settings form with an empty or missing secret keeps the stored secret
$options['mspecs_settings'] = array('api_domain' => 'old.invalid', 'api_secret' => 'stored');
expect(array('api_domain' => 'new.invalid', 'api_secret' => 'stored'), Mspecs_Admin::sanitize_settings(array('api_domain' => 'new.invalid')));
expect('stored', Mspecs_Admin::sanitize_settings(array('api_secret' => '  '))['api_secret']);

// A pasted or generated secret wins, without stray whitespace
expect('pasted', Mspecs_Admin::sanitize_settings(array('api_secret' => ' pasted '))['api_secret']);

// First save, nothing stored yet
$options = array();
expect('', Mspecs_Admin::sanitize_settings(array())['api_secret']);

$syncer = new Test_Syncer();

// API errors are returned for the queue to retry, not a fatal error
Mspecs::$response = api_error(500);
expect('API says no', $syncer->set_webhook_secret()->get_error_message());

// A token that may not generate the secret gets told to paste one
Mspecs::$response = api_error(403);
$error = $syncer->set_webhook_secret();
expect(true, str_contains($error->get_error_message(), 'paste it in the Webhook secret field'));
expect(403, $error->get_error_data('mspecs_api_error')['status']);

// A response without a secret is an error and leaves the stored secret alone
Mspecs::$response = array();
expect('mspecs_webhook_secret_missing', $syncer->set_webhook_secret()->code);
expect(false, isset($options['mspecs_settings']['api_secret']));

// Success stores the secret
Mspecs::$response = array('secret' => 'generated');
expect(false, $syncer->set_webhook_secret());
expect('generated', $options['mspecs_settings']['api_secret']);

// The queue retries server errors, but drops 401 and 403 with an error notice
Mspecs::$response = api_error(500);
expect(1, $syncer->run_task(array('action' => 'set_webhook_secret'))['retry']);
foreach(array(401, 403) as $status){
    Mspecs_Error_Handler::$latest_error = null;
    Mspecs::$response = api_error($status);
    expect(false, $syncer->run_task(array('action' => 'set_webhook_secret')));
    expect(true, is_string(Mspecs_Error_Handler::$latest_error));
}

echo 'Webhook secret checks passed' . PHP_EOL;
