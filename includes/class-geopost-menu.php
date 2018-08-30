<?php

class GeoPostMenu
{
    public static function load()
    {
        // Adjust all posts table
        add_filter('manage_' . GeoPost::POST_TYPE . '_posts_columns', [__CLASS__, 'manage_posts_columns'], 99);
        add_action('manage_' . GeoPost::POST_TYPE . '_posts_custom_column', [__CLASS__, 'manage_posts_custom_column'],
            10, 2);

        // Add extra filters
        /*
          Note: This is unfinished and would require wordpress to support
          custom SQL queries for the posts table.

          add_action('restrict_manage_posts', array(__CLASS__, 'restrict_manage_posts'));
          add_filter('parse_query', array(__CLASS__, 'parse_query'));
        */
    }

    public static function manage_posts_columns($columns)
    {
        return [
            'cb'          => '<input type="checkbox" />',
            'title'       => __('Title'),
            'address'     => __('Address'),
            'coordinates' => __('Coordinates'),
            'date'        => __('Date'),
        ];
    }

    public static function manage_posts_custom_column($column_name, $post_id)
    {
        switch ($column_name) {
            case 'address':
                $address = [
                    'street' => get_post_meta($post_id, 'street', true),
                    'city'   => get_post_meta($post_id, 'city', true),
                    'state'  => get_post_meta($post_id, 'state', true),
                    'zip'    => get_post_meta($post_id, 'zip', true),
                ];

                if ( ! ($address['street'] && $address['city'] && $address['state'])) {
                    echo "No address available";

                    return;
                }

                if ($address['street']) {
                    echo $address['street'] . '<br>';
                }

                if ($address['city']) {
                    echo $address['city'] . ', ';
                }

                if ($address['state']) {
                    echo $address['state'] . ' ';
                }

                if ($address['zip']) {
                    echo $address['zip'];
                }

                break;

            case 'coordinates':
                $latitude  = get_post_meta($post_id, 'latitude', true);
                $longitude = get_post_meta($post_id, 'longitude', true);

                if ($latitude && $longitude) {
                    echo "Latitude: $latitude<br>Longitude: $longitude";
                } else {
                    echo "No coordinates";
                }

                break;
        }
    }

    public static function restrict_manage_posts()
    {
        $screen = get_current_screen();
        if ($screen->post_type !== self::POST_TYPE) {
            return;
        }

        $distances = [10, 25, 50, 100, 250, 500];

        $distance_list = '<option value>Distance</option>';
        foreach ($distances as $index => $distance) {
            $distance_list .= "<option value='$distance'>$distance mi</option>";
        }

        echo "
      <fieldset>
        <select name='distance'>
          $distance_list
        </select>
        <input type='text' name='address' placeholder='Address'>
      </fieldset>
    ";
    }

    public static function parse_query($query)
    {
        if ( ! is_admin() || $query->query['post_type'] != self::POST_TYPE) {
            return;
        }
    }
}

?>
