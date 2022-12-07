<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022, Vinos de Frutas Tropicales.
//
// This script is loaded based on a notification that a page's rendering is complete and the </body> tag
// is about to be rendered by /includes/classes/observers/class.ga4_analytics.php, so long as the
// GA4 Analytics is currently enabled.
//
// -----
// If there are no additional events to be pushed for the GA4 Analytics, nothing further to be done.
//
if (empty($_SESSION['ga4_analytics'])) {
    return;
}

// -----
// Otherwise, the $_SESSION['ga4_analytics'] array contains arrays of event names and
// their associated values.
//
?>
<script>
<?php
foreach ($_SESSION['ga4_analytics'] as $next_event) {
    $event_parameters = '';
    if (!empty($next_event['parameters'])) {
        $event_parameters = ', ' . json_encode($next_event['parameters']);
    }
?>
    gtag('event', '<?php echo $next_event['event']; ?>'<?php echo $event_parameters; ?>);
<?php
}
unset($_SESSION['ga4_analytics']);
?>
</script>