<?php
/**
 * Plugin Name: СДЭК Доставка для WooCommerce
 * Plugin URI: https://yoursite.com
 * Description: Плагин для интеграции доставки СДЭК с упрощенной формой адреса и картой пунктов выдачи
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: cdek-delivery
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Проверяем, активен ли WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('CDEK_DELIVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDEK_DELIVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CDEK_DELIVERY_VERSION', '1.0.0');

// Основной класс плагина
class CdekDeliveryPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Хуки для настройки полей адреса
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_filter('woocommerce_default_address_fields', array($this, 'customize_address_fields'));
        
        // Хуки для СДЭК
        add_action('woocommerce_shipping_init', array($this, 'init_cdek_shipping'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_cdek_shipping_method'));
        
        // AJAX обработчики
        add_action('wp_ajax_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_nopriv_get_cdek_points', array($this, 'ajax_get_cdek_points'));
        add_action('wp_ajax_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', array($this, 'ajax_calculate_delivery_cost'));
        add_action('wp_ajax_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        add_action('wp_ajax_nopriv_get_address_suggestions', array($this, 'ajax_get_address_suggestions'));
        
        // Регистрация настроек плагина
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Сохранение данных о выбранном пункте выдачи
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Скрытие ненужных полей через CSS
        add_action('wp_head', array($this, 'hide_checkout_fields_css'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Поддержка новых блоков WooCommerce
        add_action('plugins_loaded', array($this, 'load_blocks_integration'));
    }
    
    public function init() {
        load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
            
            wp_enqueue_script('cdek-delivery-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js', array('jquery', 'yandex-maps'), CDEK_DELIVERY_VERSION, true);
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), CDEK_DELIVERY_VERSION);
            
            wp_localize_script('cdek-delivery-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce')
            ));
        }
    }
    
    public function customize_checkout_fields($fields) {
        // Убираем ненужные поля для доставки
        unset($fields['shipping']['shipping_city']);
        unset($fields['shipping']['shipping_state']);
        unset($fields['shipping']['shipping_postcode']);
        
        // Убираем поля для биллинга тоже
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_postcode']);
        
        // Меняем метку для поля адреса
        $fields['shipping']['shipping_address_1']['label'] = 'Город доставки';
        $fields['shipping']['shipping_address_1']['placeholder'] = 'Например: Москва';
        $fields['shipping']['shipping_address_1']['required'] = true;
        
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        // Убираем ненужные поля из формы адреса
        unset($fields['city']);
        unset($fields['state']);
        unset($fields['postcode']);
        
        // Настраиваем поле адреса
        $fields['address_1']['label'] = 'Город доставки';
        $fields['address_1']['placeholder'] = 'Например: Москва';
        $fields['address_1']['required'] = true;
        
        return $fields;
    }
    
    public function init_cdek_shipping() {
        if (!class_exists('WC_Cdek_Shipping_Method')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-cdek-shipping-method.php';
        }
    }
    
    public function add_cdek_shipping_method($methods) {
        $methods['cdek_delivery'] = 'WC_Cdek_Shipping_Method';
        return $methods;
    }
    
    public function ajax_get_cdek_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $address = sanitize_text_field($_POST['address']);
        
        // Добавляем отладочную информацию
        error_log('СДЭК AJAX: Запрос пунктов для адреса: ' . $address);
        
        $cdek_api = new CdekAPI();
        $points = $cdek_api->get_delivery_points($address);
        
        // Логируем результат
        error_log('СДЭК AJAX: Получено пунктов: ' . count($points));
        if (!empty($points)) {
            error_log('СДЭК AJAX: Первый пункт: ' . print_r($points[0], true));
        }
        
        wp_send_json_success($points);
    }
    
    public function ajax_calculate_delivery_cost() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $point_code = sanitize_text_field($_POST['point_code']);
        $point_data = json_decode(stripslashes($_POST['point_data']), true);
        $cart_weight = floatval($_POST['cart_weight']);
        $cart_dimensions = json_decode(stripslashes($_POST['cart_dimensions']), true);
        $cart_value = floatval($_POST['cart_value']);
        $has_real_dimensions = intval($_POST['has_real_dimensions']);
        
        error_log('СДЭК расчет: Данные для расчета - Код пункта: ' . $point_code . ', Вес: ' . $cart_weight . ', Стоимость: ' . $cart_value);
        error_log('СДЭК расчет: Размеры: ' . print_r($cart_dimensions, true));
        
        $cdek_api = new CdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        if ($cost_data && isset($cost_data['delivery_sum'])) {
            error_log('СДЭК расчет: Успешно рассчитана стоимость: ' . $cost_data['delivery_sum']);
            wp_send_json_success($cost_data);
        } else {
            error_log('СДЭК расчет: Ошибка расчета, используем fallback');
            // Fallback расчет
            $fallback_cost = $this->calculate_fallback_cost($cart_weight, $cart_value, $cart_dimensions, $has_real_dimensions);
            wp_send_json_success(array('delivery_sum' => $fallback_cost));
        }
    }
    
    public function ajax_get_address_suggestions() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            wp_die('Security check failed');
        }
        
        $search = sanitize_text_field($_POST['search']);
        
        // Генерируем предложения адресов
        $suggestions = $this->generate_address_suggestions($search);
        
        wp_send_json_success($suggestions);
    }
    
    private function generate_address_suggestions($search) {
        $suggestions = array();
        $search_lower = mb_strtolower($search);
        
        // Список российских городов
        $cities = array(
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'
        );
        
        foreach ($cities as $city) {
            if (mb_strpos(mb_strtolower($city), $search_lower) !== false) {
                $suggestions[] = array(
                    'value' => $city,
                    'text' => $city,
                    'city' => $city,
                    'street' => ''
                );
            }
        }
        
        return array_slice($suggestions, 0, 10);
    }
    
    private function calculate_fallback_cost($weight, $value, $dimensions, $has_real_dimensions) {
        $base_cost = 300; // Базовая стоимость
        
        // Дополнительная стоимость за вес свыше 500г
        if ($weight > 500) {
            $extra_weight = ceil(($weight - 500) / 500);
            $base_cost += $extra_weight * 35;
        }
        
        // Дополнительная стоимость за габариты
        if ($has_real_dimensions && $dimensions) {
            $volume = $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
            if ($volume > 12000) {
                $extra_volume = ceil(($volume - 12000) / 6000);
                $base_cost += $extra_volume * 50;
            }
        }
        
        // Страховка за высокую стоимость
        if ($value > 3000) {
            $base_cost += ceil(($value - 3000) / 1000) * 20;
        }
        
        return min($base_cost, 2500);
    }
    
    public function display_product_dimensions_checkout() {
        // Получаем товары из корзины
        $cart_items = WC()->cart->get_cart();
        
        if (empty($cart_items)) {
            return;
        }
        
        echo '<div id="product-dimensions-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
        echo '<h4>Габариты товаров в заказе:</h4>';
        echo '<div class="dimensions-list">';
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            if ($product->get_height() && $product->get_width() && $product->get_length()) {
                echo '<div class="product-dimensions" style="margin-bottom: 10px; padding: 8px; background: white; border: 1px solid #e0e0e0; border-radius: 3px;">';
                echo '<strong>' . $product->get_name() . '</strong>';
                if ($quantity > 1) {
                    echo ' (×' . $quantity . ')';
                }
                echo '<br>';
                echo '<span style="color: #666; font-size: 14px;">';
                echo 'Габариты: ' . $product->get_length() . '×' . $product->get_width() . '×' . $product->get_height() . ' см';
                if ($product->get_weight()) {
                    echo ' | Вес: ' . $product->get_weight() . ' г';
                }
                echo '</span>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    public function hide_checkout_fields_css() {
        if (is_checkout()) {
            echo '<style>
                /* Скрываем ненужные поля города, области и индекса */
                .wc-block-components-address-form__city,
                .wc-block-components-address-form__state,
                .wc-block-components-address-form__postcode,
                #shipping-city,
                #shipping-state,
                #shipping-postcode,
                #billing-city,
                #billing-state,
                #billing-postcode,
                .wc-block-components-text-input:has(#shipping-city),
                .wc-block-components-text-input:has(#shipping-state),
                .wc-block-components-text-input:has(#shipping-postcode) {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                
                /* Скрываем родительские контейнеры */
                [class*="city"]:not([class*="address"]),
                [class*="state"]:not([class*="address"]),
                [class*="postcode"]:not([class*="address"]) {
                    display: none !important;
                }
            </style>';
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Настройки СДЭК',
            'СДЭК Доставка',
            'manage_options',
            'cdek-delivery-settings',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        include_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
    }
    
    public function save_cdek_point_data($order_id) {
        if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
            update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_selected_point_code']));
        }
        
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            if ($point_data) {
                update_post_meta($order_id, '_cdek_point_data', $point_data);
            }
        }
    }
    
    public function display_cdek_point_in_admin($order) {
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        
        if ($point_code && $point_data) {
            echo '<div class="cdek-point-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h4>Пункт выдачи СДЭК:</h4>';
            echo '<strong>' . esc_html($point_data['name']) . '</strong><br>';
            echo 'Код: ' . esc_html($point_code) . '<br>';
            echo 'Адрес: ' . esc_html($point_data['location']['address_full']) . '<br>';
            if (isset($point_data['phone'])) {
                echo 'Телефон: ' . esc_html($point_data['phone']) . '<br>';
            }
            echo '</div>';
        }
    }
    
    public function ajax_test_cdek_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_connection')) {
            wp_die('Security check failed');
        }
        
        $cdek_api = new CdekAPI();
        $token = $cdek_api->get_auth_token();
        
        if ($token) {
            wp_send_json_success('Подключение к API СДЭК успешно установлено');
        } else {
            wp_send_json_error('Не удалось подключиться к API СДЭК. Проверьте учетные данные.');
        }
    }
    
    public function activate_plugin() {
        // Создание таблиц или начальных настроек при активации плагина
        if (!get_option('cdek_plugin_version')) {
            add_option('cdek_plugin_version', CDEK_DELIVERY_VERSION);
            add_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
            add_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
            add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            add_option('cdek_sender_city', '51'); // Саратов - код 51
        }
    }
    
    public function load_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-blocks-integration.php';
        }
    }
}

