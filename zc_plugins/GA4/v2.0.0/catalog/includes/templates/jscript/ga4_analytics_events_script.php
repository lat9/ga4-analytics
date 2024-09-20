<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022-2023, Vinos de Frutas Tropicales.
//
// Last updated: v1.2.0
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

// -----
// If the analytics are being processed in 'GA4' mode (gtag.js), output the events that
// have been accumulated.
//
if ($ga4_measurement_type === 'GA4') {
?>
<script>
<?php
    foreach ($_SESSION['ga4_analytics'] as $next_event) {
        $event_parameters = '';
        if (!empty($next_event['parameters'])) {
            if ($ga4_debug_mode === true) {
                $next_event['parameters']['debug_mode'] = true;
            }

//-bof-20230131-lat9-GitHub#288
            // -----
            // Don't include 'send_to' if group-name is empty.
            //
            if (!empty($ga4_group_name)) {
                $next_event['parameters']['send_to'] = $ga4_group_name;     //-Set by ga4_analytics_start_script.php
            }
//-eof-20230131-lat9
            $event_parameters = ', ' . json_encode($next_event['parameters']);
        }
?>
    gtag('event', '<?php echo $next_event['event']; ?>'<?php echo $event_parameters; ?>);
<?php
    }
?>
</script>
<?php
// -----
// Otherwise, the analytics are being processed in 'GTM' mode (using dataLayer.push) for gtm.js.
//
} else {
    // -----
    // For GTM mode, it's possible to send some of the accumulated events to be pushed in the page's
    // <head> and the ga4_analytics_start_script.php has already rendered the <script> tag.
    //
    if ($ga4_script_tag_required === true) {
?>
<script>
<?php
    }

    // -----
    // GTM is a bit more persnickety about duplicate events and will only use the last one
    // output.  That affects the 'add_to_cart' and 'remove_from_cart' events, because multiples
    // might have been accumulated.
    //
    // In addition, *most* of the events gathered by the plugin's observers require that the associated
    // parameters be wrapped in an 'ecommerce' array.  The exceptions to the events are the 'login',
    // 'sign_up' and 'search' ones.
    //
    // Re-format the collected events for use in the dataLayer.push for GTM.
    //
    global $ga4_analytics;

    $gtm_events = [];
    $found_add_to_cart_event = false;
    $found_remove_from_cart_event = false;
    foreach ($_SESSION['ga4_analytics'] as $next_event) {
        $event = $next_event['event'];
        $parameters = isset($next_event['parameters']) ? $next_event['parameters'] : [];
        switch ($event) {
            case 'add_to_cart':
                $found_add_to_cart_event = true;
                if (!isset($gtm_events['add_to_cart'])) {
                    $gtm_events['add_to_cart']['ecommerce'] = $parameters;
                } else {
                    $gtm_events['add_to_cart']['ecommerce']['value'] += $parameters['value'];
                    foreach ($parameters['items'] as $next_item) {
                        $gtm_events['add_to_cart']['ecommerce']['items'][] = $next_item;
                    }
                    $gtm_events['add_to_cart']['ecommerce']['value'] = $ga4_analytics->formatCurrency($gtm_events['add_to_cart']['ecommerce']['value']);
                }
                break;

            case 'remove_from_cart':
                $found_remove_from_cart_event = true;
                if (!isset($gtm_events['remove_from_cart'])) {
                    $gtm_events['remove_from_cart']['ecommerce'] = $parameters;
                } else {
                    $gtm_events['remove_from_cart']['ecommerce']['value'] += $parameters['value'];
                    foreach ($parameters['items'] as $next_item) {
                        $gtm_events['remove_from_cart']['ecommerce']['items'][] = $next_item;
                    }
                    $gtm_events['remove_from_cart']['ecommerce']['value'] = $ga4_analytics->formatCurrency($gtm_events['remove_from_cart']['ecommerce']['value']);
                }
                break;

            case 'login':
            case 'sign_up':
            case 'search':
                $gtm_events[$event] = [];
                foreach ($parameters as $key => $value) {
                    $gtm_events[$event][$key] = $value;
                }
                break;

            default:
                $gtm_events[$event]['ecommerce'] = $parameters;
                break;
        }
    }

    if ($found_add_to_cart_event === true || $found_remove_from_cart_event === true) {
        if ($found_add_to_cart_event === true) {
            $gtm_events['add_to_cart']['ecommerce']['value'] = $ga4_analytics->formatCurrency($gtm_events['add_to_cart']['ecommerce']['value']);
        }

        if ($found_remove_from_cart_event === true) {
            $gtm_events['remove_from_cart']['ecommerce']['value'] = $ga4_analytics->formatCurrency($gtm_events['remove_from_cart']['ecommerce']['value']);
        }
    }

    // -----
    // Now, cycle through the reformatted GTM events, pushing them into the dataLayer.
    //
    foreach ($gtm_events as $event => $parameters) {
        $event_parameters = '';
        if ($parameters !== []) {
            foreach ($parameters as $key => $value) {
                $event_parameters .= ", $key: " . json_encode($value);
            }
        }
?>
    dataLayer.push({event: '<?php echo $event; ?>'<?php echo $event_parameters; ?>});
<?php
    }

    // -----
    // If the <script> tag was output here, also output the </script> tag.
    //
    if ($ga4_script_tag_required === true) {
?>
</script>
<?php
    }
}

// -----
// Clean out the events that were just pushed.
//
unset($_SESSION['ga4_analytics']);
