<?php
/*
 * Setting: geopost-settings
 * Tab: Import
 * Order: 20
 * Flow: Settings Workflow
 */

echo <<<HTML
<style>
  .bs-callout {
    padding: 5px 20px;
    margin: 20px 0;
    border: 1px solid #eee;
    border-left-color: #777;
    border-left-width: 5px;
    border-radius: 3px;
  }
  .bs-callout h4 {
    font-size: 16px;
    margin-top: 0;
    margin-bottom: 5px;
  }
  .bs-callout p {
    font-size: 14px;
    margin-top: 0;
    margin-bottom: 20px;
  }
</style>
<div class="bs-callout">
  <h4>Note:</h4>
  <p>Importing can take a while, especially if coordinates are being retrieved and the list is long. Also <strong>be careful</strong> of the google geocode usage limit (is 2000/day at this point).</p>
  <p>The best way to import is to first export and then reuse the structure provided.</p>
  <p>The ID column is a special column. If omitted, it will be assumed that all posts are new. If included, the post with the provided ID will be updated. It is acceptable for some posts to have an ID and not others in the same file.</p>
</div>
HTML;

piklist('field', array(
  'type'    => 'file',
  'field'   => 'import_file',
  'label'   => 'File',
  'options' => array(
    'basic'   => true
  )
));

piklist('field', array(
  'help'    => 'When to retrieve the latitude and longitude coordintes for the posts.',
  'type'    => 'radio',
  'field'   => 'retrieve_coordinates',
  'label'   => 'Retrieve Coordinates',
  'value'   => 'omitted',
  'choices' => array(
    'never'   => 'Do not retrieve any',
    'omitted' => 'Only if omitted',
    'always'  => 'For all of them'
  )
));

?>

<script type='text/javascript'>
(function($) {
  $(document).ready(function() {
    // Get submit button
    $submit = $('#submit');

    // Change submit title
    $submit.val('Import');

    // Remove 'Settings Saved' Message
    $('#setting-error-settings_updated').remove();
  })
})(jQuery);
</script>
