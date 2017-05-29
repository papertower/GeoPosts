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
    if ( !isset($new['import_file']) ) return $settings;

    // Check that csv is supported
    $mime_types = get_allowed_mime_types();
    if ( !array_key_exists('csv', $mime_types) ) {
      self::set_notification('error', 'Error: CSV file type not permitted. Please contact your site administrator');
      return $old;
    }

    // Return if no file was provided
    if ( empty($new['import_file'][0]) ) {
      self::set_notification('error', 'Error: No file was provided');
      return $old;
    }
    $file_id = $new['import_file'][0];

    // Retrieve csv and parse contents
    $file_path = get_attached_file($file_id);
    $contents = array_map('str_getcsv', file($file_path));
    $header = array_shift($contents);

    if ( empty($header) ) {
      self::set_notification('error', 'Error: The file provided did not have the proper headers');
      return $old;
    }

    // We're done with the file, let's delete it
    wp_delete_attachment($file_id, true);

    $count = self::import_post_from_data($contents, $header, $new);

    if ( is_wp_error($count) ) {
      self::finalize_queries($count);
    } else {
      self::finalize_queries();
      self::set_notification('updated', "$count Posts successfully imported");
    }

    return $old;
  }

  private static function import_post_from_data($contents, $header, $options) {
    // Requirements
    $post_keys    = apply_filters('geopost-import-required-posts', array('post_name', 'post_title', 'post_status'));
    $meta_keys    = apply_filters('geopost-import-required-meta', array('street', 'city', 'state', 'zip'));

    // Check Required Keys
    $missing_keys = array_diff($post_keys, $header);
    $missing_keys = array_merge($missing_keys, array_diff($meta_keys, $header));
    if ( !empty($missing_keys) ) {
      self::set_notification('error', 'Error: The following required columns were missing: ' . explode(', ', $missing_keys));
      return $old;
    }

    // Gather optional keys
    $optional_meta = apply_filters('geopost-import-optional-meta', array());
    $optional_post = apply_filters('geopost-import-optional-post-columns', array('ID', 'post_author', 'post_date', 'post_date_gmt'));

    // Apply optional keys
    $post_keys = array_merge($post_keys, $optional_post);
    $meta_keys = array_merge($meta_keys, $optional_meta);

    // Parse keys from header
    $key_indexes = array();
    foreach($header as $index => $key)
      $key_indexes[$key] = $index;


    // Defer database counting to speed things up
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);

    $count = 0;
    foreach($contents as $index => $data) {
      // Prepare post
      $post = array(
        'post_type'   => GeoPost::POST_TYPE,
        'meta_input'  => array()
      );

      // Add post data from row
      foreach($post_keys as $key) {
        if ( isset($key_indexes[$key]) ) {
          switch($key) {
            // Make sure the dates are formatted properly as some programs (e.g. Excel) will
            // automatically change the date formats
            case 'post_date':
            case 'post_date_gmt':
            case 'post_modified':
            case 'post_modified_gmt':
              if ( $data[$key_indexes[$key]] ) {
                $post[$key] = date('Y-m-d H:i:s', strtotime($data[$key_indexes[$key]]));
              }
              break;

            default:
              $post[$key] = apply_filters("geopost-import-post-data", $data[$key_indexes[$key]], $key, $data);
          }
        }
      }

      // Add post meta from row
      foreach($meta_keys as $key) {
        if ( isset($key_indexes[$key])) {
          $post['meta_input'][$key] = $data[$key_indexes[$key]];
        }
      }

      // Optionally retrieve coordinates
      $coordinates_included = ( !( empty($data[$key_indexes['latitude']]) || empty($data[$key_indexes['longitude']]) ) );
      $retrieve_condition = is_array($options['retrieve_coordinates']) ? $options['retrieve_coordinates'][0] : $options['retrieve_coordinates'];

      switch(true) {
        // Use provided coordinates
        case ( $coordinates_included && in_array($retrieve_condition, array('never', 'omitted')) ):
          $post['meta_input']['latitude'] = $data[$key_indexes['latitude']];
          $post['meta_input']['longitude'] = $data[$key_indexes['longitude']];
          break;

        // Request coordinates
        case ( $coordinates_included && $retrieve_condition = 'always' ):
        case ( !$coordinates_included && in_array($retrieve_condition, array('omitted', 'always')) ):
          $coordinates = GeoPost::get_coordinates("{$data['street']} {$data['city']} {$data['state']} {$data['zip']}");

          if ( is_wp_error($coordinates) ) {
            return $coordinates;
          }

          $post['meta_input']['latitude'] = $coordinates->lat;
          $post['meta_input']['longitude'] = $coordinates->lng;
          break;

      }

      // Insert post and check for errors
      $post_id = wp_insert_post($post, true);
      if ( is_wp_error($post_id) ) {
        return $post_id;
      }

      $count++;
    }

    return $count;
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
