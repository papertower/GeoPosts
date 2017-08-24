<?php
class GeoPostRestController extends WP_REST_Posts_Controller {
  /**
   * Overrides the parent get_items function to add properties to the post before being sent out
   * @param  WP_REST_Request $request request object about to get sent out
   * @return WP_REST_Request
   */
  public function get_items($request) {
    add_filter('rest_' . GeoPost::POST_TYPE . '_query', array($this, 'rest_query'), 10, 2);

    $response = parent::get_items($request);

    foreach($response->data as &$post) {
      if ( !isset($post['latitude']) ) {
        $post['latitude'] = (float) get_post_meta($post['id'], 'latitude', true);
      }

      if ( !isset($post['longitude']) ) {
        $post['longitude'] = (float) get_post_meta($post['id'], 'longitude', true);
      }

      $post['address'] = array(
        'street' => get_post_meta($post['id'], 'street', true),
        'city'   => get_post_meta($post['id'], 'city', true),
        'state'  => get_post_meta($post['id'], 'state', true),
        'zip'    => get_post_meta($post['id'], 'zip', true),
      );

      $post['address']['full'] = $post['address']['street'];
      if ( $post['address']['city'] ) $post['address']['full'] .= ", {$post['address']['city']}";
      if ( $post['address']['state'] ) $post['address']['full'] .= " {$post['address']['state']}";
      if ( $post['address']['zip'] ) $post['address']['full'] .= " {$post['address']['zip']}";
    }

    return apply_filters('geopost_rest_get_items_response', $response, $request);
  }

  /**
   * Filters the rest query for this post type to add support for location parameters
   * @param  array            $vars    wp_query arguments
   * @param  WP_REST_Request  $request corresponding request object
   * @return array            arguments with the added parameters, when applicable
   */
  public function rest_query($vars, $request) {
    if ( isset($request['location']) ) {
      $vars['location'] = is_array($request['location']) ? array(
        'latitude'  => $request['location'][0],
        'longitude' => $request['location'][1],
      ) : $request['location'];
    }

    if ( isset($request['distance']) ) {
      $vars['distance'] = $request['distance'];
    }

    if ( isset($request['bounds']) && 4 === count($request['bounds']) ) {
      $vars['bounds'] = array(
        'northeast' => array('latitude' => $request['bounds'][0], 'longitude' => $request['bounds'][1]),
        'southwest' => array('latitude' => $request['bounds'][2], 'longitude' => $request['bounds'][3]),
      );
    }

    return apply_filters('geopost_rest_query_vars', $vars, $request);
  }

  public function prepare_item_for_database($request) {
    $post = parent::prepare_item_for_database($request);

    if ( !isset($post['meta_input']) ) {
      $post['meta_input'] = array();
    }

    if ( !empty($request['latitude']) ) {
      $post['meta_input']['latitude'] = $request['latitude'];
    }

    if ( !empty($request['longitude']) ) {
      $post['meta_input']['longitude'] = $request['longitude'];
    }

    return $post;
  }

  public function prepare_item_for_response($post, $request) {
    $response = parent::prepare_item_for_response($post, $request);

    if ( isset($post->distance) ) {
      $response->data['distance'] = $post->distance;
    }

    if ( isset($post->latitude) ) {
      $response->data['latitude'] = $post->latitude;
    }

    if ( isset($post->longitude) ) {
      $response->data['longitude'] = $post->longitude;
    }

    return $response;
  }

  public function get_collection_params() {
    $query_parms = parent::get_collection_params();

    $query_parms['distance'] = array(
      'description' => __('The maximum distance from the post to the location provided.'),
      'type'        => 'number',
    );

    $query_parms['location'] = array(
      'description' => __('The central location all posts are measured against. Format is: latitude, longitude'),
      'type'        => array('array', 'string'),
      'items'  => array(
        'type'      => 'number'
      )
    );

    $query_parms['bounds'] = array(
      'description' => __('The rectangular bounds the post results will be limited to. Format is: northeast-lat, northeast-lng, southwest-lat, southwest-lng'),
      'type'        => 'array',
      'items'  => array(
        'type'      => 'number'
      )
    );

    return $query_parms;
  }

  public function get_item_schema() {
    $schema = parent::get_item_schema();

    $schema['properties']['distance'] = array(
      'description' => __('The distance of the post relative to the location provided in the request.'),
      'type'        => 'number',
      'context'     => array('view')
    );

    $schema['properties']['latitude'] = array(
      'description' => __('The latitude of the post location.'),
      'type'        => 'number',
      'context'     => array('view', 'edit')
    );

    $schema['properties']['longitude'] = array(
      'description' => __('The longitude of the post location.'),
      'type'        => 'number',
      'context'     => array('view', 'edit')
    );

    return $schema;
  }
}
