<?php

// Run with: php tests/webhook-secret.php. WordPress and the Mspecs API are offline stubs.
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

class WP_Error {
    public function __construct(public $code, public $message = ''){}
    public function get_error_message(){ return $this->message; }
}
class Mspecs_WP_Background_Process { public function __construct(){} }
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

require dirname(__DIR__) . '/inc/helpers.php';
require dirname(__DIR__) . '/inc/class.mspecs-admin.php';
require dirname(__DIR__) . '/inc/class.mspecs-syncer.php';

function expect($expected, $actual){
    if($expected !== $actual) throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

// Saving the settings form (no api_secret posted) keeps the stored secret
$options['mspecs_settings'] = array('api_domain' => 'old.invalid', 'api_secret' => 'stored');
expect(array('api_domain' => 'new.invalid', 'api_secret' => 'stored'), Mspecs_Admin::sanitize_settings(array('api_domain' => 'new.invalid')));

// An explicit new secret (from mspecs_update_setting) still wins
expect('fresh', Mspecs_Admin::sanitize_settings(array('api_secret' => 'fresh'))['api_secret']);

// First save, nothing stored yet
$options = array();
expect('', Mspecs_Admin::sanitize_settings(array())['api_secret']);

$syncer = new Mspecs_Syncer();

// API errors are returned for the queue to retry, not a fatal error
Mspecs::$response = new WP_Error('mspecs_api_error', 'Forbidden');
expect('Forbidden', $syncer->set_webhook_secret()->get_error_message());

// A response without a secret is an error and leaves the stored secret alone
Mspecs::$response = array();
expect('mspecs_webhook_secret_missing', $syncer->set_webhook_secret()->code);
expect(false, isset($options['mspecs_settings']['api_secret']));

// Success stores the secret
Mspecs::$response = array('secret' => 'generated');
expect(false, $syncer->set_webhook_secret());
expect('generated', $options['mspecs_settings']['api_secret']);

echo 'Webhook secret checks passed' . PHP_EOL;
