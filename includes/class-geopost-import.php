<?php
class GeoPostImport {
  const NOTICE_SETTING = 'geopost-import-notice';

  public static function load() {
    // Intercept imported settings
    add_filter('piklist_pre_update_option_geopost-settings', array(__CLASS__, 'pre_update_option'), 10, 3);

    // Display notification messages
    add_filter('admin_head', array(__CLASS__, 'admin_head'));
  }

  public static function admin_head() {
    if ( get_option(self::NOTICE_SETTING) )
      add_action('admin_notices', array(__CLASS__, 'admin_notices'));
  }

  public static function admin_notices() {
    $notice = get_option(self::NOTICE_SETTING);
    echo "<div class='{$notice['type']}'><p>{$notice['message']}</p></div>";
    update_option(self::NOTICE_SETTING, 0);
  }

  private static function set_notification($type, $message) {
    if ( is_wp_error($message) )
      $message = $message->get_error_message();

    update_option(self::NOTICE_SETTING, array(
      'type'    => $type,
      'message' => $message
    ));
  }

  public static function pre_update_option($settings, $new, $old) {
    // Return if not import tab
    if ( !isset($new['import-file']) ) return $settings;

    // Check that csv is supported
    $mime_types = get_allowed_mime_types();
    if ( !array_key_exists('csv', $mime_types) ) {
      self::set_notification('error', 'Error: CSV file type not permitted. Please contact your site administrator.');
      return $old;
    }

    // Return if no file was provided
    if ( empty($new['import-file'][0][0]) ) {
      self::set_notification('error', 'Error: No file was provided');
      if ( class_exists('PhpConsole\Connector') )
        PC::missing_file($new['import-file']);
      return $old;
    }
    $file_id = $new['import-file'][0][0];

    // Retrieve csv and parse contents
    $file_path = get_attached_file($file_id);
    $contents = array_map('str_getcsv', file($file_path));
    $header = array_shift($contents);

    if ( empty($header) ) {
      self::set_notification('error', 'Error: The file provided was empty');
      return $old;
    }

    // We're done with the file, let's delete it
    wp_delete_attachment($file_id, true);

    // Requirements
    $post_keys    = apply_filters('geopost-import-required-posts', array('post_name', 'post_title', 'post_status'));
    $meta_keys    = apply_filters('geopost-import-required-meta', array());
    $address_keys = array('address', 'city', 'state', 'zip_code');

    // Check Required Keys
    $missing_keys = array_diff($post_keys, $header);
    $missing_keys = array_merge($missing_keys, array_diff($meta_keys, $header));
    $missing_keys = array_merge($missing_keys, array_diff($address_keys, $header));
    if ( !empty($missing_keys) ) return $old;

    // Apply optional keys
    $post_keys = array_merge($post_keys, array('post_author', 'post_date', 'post_date_gmt'));
    $meta_keys = array_merge($meta_keys, array());

    // Filter keys
    $post_keys = apply_filters('geopost-import-optional-posts', $post_keys);
    $meta_keys = apply_filters('geopost-import-optional-meta', $meta_keys);

    // Parse keys from header
    $key_indexes = array();
    foreach($header as $index => $key)
      $key_indexes[$key] = $index;

    // Defer database counting to speed things up
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);

    // Start a bulk transaction
    global $wpdb;
    $wpdb->query('SET autocommit = 0; START TRANSACTION');

    // Delete old posts if replacing
    if ( $new['import-type'] == 'replace' ) {
      // Retrieve all the old posts
      $old_posts = get_posts(array(
        'post_type'   => GeoPost::POST_TYPE,
        'numberposts' => -1,
        'fields'      => 'ids'
      ));

      // Delete all old posts and related rows (e.g. meta)
      foreach($old_posts as $index => $id) {
        if ( false === wp_delete_post($id) ) {
          self::finalize_queries(new WP_Error('', 'Failed to delete old posts'));
          return $old;
        }
      }
    }

    $count = 0;
    foreach($contents as $index => $data) {
      // Prepare post
      $post = array( 'post_type'   => GeoPost::POST_TYPE );
      foreach($post_keys as $sub_index => $key)
        if ( isset($key_indexes[$key]) )
          $post[$key] = $data[$key_indexes[$key]];

      // Insert post and check for errors
      $post_id = wp_insert_post($post, true);
      if ( is_wp_error($post_id) ) {
        self::finalize_queries($post_id);
        return $old;
      }

      // Insert post meta
      foreach($meta_keys as $sub_index => $key)
        if ( isset($key_indexes[$key]) )
          add_post_meta($post_id, $key, $data[$key_indexes[$key]]);

      // Insert address
      $address = array();
      foreach($address_keys as $sub_index => $key) {
        $address[$key] = array($data[$key_indexes[$key]]);
      }
      add_post_meta($post_id, 'address_group', $address);

      // Optionally retrieve coordinates
      $coordinates_included = ( !( empty($data[$key_indexes['latitude']]) || empty($data[$key_indexes['longitude']]) ) );
      $retrieve_condition = $new['retrieve-coordinates'];

      switch (true) {
      // Use provided coordinates
      case ( $coordinates_included && in_array($retrieve_condition, array('never', 'omitted')) ):
        add_post_meta($post_id, 'latitude', $data[$key_indexes['latitude']]);
        add_post_meta($post_id, 'longitude', $data[$key_indexes['longitude']]);
        break;

      // Request coordinates
      case ( $coordinates_included && $retrieve_condition = 'always' ):
      case ( !$coordinates_included && in_array($retrieve_condition, array('omitted', 'always')) ):
        $coordinates = self::get_coordinates($address);
        if ( is_wp_error($coordinates) ) {
          self::finalize_queries($coordinates);
          return $old;
        }

        add_post_meta($post_id, 'latitude', $coordinates->lat);
        add_post_meta($post_id, 'longitude', $coordinates->lng);
        break;

      }

      $count++;
    }

    self::finalize_queries();
    self::set_notification('updated', "$count Posts successfully imported");

    return $old;
  }

  private static function get_coordinates($address) {
    $full_address = $address['address'][0];
    $full_address .= " {$address['city'][0]}";
    $full_address .= " {$address['state'][0]}";
    $full_address .= " {$address['zip_code'][0]}";

    return GeoPost::get_coordinates($full_address);
  }

  private static function finalize_queries($error = false) {
    global $wpdb;

    // Rollback or Finalize
    if ( is_wp_error($error) ) {
      $wpdb->query('ROLLBACK;');
      self::set_notification('error', $error);
    } else
      $wpdb->query('COMMIT;');

    //Reset autocommit
    $wpdb->query('SET autocommit = 1;');

    // Make sure to turn counting back on!
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);
  }
}
?>
