<?php
/**
 * Plugin activation and deactivation hooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDEK_Activator {
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Для работы плагина СДЭК доставки требуется активный WooCommerce.', 'cdek-shipping'),
                __('Ошибка активации плагина', 'cdek-shipping'),
                array('back_link' => true)
            );
        }
        
        // Create default options
        add_option('cdek_account_id', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        add_option('cdek_secure_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
        add_option('cdek_default_sender_city', '44'); // Moscow
        add_option('cdek_test_mode', '0');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Clear any cached data
        delete_transient('cdek_access_token');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}