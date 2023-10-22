<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022-2023, Vinos de Frutas Tropicales.
//
// Last updated: v1.2.0
//
// This script is loaded based on a notification that a page's <head> tag has been rendered by
// /includes/classes/observers/class.ga4_analytics.php, so long as the GA4 Analytics is currently
// enabled.
//
// See this (https://developers.google.com/analytics/devguides/migration/ecommerce/gtagjs-dual-ua-ga4?hl=en) Google
// developers' posting for the use of the 'groups' on the GA4 config tag.
//
// -----
// If a non-guest customer is logged-in, send the 'user_id' parameter as the customer's 'customer_id'.  See
// this (https://developers.google.com/analytics/devguides/collection/ga4/user-id/?platform=websites) google
// documentation for additional information.
//
global $ga4_group_name, $zco_notifier;

// -----
// NOTE: Setting $ga4_group_name to an empty string, needed for the events' script.
//
$ga4_group_name = '';
$ga4_config_parameters = [];
/*
$ga4_group_name = 'GA4';
$ga4_config_parameters = [
    'groups' => $ga4_group_name,
];
*/

if (zen_is_logged_in() && !zen_in_guest_checkout()) {
    $ga4_config_parameters['user_id'] = (string)$_SESSION['customer_id'];
}
$zco_notifier->notify('NOTIFY_GA4_START_CONFIG_PARAMETERS', [], $ga4_config_parameters);

// -----
// If processing in 'base' GA-4 analytics mode, i.e. the measurement ID starts with 'G-', use the
// gtag.js implementation.
//
if ($ga4_measurement_type === 'GA4') {
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $ga4_measurement_id; ?>"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
<?php
    if (defined('GA4_ANALYTICS_TRACKING_ID_UA') && strpos(GA4_ANALYTICS_TRACKING_ID_UA, 'UA-') === 0) {
?>
    gtag('config', '<?php echo GA4_ANALYTICS_TRACKING_ID_UA; ?>');
<?php
    }

    $ga4_json_parameters = '';
    if ($ga4_config_parameters !== []) {
        $ga4_json_parameters = ', ' . json_encode($ga4_config_parameters);
    }
?>
    gtag('config', '<?php echo $ga4_measurement_id; ?>'<?php echo $ga4_json_parameters; ?>);
</script>
<?php
// -----
// Otherwise, processing in GTM analytics mode, i.e. the measurement ID starts with 'GTM-', so use the
// gtm.js implementation.
//
} else {
?>
<script>
    window.dataLayer = window.dataLayer || [];
    dataLayer.push({ ecommerce: null });
<?php
    // -----
    // If a customer is logged in, push the user_id to the dataLayer. See
    // https://developers.google.com/analytics/devguides/collection/ga4/user-id?client_type=gtm
    // for details.
    //
    // Also push any site-specific 'global' parameters defined.
    //
    if (count($ga4_config_parameters) !== 0) {
        $gtm_parameters = '';
        foreach ($ga4_config_parameters as $key => $value) {
            $gtm_parameters .= "'" . $key . "': '" . $value . "', ";
        }
?>
    dataLayer.push({
        <?php echo rtrim($gtm_parameters, ', '); ?>
    });
<?php
    }

    // -----
    // If any session-based events are waiting to be pushed to the dataLayer, push them now.
    //
    if (!empty($_SESSION['ga4_analytics'])) {
        $ga4_script_tag_required = false;
        require $template->get_template_dir('ga4_analytics_events_script.php', DIR_WS_TEMPLATE, $current_page_base, 'jscript') . '/ga4_analytics_events_script.php';
    }
?>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo $ga4_measurement_id; ?>');
</script>
<?php
}
