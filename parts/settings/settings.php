<?php
/*
 * Setting: geopost-settings
 * Order: 10
 * Flow: Settings Workflow
 * Tab: General
 */

piklist('field', [
    'description' => '<br>It is strongly recommended an API key is used. This allows you to monitor api limitations and ' .
                     'Google may contact you if you reach your limit. If no key is provided and the limit is exceeded, it will stop ' .
                     'working immediately.',
    'type'        => 'radio',
    'field'       => 'use-key',
    'label'       => 'Use API Key',
    'value'       => true,
    'list'        => false,
    'choices'     => [
        true  => 'Yes',
        false => 'No'
    ]
]);

piklist('field', [
    'type'       => 'text',
    'field'      => 'geocoding_api_key',
    'label'      => 'Google Geocoding API Key',
    'columns'    => 6,
    'conditions' => [
        [
            'field' => 'use-key',
            'value' => 1
        ]
    ]
]);

$settings = GeoPost::get_settings();
$has_settings = ( false !== $settings );

$title  = ($has_settings && isset($settings['title'])) ? $settings['title'] : 'GeoPost';
$slug   = ($has_settings && isset($settings['slug'])) ? $settings['slug'] : 'geopost';

$arguments = GeoPostModel::get_post_arguments();

if ( $arguments['title'] === $title ) {
    piklist('field', array(
        'type'    => 'text',
        'field'   => 'title',
        'label'   => 'Post Title',
    ));
} else {
    piklist('field', array(
        'type'    => 'html',
        'label'   => 'Post Title',
        'value'   => 'Title is set by the theme or another plugin'
    ));
}

if ( $arguments['rewrite']['slug'] === $slug ) {
    piklist('field', array(
        'type'    => 'text',
        'field'   => 'slug',
        'label'   => 'Post Slug',
    ));
} else {
    piklist('field', array(
        'type'    => 'html',
        'label'   => 'Post Slug',
        'value' => 'Slug is set by the theme or another plugin'
    ));
}