// Инициализация плагина
new CdekDeliveryPlugin();

// Класс для работы с СДЭК API
class CdekAPI {
    
    private $account;
    private $password;
    private $test_mode;
    private $base_url;
    
    public function __construct() {
        $this->account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        $this->test_mode = get_option('cdek_test_mode', 0);
        $this->base_url = $this->test_mode ? 'https://api.edu.cdek.ru/v2' : 'https://api.cdek.ru/v2';
        
        // Обновляем город отправителя на Саратов
        update_option('cdek_sender_city', '51');
    }
    
    public function get_auth_token() {
        $cache_key = 'cdek_auth_token';
        $token = get_transient($cache_key);
        
        if (!$token) {
            $response = wp_remote_post($this->base_url . '/oauth/token', array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => array(
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->account,
                    'client_secret' => $this->password
                )
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['access_token'])) {
                    $token = $body['access_token'];
                    set_transient($cache_key, $token, $body['expires_in'] - 60);
                }
            }
        }
        
        return $token;
    }
    
    public function get_delivery_points($address) {
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('СДЭК API: Не удалось получить токен авторизации');
            return array();
        }
        
        // Извлекаем город из адреса
        $city = $this->extract_city_from_address($address);
        error_log('СДЭК API: Ищем пункты для города: ' . $city);
        
        // Строим URL с параметрами для GET запроса
        $url = add_query_arg(array(
            'city' => $city,
            'type' => 'PVZ', // Пункты выдачи заказов
            'have_cash' => 'true',
            'have_cashless' => 'true',
            'is_handout' => 'true'
        ), $this->base_url . '/deliverypoints');
        
        error_log('СДЭК API: URL запроса: ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('СДЭК API: Ответ от сервера: ' . print_r($body, true));
            
            if (isset($body['entity'])) {
                error_log('СДЭК API: Найдено пунктов в entity: ' . count($body['entity']));
                return $body['entity'];
            }
            error_log('СДЭК API: Возвращаем весь ответ: ' . count($body));
            return $body;
        } else {
            error_log('СДЭК API: Ошибка запроса: ' . $response->get_error_message());
        }
        
        return array();
    }
    
    public function calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions) {
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('СДЭК расчет: Не удалось получить токен авторизации');
            return false;
        }
        
        // Подготавливаем данные для расчета
        $from_location = array(
            'code' => get_option('cdek_sender_city', '51') // Саратов
        );
        
        // Определяем локацию назначения
        $to_location = array();
        
        // Для расчета до пункта выдачи используем именно код пункта
        if ($point_code) {
            // Для API калькулятора используем postal_code пункта, если есть
            if ($point_data && isset($point_data['location']['postal_code'])) {
                $to_location['postal_code'] = $point_data['location']['postal_code'];
            } elseif ($point_data && isset($point_data['location']['city_code'])) {
                $to_location['code'] = $point_data['location']['city_code'];
            } else {
                // Если нет кода города, попробуем определить его по названию города
                if ($point_data && isset($point_data['location']['city'])) {
                    $to_location['city'] = $point_data['location']['city'];
                } else {
                    error_log('СДЭК расчет: Не удалось определить локацию назначения. Данные пункта: ' . print_r($point_data, true));
                    return false;
                }
            }
        } else {
            error_log('СДЭК расчет: Не указан код пункта выдачи');
            return false;
        }
        
        // Подготавливаем данные о посылках
        $packages = array(
            array(
                'weight' => max(100, intval($cart_weight)), // Минимум 100г
                'length' => intval($cart_dimensions['length']),
                'width' => intval($cart_dimensions['width']),
                'height' => intval($cart_dimensions['height'])
            )
        );
        
        // Определяем тариф (136 - пункт выдачи)
        $tariff_code = 136;
        
        $data = array(
            'type' => 1, // Тип заказа: интернет-магазин
            'tariff_code' => $tariff_code,
            'from_location' => $from_location,
            'to_location' => $to_location,
            'packages' => $packages
        );
        
        // Добавляем услуги если нужны
        $services = array();
        
        // Страхование если стоимость товара больше 3000 руб
        if ($cart_value > 3000) {
            $services[] = array(
                'code' => 'INSURANCE',
                'parameter' => strval(intval($cart_value))
            );
        }
        
        if (!empty($services)) {
            $data['services'] = $services;
        }
        
        error_log('СДЭК расчет: Данные для API: ' . print_r($data, true));
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 15
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            error_log('СДЭК расчет: Ответ API: ' . print_r($body, true));
            
            if (isset($body['delivery_sum'])) {
                return array(
                    'delivery_sum' => intval($body['delivery_sum']),
                    'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                    'period_max' => isset($body['period_max']) ? $body['period_max'] : null
                );
            } elseif (isset($body['errors'])) {
                error_log('СДЭК расчет: Ошибки API: ' . print_r($body['errors'], true));
            }
        } else {
            error_log('СДЭК расчет: Ошибка HTTP запроса: ' . $response->get_error_message());
        }
        
        return false;
    }
    
    private function extract_city_from_address($address) {
        // Простое извлечение города из адреса
        // Предполагаем, что город указан в начале адреса
        $parts = explode(',', $address);
        return trim($parts[0]);
    }
}