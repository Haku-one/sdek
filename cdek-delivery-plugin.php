<?php
/**
 * Plugin Name: СДЭК Доставка для WooCommerce
 * Plugin URI: https://yoursite.com
 * Description: Плагин для интеграции доставки СДЭК с упрощенной формой адреса и картой пунктов выдачи
 * Version: 2.7.0
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
define('CDEK_DELIVERY_VERSION', '2.7.0');

// Основной класс плагина
class CdekDeliveryPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Логирование версий для диагностики
        add_action('wp_loaded', array($this, 'log_compatibility_info'));
        
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
        
        // Сохранение данных о выбранном пункте выдачи - СОВМЕСТИМОСТЬ С РАЗНЫМИ ВЕРСИЯМИ WC
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_cdek_point_data'));
        
        // Store API hooks will be registered in register_rest_fields method
        
        // Отображение информации о пункте выдачи в админке
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_cdek_point_in_admin'));
        
        // НОВОЕ: Добавляем информацию о доставке в email уведомления
        add_action('woocommerce_email_order_details', array($this, 'add_cdek_info_to_email'), 20, 4);
        
        // НОВОЕ: Добавляем информацию о доставке на страницу заказа (thank you page)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_cdek_info_on_order_page'));
        
        // Добавляем стоимость доставки в итоги заказа
        add_action('woocommerce_order_details_after_order_table_items', array($this, 'add_cdek_cost_to_order_totals'));
        
        // НОВОЕ: Обновляем стоимость доставки в заказе
        add_action('woocommerce_checkout_update_order_meta', array($this, 'update_order_shipping_cost'), 20, 1);
        
        // НОВОЕ: Дополнительные хуки для блоков WooCommerce
        add_action('woocommerce_loaded', array($this, 'register_rest_fields'));
        add_action('woocommerce_rest_checkout_process_payment', array($this, 'save_cdek_data_from_rest'), 10, 2);
        
        // Правильные хуки для Store API
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        
        // Добавляем поддержку Store API
        add_action('init', array($this, 'init_store_api_support'));
        
        // Загружаем расширение Store API
        add_action('plugins_loaded', array($this, 'load_store_api_extension'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // AJAX для тестирования расчета стоимости
        add_action('wp_ajax_test_cdek_calculation', array($this, 'ajax_test_cdek_calculation'));
        

        
        // Вывод габаритов товаров в оформлении заказа
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_product_dimensions_checkout'), 5);
        
        // Скрытие ненужных полей через CSS
        add_action('wp_head', array($this, 'hide_checkout_fields_css'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Поддержка новых блоков WooCommerce
        add_action('plugins_loaded', array($this, 'load_blocks_integration'));
        
        // Добавляем габариты в описание товара в корзине
        add_filter('woocommerce_get_item_data', array($this, 'add_dimensions_to_cart_item'), 10, 2);
    }
    
    public function init() {
        load_plugin_textdomain('cdek-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function log_compatibility_info() {
        // Логируем информацию о совместимости только в режиме отладки
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CDEK PLUGIN v' . CDEK_DELIVERY_VERSION . ' - Информация о совместимости:');
            error_log('WordPress: ' . get_bloginfo('version'));
            error_log('WooCommerce: ' . (defined('WC_VERSION') ? WC_VERSION : 'не установлен'));
            error_log('PHP: ' . phpversion());
            
            // Проверяем доступность Store API
            if (class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
                error_log('WooCommerce Store API: доступен');
            } else {
                error_log('WooCommerce Store API: недоступен');
            }
            
            // Проверяем deprecated warnings
            if (version_compare(WC_VERSION, '7.2.0', '>=')) {
                error_log('WooCommerce Blocks: используем актуальные хуки (woocommerce_store_api_checkout_update_order_meta)');
            }
        }
    }
    
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=4020b4d5-1d96-476c-a10e-8ab18f0f3702&lang=ru_RU', array(), null, true);
            
            wp_enqueue_script('cdek-delivery-js', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-delivery.js', array('jquery', 'yandex-maps'), '2.6.2', true);
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), '2.6.2');
            
            wp_localize_script('cdek-delivery-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce')
            ));
        }
    }
    
    public function customize_checkout_fields($fields) {
        // Убираем ненужные поля для доставки
       
        
        // Убираем поля для биллинга тоже
        
        
        // Меняем метку для поля адреса
        $fields['shipping']['shipping_address_1']['label'] = 'Город доставки';
        $fields['shipping']['shipping_address_1']['placeholder'] = 'Например: Москва';
        $fields['shipping']['shipping_address_1']['required'] = true;
        
        return $fields;
    }
    
    public function customize_address_fields($fields) {
        // Убираем ненужные поля из формы адреса
        
        
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
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
                wp_send_json_error('Security check failed');
                return;
            }
            
            $address = sanitize_text_field($_POST['address']);
            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            
            // Добавляем отладочную информацию
            error_log('СДЭК AJAX: Запрос пунктов для адреса: ' . $address . ', города: ' . $city);
            
            $cdek_api = new CdekAPI();
            $points = $cdek_api->get_delivery_points($address, $city);
            
            // Логируем результат
            error_log('СДЭК AJAX: Получено пунктов: ' . count($points));
            if (!empty($points)) {
                error_log('СДЭК AJAX: Первый пункт: ' . print_r($points[0], true));
            }
            
            wp_send_json_success($points);
            
        } catch (Exception $e) {
            error_log('СДЭК AJAX: Ошибка получения пунктов: ' . $e->getMessage());
            wp_send_json_error('Ошибка получения пунктов выдачи: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('СДЭК AJAX: Фатальная ошибка в ajax_get_cdek_points: ' . $e->getMessage());
            wp_send_json_error('Фатальная ошибка при загрузке пунктов выдачи');
        }
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
        error_log('СДЭК расчет: Реальные габариты: ' . ($has_real_dimensions ? 'Да' : 'Нет'));
        
        // Проверяем, что у нас есть все необходимые данные
        if (empty($point_code)) {
            error_log('СДЭК расчет: Не указан код пункта выдачи');
            wp_send_json_error('Не указан код пункта выдачи');
            return;
        }
        
        if (empty($cart_dimensions) || !isset($cart_dimensions['length']) || !isset($cart_dimensions['width']) || !isset($cart_dimensions['height'])) {
            error_log('СДЭК расчет: Некорректные габариты товара');
            wp_send_json_error('Некорректные габариты товара');
            return;
        }
        
        $cdek_api = new CdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        if ($cost_data && isset($cost_data['delivery_sum']) && $cost_data['delivery_sum'] > 0) {
            error_log('СДЭК расчет: ✅ Успешно рассчитана стоимость через НАСТОЯЩИЙ API: ' . $cost_data['delivery_sum']);
            
            // Убедимся что передаем флаг успешного API расчета
            $cost_data['api_success'] = true;
            $cost_data['fallback'] = false;
            
            wp_send_json_success($cost_data);
        } else {
            error_log('СДЭК расчет: ❌ API не вернул корректную стоимость.');
            error_log('СДЭК расчет: Детали ответа API: ' . print_r($cost_data, true));
            error_log('СДЭК расчет: ❌ ОТКАЗЫВАЕМСЯ ОТ РАСЧЕТА - НЕТ FALLBACK');
            
            // НЕТ РЕЗЕРВНОГО РАСЧЕТА! Возвращаем ошибку
            wp_send_json_error(array(
                'message' => 'API СДЭК недоступен, расчет стоимости невозможен',
                'api_response' => $cost_data,
                'debug_info' => array(
                    'point_code' => $point_code,
                    'cart_weight' => $cart_weight,
                    'cart_value' => $cart_value,
                    'cart_dimensions' => $cart_dimensions
                )
            ));
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
        
        return $base_cost;
    }
    
    public function display_product_dimensions_checkout() {
        // Получаем товары из корзины
        $cart_items = WC()->cart->get_cart();
        
        if (empty($cart_items)) {
            return;
        }
        
        echo '<div id="product-dimensions-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: block !important;">';
        echo '<h4>📦 Габариты товаров в заказе:</h4>';
        echo '<div class="dimensions-list">';
        
        $has_dimensions = false;
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            // Получаем габариты товара
            $length = $product->get_length();
            $width = $product->get_width(); 
            $height = $product->get_height();
            $weight = $product->get_weight();
            
            // Если хотя бы один из размеров указан, выводим товар
            if ($length || $width || $height || $weight) {
                $has_dimensions = true;
                
                echo '<div class="product-dimensions" style="margin-bottom: 10px; padding: 8px; background: white; border: 1px solid #e0e0e0; border-radius: 3px;">';
                echo '<strong>' . $product->get_name() . '</strong>';
                if ($quantity > 1) {
                    echo ' <span style="color: #666;">(×' . $quantity . ')</span>';
                }
                echo '<br>';
                echo '<span style="color: #666; font-size: 14px;">';
                
                // Выводим габариты если они есть
                if ($length && $width && $height) {
                    echo '📏 Габариты: ' . $length . '×' . $width . '×' . $height . ' см';
                } else {
                    // Выводим те размеры что есть
                    $dimensions = array();
                    if ($length) $dimensions[] = 'Д: ' . $length . 'см';
                    if ($width) $dimensions[] = 'Ш: ' . $width . 'см';
                    if ($height) $dimensions[] = 'В: ' . $height . 'см';
                    if (!empty($dimensions)) {
                        echo '📏 ' . implode(' | ', $dimensions);
                    }
                }
                
                // Выводим вес если он есть
                if ($weight) {
                    if ($length || $width || $height) {
                        echo ' | ';
                    }
                    echo '⚖️ Вес: ' . $weight;
                    // Определяем единицы измерения
                    if (get_option('woocommerce_weight_unit') === 'kg') {
                        echo ' кг';
                    } else {
                        echo ' г';
                    }
                }
                
                echo '</span>';
                echo '</div>';
            }
        }
        
        // Если ни у одного товара нет габаритов, показываем сообщение
        if (!$has_dimensions) {
            echo '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; color: #856404;">';
            echo '⚠️ <strong>Внимание:</strong> У товаров в корзине не указаны габариты и вес.<br>';
            echo 'Стоимость доставки будет рассчитана приблизительно.';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Добавляем скрытые поля с данными для JavaScript
        echo '<div id="wc-cart-data" style="display: none;">';
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            echo '<div class="cart-item-data" ';
            echo 'data-product-id="' . $product->get_id() . '" ';
            echo 'data-quantity="' . $quantity . '" ';
            echo 'data-length="' . ($product->get_length() ?: 0) . '" ';
            echo 'data-width="' . ($product->get_width() ?: 0) . '" ';
            echo 'data-height="' . ($product->get_height() ?: 0) . '" ';
            echo 'data-weight="' . ($product->get_weight() ?: 0) . '" ';
            echo 'data-price="' . $product->get_price() . '"';
            echo '></div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    public function add_dimensions_to_cart_item($item_data, $cart_item) {
        $product = $cart_item['data'];
        
        // Получаем габариты товара
        $length = $product->get_length();
        $width = $product->get_width(); 
        $height = $product->get_height();
        
        // Если есть габариты, добавляем их в метаданные
        if ($length && $width && $height) {
            $item_data[] = array(
                'name' => 'Габариты (Д×Ш×В)',
                'value' => $length . '×' . $width . '×' . $height . ' см'
            );
        }
        
        return $item_data;
    }
    
    public function hide_checkout_fields_css() {
        if (is_checkout()) {
            echo '<style>
                
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
        try {
            // Защита от пустого order_id
            if (empty($order_id)) {
                error_log('СДЭК: Пустой order_id в save_cdek_point_data');
                return;
            }
            
            // ОТЛАДКА: Логируем получение данных СДЭК
            error_log('CDEK DEBUG: Saving order data for order ID: ' . $order_id);
            error_log('CDEK DEBUG: POST data: ' . print_r($_POST, true));
        
        // Сохраняем код и данные пункта выдачи
        if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
            update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($_POST['cdek_selected_point_code']));
            error_log('CDEK DEBUG: Saved point code: ' . $_POST['cdek_selected_point_code']);
        }
        
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            if ($point_data) {
                update_post_meta($order_id, '_cdek_point_data', $point_data);
            }
        }
        
        // НОВОЕ: Сохраняем габариты и вес товаров
        if (isset($_POST['cdek_cart_dimensions']) && !empty($_POST['cdek_cart_dimensions'])) {
            $cart_dimensions = json_decode(stripslashes($_POST['cdek_cart_dimensions']), true);
            if ($cart_dimensions) {
                update_post_meta($order_id, '_cdek_cart_dimensions', $cart_dimensions);
            }
        }
        
        if (isset($_POST['cdek_cart_weight']) && !empty($_POST['cdek_cart_weight'])) {
            update_post_meta($order_id, '_cdek_cart_weight', floatval($_POST['cdek_cart_weight']));
        }
        
        if (isset($_POST['cdek_cart_value']) && !empty($_POST['cdek_cart_value'])) {
            update_post_meta($order_id, '_cdek_cart_value', floatval($_POST['cdek_cart_value']));
        }
        
        // НОВОЕ: Сохраняем стоимость доставки
        if (isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
            $delivery_cost = floatval($_POST['cdek_delivery_cost']);
            update_post_meta($order_id, '_cdek_delivery_cost', $delivery_cost);
            error_log('CDEK DEBUG: Saved delivery cost: ' . $delivery_cost);
        } else {
            error_log('CDEK DEBUG: No delivery cost in POST data');
        }
        
        // НОВОЕ: Сохраняем детали товаров для отчетности
        $order = wc_get_order($order_id);
        if ($order) {
            $order_details = array();
            
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $item_details = array(
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'price' => $item->get_total(),
                        'weight' => $product->get_weight() ? $product->get_weight() : 0,
                        'dimensions' => array(
                            'length' => $product->get_length() ? $product->get_length() : 0,
                            'width' => $product->get_width() ? $product->get_width() : 0,
                            'height' => $product->get_height() ? $product->get_height() : 0
                        )
                    );
                    $order_details[] = $item_details;
                }
            }
            
            if (!empty($order_details)) {
                update_post_meta($order_id, '_cdek_order_items_details', $order_details);
            }
        }
        
        } catch (Exception $e) {
            error_log('СДЭК: Ошибка сохранения данных заказа: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('СДЭК: Фатальная ошибка в save_cdek_point_data: ' . $e->getMessage());
        }
    }
    
    public function display_cdek_point_in_admin($order) {
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        $cart_dimensions = get_post_meta($order->get_id(), '_cdek_cart_dimensions', true);
        $cart_weight = get_post_meta($order->get_id(), '_cdek_cart_weight', true);
        $cart_value = get_post_meta($order->get_id(), '_cdek_cart_value', true);
        $delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
        $order_items_details = get_post_meta($order->get_id(), '_cdek_order_items_details', true);
        
        // Отображаем информацию если есть хотя бы что-то связанное со СДЭК
        if ($point_code || $cart_dimensions || $cart_weight) {
            echo '<div class="cdek-point-info">';
            echo '<h4>📦 Информация о доставке СДЭК</h4>';
            
            // Информация о пункте выдачи
            if ($point_code && $point_data) {
                echo '<div style="margin-bottom: 15px; padding: 10px; background: #e8f5e8; border-left: 4px solid #4caf50;">';
                echo '<h5>🏪 Пункт выдачи:</h5>';
                echo '<strong>' . esc_html($point_data['name']) . '</strong><br>';
                echo '<strong>Код:</strong> ' . esc_html($point_code) . '<br>';
                echo '<strong>Адрес:</strong> ' . esc_html($point_data['location']['address_full']) . '<br>';
                if (isset($point_data['phone']) && $point_data['phone']) {
                    echo '<strong>Телефон:</strong> ' . esc_html($point_data['phone']) . '<br>';
                }
                if (isset($point_data['work_time'])) {
                    echo '<strong>Время работы:</strong> ' . esc_html($point_data['work_time']) . '<br>';
                }
                echo '</div>';
            }
            
            // Габариты и вес
            if ($cart_dimensions || $cart_weight) {
                echo '<div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
                echo '<h5>📏 Габариты и вес посылки:</h5>';
                
                if ($cart_dimensions) {
                    echo '<strong>Размеры:</strong> ' . 
                         esc_html($cart_dimensions['length']) . ' × ' . 
                         esc_html($cart_dimensions['width']) . ' × ' . 
                         esc_html($cart_dimensions['height']) . ' см<br>';
                    
                    $volume = $cart_dimensions['length'] * $cart_dimensions['width'] * $cart_dimensions['height'];
                    echo '<strong>Объем:</strong> ' . number_format($volume, 2) . ' см³<br>';
                }
                
                if ($cart_weight) {
                    echo '<strong>Вес:</strong> ' . number_format($cart_weight, 0) . ' г<br>';
                }
                
                if ($cart_value) {
                    echo '<strong>Стоимость товаров:</strong> ' . number_format($cart_value, 2) . ' руб.<br>';
                }
                
                echo '</div>';
            }
            
            // Стоимость доставки - выделяем отдельно
            if ($delivery_cost && $delivery_cost > 0) {
                echo '<div style="margin-bottom: 15px; padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">';
                echo '<h5 style="margin: 0 0 5px 0; color: #155724;">💰 Стоимость доставки СДЭК</h5>';
                echo '<span style="font-size: 18px; font-weight: bold; color: #155724;">' . number_format($delivery_cost, 2) . ' руб.</span>';
                if ($point_code) {
                    echo '<br><small style="color: #6c757d;">До пункта выдачи: ' . esc_html($point_code) . '</small>';
                }
                echo '</div>';
            }
            
            // Детали товаров
            if ($order_items_details && is_array($order_items_details)) {
                echo '<div style="margin-bottom: 15px; padding: 10px; background: #d1ecf1; border-left: 4px solid #17a2b8;">';
                echo '<h5>🛍️ Детали товаров:</h5>';
                echo '<table style="width: 100%; border-collapse: collapse;">';
                echo '<thead>';
                echo '<tr style="background: #f8f9fa;">';
                echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Товар</th>';
                echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">Кол-во</th>';
                echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">Габариты (Д×Ш×В)</th>';
                echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">Вес</th>';
                echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">Стоимость</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($order_items_details as $item) {
                    echo '<tr>';
                    echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . esc_html($item['name']) . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">' . esc_html($item['quantity']) . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">';
                    if ($item['dimensions']['length'] || $item['dimensions']['width'] || $item['dimensions']['height']) {
                        echo esc_html($item['dimensions']['length']) . '×' . 
                             esc_html($item['dimensions']['width']) . '×' . 
                             esc_html($item['dimensions']['height']) . ' см';
                    } else {
                        echo '—';
                    }
                    echo '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">';
                    echo $item['weight'] ? number_format($item['weight'], 0) . ' г' : '—';
                    echo '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">' . number_format($item['price'], 2) . ' руб.</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
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
    
    public function ajax_test_cdek_calculation() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_calculation')) {
            wp_die('Security check failed');
        }
        
        error_log('🧪 ТЕСТ РАСЧЕТА СТОИМОСТИ СДЭК: Начинаем тестирование');
        
        $cdek_api = new CdekAPI();
        
        // Тестовые данные для расчета
        $test_point_code = 'MSK1'; // Тестовый пункт в Москве
        $test_point_data = array(
            'location' => array(
                'city_code' => 44, // Москва
                'city' => 'Москва',
                'address' => 'ул. Тестовая, 1'
            ),
            'name' => 'Тестовый ПВЗ, Москва'
        );
        $test_weight = 1000; // 1 кг
        $test_dimensions = array(
            'length' => 20,
            'width' => 15,
            'height' => 10
        );
        $test_value = 5000; // 5000 руб
        $has_real_dimensions = 1;
        
        error_log('🧪 ТЕСТ: Данные для расчета - Пункт: ' . $test_point_code . ', Вес: ' . $test_weight . ', Габариты: ' . print_r($test_dimensions, true));
        
        $result = $cdek_api->calculate_delivery_cost_to_point(
            $test_point_code, 
            $test_point_data, 
            $test_weight, 
            $test_dimensions, 
            $test_value, 
            $has_real_dimensions
        );
        
        if ($result && isset($result['delivery_sum']) && $result['delivery_sum'] > 0) {
            $message = '✅ Расчет стоимости работает! Тестовая доставка: ' . $result['delivery_sum'] . ' руб.';
            if (isset($result['period_min']) && isset($result['period_max'])) {
                $message .= ' Срок: ' . $result['period_min'] . '-' . $result['period_max'] . ' дней.';
            }
            error_log('🧪 ТЕСТ: ✅ ' . $message);
            wp_send_json_success($message);
        } else {
            $error_message = '❌ Расчет стоимости не работает. Детали: ' . print_r($result, true);
            error_log('🧪 ТЕСТ: ❌ ' . $error_message);
            wp_send_json_error($error_message);
        }
    }
    

    
    public function activate_plugin() {
        // Создание таблиц или начальных настроек при активации плагина
        if (!get_option('cdek_plugin_version')) {
            add_option('cdek_plugin_version', CDEK_DELIVERY_VERSION);
            add_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
            add_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
            add_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
            add_option('cdek_sender_city', '354'); // Саратов - код 354
        }
    }
    
    public function load_blocks_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-blocks-integration.php';
            
            // Регистрируем интеграцию с блоками
            add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        }
    }
    
    /**
     * Регистрация интеграции с WooCommerce Blocks
     */
    public function register_blocks_integration() {
        try {
            if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry') && 
                class_exists('WC_Cdek_Blocks_Integration')) {
                $container = \Automattic\WooCommerce\Blocks\Package::container();
                $container->get(\Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry::class)
                    ->register(new WC_Cdek_Blocks_Integration());
                error_log('CDEK: Blocks integration зарегистрирована');
            } else {
                error_log('CDEK: Не удалось зарегистрировать blocks integration - отсутствуют классы');
            }
        } catch (Exception $e) {
            error_log('CDEK: Ошибка регистрации blocks integration: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK: Фатальная ошибка при регистрации blocks integration: ' . $e->getMessage());
        }
    }
    
    /**
     * Загружает расширение Store API для СДЭК
     */
    public function load_store_api_extension() {
        try {
            // Проверяем, что WooCommerce загружен
            if (!class_exists('WooCommerce')) {
                return;
            }
            
            // Подключаем класс расширения Store API
            if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php')) {
                require_once plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php';
                
                // Проверяем что класс загружен успешно
                if (class_exists('WC_Cdek_Store_API_Extension')) {
                    WC_Cdek_Store_API_Extension::init();
                    error_log('CDEK: Store API extension загружен и инициализирован');
                } else {
                    error_log('CDEK: Класс WC_Cdek_Store_API_Extension не найден после подключения файла');
                }
            } else {
                error_log('CDEK: Файл Store API extension не найден');
            }
            
        } catch (Exception $e) {
            error_log('CDEK: Ошибка загрузки Store API extension: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK: Фатальная ошибка при загрузке Store API extension: ' . $e->getMessage());
        }
    }
    
    public function add_cdek_info_to_email($order, $sent_to_admin, $plain_text, $email) {
        // Показываем информацию о СДЭК только в email клиенту и администратору
        if (!in_array($email->id, ['new_order', 'customer_processing_order', 'customer_completed_order'])) {
            return;
        }
        
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        $cart_dimensions = get_post_meta($order->get_id(), '_cdek_cart_dimensions', true);
        $cart_weight = get_post_meta($order->get_id(), '_cdek_cart_weight', true);
        $delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
        
        // Выводим информацию если есть данные о доставке СДЭК
        if ($point_code || $cart_dimensions || $cart_weight) {
            if ($plain_text) {
                // Текстовая версия email
                echo "\n" . str_repeat('=', 50) . "\n";
                echo "📦 ИНФОРМАЦИЯ О ДОСТАВКЕ СДЭК\n";
                echo str_repeat('=', 50) . "\n";
                
                if ($point_code && $point_data) {
                    echo "\n🏪 ПУНКТ ВЫДАЧИ:\n";
                    echo "Название: " . $point_data['name'] . "\n";
                    echo "Код: " . $point_code . "\n";
                    echo "Адрес: " . $point_data['location']['address_full'] . "\n";
                    if (isset($point_data['phone']) && $point_data['phone']) {
                        echo "Телефон: " . $point_data['phone'] . "\n";
                    }
                    if (isset($point_data['work_time'])) {
                        echo "Время работы: " . $point_data['work_time'] . "\n";
                    }
                }
                
                if ($cart_dimensions || $cart_weight) {
                    echo "\n📏 ГАБАРИТЫ И ВЕС ПОСЫЛКИ:\n";
                    if ($cart_dimensions) {
                        echo "Размеры: " . $cart_dimensions['length'] . " × " . 
                             $cart_dimensions['width'] . " × " . 
                             $cart_dimensions['height'] . " см\n";
                        $volume = $cart_dimensions['length'] * $cart_dimensions['width'] * $cart_dimensions['height'];
                        echo "Объем: " . number_format($volume, 2) . " см³\n";
                    }
                    if ($cart_weight) {
                        echo "Вес: " . number_format($cart_weight, 0) . " г\n";
                    }
                }
                
                // Стоимость доставки выделяем отдельно
                if ($delivery_cost && $delivery_cost > 0) {
                    echo "\n💰 СТОИМОСТЬ ДОСТАВКИ: " . number_format($delivery_cost, 2) . " руб.\n";
                }
                
                echo str_repeat('=', 50) . "\n\n";
                
            } else {
                // HTML версия email
                echo '<div style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; font-family: Arial, sans-serif;">';
                echo '<h3 style="color: #333; margin-top: 0; border-bottom: 2px solid #007cba; padding-bottom: 10px;">📦 Информация о доставке СДЭК</h3>';
                
                if ($point_code && $point_data) {
                    echo '<div style="margin: 15px 0; padding: 15px; background: #e8f5e8; border-left: 4px solid #4caf50; border-radius: 4px;">';
                    echo '<h4 style="color: #2e7d32; margin: 0 0 10px 0;">🏪 Пункт выдачи</h4>';
                    echo '<p style="margin: 5px 0;"><strong>Название:</strong> ' . esc_html($point_data['name']) . '</p>';
                    echo '<p style="margin: 5px 0;"><strong>Код:</strong> ' . esc_html($point_code) . '</p>';
                    echo '<p style="margin: 5px 0;"><strong>Адрес:</strong> ' . esc_html($point_data['location']['address_full']) . '</p>';
                    if (isset($point_data['phone']) && $point_data['phone']) {
                        echo '<p style="margin: 5px 0;"><strong>Телефон:</strong> ' . esc_html($point_data['phone']) . '</p>';
                    }
                    if (isset($point_data['work_time'])) {
                        echo '<p style="margin: 5px 0;"><strong>Время работы:</strong> ' . esc_html($point_data['work_time']) . '</p>';
                    }
                    echo '</div>';
                }
                
                if ($cart_dimensions || $cart_weight) {
                    echo '<div style="margin: 15px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
                    echo '<h4 style="color: #856404; margin: 0 0 10px 0;">📏 Габариты и вес посылки</h4>';
                    
                    if ($cart_dimensions) {
                        echo '<p style="margin: 5px 0;"><strong>Размеры:</strong> ' . 
                             esc_html($cart_dimensions['length']) . ' × ' . 
                             esc_html($cart_dimensions['width']) . ' × ' . 
                             esc_html($cart_dimensions['height']) . ' см</p>';
                        
                        $volume = $cart_dimensions['length'] * $cart_dimensions['width'] * $cart_dimensions['height'];
                        echo '<p style="margin: 5px 0;"><strong>Объем:</strong> ' . number_format($volume, 2) . ' см³</p>';
                    }
                    
                    if ($cart_weight) {
                        echo '<p style="margin: 5px 0;"><strong>Вес:</strong> ' . number_format($cart_weight, 0) . ' г</p>';
                    }
                    

                    
                    echo '</div>';
                }
                
                // Стоимость доставки - отдельный блок
                if ($delivery_cost && $delivery_cost > 0) {
                    echo '<div style="margin: 15px 0; padding: 20px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; text-align: center;">';
                    echo '<h4 style="color: #155724; margin: 0 0 10px 0;">💰 Стоимость доставки СДЭК</h4>';
                    echo '<div style="font-size: 24px; font-weight: bold; color: #155724;">' . number_format($delivery_cost, 2) . ' руб.</div>';
                    if ($point_code) {
                        echo '<p style="margin: 10px 0 0 0; color: #6c757d; font-size: 14px;">До пункта выдачи: ' . esc_html($point_code) . '</p>';
                    }
                    echo '</div>';
                }
                
                echo '<div style="margin-top: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">';
                echo '<p style="margin: 0; color: #155724; font-size: 14px;">💡 <strong>Важно:</strong> Сохраните код пункта выдачи для получения посылки. При получении необходимо предъявить документ, удостоверяющий личность.</p>';
                echo '</div>';
                
                echo '</div>';
            }
        }
    }
    
    // НОВОЕ: Отображение информации о СДЭК на странице заказа (thank you page)
    public function display_cdek_info_on_order_page($order) {
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        $point_data = get_post_meta($order->get_id(), '_cdek_point_data', true);
        $cart_dimensions = get_post_meta($order->get_id(), '_cdek_cart_dimensions', true);
        $cart_weight = get_post_meta($order->get_id(), '_cdek_cart_weight', true);
        $delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
        
        // Отображаем информацию если есть данные о доставке СДЭК
        if ($point_code || $cart_dimensions || $cart_weight) {
            echo '<section class="woocommerce-cdek-details">';
            echo '<h2 class="woocommerce-order-details__title">📦 Информация о доставке СДЭК</h2>';
            
            if ($point_code && $point_data) {
                echo '<div class="cdek-point-details" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">';
                echo '<h3 style="color: #28a745; margin: 0 0 15px 0;">🏪 Пункт выдачи</h3>';
                
                $point_name = isset($point_data['name']) ? $point_data['name'] : 'Пункт выдачи СДЭК';
                $point_address = isset($point_data['location']['address_full']) ? $point_data['location']['address_full'] : 
                               (isset($point_data['location']['address']) ? $point_data['location']['address'] : 'Адрес не указан');
                
                echo '<p><strong>Название:</strong> ' . esc_html($point_name) . '</p>';
                echo '<p><strong>Код пункта:</strong> ' . esc_html($point_code) . '</p>';
                echo '<p><strong>Адрес:</strong> ' . esc_html($point_address) . '</p>';
                
                if (isset($point_data['phone']) && $point_data['phone']) {
                    echo '<p><strong>Телефон:</strong> ' . esc_html($point_data['phone']) . '</p>';
                }
                
                if (isset($point_data['work_time']) && $point_data['work_time']) {
                    echo '<p><strong>Время работы:</strong> ' . esc_html($point_data['work_time']) . '</p>';
                }
                

                
                echo '</div>';
            }
            
            if ($cart_dimensions || $cart_weight) {
                echo '<div class="cdek-package-details" style="margin: 20px 0; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">';
                echo '<h3 style="color: #856404; margin: 0 0 15px 0;">📏 Габариты и вес посылки</h3>';
                
                if ($cart_dimensions) {
                    echo '<p><strong>Размеры:</strong> ' . 
                         esc_html($cart_dimensions['length']) . ' × ' . 
                         esc_html($cart_dimensions['width']) . ' × ' . 
                         esc_html($cart_dimensions['height']) . ' см</p>';
                    
                    $volume = $cart_dimensions['length'] * $cart_dimensions['width'] * $cart_dimensions['height'];
                    echo '<p><strong>Объем:</strong> ' . number_format($volume, 2) . ' см³</p>';
                }
                
                if ($cart_weight) {
                    echo '<p><strong>Вес:</strong> ' . number_format($cart_weight, 0) . ' г</p>';
                }
                
                echo '</div>';
            }
            
            // Стоимость доставки - отдельный выделенный блок
            if ($delivery_cost && $delivery_cost > 0) {
                echo '<div class="cdek-delivery-cost" style="margin: 20px 0; padding: 25px; background: #d4edda; border: 2px solid #28a745; border-radius: 8px; text-align: center;">';
                echo '<h3 style="color: #155724; margin: 0 0 15px 0;">💰 Стоимость доставки СДЭК</h3>';
                echo '<div style="font-size: 28px; font-weight: bold; color: #155724; margin: 10px 0;">' . number_format($delivery_cost, 2) . ' руб.</div>';
                if ($point_code) {
                    echo '<p style="margin: 10px 0 0 0; color: #6c757d;">До пункта выдачи: ' . esc_html($point_code) . '</p>';
                }
                echo '</div>';
            }
            
            echo '</section>';
        }
    }
    
    public function add_cdek_cost_to_order_totals($order) {
        $delivery_cost = get_post_meta($order->get_id(), '_cdek_delivery_cost', true);
        $point_code = get_post_meta($order->get_id(), '_cdek_point_code', true);
        
        if ($delivery_cost && $delivery_cost > 0) {
            echo '<tr class="cdek-delivery-cost-row">';
            echo '<th scope="row" style="color: #28a745; font-weight: bold;">💰 Доставка СДЭК:</th>';
            echo '<td style="color: #28a745; font-weight: bold; font-size: 16px;">' . wc_price($delivery_cost) . '</td>';
            echo '</tr>';
            
            if ($point_code) {
                echo '<tr class="cdek-point-code-row">';
                echo '<th scope="row" style="color: #6c757d;">📍 Пункт выдачи:</th>';
                echo '<td style="color: #6c757d;">' . esc_html($point_code) . '</td>';
                echo '</tr>';
            }
        }
    }
    
    // НОВОЕ: Обновляем стоимость доставки в заказе
    public function update_order_shipping_cost($order_id) {
        // Проверяем, есть ли стоимость доставки СДЭК
        if (isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
            $delivery_cost = floatval($_POST['cdek_delivery_cost']);
            
            if ($delivery_cost > 0) {
                $order = wc_get_order($order_id);
                
                if ($order) {
                    // Ищем метод доставки СДЭК в заказе
                    $shipping_methods = $order->get_shipping_methods();
                    
                    foreach ($shipping_methods as $shipping_method) {
                        if (strpos($shipping_method->get_method_id(), 'cdek_delivery') !== false) {
                            // Обновляем стоимость доставки
                            $shipping_method->set_total($delivery_cost);
                            
                            // Обновляем название метода доставки с выбранным пунктом
                            if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
                                $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
                                if ($point_data && isset($point_data['name'])) {
                                    $point_name = $point_data['name'];
                                    $city = isset($point_data['location']['city']) ? $point_data['location']['city'] : '';
                                    
                                    $new_title = $city ? $city . ', ' . $point_name : $point_name;
                                    $shipping_method->set_method_title($new_title);
                                }
                            }
                            
                            $shipping_method->save();
                            break;
                        }
                    }
                    
                    // Пересчитываем общую стоимость заказа
                    $order->calculate_totals();
                    $order->save();
                }
            }
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
        
        // ПРИНУДИТЕЛЬНО ОТКЛЮЧАЕМ ТЕСТОВЫЙ РЕЖИМ - он не работает с данными учетными данными
        $this->test_mode = 0;
        update_option('cdek_test_mode', 0);
        $this->base_url = 'https://api.cdek.ru/v2'; // Всегда используем продакшн API
        
        // Устанавливаем город отправителя как Саратов (правильный код 354)
        update_option('cdek_sender_city', '354');
        
        // Логируем настройки подключения для отладки
        error_log('🔧 СДЭК API CONFIG: Режим - ПРОДАКШН (принудительно)');
        error_log('🔧 СДЭК API CONFIG: URL - ' . $this->base_url);
        error_log('🔧 СДЭК API CONFIG: Account ID - ' . substr($this->account, 0, 8) . '...');
        error_log('🔧 СДЭК API CONFIG: Password length - ' . strlen($this->password) . ' символов');
    }
    
    public function get_auth_token() {
        $cache_key = 'cdek_auth_token';
        $token = get_transient($cache_key);
        
        if (!$token) {
            error_log('🔑 СДЭК AUTH: Получаем новый токен авторизации');
            error_log('🔑 СДЭК AUTH: URL: ' . $this->base_url . '/oauth/token');
            error_log('🔑 СДЭК AUTH: Client ID: ' . $this->account);
            error_log('🔑 СДЭК AUTH: Client Secret: ' . substr($this->password, 0, 8) . '...');
            
            $auth_data = array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->password
            );
            
            error_log('🔑 СДЭК AUTH: Данные авторизации: ' . print_r($auth_data, true));
            
            $response = wp_remote_post($this->base_url . '/oauth/token', array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'WordPress/CDEK-Plugin'
                ),
                'body' => $auth_data,
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                error_log('🔑 СДЭК AUTH: HTTP код: ' . $response_code);
                error_log('🔑 СДЭК AUTH: Ответ: ' . $body);
                
                $parsed_body = json_decode($body, true);
                if (isset($parsed_body['access_token'])) {
                    $token = $parsed_body['access_token'];
                    $expires_in = isset($parsed_body['expires_in']) ? intval($parsed_body['expires_in']) : 3600;
                    set_transient($cache_key, $token, $expires_in - 60);
                    error_log('🔑 СДЭК AUTH: ✅ Токен получен успешно, действует ' . $expires_in . ' сек');
                } else {
                    error_log('🔑 СДЭК AUTH: ❌ Не удалось получить токен. Ответ: ' . print_r($parsed_body, true));
                }
            } else {
                error_log('🔑 СДЭК AUTH: ❌ Ошибка HTTP запроса: ' . $response->get_error_message());
            }
        } else {
            error_log('🔑 СДЭК AUTH: ✅ Используем кэшированный токен');
        }
        
        return $token;
    }
    
    public function get_delivery_points($address, $city = '', $weight = 0, $dimensions = array()) {
        // Подготавливаем название города для API
        $city_for_api = !empty($city) ? trim($city) : trim($address);
        
        // ИСПРАВЛЕНИЕ: Улучшенный поиск - убираем ограничения длины для быстрого поиска
        if (empty($city_for_api)) {
            error_log('СДЭК API: ❌ Не указан город для поиска');
            return array();
        }
        
        error_log('СДЭК API: 🔍 Поиск ПВЗ для города: "' . $city_for_api . '"');
        
        // Получаем токен
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('СДЭК API: ❌ Не удалось получить токен авторизации');
            return array();
        }
        
        // ИСПРАВЛЕННАЯ ЛОГИКА: Только точный поиск без широкого поиска
        $search_strategies = array(
            // Стратегия 1: Точный поиск с city_code
            array('use_city_code' => true, 'description' => 'точный поиск с city_code'),
            // Стратегия 2: Поиск по названию города с точным совпадением
            array('use_city_code' => false, 'exact_city' => true, 'description' => 'точный поиск по названию города'),
            // Стратегия 3: Поиск по названию города с дополнительными вариантами
            array('use_city_code' => false, 'fuzzy_city' => true, 'description' => 'поиск с вариантами названия')
        );
        
        foreach ($search_strategies as $index => $strategy) {
            error_log("СДЭК API: Попытка " . ($index + 1) . ": " . $strategy['description']);
            
            $city_code = null;
            
            // Получаем city_code если требуется
            if ($strategy['use_city_code']) {
                if (mb_strlen(trim($city_for_api)) >= 3) {
                    $city_code = $this->get_city_code($city_for_api, $token);
                    
                    // Если не нашли точное совпадение, пытаемся альтернативный поиск
                    if (!$city_code) {
                        $city_code = $this->get_city_code_fuzzy($city_for_api, $token);
                    }
                }
                
                // Если не нашли city_code, переходим к следующей стратегии
                if (!$city_code) {
                    error_log("СДЭК API: City_code не найден, пропускаем стратегию " . ($index + 1));
                    continue;
                }
            }
            
            // Формируем параметры запроса
            $params = array(
                'type' => 'PVZ',
                'country_code' => 'RU',
                'size' => '5000' // Убираем лимиты - максимальное количество для мощного поиска
            );
            
            // Добавляем ограничения по весу и габаритам если указаны
            if ($weight > 0) {
                $params['weight_max'] = max($weight, 30000);
            }
            
            if (!empty($dimensions) && is_array($dimensions)) {
                if (isset($dimensions['length']) && $dimensions['length'] > 0) {
                    $params['dimension_length'] = $dimensions['length'];
                }
                if (isset($dimensions['width']) && $dimensions['width'] > 0) {
                    $params['dimension_width'] = $dimensions['width'];
                }
                if (isset($dimensions['height']) && $dimensions['height'] > 0) {
                    $params['dimension_height'] = $dimensions['height'];
                }
            }
            
            // ИСПРАВЛЕННЫЙ фильтр по городу - ВСЕГДА используем фильтр
            if ($city_code) {
                $params['city_code'] = $city_code;
                error_log('СДЭК API: ✅ Используем city_code: ' . $city_code);
            } else {
                // ВСЕГДА добавляем название города для фильтрации
                if (isset($strategy['exact_city'])) {
                    $params['city'] = $city_for_api;
                    error_log('СДЭК API: 🎯 Точный поиск по городу: ' . $city_for_api);
                } else if (isset($strategy['fuzzy_city'])) {
                    // Пробуем разные варианты названия города
                    $city_variants = $this->getCityVariants($city_for_api);
                    $params['city'] = $city_variants[0]; // Берем первый вариант
                    error_log('СДЭК API: 🔍 Поиск с вариантами: ' . $params['city']);
                } else {
                    $params['city'] = $city_for_api;
                    error_log('СДЭК API: 🔍 Обычный поиск по городу: ' . $city_for_api);
                }
            }
            
            // Строим URL с параметрами
            $url = 'https://api.cdek.ru/v2/deliverypoints?' . http_build_query($params);
            error_log('СДЭК API: URL запроса: ' . $url);
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                error_log('СДЭК API: Ошибка запроса стратегии ' . ($index + 1) . ': ' . $response->get_error_message());
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($body, true);
                
                if (is_array($data) && !empty($data)) {
                    error_log('СДЭК API: ✅ Стратегия ' . ($index + 1) . ' успешна: найдено ' . count($data) . ' ПВЗ');
                    
                    // ИСПРАВЛЕННАЯ фильтрация - строгая проверка соответствия городу
                    if (!empty($city_for_api)) {
                        $filtered_data = array_filter($data, function($point) use ($city_for_api) {
                            if (isset($point['location']['city'])) {
                                $point_city = strtolower(trim($point['location']['city']));
                                $search_city = strtolower(trim($city_for_api));
                                
                                // СТРОГОЕ соответствие - точное совпадение или город содержится в названии
                                return $point_city === $search_city || 
                                       strpos($point_city, $search_city) === 0 ||  // город начинается с искомого
                                       strpos($search_city, $point_city) === 0;    // искомый начинается с города
                            }
                            return false;
                        });
                        
                        if (count($filtered_data) > 0) {
                            error_log('СДЭК API: После строгой фильтрации: ' . count($filtered_data) . ' ПВЗ для города "' . $city_for_api . '"');
                            $data = array_values($filtered_data);
                        } else {
                            error_log('СДЭК API: Строгая фильтрация не дала результатов для города: ' . $city_for_api);
                            continue;
                        }
                    }
                    
                    // ЛОГИРОВАНИЕ для конкретных городов
                    if (in_array(strtolower($city_for_api), ['курск', 'москва', 'тюмень'])) {
                        error_log('СДЭК API: 🎯 Отладка для города "' . $city_for_api . '": найдено ' . count($data) . ' ПВЗ');
                        
                        // Проверяем наличие ПВЗ на "Зелинского" для Тюмени
                        if (strtolower($city_for_api) === 'тюмень') {
                            $zelinsky_found = false;
                            foreach ($data as $point) {
                                if (isset($point['location']['address']) && stripos($point['location']['address'], 'зелинского') !== false) {
                                    $zelinsky_found = true;
                                    error_log('СДЭК API: ✅ Найден ПВЗ на Зелинского: ' . $point['location']['address']);
                                    break;
                                }
                            }
                            if (!$zelinsky_found) {
                                error_log('СДЭК API: ❌ ПВЗ на Зелинского НЕ найден');
                            }
                        }
                    }
                    
                    // Убрано ограничение количества результатов
                    
                    return $data;
                } else {
                    error_log('СДЭК API: Стратегия ' . ($index + 1) . ' не дала результатов');
                }
            } else {
                error_log('СДЭК API: Ошибка стратегии ' . ($index + 1) . '. Код: ' . $response_code . ', Ответ: ' . $body);
            }
        }
        
        error_log('СДЭК API: ❌ Все стратегии поиска исчерпаны для города: ' . $city_for_api);
        return array();
    }
    
    /**
     * Получить варианты названия города для поиска
     */
    private function getCityVariants($city_name) {
        $variants = array();
        $city_clean = trim($city_name);
        
        // Основное название
        $variants[] = $city_clean;
        
        // Без префиксов
        $clean_no_prefix = preg_replace('/^(г\.|город|г\s)/i', '', $city_clean);
        if ($clean_no_prefix !== $city_clean) {
            $variants[] = trim($clean_no_prefix);
        }
        
        // С префиксом "г."
        if (!preg_match('/^г\./i', $city_clean)) {
            $variants[] = 'г. ' . $city_clean;
        }
        
        // Разные регистры
        $variants[] = ucfirst(strtolower($city_clean));
        $variants[] = mb_strtoupper($city_clean);
        
        // Убираем дубликаты
        return array_unique($variants);
    }
    
    /**
     * Альтернативный поиск city_code по частичному совпадению
     */
    private function get_city_code_fuzzy($city_name, $token) {
        // Список вариантов поиска (убираем лишние слова, пробуем сокращения)
        $search_variants = array(
            $city_name,
            trim(preg_replace('/\s+(город|г\.|обл\.|область)$/i', '', $city_name)),
            ucfirst(strtolower(trim($city_name)))
        );
        
        // Убираем дубликаты
        $search_variants = array_unique($search_variants);
        
        foreach ($search_variants as $variant) {
            if (empty($variant)) continue;
            
            $url = 'https://api.cdek.ru/v2/location/cities?' . http_build_query(array(
                'city' => $variant,
                'country_codes' => 'RU',
                'size' => 100 // Убираем лимиты - больше вариантов городов
            ));
            
            error_log('СДЭК API: Альтернативный поиск city_code для "' . $variant . '": ' . $url);
            
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($body, true);
                
                if (is_array($data) && !empty($data)) {
                    // Ищем наиболее точное совпадение
                    foreach ($data as $city_info) {
                        if (isset($city_info['city']) && isset($city_info['code'])) {
                            // Проверяем точное совпадение (игнорируя регистр)
                            if (strtolower(trim($city_info['city'])) === strtolower(trim($city_name))) {
                                error_log('СДЭК API: ✅ Точное совпадение найдено: ' . $city_info['code'] . ' для "' . $city_info['city'] . '"');
                                return $city_info['code'];
                            }
                        }
                    }
                    
                    // Если точного совпадения нет, берем первый результат
                    if (isset($data[0]['code'])) {
                        error_log('СДЭК API: ✅ Приблизительное совпадение: ' . $data[0]['code'] . ' для "' . $data[0]['city'] . '"');
                        return $data[0]['code'];
                    }
                }
            }
        }
        
        error_log('СДЭК API: ❌ Альтернативный поиск city_code не дал результатов для города: ' . $city_name);
        return null;
    }

    /**
     * Получение city_code по названию города
     */
    private function get_city_code($city_name, $token) {
        $url = 'https://api.cdek.ru/v2/location/cities?' . http_build_query(array(
            'city' => $city_name,
            'country_codes' => 'RU',
            'size' => 50 // Увеличиваем для точного поиска города
        ));
        
        error_log('СДЭК API: Запрос city_code для города "' . $city_name . '": ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('СДЭК API: Ошибка запроса city_code: ' . $response->get_error_message());
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($body, true);
            
            if (is_array($data) && !empty($data)) {
                $city_code = $data[0]['code'];
                error_log('СДЭК API: ✅ Найден city_code: ' . $city_code . ' для города: ' . $city_name);
                return $city_code;
            } else {
                error_log('СДЭК API: ❌ Город "' . $city_name . '" не найден в базе СДЭК');
            }
        } else {
            error_log('СДЭК API: Ошибка получения city_code. Код: ' . $response_code . ', Ответ: ' . $body);
        }
        
        return null;
    }
    
    public function calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions) {
        error_log('🎯 СДЭК РАСЧЕТ: Начинаем расчет для пункта ' . $point_code);
        
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('❌ СДЭК расчет: Не удалось получить токен авторизации');
            return false;
        }
        
        error_log('✅ СДЭК РАСЧЕТ: Токен авторизации получен: ' . substr($token, 0, 20) . '...');
        
        // Подготавливаем данные для расчета  
        $from_location = array(
            'code' => get_option('cdek_sender_city', '354') // Саратов (правильный код 354 для API)
        );
        
        // Определяем локацию назначения
        $to_location = array();
        
        // Для расчета до пункта выдачи используем данные пункта
        if ($point_code && $point_data) {
            error_log('СДЭК API: Данные пункта для определения локации: ' . print_r($point_data, true));
            
            // Множественные способы определения локации
            $location_found = false;
            
            // Способ 1: city_code
            if (isset($point_data['location']['city_code']) && !empty($point_data['location']['city_code'])) {
                $to_location['code'] = intval($point_data['location']['city_code']);
                error_log('СДЭК API: Используем city_code: ' . $point_data['location']['city_code']);
                $location_found = true;
            }
            // Способ 2: postal_code 
            elseif (isset($point_data['location']['postal_code']) && !empty($point_data['location']['postal_code'])) {
                $to_location['postal_code'] = $point_data['location']['postal_code'];
                error_log('СДЭК API: Используем postal_code: ' . $point_data['location']['postal_code']);
                $location_found = true;
            }
            // Способ 3: city name
            elseif (isset($point_data['location']['city']) && !empty($point_data['location']['city'])) {
                $city_name = trim($point_data['location']['city']);
                $to_location['city'] = $city_name;
                error_log('СДЭК API: Используем city: ' . $city_name);
                $location_found = true;
            }
            
            // Способ 4: извлечение из name пункта
            if (!$location_found && isset($point_data['name'])) {
                $name_parts = explode(',', $point_data['name']);
                if (count($name_parts) >= 2) {
                    $city_from_name = trim($name_parts[1]);
                    if ($city_from_name) {
                        $to_location['city'] = $city_from_name;
                        error_log('СДЭК API: Извлекли город из name: ' . $city_from_name);
                        $location_found = true;
                    }
                }
            }
            
            // Способ 5: извлечение из полного адреса
            if (!$location_found && isset($point_data['location']['address_full'])) {
                $address_parts = explode(',', $point_data['location']['address_full']);
                foreach ($address_parts as $part) {
                    $part = trim($part);
                    // Ищем часть с "Москва", "Санкт-Петербург" и т.д.
                    if (preg_match('/^(г\.?\s*)?([А-Яа-я\-\s]+)$/u', $part, $matches)) {
                        $city_candidate = trim($matches[2]);
                        if (in_array($city_candidate, ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород', 'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж', 'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'])) {
                            $to_location['city'] = $city_candidate;
                            error_log('СДЭК API: Извлекли известный город из адреса: ' . $city_candidate);
                            $location_found = true;
                            break;
                        }
                    }
                }
            }
            
            // Способ 6: Определение города по коду пункта
            if (!$location_found) {
                $city_codes = array(
                    'MSK' => array('code' => 44, 'name' => 'Москва'),
                    'SPB' => array('code' => 137, 'name' => 'Санкт-Петербург'),
                    'MKHCH' => array('code' => 470, 'name' => 'Махачкала'),
                    'NSK' => array('code' => 270, 'name' => 'Новосибирск'),
                    'EKB' => array('code' => 51, 'name' => 'Екатеринбург'),
                    'KZN' => array('code' => 172, 'name' => 'Казань'),
                    'NN' => array('code' => 276, 'name' => 'Нижний Новгород'),
                    'CHE' => array('code' => 56, 'name' => 'Челябинск'),
                    'SAM' => array('code' => 350, 'name' => 'Самара'),
                    'UFA' => array('code' => 414, 'name' => 'Уфа'),
                    'ROV' => array('code' => 335, 'name' => 'Ростов-на-Дону'),
                    'KRD' => array('code' => 93, 'name' => 'Краснодар'),
                    'PERM' => array('code' => 296, 'name' => 'Пермь'),
                    'VRN' => array('code' => 432, 'name' => 'Воронеж'),
                    'VGG' => array('code' => 438, 'name' => 'Волгоград'),
                    'KRS' => array('code' => 207, 'name' => 'Красноярск'),
                    'SRT' => array('code' => 354, 'name' => 'Саратов'),
                    'TYU' => array('code' => 409, 'name' => 'Тюмень')
                );
                
                foreach ($city_codes as $prefix => $city_info) {
                    if (stripos($point_code, $prefix) === 0) {
                        $to_location['code'] = $city_info['code'];
                        error_log('🏙️ СДЭК API: Найден город ' . $city_info['name'] . ' (код: ' . $city_info['code'] . ') по префиксу пункта: ' . $prefix);
                        $location_found = true;
                        break;
                    }
                }
                
                if (!$location_found) {
                    error_log('⚠️ СДЭК API: Код пункта "' . $point_code . '" не найден в списке городов. Доступные префиксы: ' . implode(', ', array_keys($city_codes)));
                }
            }
            
            if (!$location_found) {
                error_log('СДЭК расчет: Не удалось определить локацию назначения всеми способами');
                return false;
            }
            
        } else {
            error_log('СДЭК расчет: Не указан код пункта выдачи или данные пункта');
            return false;
        }
        
        // Подготавливаем данные о посылках
        $packages = array(
            array(
                'weight' => max(100, intval($cart_weight)), // Минимум 100г
                'length' => max(10, intval($cart_dimensions['length'])), // Минимум 10см
                'width' => max(10, intval($cart_dimensions['width'])), // Минимум 10см
                'height' => max(5, intval($cart_dimensions['height'])) // Минимум 5см
            )
        );
        
        error_log('СДЭК API: Подготовленная посылка: ' . print_r($packages[0], true));
        
        // Определяем тариф для доставки ИЗ САРАТОВА до пункта выдачи
        // 136 - Посылка склад-постамат/пункт выдачи (ПРАВИЛЬНЫЙ для ПВЗ)
        // 138 - Посылка дверь-постамат
        $tariff_code = 136; // Возвращаем обратно для пунктов выдачи
        
        // Формируем запрос согласно официальной документации API СДЭК
        $data = array(
            'date' => date('Y-m-d\TH:i:sO'), // Правильный формат даты с часовым поясом
            'type' => 1, // Тип заказа: интернет-магазин
            'currency' => 1, // Валюта RUB
            'lang' => 'rus', // Язык ответа
            'tariff_code' => $tariff_code,
            'from_location' => $from_location,
            'to_location' => $to_location,
            'packages' => $packages
        );
        
        error_log('📋 СДЭК API: Используем тариф ' . $tariff_code . ' от города ' . $from_location['code'] . ' до города ' . (isset($to_location['code']) ? $to_location['code'] : 'не определен'));
        
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
        
        // Делаем запрос к API СДЭК

        
        error_log('🚀 СДЭК API: Отправляем запрос к ' . $this->base_url . '/calculator/tariff');
        error_log('📤 СДЭК API: Данные запроса: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        error_log('🔑 СДЭК API: Токен: ' . substr($token, 0, 20) . '...');
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30 // Увеличиваем таймаут
        ));
        
        if (is_wp_error($response)) {
            error_log('СДЭК расчет: Ошибка HTTP запроса: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        

        
        error_log('📥 СДЭК API: HTTP код ответа: ' . $response_code);
        error_log('📥 СДЭК API: Заголовки ответа: ' . print_r($headers, true));
        error_log('📥 СДЭК API: Тело ответа: ' . $body);
        
        // Дополнительная диагностика для отладки
        if ($response_code !== 200) {
            error_log('❌ СДЭК API: Некорректный HTTP код. Возможные причины:');
            error_log('- HTTP 401: Проблемы с авторизацией (токен устарел или неверен)');
            error_log('- HTTP 400: Неверные параметры запроса');
            error_log('- HTTP 500: Ошибка на стороне сервера СДЭК');
        }
        
        $parsed_body = json_decode($body, true);
        
        if ($response_code === 200 && $parsed_body) {
            error_log('✅ СДЭК API: Успешный HTTP ответ, разбираем JSON: ' . print_r($parsed_body, true));
            
            if (isset($parsed_body['delivery_sum']) && $parsed_body['delivery_sum'] > 0) {
                error_log('🎉 СДЭК API: Успешно получена стоимость от API: ' . $parsed_body['delivery_sum'] . ' руб.');
                return array(
                    'delivery_sum' => intval($parsed_body['delivery_sum']),
                    'period_min' => isset($parsed_body['period_min']) ? $parsed_body['period_min'] : null,
                    'period_max' => isset($parsed_body['period_max']) ? $parsed_body['period_max'] : null,
                    'api_success' => true
                );
            } elseif (isset($parsed_body['errors']) && !empty($parsed_body['errors'])) {
                error_log('❌ СДЭК API: API вернул ошибки: ' . print_r($parsed_body['errors'], true));
                
                // Анализируем ошибки для понимания проблемы
                foreach ($parsed_body['errors'] as $error) {
                    if (isset($error['code']) && isset($error['message'])) {
                        error_log('❌ СДЭК API: Ошибка ' . $error['code'] . ': ' . $error['message']);
                        
                        // Специальная обработка распространенных ошибок
                        switch ($error['code']) {
                            case 'v2_entity_not_found':
                                error_log('💡 СДЭК API: Пункт выдачи или город не найден в базе СДЭК');
                                break;
                            case 'v2_tariff_not_found':
                                error_log('💡 СДЭК API: Тариф не доступен для данного направления');
                                break;
                            case 'invalid_token':
                                error_log('💡 СДЭК API: Токен авторизации истек или неверен');
                                break;
                            default:
                                error_log('💡 СДЭК API: Неизвестная ошибка, проверьте документацию API');
                        }
                    }
                }
                
                // Пробуем альтернативный способ расчета
                return $this->try_alternative_calculation($data, $token);
            } else {
                error_log('⚠️ СДЭК API: API вернул ответ без delivery_sum: ' . print_r($parsed_body, true));
                
                // Проверяем, есть ли warnings
                if (isset($parsed_body['warnings']) && !empty($parsed_body['warnings'])) {
                    error_log('⚠️ СДЭК API: Предупреждения: ' . print_r($parsed_body['warnings'], true));
                }
                
                return $this->try_alternative_calculation($data, $token);
            }
        } else {
            error_log('❌ СДЭК API: Некорректный ответ. HTTP код: ' . $response_code . ', JSON валиден: ' . ($parsed_body ? 'Да' : 'Нет'));
            if (!$parsed_body && $body) {
                error_log('❌ СДЭК API: Ошибка парсинга JSON. Сырое тело: ' . substr($body, 0, 500));
            }
            return false;
        }
        
        return false;
    }
    
    private function try_alternative_calculation($original_data, $token) {
        error_log('СДЭК расчет: Пробуем альтернативный метод расчета');
        
        // Попробуем разные тарифы ИЗ САРАТОВА
        $alternative_tariffs = [136, 138, 233, 234]; // ПВЗ, Постамат, Эконом, Стандарт
        
        foreach ($alternative_tariffs as $tariff) {
            $data = $original_data;
            $data['tariff_code'] = $tariff;
            
            // Добавляем недостающие поля если их нет
            if (!isset($data['date'])) {
                $data['date'] = date('Y-m-d\TH:i:sO');
            }
            if (!isset($data['currency'])) {
                $data['currency'] = 1; // RUB
            }
            if (!isset($data['lang'])) {
                $data['lang'] = 'rus';
            }
            
            // Упростим локацию - используем только город Москва если не указано
            if (!isset($data['to_location']['code'])) {
                $data['to_location'] = array('code' => 44); // Москва
            }
            
            error_log('СДЭК расчет: Пробуем тариф ' . $tariff . ' с данными: ' . print_r($data, true));
            
            $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                        error_log('СДЭК расчет: Альтернативный расчет успешен с тарифом ' . $tariff . ': ' . $body['delivery_sum']);
                        return array(
                            'delivery_sum' => intval($body['delivery_sum']),
                            'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                            'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                            'api_success' => true,
                            'alternative_tariff' => $tariff
                        );
                    }
                }
            }
        }
        
        error_log('СДЭК расчет: Альтернативные методы не сработали');
        return false;
    }
    
    private function extract_city_from_address($address) {
        // Улучшенное извлечение города из адреса
        $address = trim($address);
        
        // Обработка частых случаев пустых или некорректных адресов
        if (empty($address) || $address === 'Россия' || strlen($address) < 2) {
            return '';
        }
        
        // Очищаем от префиксов "г.", "город", "г "
        $city = preg_replace('/^(г\.?\s*|город\s+)/ui', '', $address);
        
        // Если есть запятые, берем первую часть
        $parts = explode(',', $city);
        $city = trim($parts[0]);
        
        error_log('СДЭК API: Извлеченный город: ' . $city);
        return $city;
    }
    
    // ДИАГНОСТИЧЕСКАЯ ФУНКЦИЯ: Сравнение количества ПВЗ с разными параметрами
    public function diagnose_pvz_count($city_for_api, $city_code, $token) {
        error_log('СДЭК API: 🔬 === ДИАГНОСТИКА ПВЗ ===');
        
        // Тест 1: Без ограничений - максимальный поиск
        $params_minimal = array(
            'type' => 'PVZ',
            'country_code' => 'RU',
            'size' => '5000' // Максимум для диагностики
        );
        if ($city_code) {
            $params_minimal['city_code'] = $city_code;
        } else {
            $params_minimal['city'] = $city_for_api;
        }
        
        $url_minimal = 'https://api.cdek.ru/v2/deliverypoints?' . http_build_query($params_minimal);
        $response_minimal = wp_remote_get($url_minimal, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response_minimal)) {
            $body_minimal = wp_remote_retrieve_body($response_minimal);
            $data_minimal = json_decode($body_minimal, true);
            $count_minimal = is_array($data_minimal) ? count($data_minimal) : 0;
            error_log('СДЭК API: 📊 Без ограничений: ' . $count_minimal . ' ПВЗ');
        }
        
        // Тест 2: С ограничениями (старые параметры)
        $params_restricted = array(
            'type' => 'PVZ',
            'country_code' => 'RU',
            'is_reception' => 'true',
            'have_cash' => 'true', 
            'have_cashless' => 'true',
            'allowed_cod' => 'true',
            'is_handout' => 'true',
            'size' => '5000' // Максимум для диагностики с ограничениями
        );
        if ($city_code) {
            $params_restricted['city_code'] = $city_code;
        } else {
            $params_restricted['city'] = $city_for_api;
        }
        
        $url_restricted = 'https://api.cdek.ru/v2/deliverypoints?' . http_build_query($params_restricted);
        $response_restricted = wp_remote_get($url_restricted, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response_restricted)) {
            $body_restricted = wp_remote_retrieve_body($response_restricted);
            $data_restricted = json_decode($body_restricted, true);
            $count_restricted = is_array($data_restricted) ? count($data_restricted) : 0;
            error_log('СДЭК API: 📊 С ограничениями: ' . $count_restricted . ' ПВЗ');
            
            $difference = $count_minimal - $count_restricted;
            if ($difference > 0) {
                error_log('СДЭК API: ⚠️ НАЙДЕНА ПРИЧИНА: Ограничения исключают ' . $difference . ' ПВЗ!');
            }
        }
        
        error_log('СДЭК API: 🔬 === КОНЕЦ ДИАГНОСТИКИ ===');
    }
    
    /**
     * Регистрация полей для REST API (WooCommerce Store API)
     */
    public function register_rest_fields() {
        try {
            error_log('CDEK: Начинаем регистрацию REST полей');
            
            // Store API обработчики теперь регистрируются в WC_Cdek_Store_API_Extension
            // Здесь только регистрируем обработчики для REST API
            add_action('woocommerce_rest_checkout_process_payment', array($this, 'save_cdek_data_from_rest'), 10, 2);
            
            error_log('CDEK: REST поля успешно зарегистрированы');
            
        } catch (Exception $e) {
            error_log('CDEK: Ошибка регистрации REST полей: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK: Фатальная ошибка в register_rest_fields: ' . $e->getMessage());
        }
    }
    

    
    /**
     * Добавляет данные СДЭК в ответ Store API
     */
    public function add_cdek_data_to_order_response($response, $order, $request) {
        try {
            if ($order && is_object($order) && method_exists($order, 'get_id')) {
                $order_id = $order->get_id();
                
                $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
                $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
                $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
                
                if ($cdek_point_code || $cdek_point_data || $cdek_delivery_cost) {
                    $response['cdek_data'] = array(
                        'point_code' => $cdek_point_code,
                        'point_data' => $cdek_point_data,
                        'delivery_cost' => $cdek_delivery_cost
                    );
                }
            }
        } catch (Exception $e) {
            error_log('CDEK: Ошибка добавления данных в ответ: ' . $e->getMessage());
        }
        
        return $response;
    }
    
    /**
     * Инициализация поддержки Store API
     */
    public function init_store_api_support() {
        try {
            error_log('CDEK: Инициализация поддержки Store API');
            
            // Проверяем что WooCommerce загружен
            if (!class_exists('WooCommerce')) {
                error_log('CDEK: WooCommerce не найден при инициализации Store API');
                return;
            }
            
            // Проверяем доступность Store API
            if (class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
                error_log('CDEK: Store API доступен');
            } else {
                error_log('CDEK: Store API недоступен');
            }
            
        } catch (Exception $e) {
            error_log('CDEK: Ошибка инициализации Store API: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK: Фатальная ошибка при инициализации Store API: ' . $e->getMessage());
        }
    }
    
    /**
     * Сохранение данных СДЭК из REST API запроса
     */
    public function save_cdek_data_from_rest($order, $request) {
        try {
            $order_id = $order->get_id();
            
            // Получаем данные из REST запроса
            $cdek_point_code = $request->get_param('cdek_point_code');
            $cdek_point_data = $request->get_param('cdek_point_data'); 
            $cdek_delivery_cost = $request->get_param('cdek_delivery_cost');
            
            // Сохраняем данные если они есть
            if (!empty($cdek_point_code)) {
                update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($cdek_point_code));
                error_log('CDEK REST: Сохранен код ПВЗ: ' . $cdek_point_code);
            }
            
            if (!empty($cdek_point_data) && is_array($cdek_point_data)) {
                update_post_meta($order_id, '_cdek_point_data', $cdek_point_data);
                error_log('CDEK REST: Сохранены данные ПВЗ');
            }
            
            if (!empty($cdek_delivery_cost) && is_numeric($cdek_delivery_cost)) {
                update_post_meta($order_id, '_cdek_delivery_cost', floatval($cdek_delivery_cost));
                error_log('CDEK REST: Сохранена стоимость доставки: ' . $cdek_delivery_cost);
            }
            
            // Дополнительно сохраняем данные из сессии если они есть
            if (WC()->session) {
                $session_point_code = WC()->session->get('cdek_selected_point_code');
                $session_point_data = WC()->session->get('cdek_selected_point_data');
                $session_delivery_cost = WC()->session->get('cdek_delivery_cost');
                
                if (!empty($session_point_code) && empty($cdek_point_code)) {
                    update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($session_point_code));
                    error_log('CDEK REST: Сохранен код ПВЗ из сессии: ' . $session_point_code);
                }
                
                if (!empty($session_point_data) && empty($cdek_point_data)) {
                    update_post_meta($order_id, '_cdek_point_data', $session_point_data);
                    error_log('CDEK REST: Сохранены данные ПВЗ из сессии');
                }
                
                if (!empty($session_delivery_cost) && empty($cdek_delivery_cost)) {
                    update_post_meta($order_id, '_cdek_delivery_cost', floatval($session_delivery_cost));
                    error_log('CDEK REST: Сохранена стоимость доставки из сессии: ' . $session_delivery_cost);
                }
            }
            
        } catch (Exception $e) {
            error_log('CDEK REST: Ошибка сохранения данных: ' . $e->getMessage());
        }
    }
    
    /**
     * Сохранение данных СДЭК из Store API (один параметр)
     */
    public function save_cdek_data_from_store_api($order) {
        try {
            error_log('CDEK Store API: Вызван save_cdek_data_from_store_api');
            
            // Проверяем что заказ корректный
            if (!$order || !method_exists($order, 'get_id')) {
                error_log('CDEK Store API: Некорректный объект заказа');
                return;
            }
            
            $order_id = $order->get_id();
            error_log('CDEK Store API: Обрабатываем заказ ID: ' . $order_id);
            
            // Пытаемся получить данные из различных источников
            if (isset($_POST['extensions']['cdek-delivery'])) {
                $cdek_data = $_POST['extensions']['cdek-delivery'];
                
                if (!empty($cdek_data['point_code'])) {
                    update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($cdek_data['point_code']));
                    error_log('CDEK Store API: Сохранен код ПВЗ: ' . $cdek_data['point_code']);
                }
                
                if (!empty($cdek_data['point_data'])) {
                    update_post_meta($order_id, '_cdek_point_data', $cdek_data['point_data']);
                    error_log('CDEK Store API: Сохранены данные ПВЗ');
                }
                
                if (!empty($cdek_data['delivery_cost'])) {
                    update_post_meta($order_id, '_cdek_delivery_cost', floatval($cdek_data['delivery_cost']));
                    error_log('CDEK Store API: Сохранена стоимость доставки: ' . $cdek_data['delivery_cost']);
                }
            }
            
            // Дополнительно пытаемся получить данные из сессии
            if (function_exists('WC') && WC()->session) {
                $session_point_code = WC()->session->get('cdek_selected_point_code');
                $session_point_data = WC()->session->get('cdek_selected_point_data');
                $session_delivery_cost = WC()->session->get('cdek_delivery_cost');
                
                if (!empty($session_point_code)) {
                    update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($session_point_code));
                    error_log('CDEK Store API: Сохранен код ПВЗ из сессии: ' . $session_point_code);
                }
                
                if (!empty($session_point_data)) {
                    update_post_meta($order_id, '_cdek_point_data', $session_point_data);
                    error_log('CDEK Store API: Сохранены данные ПВЗ из сессии');
                }
                
                if (!empty($session_delivery_cost)) {
                    update_post_meta($order_id, '_cdek_delivery_cost', floatval($session_delivery_cost));
                    error_log('CDEK Store API: Сохранена стоимость доставки из сессии: ' . $session_delivery_cost);
                }
            }
            
        } catch (Exception $e) {
            error_log('CDEK Store API: Ошибка сохранения данных: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK Store API: Фатальная ошибка: ' . $e->getMessage());
        }
    }
}
