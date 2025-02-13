<?php
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'GA4 Analytics';

    public const GA4_ANALYTICS_CURRENT_VERSION = '2.0.1';

    protected int $configurationGroupId;

    /**
     * @return bool
     */
    protected function executeInstall()
    {
        if (!$this->purgeOldFiles()) {
            return false;
        }

        $this->configurationGroupId = $this->getOrCreateConfigGroupId($this->configGroupTitle, $this->configGroupTitle, null);

        // -----
        // If 'GOOGLE_TAG_MANAGER_ID' is defined already, use it. It will be if updating from
        // a previously-installed version.
        //
        $oldId = defined('GOOGLE_TAG_MANAGER_ID') ? constant('GOOGLE_TAG_MANAGER_ID') : '';
        $this->executeInstallerSql(
            "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
            VALUES
                ('Plugin Version', 'GA4_ANALYTICS_VERSION', '" . self::GA4_ANALYTICS_CURRENT_VERSION . "', 'The <em>GA4 Analytics</em> installed version.', $this->configurationGroupId, now(), 1, NULL, 'zen_cfg_read_only('),

                ('GA4 Analytics Measuring ID', 'GA4_ANALYTICS_TRACKING_ID', '$oldId', 'Enter the GA4 Analytics <em>Measuring ID</em> provided to you when you registered your site with google.  That ID will start with <code>G-</code>.  Set this value to an empty string (the default) to disable the <em>GA4 Analytics</em> plugin.<br>', $this->configurationGroupId, now(), 5, NULL, NULL),

                ('Product Variants\' Separator', 'GA4_ANALYTICS_VARIANT_SEPARATOR', '|', 'If your store has products with multiple attributes, identify the character-string to use as a separator for an attributed product\'s <code>item_variant</code> property.  Default: <code>|</code>.<br>', $this->configurationGroupId, now(), 20, NULL, NULL),

                ('Enable Debug Mode?', 'GA4_ANALYTICS_DEBUG_MODE', 'false', 'Should <b>all</b> GA4 events be sent in <code>debug_mode</code>?  This can be used to help you debug your GA4 installation.  Default: <b>false</b>.', $this->configurationGroupId, now(), 500, NULL, 'zen_cfg_select_option([\'false\', \'true\'],'),

                ('Choose <code>item_id</code> Parameter Value', 'GA4_ANALYTICS_ITEM_ID_VALUE', 'products_model', 'When products are included in GA4 events, what value should be used for the <code>item_id</code> parameter?  If you choose <code>products_id</code>, a plugin-specific <code>item_model</code> parameter will be included, containing the product\'s model (if that value is not empty).  Default: <code>products_model</code>', $this->configurationGroupId, now(), 25, NULL, 'zen_cfg_select_option([\'products_model\', \'products_id\'],'),

                ('Debug Mode, IP List', 'GA4_ANALYTICS_DEBUG_IP_LIST', '', 'If you want to enable <em>Debug Mode</em> for only certain IP addresses, enter those IP addresses here, using a comma-separated list (intervening spaces are OK).  Leave this field empty (the default) and the <em>Debug Mode</em> applies to <b>all</b> IP addresses.<br>', $this->configurationGroupId, now(), 505, NULL, NULL),

                ('Choose <code>products_model</code> Field Name', 'GA4_ANALYTICS_ITEM_MODEL_FIELD', 'ep.item_model', 'If you chose <code>products_id</code> for the setting above, identify the name of the event field into which the <code>products_model</code> should be placed.  The default (<code>ep.item_model</code>) might be &quot;difficult&quot; to see in your Google Management Console.  Some alternate suggestions, reusing built-in GA4 fields are <code>item_list_id</code> and <code>item_list_name</code>.<br>', $this->configurationGroupId, now(), 30, NULL, NULL)"
        );

        // -----
        // Updating/removing various configuration descriptions and/or values if
        // installing the encapsulated version over a previously-installed
        // non-encapsulated one.
        //
        $this->executeInstallerSql(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET last_modified = now(),
                    configuration_value = '" . self::GA4_ANALYTICS_CURRENT_VERSION . "'
              WHERE configuration_key = 'GA4_ANALYTICS_VERSION'
              LIMIT 1"
        );
        $this->executeInstallerSql(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET last_modified = now(),
                    configuration_description = '<br>When products are included in GA4 events, what value should be used for the <code>item_id</code> parameter?  If you choose <code>products_id</code>, the product\'s model (if that value is not empty) is placed into the field name you identify below. Default: <code>products_model</code>.'
              WHERE configuration_key = 'GA4_ANALYTICS_ITEM_ID_VALUE'
              LIMIT 1"
        );
        $this->executeInstallerSql(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET last_modified = now(),
                    configuration_description = '<br>Enter either the GA4 Analytics <em>Measuring ID</em> or the Google Tag Manager <em>container ID</em> provided to you when you registered your site with google. The GA4 ID starts with <code>G-</code> while the GTM ID starts with <code>GTM-</code>.  Set this value to an empty string (the default) to disable the <em>GA4 Analytics</em> plugin.<br>'
              WHERE configuration_key = 'GA4_ANALYTICS_TRACKING_ID'
              LIMIT 1"
        );

        $this->deleteConfigurationKeys(['GA4_ANALYTICS_TRACKING_ID_UA']);

        // -----
        // Register the plugin's configuration page for the admin menus.
        //
        if (!zen_page_key_exists('configGA4Analytics')) {
            zen_register_admin_page('configGA4Analytics', 'BOX_GA4_ANALYTICS_NAME', 'FILENAME_CONFIGURATION', "gID=$this->configurationGroupId", 'configuration', 'Y');
        }

        return true;
    }

    // -----
    // Note: This (https://github.com/zencart/zencart/pull/6498) Zen Cart PR must
    // be present in the base code or a PHP Fatal error is generated due to the
    // function signature difference.
    //
    protected function executeUpgrade($oldVersion)
    {
        $this->executeInstallerSql(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET last_modified = now(),
                    configuration_value = '" . self::GA4_ANALYTICS_CURRENT_VERSION . "'
              WHERE configuration_key = 'GA4_ANALYTICS_VERSION'
              LIMIT 1"
        );
    }

    /**
     * @return bool
     */
    protected function executeUninstall()
    {
        zen_deregister_admin_pages('configGA4Analytics');
        $this->deleteConfigurationGroup($this->configGroupTitle, true);
        return true;
    }

    protected function purgeOldFiles(): bool
    {
        $filesToDelete = [
            DIR_FS_ADMIN . 'includes/auto_loaders/config.ga4_analytics_admin.php',
            DIR_FS_ADMIN . 'includes/init_includes/init_ga4_analytics_admin.php',
            DIR_FS_ADMIN . 'includes/languages/english/extra_definitions/ga4_analytics_admin_names.php',
            DIR_FS_CATALOG . 'includes/auto_loaders/config.ga4_analytics.php',
            DIR_FS_CATALOG . 'includes/classes/observers/class.ga4_analytics.php',
            DIR_FS_CATALOG . 'includes/languages/english/extra_definitions/ga4_analytics_extra_definitions.php',
            DIR_FS_CATALOG . 'includes/templates/template_default/jscript/ga4_analytics_events_script.php',
            DIR_FS_CATALOG . 'includes/templates/template_default/jscript/ga4_analytics_start_script.php',
        ];

        $errorOccurred = false;
        foreach ($filesToDelete as $key => $nextFile) {
            if (file_exists($nextFile)) {
                $result = unlink($nextFile);
                if (!$result && file_exists($nextFile)) {
                    $errorOccurred = true;
                    $this->errorContainer->addError(
                        0,
                        sprintf(ERROR_UNABLE_TO_DELETE_FILE, $nextFile),
                        false,
                        // this str_replace has to do DIR_FS_ADMIN before CATALOG because catalog is contained within admin, so results are wrong.
                        // also, '[admin_directory]' is used to obfuscate the admin dir name, in case the user copy/pastes output to a public forum for help.
                        sprintf(ERROR_UNABLE_TO_DELETE_FILE, str_replace([DIR_FS_ADMIN, DIR_FS_CATALOG], ['[admin_directory]/', ''], $nextFile))
                    );
                }
            }
        }
        return !$errorOccurred;
    }
}
