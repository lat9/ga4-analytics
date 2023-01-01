<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022, Vinos de Frutas Tropicales.
//
// Last updated: v1.1.0
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
global $zco_notifier, $ga4_group_name;
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

// -----
// If the debug-mode is enabled and the configuration value (added in v1.1.0) indicates that there's a
// limit on the IP addresses for which the mode should be enabled, enable the debug mode only if the
// current IP address is in that specified list.
//
if ($ga4_debug_mode === true && isset($_SERVER['REMOTE_ADDR']) && defined('GA4_ANALYTICS_DEBUG_IP_LIST') && GA4_ANALYTICS_DEBUG_IP_LIST !== '') {
    $ga4_ip_list = explode(',', str_replace(' ', '', GA4_ANALYTICS_DEBUG_IP_LIST));
    $ga4_debug_mode = in_array($_SERVER['REMOTE_ADDR'], $ga4_ip_list);
}
?>
<script>
<?php
foreach ($_SESSION['ga4_analytics'] as $next_event) {
    $event_parameters = '';
    if (!empty($next_event['parameters'])) {
        if ($ga4_debug_mode === true) {
            $next_event['parameters']['debug_mode'] = true;
        }
        $next_event['parameters']['send_to'] = $ga4_group_name;     //-Set by ga4_analytics_start_script.php
        $event_parameters = ', ' . json_encode($next_event['parameters']);
    }
?>
    gtag('event', '<?php echo $next_event['event']; ?>'<?php echo $event_parameters; ?>);
<?php
}
unset($_SESSION['ga4_analytics']);
?>
</script>