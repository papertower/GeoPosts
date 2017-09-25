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
      'columns'   => 4,
      'sanitize'  => array(
        array(
          'type'    => 'text_field'
        )
      )
    ),
    array(
      'type'      => 'text',
      'field'     => 'state',
      'label'     => 'State / Region',
      'columns'   => 2,
      'sanitize'  => array(
        array(
          'type'    => 'text_field'
        )
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
    array(
      'type'      => 'text',
      'field'     => 'country',
      'label'     => 'Country',
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
