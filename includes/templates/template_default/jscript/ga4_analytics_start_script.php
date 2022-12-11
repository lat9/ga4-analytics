<?php 
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022, Vinos de Frutas Tropicales.
//
// This script is loaded based on a notification that a page's <head> tag has been rendered by
// /includes/classes/observers/class.ga4_analytics.php, so long as the GA4 Analytics is currently
// enabled.
//
?>
<!-- Google tag (gtag.js) -->
 <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $ga4_measurement_id; ?>"></script>
 <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo $ga4_measurement_id; ?>');
</script>