<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022, Vinos de Frutas Tropicales.
//
// Last updated: v1.1.0
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
$ga4_group_name = 'GA4';
$ga4_config_parameters = [
    'groups' => $ga4_group_name,
];

if (zen_is_logged_in() && !zen_in_guest_checkout()) {
    $ga4_config_parameters['user_id'] = (string)$_SESSION['customer_id'];
}
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
?>
    gtag('config', '<?php echo $ga4_measurement_id; ?>', <?php echo json_encode($ga4_config_parameters); ?>);
</script>
