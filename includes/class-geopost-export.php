<?php
class GeoPostExport {
  public static function load() {
    add_action( 'admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));

    add_action( 'wp_ajax_geopost_export', array(__CLASS__, 'ajax_export'));
  }

  public static function admin_enqueue_scripts() {
    if ( stripos($_SERVER['REQUEST_URI'], 'page=geopost-settings') !== FALSE
      && stripos($_SERVER['REQUEST_URI'], 'tab=export') !== FALSE )
      wp_enqueue_script( 'download-js', plugins_url('lib/download.js', GeoPost::$plugin_file ), array(), '3.1', true );
  }

  public static function ajax_export() {
    check_ajax_referer('geopost-export', 'security');

    // Extract request variables
    extract($_POST);

    // Retrieve posts
    $posts = get_posts(array(
      'post_type'   => GeoPost::POST_TYPE,
      'numberposts' => -1
    ));

    // Create file in memory
    $out = fopen('php://output', 'w');

    // Define and filter post keys
    $keys = array('post_name', 'post_author', 'post_title', 'post_date', 'post_date_gmt', 'post_status');
    $keys = apply_filters('geopost-export-posts', $keys);

    // Define and filter meta keys
    $meta_keys = array('latitude', 'longitude');
    $meta_keys = ( $all_meta ) ? $meta_keys = apply_filters('geopost-export-meta', $meta_keys) : $meta_keys;

    // Define the address keys
    $address_keys = array('address', 'city', 'state', 'zip_code');

    // Optionally append header
    if ( $add_header )
      fputcsv($out, array_merge($keys, $address_keys, $meta_keys));

    // Loop through and append posts
    foreach($posts as $post) {
      $line = array();

      // Append post sections
      foreach($keys as $index => $key)
        $line[] = $post->$key;

      // Retrieve post meta
      $meta = get_post_custom($post->ID);

      // Append address sections
      $address = unserialize($meta['address_group']);
      foreach($address_keys as $index => $key)
        $line[] = $address[$key];

      // Append selected meta
      foreach($meta_keys as $index => $key)
        $line[] = $meta[$key];

      // Write to file
      fputcsv($out, $line);
    }

    // Write contents to variable
    $output = stream_get_contents($out);
    fclose($out);

    die($output);
  }
}
?>
