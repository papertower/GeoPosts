<?php
/**
 * Title: Location
 * Post Type: geo-location
 * Context: normal
 * Priority: high
 */

piklist('field', array(
  'type'    => 'group',
  'label'   => 'Address',
  'list'    => false,
  'fields'  => array(
    array(
      'type'      => 'text',
      'field'     => 'street',
      'label'     => 'Street Address',
      'columns'   => 12,
      'sanitize'  => array(
        array(
          'type'    => 'text_field'
        )
      )
    ),
    array(
      'type'      => 'text',
      'field'     => 'city',
      'label'     => 'City',
      'columns'   => 5,
      'sanitize'  => array(
        array(
          'type'    => 'text_field'
        )
      )
    ),
    array(
      'type'      => 'select',
      'field'     => 'state',
      'label'     => 'State',
      'columns'   => 4,
      'choices'   => array(
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District Of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
      )
    ),
    array(
      'type'      => 'text',
      'field'     => 'zip',
      'label'     => 'Zip Code',
      'columns'   => 3,
      'sanitize'  => array(
        array(
          'type'    => 'text_field'
        )
      )
    ),
  )
));

piklist('field', array(
  'type'    => 'group',
  'label'   => 'Coordinates',
  'fields'  => array(
    array(
      'type'        => 'text',
      'field'       => 'latitude',
      'label'       => 'Latitude',
      'display'     => true,
      'columns'     => 3,
    ),
    array(
      'type'        => 'text',
      'field'       => 'longitude',
      'label'       => 'Longitude',
      'display'     => true,
      'columns'     => 3,
    )
  )
));
