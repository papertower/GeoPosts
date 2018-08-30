<?php

class GeoPostExport
{
    public static function load()
    {
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts']);

        add_action('wp_ajax_geopost_export', [__CLASS__, 'ajax_export']);
    }

    public static function admin_enqueue_scripts()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'geopost_settings'
            && isset($_GET['_']['flow']) && 'settings_workflow' === $_GET['_']['flow']
            && isset($_GET['_']['flow_page']) && 'export' === $_GET['_']['flow_page']) {

            wp_enqueue_script('download-js', plugins_url('lib/download.min.js', GeoPost::$plugin_file), [], '4.2',
                true);
        }
    }

    public static function ajax_export()
    {
        check_ajax_referer('geopost-export', 'security');

        // Extract request variables
        extract($_POST);

        // Retrieve posts
        $posts = get_posts([
            'post_type'   => GeoPost::POST_TYPE,
            'numberposts' => -1
        ]);

        // Create file in memory
        $out = fopen('php://output', 'w');

        // Define and filter post keys
        $keys = ['ID', 'post_name', 'post_author', 'post_title', 'post_date', 'post_date_gmt', 'post_status'];
        $keys = apply_filters('geopost_export_post_keys', $keys);

        // Define and filter meta keys
        $meta_keys = ['latitude', 'longitude', 'street', 'city', 'state', 'zip', 'country'];
        $meta_keys = ($all_meta) ? $meta_keys = apply_filters('geopost_export_meta_keys', $meta_keys) : $meta_keys;

        $extra_keys = apply_filters('geopost_export_extra_keys', []);

        // Optionally append header
        if ($add_header) {
            if ( ! fputcsv($out, array_merge($keys, $meta_keys, $extra_keys))) {
                wp_send_json_error('Error: Failed to add the header. This probably means invalid meta keys were provided. Please notify the theme developer.');
            }
        }

        // Loop through and append posts
        foreach ($posts as $post) {
            $line = [];

            // Append post sections
            foreach ($keys as $index => $key) {
                $line[] = apply_filters('geopost_export_post_value', $post->$key, $key);
            }

            // Retrieve post meta
            $meta = get_post_custom($post->ID);

            // Append selected meta
            foreach ($meta_keys as $index => $key) {
                if (isset($meta[$key][1])) {
                    $value = serialize($meta[$key]);
                } else {
                    $value = $meta[$key][0];
                }

                $line[] = apply_filters('geopost_export_meta_value', $value, $key, $post);
            }

            // Append extra keys
            foreach ($extra_keys as $key) {
                $line[] = apply_filters('geopost_export_extra_value', '', $key, $post);
            }

            // Write to file
            if ( ! fputcsv($out, $line)) {
                wp_send_json_error('Error: There was a problem adding the following line to the CSV file. Please check for issues: ' . implode(', ',
                        $line));
            }
        }

        // Write contents to variable
        $output = stream_get_contents($out);
        fclose($out);

        die($output);
    }
}
