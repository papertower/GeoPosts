<?php
/*
Plugin Name: GeoPosts
Plugin URI: http://papertower.com
Plugin Type: Piklist
Description: Creates posts that store geolcation information for relative lookup
Version: 0.2.0
Author: Jason Adams
Author URI: http://jasontheadams.com
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

?>
