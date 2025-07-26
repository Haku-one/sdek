<?php
/**
 * Uninstall script for CDEK Shipping Plugin
 * This file is executed when the plugin is deleted via WordPress admin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('cdek_account_id');
delete_option('cdek_secure_password');
delete_option('cdek_yandex_api_key');
delete_option('cdek_default_sender_city');
delete_option('cdek_test_mode');

// Clear any cached data
delete_transient('cdek_access_token');

// Remove any custom database tables if they were created
// (None in this plugin, but good practice to include)

// Clear any scheduled hooks if they were used
// wp_clear_scheduled_hook('cdek_sync_hook');

// Note: WordPress will automatically remove the plugin files
// and any options that start with the plugin's prefix