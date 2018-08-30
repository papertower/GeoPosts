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

piklist('field', [
    'type'  => 'text',
    'field' => 'title',
    'label' => 'Post Title',
]);

piklist('field', [
    'type'  => 'text',
    'field' => 'slug',
    'label' => 'Post Slug',
]);
