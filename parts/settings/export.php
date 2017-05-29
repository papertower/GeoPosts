<?php
/*
 * Setting: geopost-settings
 * Tab: Export
 * Order: 30
 * Flow: Settings Workflow
*/

echo '<p id="exportError" style="display: none;
    color: red;
    border: solid;
    border-width: 2px;
    padding: 1em;
    font-size: 1.2em;"></p>';

piklist('field', array(
  'type'    => 'select',
  'field'   => 'include-custom-meta',
  'label'   => 'Include custom meta',
  'value'   => true,
  'choices' => array(
    true      => 'True',
    false    => 'False'
  )
));

piklist('field', array(
  'type'    => 'select',
  'field'   => 'include-headers',
  'label'   => 'Include Headers',
  'value'   => true,
  'choices' => array(
    true      => 'True',
    false    => 'False'
  )
));

?>

<script type='text/javascript'>
(function($) {
  $(document).ready(function() {
    // Get jquery objects
    $submit = $('#submit');

    // Change submit title
    $submit.val('Download CSV');

    $submit.click(function(event) {
      event.preventDefault();

      var options = {
        action:     'geopost_export',
        all_meta:   $('.geopost-settings_include-custom-meta option:selected').val(),
        add_header: $('.geopost-settings_include-headers option:selected').val(),
        security:   '<?php echo wp_create_nonce('geopost-export'); ?>'
      };

      $.post(ajaxurl, options, function(data) {
        if ( typeof data === 'object' ) {
          $('#exportError').text(data.data).fadeIn();
        } else {
          download(data, 'GeoPosts.csv', 'text/csv');
        }
      })
    });
  })
})(jQuery);
</script>
