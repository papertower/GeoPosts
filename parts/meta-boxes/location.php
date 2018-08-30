<?php
/**
 * Title: Location
 * Post Type: geo-location
 * Context: normal
 * Priority: high
 */

piklist('field', [
    'type'   => 'group',
    'label'  => 'Address',
    'list'   => false,
    'fields' => [
        [
            'type'     => 'text',
            'field'    => 'street',
            'label'    => 'Street Address',
            'columns'  => 12,
            'sanitize' => [
                [
                    'type' => 'text_field'
                ]
            ]
        ],
        [
            'type'     => 'text',
            'field'    => 'city',
            'label'    => 'City',
            'columns'  => 4,
            'sanitize' => [
                [
                    'type' => 'text_field'
                ]
            ]
        ],
        [
            'type'     => 'text',
            'field'    => 'state',
            'label'    => 'State / Region',
            'columns'  => 2,
            'sanitize' => [
                [
                    'type' => 'text_field'
                ]
            ]
        ],
        [
            'type'     => 'text',
            'field'    => 'zip',
            'label'    => 'Zip Code',
            'columns'  => 3,
            'sanitize' => [
                [
                    'type' => 'text_field'
                ]
            ]
        ],
        [
            'type'     => 'text',
            'field'    => 'country',
            'label'    => 'Country',
            'columns'  => 3,
            'sanitize' => [
                [
                    'type' => 'text_field'
                ]
            ]
        ],
    ]
]);

piklist('field', [
    'type'   => 'group',
    'label'  => 'Coordinates',
    'fields' => [
        [
            'type'    => 'text',
            'field'   => 'latitude',
            'label'   => 'Latitude',
            'display' => true,
            'columns' => 3,
        ],
        [
            'type'    => 'text',
            'field'   => 'longitude',
            'label'   => 'Longitude',
            'display' => true,
            'columns' => 3,
        ]
    ]
]);
