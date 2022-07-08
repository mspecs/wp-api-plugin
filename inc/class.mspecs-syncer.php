<?php

class Mspecs_Syncer extends Mspecs_WP_Background_Process{
    protected $action = 'mspecs_sync';
    
    public $max_retries = 5;
    public $cron_interval = 5; // Minutes

    public static $TOO_MANY_REQUESTS_STATUS_CODE = 429;

    public function __construct(){
        /**
         * Filters the maximum number of retries for a background process
         */
        $this->max_retries = apply_filters('mspecs_max_retries', $this->max_retries);

        /**
         * Filters the number of minutes between each background process revive attempt
         */
        $this->cron_interval = apply_filters('mspecs_cron_interval', $this->cron_interval);

        parent::__construct();
    }

    protected function task($item){
        $action = mspecs_get($item, 'action');
        $retry = mspecs_get($item, 'retry', 0);
        $result = false;

        if($this->max_retries >= 0 && $retry > $this->max_retries){
            mspecs_log('Max retries exceeded for background process', $item);

            return false;
        }

        switch($action){
            case 'download_deal':
                $result = $this->download_deal(mspecs_get($item, 'deal'));
                break;
            case 'delete_deal':
                $result = $this->delete_deal(mspecs_get($item, 'deal'));
                break;
            case 'download_subscriber_details':
                $result = $this->download_subscriber_details();
                break;
            case 'delete_subscriber_details':
                $result = $this->delete_subscriber_details();
                break;
            case 'set_webhook_secret':
                $result = $this->set_webhook_secret();
                break;
        }

        if(is_wp_error($result)){
            mspecs_log($result);

            $item['retry'] = $retry + 1;

            $http_status = mspecs_get($result, 'error_data.mspecs_api_error.status');
            $throttled = $http_status === self::$TOO_MANY_REQUESTS_STATUS_CODE;

            if($throttled){
                // Throttle process, by exiting and waiting for cron to run again
                exit;
            }else{
                Mspecs_Error_Handler::set_latest_error($result->get_error_message());
            }

            return $item;
        }

        return false;
    }

    protected function complete() {
		parent::complete();
	}

    public function download_subscriber_details(){
        if(!apply_filters('mspecs_should_download_subscriber_details', true)){
            return false;
        }

        $api_client = Mspecs::get_api_client();
        $subscriber = $api_client->get_subscriber_details();

        if(is_wp_error($subscriber)) return $subscriber;

        $result = $this->download_organization(mspecs_get($subscriber, 'organization'));
        if(is_wp_error($result)) return $result;

        $offices = mspecs_get($subscriber, 'offices');
        foreach($offices as $office){
            $result = $this->download_office($office);
            if(is_wp_error($result)) return $result;
        }

        $users = mspecs_get($subscriber, 'users');
        foreach($users as $user){
            $result = $this->download_user($user);
            if(is_wp_error($result)) return $result;
        }

        update_option('mspecs_has_subscriber_details', 1);

        do_action('mspecs_subscriber_details_downloaded');

        return false;
    }

    public function download_organization($mspecs_organization){
        $get = mspecs_getify($mspecs_organization);

        // Set WP default fields
        $args = array(
            'post_type' => 'mspecs_organization',
            'post_title' => $get('name', ''),
            'post_status' => 'publish',

            // 'post_date' // TODO
        );

        $old_organization = mspecs_get_organization();
        if($old_organization){
            $args['ID'] = $old_organization->ID;
        }

        // Build meta
        $meta = $mspecs_organization;

        // Prefix some meta keys with mspecs_
        $prefixed_meta = mspecs_prefixed_post_metas();
        foreach($prefixed_meta as $meta_key){
            $value = $get($meta_key);
            unset($meta[$meta_key]);
            $meta['mspecs_' . $meta_key] = $value;
        }

        $meta_keys = array_keys($meta);
        $meta['mspecs_meta_keys'] = $meta_keys;

        $args['meta_input'] = $meta;

        $args = apply_filters('mspecs_insert_organization_args', $args);

        $id = Mspecs_Store::insert_post($args);

        if(is_wp_error($id)){
            // TODO: Handle error
            return $id;
        }else{
            do_action('mspecs_organization_inserted', $id);
        }

        return false;
    }

