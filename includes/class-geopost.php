<?php

class GeoPost{
  const POST_TYPE   = 'geo-location';
  const SETTINGS    = 'geopost-settings';

  private static
    $_settings;

  public static
    $plugin_file,
    $plugin_path;

  private static function get_bounds_query($source, $bounds) {
    global $wpdb;

    $min_lat = $bounds['southwest']['latitude'];
    $max_lat = $bounds['northeast']['latitude'];
    $min_lng = $bounds['southwest']['longitude'];
    $max_lng = $bounds['northeast']['longitude'];

    return $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT
      {$wpdb->posts}.*, lat.meta_value AS latitude, lng.meta_value AS longitude,
      ( 3963.1676 * acos( cos( radians(%F) ) * cos( radians( lat.meta_value ) ) * cos( radians( lng.meta_value ) - radians(%F) ) + sin( radians(%F) ) * sin( radians( lat.meta_value ) ) ) ) AS distance

      FROM {$wpdb->posts}
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'latitude' ) as lat
          ON {$wpdb->posts}.ID = lat.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'longitude' ) as lng
          ON {$wpdb->posts}.ID = lng.post_id

      WHERE {$wpdb->posts}.post_type = %s AND
        lat.meta_value >= %F AND
        lat.meta_value <= %F AND
        lng.meta_value >= %F AND
        lng.meta_value <= %F

      ORDER BY distance;",
    $source->lat, $source->lng, $source->lat, self::POST_TYPE, $min_lat, $max_lat, $min_lng, $max_lng), 'OBJECT_K');
  }

  private static function get_radial_query($source, $distance) {
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
      "SELECT DISTINCT
        {$wpdb->posts}.ID, lat.meta_value AS latitude, lng.meta_value AS longitude,
        ( 3963.1676 * acos( cos( radians(%F) ) * cos( radians( lat.meta_value ) ) * cos( radians( lng.meta_value ) - radians(%F) ) + sin( radians(%F) ) * sin( radians( lat.meta_value ) ) ) ) AS distance

      FROM {$wpdb->posts}
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'latitude' ) as lat
          ON {$wpdb->posts}.ID = lat.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'longitude' ) as lng
          ON {$wpdb->posts}.ID = lng.post_id

      WHERE {$wpdb->posts}.post_type = %s

      HAVING distance <= %F

      ORDER BY distance;",
    $source->lat, $source->lng, $source->lat, self::POST_TYPE, $distance), 'OBJECT_K');
  }

  /**
   * Uses the GeoPost query vars to limit the query within a radial or rectangular area
   * @param  WP_Query $query
   */
  public static function pre_get_posts($query) {
    if ( self::POST_TYPE !== $query->get('post_type') || !isset($query->query_vars['location']) ) return;

    // Get primary location cordinates
    $primary_location = $query->query_vars['location'];
    if ( is_string($primary_location) ) {
      // Address provided
      $primary_location = self::get_coordinates($primary_location);
      if ( is_wp_error($primary_location) ) return;

    } else if ( is_array($primary_location) && isset($primary_location['latitude'], $primary_location['longitude']) ) {
      // Coordinates provided
      $primary_location = (object) array(
        'lat' => $primary_location['latitude'],
        'lng' => $primary_location['longitude']
      );

    } else {
      trigger_error("Invalid primary_location in GeoPost query: {$query->query_vars['primary_location']}", E_USER_ERROR);
    }

    if ( isset($query->query_vars['distance']) ) {
      // Radial Query
      $posts_to_include = self::get_radial_query($primary_location, $query->query_vars['distance']);

    } else if ( isset($query->query_vars['bounds']) ) {
      // Bounds Query
      $posts_to_include = self::get_bounds_query($primary_location, $query->query_vars['bounds']);

    } else {
      return;
    }

    if ( is_array($posts_to_include) ) {
      // Limit the final query to the ids of the retrieved posts
      $posts_in = $query->get('post__in');
      $posts_in = empty($posts_in) ? array_keys($posts_to_include) : array_intersect($posts_in, array_keys($posts_to_include));
      $query->set('post__in', empty($posts_in) ? array(-1) : $posts_in);

      // Pass the results to the query to be later applied to the results
      $query->set('geoposts', $posts_to_include);

    } else {
      // SQL Error occurred
      trigger_error("There was a SQL error returned in the GeoPost query: {$posts_to_include}");
    }
  }

  /**
   * Adds the latitude, longitude, and distance data to GeoPost query results
   * @param  array    $posts
   * @param  WP_Query $query
   * @return array            collection of posts with data added
   */
  public static function the_posts($posts, $query) {
    $geoposts = $query->get('geoposts');
    if ( empty($geoposts) ) return $posts;

    foreach($posts as &$post) {
      if ( isset($geoposts[$post->ID]) ) {
        $post->latitude   = $geoposts[$post->ID]->latitude;
        $post->longitude  = $geoposts[$post->ID]->longitude;
        $post->distance   = $geoposts[$post->ID]->distance;
      }
    }

    return $posts;
  }

  public static function load($plugin_file) {
    self::$plugin_file = $plugin_file;
    self::$plugin_path = plugin_dir_path($plugin_file);

    self::autoload_classes();

    // Extend posts queries to support geographical parameters
    add_action('pre_get_posts', array(__CLASS__, 'pre_get_posts'));
    add_filter('the_posts', array(__CLASS__, 'the_posts'), 10, 2);

    // Retrieve coordinates on post save/update
    add_action('save_post', array(__CLASS__, 'intercept_post_save'));

    // Display admin notices if any
    add_action('admin_head-post.php', array(__CLASS__, 'check_admin_notice'));
  }

  public static function autoload_classes() {
    $files = glob( self::$plugin_path . 'includes/*.php' );
    foreach($files as $index => $file) {
      if ( strpos($file, 'class-geopost-') !== false ) {
        include_once($file);

        $parts = explode('-', basename($file, '.php'));
        $class_name = 'GeoPost' . ucfirst($parts[2]);
        if ( class_exists($class_name) && method_exists($class_name, 'load') )
          call_user_func(array($class_name, 'load'));
      }
    }
  }

  public static function check_admin_notice() {
    if ( get_option('geopost_admin_notice') )
      add_action('admin_notices', array(__CLASS__, 'update_notice'));
  }

  public static function get_coordinates($address) {
    $settings = self::get_settings();
    $address = urlencode($address);

    if ( !empty($settings['use-key']) ) {
      if ( empty($settings['geocoding_api_key']) )
        return new WP_Error('Error', __('Failed to retrieve api key'));

      $api_key = $settings['geocoding_api_key'];
      $contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?key=$api_key&address=$address"));
    } else {
      $contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=$address"));
    }

    switch ( $contents->status ) {
      case 'OK':
        return $contents->results[0]->geometry->location;
      case 'REQUEST_DENIED':
        if ( $settings['use-key'] )
          return new WP_Error('Error', __('API Key Failed'));
        else
          return new WP_Error('Error', __('Usage limit exceeded'));
    }
  }

  public static function update_notice() {
    $notice = get_option('geopost_admin_notice');
    echo "<div class='error'><p>$notice</p></div>";
    update_option('geopost_admin_notice', 0);
  }

  public static function intercept_post_save( $post_id ) {
    if ( false !== wp_is_post_revision($post_id) || false !== wp_is_post_autosave($post_id) ) return;

    if ( !isset($_POST['post_type']) || ($_POST['post_type'] !== 'geo-location') ) return;

    if ( !isset($_POST['_post_meta']['street'], $_POST['_post_meta']['city'], $_POST['_post_meta']['state']) ) return;

    static $has_retrieved = false;
    if ( $has_retrieved ) return;

    // Retrieve address
    $address = implode(',',array(
      $_POST['_post_meta']['street'],
      $_POST['_post_meta']['city'],
      $_POST['_post_meta']['state'],
      isset($_POST['_post_meta']['zip']) ? $_POST['_post_meta']['zip'] : '',
    ));

    // GET Coordinates from Google GeoCode
    $coordinates = self::get_coordinates($address);

    if ( is_wp_error($coordinates) ) {
      update_option('geopost_admin_notice', "Error: {$coordinates->get_error_message()}");
      return;
    }

    update_post_meta($post_id, 'latitude', $coordinates->lat);
    update_post_meta($post_id, 'longitude', $coordinates->lng);

    $has_retrieved = true;
  }

  public static function get_settings() {
    if ( isset(self::$_settings) ) return self::$_settings;

    self::$_settings = get_option(self::SETTINGS);
    return self::$_settings;
  }
}
