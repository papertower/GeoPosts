<?php

class GeoPost{
  const POST_TYPE   = 'geo-location';
  const SETTINGS    = 'geopost-settings';

  private static
    $_settings;

  public static
    $plugin_file,
    $plugin_path;


  /**
  * Retrieve the local posts
  * @param  string  $address
  * @param  float   $distance   in miles
  * @return array   containing objects
  */
  public static function get_posts(array $args) {
    global $wpdb;

    extract(wp_parse_args($args, array(
      'type'      => 'radial',
      'distance'  => false,
      'address'   => false,
      'bounds'    => false,
      'latitude'  => false,
      'longitude' => false,
      'tax_query' => false,
      'meta_query'=> false,
      'post__in'  => false
    )));

    // Check for required arguments
    $has_coordinates = ( $latitude && $longitude );
    $has_location = ( $address || $has_coordinates );

    if ( !($has_coordinates || $has_location) )
      return new WP_Error('Error', __('Coordinates or address required'));

    // Retrieve coordinates from address
    if ( $has_coordinates ) {
      $source = new stdclass;
      $source->lat = $latitude;
      $source->lng = $longitude;
    } else {
      $source = self::get_coordinates($address);

      if ( is_wp_error($source) )
        return $source;
    }

    // Check if tax_query is included
    if ( $tax_query ) {
      $tax_query = new WP_Tax_Query($tax_query);
      $tax_sql = $tax_query->get_sql( $wpdb->posts, 'ID' );
      if ( $tax_sql['join'] === '' )
        return new WP_Error('Error', __('Invalid tax_query parameters'));
    } else {
      // Fill as empty
      $tax_sql['join'] = $tax_sql['where'] = '';
    }

    if ( $meta_query ) {
      $meta_query = new WP_Meta_Query($meta_query);
      $meta_sql = $meta_query->get_sql('post', $wpdb->posts, 'ID');
      if ( '' === $meta_sql['join'] ) {
        return new WP_Error('meta_query_eror', __('Invalid meta_query parameters'));
      }
    } else {
      $meta_sql['join'] = $meta_sql['where'] = '';
    }

    // Additional where clause
    $where = '';
    if ( $post__in ) {
      $post__in = implode(',', array_map('absint', $post__in));
      $where .= " AND {$wpdb->posts}.ID IN ($post__in)";
    }

    // Get SQL based on type
    switch($type) {
      case 'radial':
        if ( !$distance )
          return new WP_Error('Error', __('Must provide distance'));

        $sql = self::get_radial_query($source, $distance, $where, $tax_sql, $meta_sql);
        break;

      case 'boundary':
        if ( !$bounds )
          return new WP_Error('Error', __('Must provide boundries'));

        $sql = self::get_bounds_query($source, $bounds, $where, $tax_sql, $meta_sql);
        break;

      default:
        return new WP_Error('Error', __("Invalid query type: $type"));
    }

    // Query Location
    $results = $wpdb->get_results($sql);

    if ( null === $results )
      return new WP_Error('GeoPost::local_posts', __('SQL returned error'), $wpdb->last_error);

    foreach($results as &$result) {
      $result->address = maybe_unserialize($result->address);
    }

    return $results;
  }

  private static function get_bounds_query($source, $bounds, $where, $tax_sql, $meta_sql) {
    global $wpdb;

    $min_lat = $bounds['southwest']['latitude'];
    $max_lat = $bounds['northeast']['latitude'];
    $min_lng = $bounds['southwest']['longitude'];
    $max_lng = $bounds['northeast']['longitude'];

    return $wpdb->prepare(
    "SELECT DISTINCT
      {$wpdb->posts}.*, lat.meta_value AS latitude, lng.meta_value AS longitude, ad.meta_value AS address,
      ( 3963.1676 * acos( cos( radians(%F) ) * cos( radians( lat.meta_value ) ) * cos( radians( lng.meta_value ) - radians(%F) ) + sin( radians(%F) ) * sin( radians( lat.meta_value ) ) ) ) AS distance

      FROM {$wpdb->posts}
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'address_group' ) as ad
          ON {$wpdb->posts}.ID = ad.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'latitude' ) as lat
          ON {$wpdb->posts}.ID = lat.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'longitude' ) as lng
          ON {$wpdb->posts}.ID = lng.post_id
        {$meta_sql['join']}
        {$tax_sql['join']}

      WHERE {$wpdb->posts}.post_type = %s AND
        {$wpdb->posts}.post_status = 'publish' AND
        lat.meta_value >= %F AND
        lat.meta_value <= %F AND
        lng.meta_value >= %F AND
        lng.meta_value <= %F
        {$where}
        {$meta_sql['where']}
        {$tax_sql['where']}

      ORDER BY distance;",
    $source->lat, $source->lng, $source->lat, self::POST_TYPE, $min_lat, $max_lat, $min_lng, $max_lng);
  }

  private static function get_radial_query($source, $distance, $where, $tax_sql, $meta_sql) {
    global $wpdb;

    return $wpdb->prepare(
      "SELECT DISTINCT
        {$wpdb->posts}.*, lat.meta_value AS latitude, lng.meta_value AS longitude, ad.meta_value AS address,
        ( 3963.1676 * acos( cos( radians(%F) ) * cos( radians( lat.meta_value ) ) * cos( radians( lng.meta_value ) - radians(%F) ) + sin( radians(%F) ) * sin( radians( lat.meta_value ) ) ) ) AS distance

      FROM {$wpdb->posts}
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'address_group' ) as ad
          ON {$wpdb->posts}.ID = ad.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'latitude' ) as lat
          ON {$wpdb->posts}.ID = lat.post_id
        INNER JOIN
          ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'longitude' ) as lng
          ON {$wpdb->posts}.ID = lng.post_id
        {$meta_sql['join']}
        {$tax_sql['join']}

      WHERE {$wpdb->posts}.post_type = %s AND
        {$wpdb->posts}.post_status = 'publish'
        {$where}
        {$meta_sql['where']}
        {$tax_sql['where']}

      HAVING distance <= %F

      ORDER BY distance;",
    $source->lat, $source->lng, $source->lat, self::POST_TYPE, $distance);
  }

  public static function load($plugin_file) {
    self::$plugin_file = $plugin_file;
    self::$plugin_path = plugin_dir_path($plugin_file);

    self::autoload_classes();

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

    if ( false !== $settings && $settings['use-key'] ) {
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

    if ( !isset($_POST) || !isset($_POST['post_type']) || ($_POST['post_type'] !== 'geo-location') )
      return;

    if ( !isset($_POST['_post_meta']['address_group']) ) return;

    static $has_retrieved = false;
    if ( $has_retrieved ) return;

    // Retrieve address
    $group = $_POST['_post_meta']['address_group'];

    $address = implode(',',array(
      $group['street'],
      $group['city'],
      $group['state'],
      $group['zip']
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

?>