    public function download_office($mspecs_office){
        if(!apply_filters('mspecs_should_download_office', true, $mspecs_office)){
            return false;
        }

        $mspecs_id = mspecs_get($mspecs_office, 'id');

        if(empty($mspecs_id))  return false;

        $get = mspecs_getify($mspecs_office);

        // Set WP default fields
        $args = array(
            'post_type' => 'mspecs_office',
            'post_title' => $get('name', ''),
            'post_status' => 'publish',

            // 'post_date' // TODO
        );
        
        $old_deal = mspecs_get_office($mspecs_id);
        if($old_deal){
            $args['ID'] = $old_deal->ID;
        }

        // Build meta
        $meta = $mspecs_office;

        // Prefix some meta keys with mspecs_
        $prefixed_meta = mspecs_prefixed_post_metas();
        foreach($prefixed_meta as $meta_key){
            $value = $get($meta_key);
            unset($meta[$meta_key]);
            $meta['mspecs_' . $meta_key] = $value;
        }

        $meta_keys = array_keys($meta);
        $meta['mspecs_meta_keys'] = $meta_keys;

        $args['meta_input'] = $meta;

        $args = apply_filters('mspecs_insert_office_args', $args);

        $id = Mspecs_Store::insert_post($args);

        if(is_wp_error($id)){
            // TODO: Handle error
            return $id;
        }else{
            do_action('mspecs_office_inserted', $id);
        }

        return false;
    }

    public function download_user($mspecs_user){
        if(!apply_filters('mspecs_should_download_user', true, $mspecs_user)){
            return false;
        }

        $mspecs_id = mspecs_get($mspecs_user, 'id');

        if(empty($mspecs_id))  return false;

        $get = mspecs_getify($mspecs_user);

        // Set WP default fields
        $args = array(
            'post_type' => 'mspecs_user',
            'post_title' => $get('firstName', '') . ' ' . $get('lastName', ''),
            'post_status' => 'publish',

            // 'post_date' // TODO
        );
        
        $old_deal = mspecs_get_user($mspecs_id);
        if($old_deal){
            $args['ID'] = $old_deal->ID;
        }

        // Build meta
        $meta = $mspecs_user;

        // Prefix some meta keys with mspecs_
        $prefixed_meta = mspecs_prefixed_post_metas();
        foreach($prefixed_meta as $meta_key){
            $value = $get($meta_key);
            unset($meta[$meta_key]);
            $meta['mspecs_' . $meta_key] = $value;
        }

        $meta_keys = array_keys($meta);
        $meta['mspecs_meta_keys'] = $meta_keys;

        $args['meta_input'] = $meta;

        $args = apply_filters('mspecs_insert_user_args', $args);

        $id = Mspecs_Store::insert_post($args);

        if(is_wp_error($id)){
            // TODO: Handle error
            return $id;
        }else{
            do_action('mspecs_user_inserted', $id);
        }

        return false;
    }

    public function download_deal($mspecs_id){
        if(empty($mspecs_id))  return false;

        if(!apply_filters('mspecs_should_download_deal', true, $mspecs_id)){
            return false;
        }

        $api_client = Mspecs::get_api_client();
        $deal = $api_client->get_deal($mspecs_id);

        if(is_wp_error($deal)){
            // TODO: Handle error
            $api_client->set_deal_status_error($mspecs_id);
            return $deal;
        }

        $get = mspecs_getify($deal);

        // Set WP default fields
        $args = array(
            'post_type' => 'mspecs_deal',
            'post_title' => $get('shortId', ''),
            'post_content' => $get('sellingTexts.sellingText', ''),
            'post_excerpt' => $get('sellingTexts.sellingTextShort', ''),
            'post_status' => 'publish', // TODO

            // 'post_date' // TODO
        );
        
        $old_deal = mspecs_get_deal($mspecs_id);
        if($old_deal){
            $args['ID'] = $old_deal->ID;
        }

        // Build meta
        $meta = $deal;

        // Prefix some meta keys with mspecs_
        $prefixed_meta = mspecs_prefixed_post_metas();
        foreach($prefixed_meta as $meta_key){
            $value = $get($meta_key);
            unset($meta[$meta_key]);
            $meta['mspecs_' . $meta_key] = $value;
        }

        // Download images
        $images = $get('images');
        if(is_array($images)){
            foreach($images as $i => $image){
                $downloaded = $this->download_image($image);
                if(is_wp_error($downloaded)){
                    // TODO: Handle error
                    $api_client->set_deal_status_error($mspecs_id);
                    return $downloaded;
                }

                $meta['images'][$i] = $downloaded;
            }
        }

        // Delete removed images
        if($old_deal){
            $old_images = mspecs_get_deal_meta('images', $old_deal);
            $new_images = wp_list_pluck($meta['images'], 'originalPath');

            foreach($old_images as $old_image){
                if(!in_array($old_image['originalPath'], $new_images)){
                    $this->delete_image($old_image);
                }
            }
        }

        // Download files
        $files = $get('files');
        if(is_array($files)){
            foreach($files as $i => $file){
                $downloaded = $this->download_file($file);
                if(is_wp_error($downloaded)){
                    // TODO: Handle error
                    $api_client->set_deal_status_error($mspecs_id);
                    return $downloaded;
                }

                $meta['files'][$i] = $downloaded;
            }
        }

        // Delete removed files
        if($old_deal){
            $old_files = mspecs_get_deal_meta('files', $old_deal);
            $new_files = wp_list_pluck($meta['files'], 'path');

            foreach($old_files as $old_file){
                if(!in_array($old_file['path'], $new_files)){
                    $this->delete_file($old_file);
                }
            }
        }

        foreach($meta as $meta_key => $meta_value){
            $formatted = $this->format_meta($meta_key, $meta_value);
            if($formatted){
                $meta = array_merge($meta, $formatted);
            }
        }

        $meta_keys = array_keys($meta);
        $meta['mspecs_meta_keys'] = $meta_keys;

        $args['meta_input'] = $meta;

        $args = apply_filters('mspecs_insert_deal_args', $args);

        $id = Mspecs_Store::insert_post($args);

        if(is_wp_error($id)){
            // TODO: Handle error
            $api_client->set_deal_status_error($mspecs_id);
            return $id;
        }else{
            $api_client->set_deal_status_published($mspecs_id);
            do_action('mspecs_deal_inserted', $id);
        }

        return false;
    }

