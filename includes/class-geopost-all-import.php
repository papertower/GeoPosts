<?php

/**
 * Extends WP All Import to retrieve coordinates upon import
 */
class GeoPostAllImport
{
    public static function load()
    {
        add_action('pmxi_saved_post', [__CLASS__, 'update_post_coordinates']);
    }

    public static function update_post_coordinates($id)
    {
        if (get_post_type($id) !== GeoPost::POST_TYPE) {
            return;
        }

        $address = self::get_post_address($id);

        if ($address !== get_post_meta($id, '_coordinate_address', true)) {
            $coordinates = GeoPost::get_coordinates($address);

            if (!is_wp_error($coordinates)) {
                update_post_meta($id, 'latitude', $coordinates->lat);
                update_post_meta($id, 'longitude', $coordinates->lng);
                update_post_meta($id, '_coordinate_address', $address);
            }
        }
    }

    private static function get_post_address($id)
    {
        return get_post_meta($id, 'street', true) . ' ' .
               get_post_meta($id, 'city', true) . ', ' .
               get_post_meta($id, 'state', true) . ' ' .
               get_post_meta($id, 'zip', true) . ' ' .
               get_post_meta($id, 'country', true);
    }
}
