<?php

class GeoPostModel
{
    public static function load()
    {
        // Add Post Type & Taxonomies
        add_filter('piklist_post_types', [__CLASS__, 'add_post_type']);
    }

    public static function add_post_type($post_types)
    {
        $post_types[GeoPost::POST_TYPE] = self::get_post_arguments();

        return $post_types;
    }

    public static function get_post_arguments()
    {
        $settings     = GeoPost::get_settings();
        $has_settings = (false !== $settings);

        $title = ($has_settings && isset($settings['title'])) ? $settings['title'] : 'GeoPost';
        $slug  = ($has_settings && isset($settings['slug'])) ? $settings['slug'] : 'geopost';

        return apply_filters('geopost_post_data', [
            'title'                 => $title,
            'labels'                => piklist('post_type_labels', $title),
            'supports'              => ['title'],
            'public'                => true,
            'has_archive'           => true,
            'rewrite'               => ['slug' => $slug],
            'capability_type'       => 'post',
            'post_states'           => false,
            'show_in_rest'          => true,
            'rest_controller_class' => 'GeoPostRestController'
        ]);
    }
}