    public function download_file($mspecs_file){
        $dir = mspecs_file_dir();
        $dir_url = mspecs_file_dir_url();
        $title = sanitize_file_name(mspecs_get($mspecs_file, 'title'));

        if(!$title){
            $title = 'file';
        }

        $url = mspecs_get($mspecs_file, 'url');

        $ext = wp_check_filetype($url)['ext'];
        $subdir = sanitize_file_name(basename($url, '.' . $ext));
        $filename = $title . '.' . $ext;

        $file = $dir . '/' . $subdir . '/' . $filename;
        $file_url = $dir_url . '/' . $subdir . '/' . $filename;

        if(!file_exists($file)){
            $result = $this->download_file_from_remote($file, $url);
            if(is_wp_error($result)) return $result;

            do_action('mspecs_file_downloaded', $file, $mspecs_file);
        }

        $mspecs_file['url'] = $file_url;
        $mspecs_file['path'] = $subdir . '/' . $filename;

        return $mspecs_file;
    }

    public function download_image($mspecs_image){
        $image_sizes = mspecs_image_sizes();
        $dir = mspecs_file_dir();
        $dir_url = mspecs_file_dir_url();
        $title = sanitize_file_name(mspecs_get($mspecs_image, 'title'));

        if(!$title){
            $title = 'image';
        }

        foreach($image_sizes as $size){
            $url = mspecs_get($mspecs_image, $size.'Url');
            if(!empty($url)){
                $ext = wp_check_filetype($url)['ext'];
                $subdir = sanitize_file_name(basename($url, '.' . $ext));
                $filename = $title;

                // Suffix with image resolution
                if($size !== 'original'){
                    $filename .= '-' . mspecs_get($mspecs_image, $size.'Resolution');
                }

                $filename .= '.' . $ext;

                $file = $dir . '/' . $subdir . '/' . $filename;
                $file_url = $dir_url . '/' . $subdir . '/' . $filename;

                if(!file_exists($file)){
                    $result = $this->download_file_from_remote($file, $url);
                    if(is_wp_error($result)) return $result;

                    do_action('mspecs_image_downloaded', $file, $mspecs_image, $size);
                }

                $mspecs_image[$size.'Url'] = $file_url;
                $mspecs_image[$size.'Path'] = $subdir . '/' . $filename;
            }
        }

        return $mspecs_image;
    }

    protected function download_file_from_remote($file_path, $remote_url){
        $response = wp_remote_get($remote_url, array('timeout' => 30)); // TODO: Domain whitelist?

        if(is_wp_error($response)) return $response;

        $dir = dirname($file_path);

        // Save file, based on wp_upload_bits
        
        wp_mkdir_p($dir);

        $ifp = @fopen( $file_path, 'wb' );
        if ( ! $ifp ) {
            return new WP_Error('mspecs_image_download_failed', sprintf( __( 'Could not write file %s' ), $file_path ));
        }

        fwrite( $ifp, wp_remote_retrieve_body($response) );
        fclose( $ifp );

        $stat  = @ stat( $dir );
        $perms = $stat['mode'] & 0007777;
        $perms = $perms & 0000666;
        chmod( $file_path, $perms );

        return true;
    }

