<?php

class Mspecs_Error_Handler {
    public static $latest_error_max_age = DAY_IN_SECONDS;

    public static function init() {
        
	}

    public static function maybe_display_admin_notices(){
        $latest_error = self::get_latest_error();
        if($latest_error){
            $class = 'notice notice-error';
            $intro = __( 'There recently was an error when trying to sync data from Mspecs, check the site log file for more information.', 'mspecs' );

            $error_message = '['.date('Y-m-d H:i:s e', $latest_error['timestamp']).'] '.$latest_error['message'];
        
            printf( '<div class="%1$s"><p>%2$s</p><p><pre>%3$s</pre></p></div>', esc_attr( $class ), esc_html( $intro ), esc_html($error_message) );
        }
    }

    public static function get_latest_error(){
        $latest_error = get_option('mspecs_latest_error');

        if(!$latest_error){
            return false;
        }

        if(time() - $latest_error['timestamp'] > self::$latest_error_max_age){
            return false;
        }

        return $latest_error;
    }

    public static function set_latest_error($message){
        update_option('mspecs_latest_error', array(
            'message' => $message,
            'timestamp' => time(),
        ));
    }

    public static function clear_leater_error(){
        delete_option('mspecs_latest_error');
    }
}