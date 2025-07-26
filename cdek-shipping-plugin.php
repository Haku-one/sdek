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

// Autoload classes
function cdek_shipping_autoload($class_name) {
    // Only load our classes
    if (strpos($class_name, 'CDEK_') === 0) {
        $class_file = str_replace('_', '-', strtolower($class_name));
        $file_path = plugin_dir_path(__FILE__) . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file_path) && is_readable($file_path)) {
            require_once $file_path;
            
            // Debug log (remove in production)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CDEK Plugin: Loaded class {$class_name} from {$file_path}");
            }
        } else {
            // Debug log (remove in production)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CDEK Plugin: Could not load class {$class_name}, file not found: {$file_path}");
            }
        }
    }
}

if (function_exists('cdek_shipping_autoload')) {
    spl_autoload_register('cdek_shipping_autoload');
}

/**
 * Main plugin class
 */
class CDEK_Shipping_Plugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Hook to modify checkout fields (both classic and block checkout)
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));
        add_filter('woocommerce_billing_fields', array($this, 'modify_billing_fields'));
        add_filter('woocommerce_shipping_fields', array($this, 'modify_shipping_fields'));
        
        // Block checkout support
        add_action('woocommerce_blocks_loaded', array($this, 'register_checkout_block_integration'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_cdek_get_pickup_points', array($this, 'ajax_get_pickup_points'));
        add_action('wp_ajax_nopriv_cdek_get_pickup_points', array($this, 'ajax_get_pickup_points'));
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // Admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Store API integration
        add_action('woocommerce_store_api_validate_add_to_cart', array($this, 'store_api_validate'), 10, 2);
        
        // REST API support
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Save pickup point data
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_pickup_point_data'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_pickup_point_in_admin'));
    }
    
    public function init() {
        load_plugin_textdomain('cdek-shipping', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function shipping_init() {
        // Classes are auto-loaded
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
    
    public function register_checkout_block_integration() {
        if (class_exists('Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationRegistry')) {
            add_action(
                'woocommerce_blocks_checkout_enqueue_data',
                array($this, 'add_checkout_data')
            );
            
            add_action(
                'wp_enqueue_scripts',
                array($this, 'enqueue_checkout_block_assets')
            );
        }
    }
    
    public function add_checkout_data() {
        if (is_admin() || !wp_script_is('wc-checkout-frontend', 'enqueued')) {
            return;
        }
        
        wp_add_inline_script(
            'wc-checkout-frontend',
            'window.cdek_checkout_params = ' . wp_json_encode(array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('cdek/v1/'),
                'nonce' => wp_create_nonce('cdek_nonce'),
                'yandex_api_key' => get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702'),
            )),
            'before'
        );
    }
    
    public function enqueue_checkout_block_assets() {
        if (has_block('woocommerce/checkout') || is_checkout()) {
            // Enqueue block checkout script
            wp_enqueue_script(
                'cdek-checkout-block',
                CDEK_SHIPPING_PLUGIN_URL . 'assets/js/cdek-checkout-block.js',
                array('wp-element', 'wp-html-entities'),
                CDEK_SHIPPING_VERSION,
                true
            );
            
            // Enqueue block checkout styles
            wp_enqueue_style(
                'cdek-checkout-block',
                CDEK_SHIPPING_PLUGIN_URL . 'assets/css/cdek-checkout-block.css',
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
            
            // Add checkout params
            wp_localize_script('cdek-checkout-block', 'cdek_checkout_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('cdek/v1/'),
                'nonce' => wp_create_nonce('cdek_nonce'),
                'yandex_api_key' => $yandex_api_key,
            ));
        }
    }
    
    public function register_rest_routes() {
        register_rest_route('cdek/v1', '/pickup-points', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_pickup_points'),
            'permission_callback' => '__return_true',
            'args' => array(
                'city' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    public function rest_get_pickup_points($request) {
        $city = $request->get_param('city');
        
        if (empty($city)) {
            return new WP_Error('no_city', 'Город не указан', array('status' => 400));
        }
        
        $cdek_api = new CDEK_API();
        $pickup_points = $cdek_api->get_pickup_points($city);
        
        return rest_ensure_response($pickup_points);
    }
    
    public function store_api_validate($errors, $request) {
        // Validation for Store API if needed
        return $errors;
    }
    
    public function save_pickup_point_data($order_id) {
        if (!empty($_POST['cdek_selected_pickup_point'])) {
            $pickup_point_data = sanitize_text_field($_POST['cdek_selected_pickup_point']);
            $pickup_point = json_decode(stripslashes($pickup_point_data), true);
            
            if ($pickup_point && isset($pickup_point['name'])) {
                update_post_meta($order_id, '_cdek_pickup_point', $pickup_point);
                update_post_meta($order_id, '_cdek_pickup_point_name', $pickup_point['name']);
                
                if (isset($pickup_point['location']['address_full'])) {
                    update_post_meta($order_id, '_cdek_pickup_point_address', $pickup_point['location']['address_full']);
                }
            }
        }
    }
    
    public function display_pickup_point_in_admin($order) {
        $pickup_point = get_post_meta($order->get_id(), '_cdek_pickup_point', true);
        
        if ($pickup_point && isset($pickup_point['name'])) {
            echo '<h3>Пункт выдачи СДЭК</h3>';
            echo '<p><strong>' . esc_html($pickup_point['name']) . '</strong></p>';
            
            if (isset($pickup_point['location']['address_full'])) {
                echo '<p>' . esc_html($pickup_point['location']['address_full']) . '</p>';
            }
            
            if (isset($pickup_point['work_time']) && is_array($pickup_point['work_time'])) {
                echo '<p><strong>Время работы:</strong></p>';
                echo '<ul>';
                $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                foreach ($pickup_point['work_time'] as $schedule) {
                    if (isset($schedule['day']) && isset($schedule['time'])) {
                        $day_name = isset($days[$schedule['day'] - 1]) ? $days[$schedule['day'] - 1] : $schedule['day'];
                        echo '<li>' . esc_html($day_name . ': ' . $schedule['time']) . '</li>';
                    }
                }
                echo '</ul>';
            }
        }
    }
}

// Activation and deactivation hooks (manually loaded)
if (!class_exists('CDEK_Activator')) {
    require_once CDEK_SHIPPING_PLUGIN_PATH . 'includes/class-cdek-activator.php';
}
register_activation_hook(__FILE__, array('CDEK_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('CDEK_Activator', 'deactivate'));

// Cleanup on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    if (function_exists('cdek_shipping_autoload')) {
        spl_autoload_unregister('cdek_shipping_autoload');
    }
});

// Initialize the plugin
new CDEK_Shipping_Plugin();