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
// The $_SESSION['ga4_analytics'] array contains arrays of event names ('event') and an array of
// their associated parameter values ('parameters').  Give an observer the chance to augment any existing events'
// parameters and/or add custom events.
//
global $zco_notifier;
$zco_notifier->notify('NOTIFY_GA4_BEFORE_EVENT_OUTPUT');

// -----
// If there are no additional events (other than the gtag defaults) to be pushed for the GA4 Analytics, nothing further to be done.
//
if (empty($_SESSION['ga4_analytics'])) {
    return;
}

// -----
// Set a processing flag to indicate whether/not the events sent by the plugin should be
// sent in GA4's 'debug_mode'.
//
$ga4_debug_mode = (GA4_ANALYTICS_DEBUG_MODE === 'true');
?>
<script>
<?php
foreach ($_SESSION['ga4_analytics'] as $next_event) {
    $event_parameters = '';
    if (!empty($next_event['parameters'])) {
        if ($ga4_debug_mode === true) {
            $next_event['parameters']['debug_mode'] = true;
        }
        $event_parameters = ', ' . json_encode($next_event['parameters']);
    }
?>
    gtag('event', '<?php echo $next_event['event']; ?>'<?php echo $event_parameters; ?>);
<?php
}
unset($_SESSION['ga4_analytics']);
?>
</script>