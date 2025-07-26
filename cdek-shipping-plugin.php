<?php
/**
 * Plugin Name: CDEK Shipping for WooCommerce
 * Plugin URI: https://example.com
 * Description: Интеграция доставки СДЭК для WooCommerce с картой пунктов выдачи
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: cdek-shipping
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CDEK_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDEK_SHIPPING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CDEK_SHIPPING_VERSION', '1.0.0');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Main plugin class
 */
class CDEK_Shipping_Plugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Hook to modify checkout fields
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));
        add_filter('woocommerce_billing_fields', array($this, 'modify_billing_fields'));
        add_filter('woocommerce_shipping_fields', array($this, 'modify_shipping_fields'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_cdek_get_pickup_points', array($this, 'ajax_get_pickup_points'));
        add_action('wp_ajax_nopriv_cdek_get_pickup_points', array($this, 'ajax_get_pickup_points'));
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // Admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function init() {
        load_plugin_textdomain('cdek-shipping', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function shipping_init() {
        require_once CDEK_SHIPPING_PLUGIN_PATH . 'includes/class-cdek-shipping-method.php';
    }
    
    public function add_shipping_method($methods) {
        $methods['cdek_shipping'] = 'CDEK_Shipping_Method';
        return $methods;
    }
    
    /**
     * Remove unnecessary fields from checkout
     */
    public function modify_checkout_fields($fields) {
        // Remove unnecessary shipping fields for CDEK delivery
        if (isset($fields['shipping'])) {
            unset($fields['shipping']['shipping_state']);
            unset($fields['shipping']['shipping_postcode']);
            
            // Modify city field
            if (isset($fields['shipping']['shipping_city'])) {
                $fields['shipping']['shipping_city']['label'] = 'Город';
                $fields['shipping']['shipping_city']['placeholder'] = 'Укажите город (например: Москва, Санкт-Петербург)';
                $fields['shipping']['shipping_city']['class'] = array('form-row-wide', 'cdek-city-field');
                $fields['shipping']['shipping_city']['required'] = true;
                $fields['shipping']['shipping_city']['priority'] = 50;
            }
            
            // Modify address field
            if (isset($fields['shipping']['shipping_address_1'])) {
                $fields['shipping']['shipping_address_1']['placeholder'] = 'Введите адрес (улица, дом)';
                $fields['shipping']['shipping_address_1']['class'] = array('form-row-wide', 'cdek-address-field');
                $fields['shipping']['shipping_address_1']['priority'] = 60;
            }
        }
        
        return $fields;
    }
    
    public function modify_billing_fields($fields) {
        // Keep billing fields as is for invoice purposes
        return $fields;
    }
    
    public function modify_shipping_fields($fields) {
        // Remove state, postcode for shipping
        unset($fields['shipping_state']);
        unset($fields['shipping_postcode']);
        
        // Change city field to be more prominent and rename it
        if (isset($fields['shipping_city'])) {
            $fields['shipping_city']['label'] = 'Город';
            $fields['shipping_city']['placeholder'] = 'Укажите город (например: Москва, Санкт-Петербург)';
            $fields['shipping_city']['class'] = array('form-row-wide', 'cdek-city-field');
            $fields['shipping_city']['required'] = true;
            $fields['shipping_city']['priority'] = 50; // Show before address
        }
        
        // Modify address field
        if (isset($fields['shipping_address_1'])) {
            $fields['shipping_address_1']['placeholder'] = 'Введите адрес (улица, дом)';
            $fields['shipping_address_1']['class'] = array('form-row-wide', 'cdek-address-field');
            $fields['shipping_address_1']['priority'] = 60; // Show after city
        }
        
        return $fields;
    }
    
    public function enqueue_scripts() {
        if (is_checkout() || is_cart()) {
            wp_enqueue_script(
                'cdek-checkout',
                CDEK_SHIPPING_PLUGIN_URL . 'assets/js/cdek-checkout.js',
                array('jquery'),
                CDEK_SHIPPING_VERSION,
                true
            );
            
            wp_enqueue_style(
                'cdek-checkout',
                CDEK_SHIPPING_PLUGIN_URL . 'assets/css/cdek-checkout.css',
                array(),
                CDEK_SHIPPING_VERSION
            );
            
            // Yandex Maps API
            $yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            wp_enqueue_script(
                'yandex-maps',
                'https://api-maps.yandex.ru/2.1/?apikey=' . $yandex_api_key . '&lang=ru_RU',
                array(),
                null,
                true
            );
            
            wp_localize_script('cdek-checkout', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce')
            ));
        }
    }
    
    public function ajax_get_pickup_points() {
        check_ajax_referer('cdek_nonce', 'nonce');
        
        $city = sanitize_text_field($_POST['city']);
        
        require_once CDEK_SHIPPING_PLUGIN_PATH . 'includes/class-cdek-api.php';
        $cdek_api = new CDEK_API();
        $pickup_points = $cdek_api->get_pickup_points($city);
        
        wp_send_json_success($pickup_points);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Настройки СДЭК',
            'СДЭК доставка',
            'manage_options',
            'cdek-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        require_once CDEK_SHIPPING_PLUGIN_PATH . 'includes/admin-page.php';
    }
    
    public function ajax_test_cdek_connection() {
        check_ajax_referer('test_cdek_connection');
        
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав');
        }
        
        $account_id = sanitize_text_field($_POST['account_id']);
        $secure_password = sanitize_text_field($_POST['secure_password']);
        
        if (empty($account_id) || empty($secure_password)) {
            wp_send_json_error(array('message' => 'Не указаны учетные данные'));
        }
        
        // Создаем временный экземпляр API с тестовыми данными
        $test_api_url = 'https://api.cdek.ru/v2/';
        
        $response = wp_remote_post($test_api_url . 'oauth/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $account_id,
                'client_secret' => $secure_password
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Ошибка подключения: ' . $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            wp_send_json_success(array('message' => 'Подключение успешно! Токен получен.'));
        } else {
            $error_msg = isset($data['error_description']) ? $data['error_description'] : 'Неизвестная ошибка';
            wp_send_json_error(array('message' => 'Ошибка авторизации: ' . $error_msg));
        }
    }
}

// Activation and deactivation hooks
require_once CDEK_SHIPPING_PLUGIN_PATH . 'includes/class-cdek-activator.php';
register_activation_hook(__FILE__, array('CDEK_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('CDEK_Activator', 'deactivate'));

// Initialize the plugin
new CDEK_Shipping_Plugin();