<?php

/**
 * mspecs_get
 *
 * Gets the value at path of object. If the resolved value is empty $default is returned in its place.
 * 
 * @param  mixed $object
 * @param  string|array $path
 * @param  mixed $default
 * @return mixed
 */
function mspecs_get( $object, $path, $default = null ) {
    if( is_array($object) ) {
      $is_array = true;
      $is_object = false;
    }elseif( is_object($object) ){
      $is_object = true;
      $is_array = $object instanceof ArrayAccess;
    }else{
      return $default;
    }
  
    $path = is_string($path) ? explode('.', $path) : $path;
  
    // Get first prop
    $prop = array_shift($path);
  
    if( $is_array && isset($object[$prop]) ){
      $value = $object[$prop];
    }elseif( $is_object && isset($object->$prop) ){
      $value = $object->$prop;
    }else{
      return $default;
    }
  
    // Follow path
    if(!empty($path)){
      return mspecs_get($value, $path, $default);
    }
  
    return empty( $value ) && $default !== null ? $default : $value;
}

function mspecs_getify($object){
    return function($path, $default = null) use ($object){
        return mspecs_get($object, $path, $default);
    };
}

function mspecs_log($message){
  $args = func_get_args();
  $arg = false;
  if(func_num_args() > 1){
    foreach ($args as $arg) {
      mspecs_log($arg);
    }
  }else{
    $arg = array_pop($args);
    error_log('['.mspecs_get_log_caller().'] '.print_r($arg, true));
  }

  return $arg;
}

function mspecs_get_log_caller(){
  $caller = array(
    'file' => 'unknown',
    'line' => '0',
    'function' => 'unknown',
  );

  $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  foreach ($stack as $trace) {
    // Find first call in stack that is not a logging function
    if(!in_array($trace['function'], array('mspecs_log', 'mspecs_get_log_caller'))){
      $caller = $trace;
      break;
    }
  }

  return str_replace(MSPECS_PLUGIN_DIR, '', $caller['file']).':'.$caller['line'];
}