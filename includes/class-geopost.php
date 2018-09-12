<?php

class GeoPost
{
    const POST_TYPE = 'geo-location';
    const SETTINGS = 'geopost-settings';

    private static
        $_settings;

    public static
        $plugin_file,
        $plugin_path;

    /**
     * Modifies the query clauses for location-based queries to perform geographical algorithms
     *
     * @param  array    $clauses
     * @param  WP_Query $query
     *
     * @return array    Modified clauses
     */
    public static function posts_clauses($clauses, $query)
    {
        if (self::POST_TYPE !== $query->get('post_type') || ! isset($query->query_vars['location'])) {
            return $clauses;
        }

        // Get primary location cordinates
        $primary_location = $query->query_vars['location'];
        if (is_string($primary_location)) {
            // Address provided
            $primary_location = self::get_coordinates($primary_location);
            if (is_wp_error($primary_location)) {
                return;
            }

        } elseif (is_array($primary_location) && isset($primary_location['latitude'], $primary_location['longitude'])) {
            // Coordinates provided
            $primary_location = (object)[
                'lat' => $primary_location['latitude'],
                'lng' => $primary_location['longitude']
            ];

        } else {
            trigger_error("Invalid primary_location in GeoPost query: {$query->query_vars['primary_location']}",
                E_USER_ERROR);
        }

        global $wpdb;

        $clauses['fields'] .= $wpdb->prepare(
            ",lat.meta_value AS latitude, lng.meta_value AS longitude,
      ( 3963.1676 * acos( cos( radians(%F) ) * cos( radians( lat.meta_value ) ) * cos( radians( lng.meta_value ) - radians(%F) ) + sin( radians(%F) ) * sin( radians( lat.meta_value ) ) ) ) AS distance
      ", $primary_location->lat, $primary_location->lng, $primary_location->lat);

        $clauses['join'] .= "
      INNER JOIN
        ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'latitude' ) as lat
        ON {$wpdb->posts}.ID = lat.post_id
      INNER JOIN
        ( SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'longitude' ) as lng
        ON {$wpdb->posts}.ID = lng.post_id
    ";

        $clauses['orderby'] = 'distance ASC';

        $clauses['distinct'] = 'DISTINCT';

        if (isset($query->query_vars['distance'])) {
            // Radial Query
            $clauses['groupby'] .= $wpdb->prepare(" $wpdb->posts.ID HAVING distance <= %F",
                (float)$query->query_vars['distance']);

        } elseif (isset($query->query_vars['bounds'])) {
            $min_lat = $bounds['southwest']['latitude'];
            $max_lat = $bounds['northeast']['latitude'];
            $min_lng = $bounds['southwest']['longitude'];
            $max_lng = $bounds['northeast']['longitude'];
            // Bounds Query
            $clauses['where'] .= $wpdb->prepare("
        AND lat.meta_value >= %F
        AND lat.meta_value <= %F
        AND lng.meta_value >= %F
        AND lng.meta_value <= %F
      ", $min_lat, $max_lat, $min_lng, $max_lng);

        } else {
            trigger_error('Invalid geoposts query. Either distance of bounds are required.', E_USER_WARNING);
        }

        return $clauses;
    }

    /**
     * Sets up the main properties and hooks
     *
     * @param  string $plugin_file path to the plugin
     */
    public static function load($plugin_file)
    {
        self::$plugin_file = $plugin_file;
        self::$plugin_path = plugin_dir_path($plugin_file);

        self::autoload_classes();

        // Extend posts queries to support geographical parameters
        add_filter('posts_clauses', [__CLASS__, 'posts_clauses'], PHP_INT_MAX, 20);

        // Retrieve coordinates on post save/update
        add_action('save_post', [__CLASS__, 'intercept_post_save']);

        // Display admin notices if any
        add_action('admin_head-post.php', [__CLASS__, 'check_admin_notice']);
    }

    /**
     * Autoloads the rest of the plugin classes
     */
    public static function autoload_classes()
    {
        $files = glob(self::$plugin_path . 'includes/*.php');
        foreach ($files as $index => $file) {
            if (strpos($file, 'class-geopost-') !== false) {
                include_once $file;

                $basename = basename($file, '.php');
                $class_name = 'GeoPost' . str_replace('-', '', ucwords(substr($basename, strlen('class-geopost-')), '-'));
                if (class_exists($class_name) && method_exists($class_name, 'load')) {
                    call_user_func([$class_name, 'load']);
                }
            }
        }
    }

    /**
     * Provides hook to display admin notices
     */
    public static function check_admin_notice()
    {
        if (get_option('geopost_admin_notice')) {
            add_action('admin_notices', [__CLASS__, 'update_notice']);
        }
    }

    /**
     * Returns the latitude and longitude coordintaes for a given address
     *
     * @param  string $address Address to lookup
     *
     * @return object          Object with the lat and lng coordinates
     */
    public static function get_coordinates($address)
    {
        $settings = self::get_settings();
        $address  = urlencode($address);

        if (empty($settings['use-key'])) {
            $use_key = false;
        } else {
            $use_key = is_array($settings['use-key']) ? $settings['use-key'][0] : $settings['use-key'];
        }

        if ($use_key) {
            if (empty($settings['geocoding_api_key'])) {
                return new WP_Error('Error', __('Failed to retrieve api key'));
            }

            $api_key  = $settings['geocoding_api_key'];
            $contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?key=$api_key&address=$address"));
        } else {
            $contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=$address"));
        }

        switch ($contents->status) {
            case 'OK':
                return $contents->results[0]->geometry->location;
            case 'REQUEST_DENIED':
                if ($settings['use-key']) {
                    return new WP_Error('Error', __('API Key was denied'));
                } else {
                    return new WP_Error('Error', __('Usage limit exceeded'));
                }
        }
    }

    /**
     * Renders the admin notice
     */
    public static function update_notice()
    {
        $notice = get_option('geopost_admin_notice');
        echo "<div class='error'><p>$notice</p></div>";
        update_option('geopost_admin_notice', 0);
    }

    /**
     * Retrieves the coordinates for the post being saved
     *
     * @param  integer $post_id
     */
    public static function intercept_post_save($post_id)
    {
        if (false !== wp_is_post_revision($post_id) || false !== wp_is_post_autosave($post_id)) {
            return;
        }

        if ( ! isset($_POST['post_type']) || ($_POST['post_type'] !== 'geo-location')) {
            return;
        }

        if ( ! isset($_POST['_post_meta']['street'], $_POST['_post_meta']['city'], $_POST['_post_meta']['state'])) {
            return;
        }

        static $has_retrieved = false;
        if ($has_retrieved) {
            return;
        }

        // Retrieve address
        $address = implode(',', [
            $_POST['_post_meta']['street'],
            $_POST['_post_meta']['city'],
            $_POST['_post_meta']['state'],
            isset($_POST['_post_meta']['zip']) ? $_POST['_post_meta']['zip'] : '',
            isset($_POST['_post_meta']['country']) ? $_POST['_post_meta']['country'] : ''
        ]);

        // GET Coordinates from Google GeoCode
        $coordinates = self::get_coordinates($address);

        if (is_wp_error($coordinates)) {
            update_option('geopost_admin_notice', "Error: {$coordinates->get_error_message()}");

            return;
        }

        update_post_meta($post_id, 'latitude', $coordinates->lat);
        update_post_meta($post_id, 'longitude', $coordinates->lng);

        $has_retrieved = true;
    }

    /**
     * Retrieves the plugin settings
     * @return array
     */
    public static function get_settings()
    {
        if (isset(self::$_settings)) {
            return self::$_settings;
        }

        self::$_settings = get_option(self::SETTINGS);

        return self::$_settings;
    }
}