    public function format_meta($meta_key, $meta_value){
        $formatted = array();

        if($this->is_constant_meta($meta_value)){
            // Format constants for easier querying (i.e objectType, objectSubType)
            $formatted[$meta_key . 'Constant'] = $meta_value['constant'];
        }else if(isset($meta_value['id'])){
            // Format one-to-one relationships for easier querying (i.e office)
            $formatted[$meta_key . 'Id'] = $meta_value['id'];
        }else if($this->is_resource_list_meta($meta_value)){
            // Format one-to-many relationships for easier querying (i.e users)
            $formatted[$meta_key . 'Ids'] = wp_list_pluck($meta_value, 'id');
        }

        if($meta_key == 'publishingInformation' && isset($meta_value['saleStatus'])){
            $formatted['saleStatus'] = $meta_value['saleStatus'];
        }

        return empty($formatted) ? false : $formatted;
    }

    public function is_constant_meta($meta_value){
        return is_array($meta_value) && isset($meta_value['constant']);
    }

    public function is_resource_list_meta($meta_value){
        return is_array($meta_value) && count($meta_value) > 0 && isset($meta_value[0]['id']);
    }

    public function delete_subscriber_details(){
        $this->delete_organization();

        $offices = mspecs_get_offices(array(
            'post_status' => get_post_stati(),
        ));

        foreach($offices as $office){
            self::delete_office(mspecs_get_deal_meta('mspecs_id', $office), false);
        }

        $users = mspecs_get_users(array(
            'post_status' => get_post_stati(),
        ));

        foreach($users as $user){
            self::delete_user(mspecs_get_deal_meta('mspecs_id', $user), false);
        }

        do_action('mspecs_subscriber_details_deleted');
    }

    public function delete_organization(){
        $organization = mspecs_get_organization();
        if($organization){
            wp_delete_post($organization->ID, true);

            do_action('mspecs_organization_deleted');
        }
    }

    public function delete_office($office_id){
        $office = mspecs_get_office($office_id);
        if($office){
            wp_delete_post($office->ID, true);

            do_action('mspecs_office_deleted', $office_id);
        }
    }

    public function delete_user($user_id){
        $user = mspecs_get_user($user_id);
        if($user){
            wp_delete_post($user->ID, true);

            do_action('mspecs_user_deleted', $user_id);
        }
    }

    public function delete_deal($deal_id){
        $deal = mspecs_get_deal($deal_id);

        if($deal){
            // Delete images
            $images = mspecs_get($deal, 'images');
            if(is_array($images)){
                foreach($images as $image){
                    $this->delete_image($image);
                }
            }

            // Delete files
            $files = mspecs_get($deal, 'files');
            if(is_array($files)){
                foreach($files as $file){
                    $this->delete_file($file);
                }
            }

            // Delete database entry
            wp_delete_post($deal->ID, true);

            do_action('mspecs_deal_deleted', $deal_id);
        }

        return false;
    }

    public function delete_file($mspecs_file){
        $path = mspecs_get($mspecs_file, 'path');
        $deleted = $this->delete_downloaded_file($path);

        if($deleted){
            do_action('mspecs_file_deleted', $mspecs_file);
        }
    }

    public function delete_image($mspecs_image){
        $deleted_any = false;
        $image_sizes = mspecs_image_sizes();

        foreach($image_sizes as $size){
            $path = mspecs_get($mspecs_image, $size.'Path');
            $deleted = $this->delete_downloaded_file($path);

            if($deleted){
                $deleted_any = true;
            }
        }

        if($deleted_any){
            do_action('mspecs_image_deleted', $mspecs_image);
        }
    }

    protected function delete_downloaded_file($path){
        if(!empty($path)){
            $file = mspecs_file_dir() . '/' . $path;
            if(file_exists($file)){
                unlink($file);

                // Remove empty directory
                $dir = dirname($file);
                if(count(scandir($dir)) == 2){
                    rmdir($dir);
                }

                return true;
            }
        }

        return false;
    }

    public function set_webhook_secret(){
        $api_client = Mspecs::get_api_client();
        $response = $api_client->generate_webhook_secret();
        $secret = isset($response['secret']) ? $response['secret'] : false;
        
        if($secret){
            mspecs_update_setting('api_secret', $secret);
        }
    }
}