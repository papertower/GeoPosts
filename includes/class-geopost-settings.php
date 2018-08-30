<?php

class GeoPostSettings
{
    public static function load()
    {
        // Add Settings page
        add_filter('piklist_admin_pages', [__CLASS__, 'add_admin_page']);
    }

    public static function add_admin_page($pages)
    {
        $settings     = GeoPost::get_settings();
        $has_settings = (false !== $settings);
        $title        = ($has_settings && isset($settings['title'])) ? $settings['title'] : 'GeoPost';

        $pages[] = [
            'page_title' => __("$title Settings"),
            'menu_title' => __('Settings'),
            'sub_menu'   => 'edit.php?post_type=' . GeoPost::POST_TYPE,
            'capability' => 'manage_options',
            'menu_slug'  => 'geopost_settings',
            'setting'    => GeoPost::SETTINGS,
            'save_text'  => 'Save Settings'
        ];

        return $pages;
    }
}

?>
