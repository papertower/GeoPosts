<?php
class GeoPostModel {
  public static function load() {
    // Add Post Type & Taxonomies
    add_filter('piklist_post_types', array(__CLASS__, 'add_post_type'));
  }

  public static function add_post_type($post_types) {
    $settings = GeoPost::get_settings();
    $has_settings = ( false !== $settings );

    $title  = ($has_settings && isset($settings['title'])) ? $settings['title'] : 'GeoPost';
    $slug   = ($has_settings && isset($settings['slug'])) ? $settings['slug'] : 'geopost';

    $post_types[GeoPost::POST_TYPE] = apply_filters('geopost_post_data', array(
      'labels'                => piklist('post_type_labels', $title),
      'title'                 => $title,
      'supports'              => array( 'title' ),
      'public'                => true,
      'has_archive'           => true,
      'rewrite'               => array('slug' => $slug),
      'capability_type'       => 'post',
      'post_states'           => false,
      'show_in_rest'          => true,
      'rest_controller_class' => 'GeoPostRestController'
    ));

    return $post_types;
  }
}
