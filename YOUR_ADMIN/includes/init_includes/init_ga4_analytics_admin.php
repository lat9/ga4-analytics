<?php
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022-2023, Vinos de Frutas Tropicales.
//
define('GA4_ANALYTICS_CURRENT_VERSION', '1.1.1-beta1');

// -----
// Wait until an admin is logged in before installing or updating ...
//
if (!isset($_SESSION['admin_id'])) {
    return;
}

// -----
// Determine the configuration-group id to use for the plugin's settings, creating that
// group if it's not currently present.
//
$configurationGroupTitle = 'GA4 Analytics';
$configuration = $db->Execute(
    "SELECT configuration_group_id
       FROM " . TABLE_CONFIGURATION_GROUP . "
      WHERE configuration_group_title = '$configurationGroupTitle'
      LIMIT 1"
);
if (!$configuration->EOF) {
    $cgi = $configuration->fields['configuration_group_id'];
} else {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
            (configuration_group_title, configuration_group_description, sort_order, visible)
         VALUES
            ('$configurationGroupTitle', '$configurationGroupTitle', 1, 1)"
    );
    $cgi = $db->Insert_ID();
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION_GROUP . "
            SET sort_order = $cgi
          WHERE configuration_group_id = $cgi
          LIMIT 1"
    );
}

// -----
// If the plugin's configuration settings aren't present, add them now.
//
if (!defined('GA4_ANALYTICS_VERSION')) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
         VALUES
            ('Plugin Version', 'GA4_ANALYTICS_VERSION', '0.0.0', 'The <em>GA4 Analytics</em> installed version.', $cgi, now(), 1, NULL, 'zen_cfg_read_only('),

            ('GA4 Analytics Measuring ID', 'GA4_ANALYTICS_TRACKING_ID', '', 'Enter the GA4 Analytics <em>Measuring ID</em> provided to you when you registered your site with google.  That ID will start with <code>G-</code>.  Set this value to an empty string (the default) to disable the <em>GA4 Analytics</em> plugin.<br>', $cgi, now(), 5, NULL, NULL),

            ('Product Variants\' Separator', 'GA4_ANALYTICS_VARIANT_SEPARATOR', '|', 'If your store has products with multiple attributes, identify the character-string to use as a separator for an attributed product\'s <code>item_variant</code> property.  Default: <code>|</code>.<br>', $cgi, now(), 20, NULL, NULL),

            ('Enable Debug Mode?', 'GA4_ANALYTICS_DEBUG_MODE', 'false', 'Should <b>all</b> GA4 events be sent in <code>debug_mode</code>?  This can be used to help you debug your GA4 installation.  Default: <b>false</b>.', $cgi, now(), 500, NULL, 'zen_cfg_select_option([\'false\', \'true\'],')"
    );

    // -----
    // Register the plugin's configuration page for the admin menus.
    //
    zen_register_admin_page('configGA4Analytics', 'BOX_GA4_ANALYTICS_NAME', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y');

    // -----
    // Let the logged-in admin know that the plugin's been installed.
    //
    define('GA4_ANALYTICS_VERSION', '0.0.0');
    $messageStack->add_session(sprintf(GA4_ANALYTICS_INSTALL_SUCCESS, GA4_ANALYTICS_CURRENT_VERSION), 'success');
}

// -----
// Update the plugin's version/configuration, if the version has changed.
//
if (GA4_ANALYTICS_VERSION !== GA4_ANALYTICS_CURRENT_VERSION) {
    switch (true) {
        case version_compare(GA4_ANALYTICS_VERSION, '1.0.1', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
                    ('Universal Analytics Tracking ID', 'GA4_ANALYTICS_TRACKING_ID_UA', '', 'If you want to enable &quot;dual tagging&quot; to keep your <em>Universal Analytics</em> implementation in place while you build out your Google Analytics 4 implementation, enter that tracking ID here.  That ID will start with <code>UA-</code>.  If this value starts with <code>UA-</code>, an additional <code>gtag</code> configuration event will be set at the start of each page load, so long as the main GA4 Analytics module is enabled.<br>', $cgi, now(), 8, NULL, NULL)"
            );

        case version_compare(GA4_ANALYTICS_VERSION, '1.1.0', '<'):      //-Fall through from above processing ...
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
                    ('Choose <code>item_id</code> Parameter Value', 'GA4_ANALYTICS_ITEM_ID_VALUE', 'products_model', 'When products are included in GA4 events, what value should be used for the <code>item_id</code> parameter?  If you choose <code>products_id</code>, a plugin-specific <code>item_model</code> parameter will be included, containing the product\'s model (if that value is not empty).  Default: <code>products_model</code>', $cgi, now(), 25, NULL, 'zen_cfg_select_option([\'products_model\', \'products_id\'],'),

                    ('Debug Mode, IP List', 'GA4_ANALYTICS_DEBUG_IP_LIST', '', 'If you want to enable <em>Debug Mode</em> for only certain IP addresses, enter those IP addresses here, using a comma-separated list (intervening spaces are OK).  Leave this field empty (the default) and the <em>Debug Mode</em> applies to <b>all</b> IP addresses.<br>', $cgi, now(), 505, NULL, NULL)"
            );

        default:            //-Fall through from above processing ...
            break;
    }

    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . GA4_ANALYTICS_CURRENT_VERSION . "',
                last_modified = now()
          WHERE configuration_key = 'GA4_ANALYTICS_VERSION'
          LIMIT 1"
    );
    if (GA4_ANALYTICS_VERSION !== '0.0.0') {
        $messageStack->add_session(sprintf(GA4_ANALYTICS_UPDATE_SUCCESS, GA4_ANALYTICS_VERSION, GA4_ANALYTICS_CURRENT_VERSION), 'success');
    }
}

// -----
// Check to ensure that, if set, the module's "Measuring ID" starts with 'G-'; otherwise, the storefront processing
// is disabled.
//
if ($current_page === (FILENAME_CONFIGURATION . '.php') && isset($_GET['gID']) && $_GET['gID'] === $cgi) {
    $ga4_check = $db->Execute(
        "SELECT configuration_value
           FROM " . TABLE_CONFIGURATION . "
          WHERE configuration_key = 'GA4_ANALYTICS_TRACKING_ID'"
    );
    if (!$ga4_check->EOF && $ga4_check->fields['configuration_value'] !== '' && strpos($ga4_check->fields['configuration_value'], 'G-') !== 0) {
        $messageStack->add(GA4_ANALYTICS_INVALID_TAG_ERROR, 'error');
    }
}
