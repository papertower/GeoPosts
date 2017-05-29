<?php
/*
Plugin Name: GeoPosts
Plugin URI: https://github.com/papertower/GeoPosts
Plugin Type: Piklist
Description: Creates posts that store geolocation information for relative lookup
Version: 1.0.0
Author: Paper Tower
Author URI: http://papertower.com
*/

// Check to make sure Piklist is activated
add_action('init', 'geo_initialize_plugin');
function geo_initialize_plugin()
{
  // Check for Piklist
  if(is_admin()) {
    include_once('includes/class-piklist-checker.php');

    if (!piklist_checker::check(__FILE__))
      return;
  }
}

if ( !class_exists('GeoPost') ) {
  require_once('includes/class-geopost.php');

  GeoPost::load(__FILE__);
}
