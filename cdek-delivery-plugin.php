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
        
        // НОВОЕ: AJAX для сохранения данных СДЭК в сессии
        add_action('wp_ajax_save_cdek_data_to_session', array($this, 'ajax_save_cdek_data_to_session'));
        add_action('wp_ajax_nopriv_save_cdek_data_to_session', array($this, 'ajax_save_cdek_data_to_session'));
        
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
        
        // Store API отключен для упрощения и стабильности
        // add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        // add_action('init', array($this, 'init_store_api_support'));
        // add_action('plugins_loaded', array($this, 'load_store_api_extension'));
        
        // AJAX для проверки подключения
        add_action('wp_ajax_test_cdek_connection', array($this, 'ajax_test_cdek_connection'));
        
        // AJAX для тестирования расчета стоимости
        add_action('wp_ajax_test_cdek_calculation', array($this, 'ajax_test_cdek_calculation'));
        add_action('wp_ajax_test_cdek_api_detailed', array($this, 'ajax_test_cdek_api_detailed'));
        add_action('wp_ajax_test_saratov_kursk', array($this, 'ajax_test_saratov_kursk'));
        add_action('wp_ajax_super_debug', array($this, 'ajax_super_debug'));
        

        
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
            
            // НОВОЕ: Подключаем обработчик сессии
            wp_enqueue_script('cdek-session-handler', CDEK_DELIVERY_PLUGIN_URL . 'assets/js/cdek-session-handler.js', array('jquery', 'cdek-delivery-js'), '1.0.0', true);
            
            wp_enqueue_style('cdek-delivery-css', CDEK_DELIVERY_PLUGIN_URL . 'assets/css/cdek-delivery.css', array(), '2.6.2');
            
            wp_localize_script('cdek-delivery-js', 'cdek_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdek_nonce')
            ));
            
            // Также локализуем для обработчика сессии
            wp_localize_script('cdek-session-handler', 'cdek_ajax', array(
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
        error_log('🔥 AJAX: Начинаем обработку запроса расчета стоимости СДЭК');
        
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
            error_log('❌ AJAX: Ошибка проверки nonce');
            wp_die('Security check failed');
        }
        
        $point_code = sanitize_text_field($_POST['point_code']);
        $point_data = json_decode(stripslashes($_POST['point_data']), true);
        $cart_weight = floatval($_POST['cart_weight']);
        $cart_dimensions = json_decode(stripslashes($_POST['cart_dimensions']), true);
        $cart_value = floatval($_POST['cart_value']);
        $has_real_dimensions = intval($_POST['has_real_dimensions']);
        
        error_log('🔥 AJAX: САРАТОВ ЖЕСТКО ЗАФИКСИРОВАН В PHP!');
        error_log('🔥 AJAX: Данные для расчета - Код пункта: ' . $point_code . ', Вес: ' . $cart_weight . ', Стоимость: ' . $cart_value);
        error_log('🔥 AJAX: Размеры: ' . print_r($cart_dimensions, true));
        error_log('🔥 AJAX: Данные пункта: ' . print_r($point_data, true));
        error_log('🔥 AJAX: Реальные габариты: ' . ($has_real_dimensions ? 'Да' : 'Нет'));
        
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
        
        error_log('🔥 AJAX: ================= ВЫЗОВ ОСНОВНОЙ ФУНКЦИИ РАСЧЕТА =================');
        error_log('🔥 AJAX: Создаем экземпляр CdekAPI и вызываем расчет');
        error_log('🔥 AJAX: Параметры расчета:');
        error_log('🔥 AJAX: - point_code: ' . $point_code);
        error_log('🔥 AJAX: - cart_weight: ' . $cart_weight);
        error_log('🔥 AJAX: - cart_value: ' . $cart_value);
        error_log('🔥 AJAX: - has_real_dimensions: ' . $has_real_dimensions);
        
        $cdek_api = new CdekAPI();
        $cost_data = $cdek_api->calculate_delivery_cost_to_point($point_code, $point_data, $cart_weight, $cart_dimensions, $cart_value, $has_real_dimensions);
        
        error_log('🔥 AJAX: ================= РЕЗУЛЬТАТ ОСНОВНОЙ ФУНКЦИИ =================');
        
        error_log('🔥 AJAX: Получен результат расчета: ' . print_r($cost_data, true));
        
        if ($cost_data && isset($cost_data['delivery_sum']) && $cost_data['delivery_sum'] > 0) {
            error_log('🔥 AJAX: ✅ Успешно рассчитана стоимость через API: ' . $cost_data['delivery_sum'] . ' руб.');
            
            // Убедимся что передаем флаг успешного API расчета
            $cost_data['api_success'] = true;
            $cost_data['fallback'] = false;
            
            error_log('🔥 AJAX: Отправляем успешный ответ в JS');
            wp_send_json_success($cost_data);
        } else {
            error_log('🔥 AJAX: ❌ API не вернул корректную стоимость!');
            error_log('🔥 AJAX: Детали ответа API: ' . print_r($cost_data, true));
            error_log('🔥 AJAX: Отправляем ошибку в JS');
            
            // Возвращаем ошибку - только API расчет
            wp_send_json_error(array(
                'message' => 'Не удалось рассчитать стоимость доставки СДЭК. Попробуйте выбрать другой пункт выдачи.',
                'api_response' => $cost_data,
                'debug_info' => array(
                    'point_code' => $point_code,
                    'cart_weight' => $cart_weight,
                    'cart_dimensions' => $cart_dimensions,
                    'from_saratov_hardcoded' => true
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
    
    /**
     * AJAX метод для сохранения данных СДЭК в сессии
     */
    public function ajax_save_cdek_data_to_session() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'cdek_nonce')) {
                wp_die('Security check failed');
            }
            
            error_log('CDEK AJAX: Сохраняем данные в сессии');
            
            // Инициализируем сессию WooCommerce если её нет
            if (!WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            
            // Сохраняем данные о выбранном пункте выдачи
            if (isset($_POST['point_code']) && isset($_POST['point_data'])) {
                $point_data = array(
                    'code' => sanitize_text_field($_POST['point_code']),
                    'data' => json_decode(stripslashes($_POST['point_data']), true)
                );
                
                WC()->session->set('cdek_selected_point', $point_data);
                error_log('CDEK AJAX: Сохранен ПВЗ в сессии: ' . $_POST['point_code']);
            }
            
            // Сохраняем стоимость доставки
            if (isset($_POST['delivery_cost']) && !empty($_POST['delivery_cost'])) {
                $delivery_cost = floatval($_POST['delivery_cost']);
                WC()->session->set('cdek_delivery_cost', $delivery_cost);
                error_log('CDEK AJAX: Сохранена стоимость в сессии: ' . $delivery_cost);
            }
            
            wp_send_json_success(array('message' => 'Данные сохранены в сессии'));
            
        } catch (Exception $e) {
            error_log('CDEK AJAX: Ошибка сохранения в сессии: ' . $e->getMessage());
            wp_send_json_error('Ошибка сохранения данных');
        }
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
            error_log('CDEK DEBUG: save_cdek_point_data вызван для заказа: ' . $order_id);
            error_log('CDEK DEBUG: Данные $_POST: ' . print_r($_POST, true));
            
            // Дополнительная проверка сессии
            if (isset($_SESSION['cdek_selected_point'])) {
                error_log('CDEK DEBUG: Данные ПВЗ в сессии: ' . print_r($_SESSION['cdek_selected_point'], true));
            }
            
            // Проверяем WooCommerce сессию
            if (WC()->session) {
                $session_point = WC()->session->get('cdek_selected_point');
                $session_cost = WC()->session->get('cdek_delivery_cost');
                if ($session_point) {
                    error_log('CDEK DEBUG: ПВЗ в WC сессии: ' . print_r($session_point, true));
                }
                if ($session_cost) {
                    error_log('CDEK DEBUG: Стоимость в WC сессии: ' . $session_cost);
                }
            }
        
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
            error_log('CDEK DEBUG: Saved delivery cost from POST: ' . $delivery_cost);
        } else {
            error_log('CDEK DEBUG: No delivery cost in POST data');
            
            // Резервное сохранение из WooCommerce сессии
            if (WC()->session) {
                $session_cost = WC()->session->get('cdek_delivery_cost');
                if ($session_cost && $session_cost > 0) {
                    update_post_meta($order_id, '_cdek_delivery_cost', floatval($session_cost));
                    error_log('CDEK DEBUG: Saved delivery cost from WC session: ' . $session_cost);
                }
            }
        }
        
        // РЕЗЕРВНОЕ СОХРАНЕНИЕ: Если данные в POST отсутствуют, пытаемся получить из сессии
        if ((!isset($_POST['cdek_selected_point_code']) || empty($_POST['cdek_selected_point_code'])) && WC()->session) {
            $session_point = WC()->session->get('cdek_selected_point');
            if ($session_point) {
                error_log('CDEK DEBUG: Пытаемся сохранить данные из WC сессии');
                
                if (isset($session_point['code'])) {
                    update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($session_point['code']));
                    error_log('CDEK DEBUG: Saved point code from session: ' . $session_point['code']);
                }
                
                if (isset($session_point['data'])) {
                    update_post_meta($order_id, '_cdek_point_data', $session_point['data']);
                    error_log('CDEK DEBUG: Saved point data from session');
                }
            }
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
    
    public function ajax_test_cdek_api_detailed() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_cdek_api_detailed')) {
            wp_die('Security check failed');
        }
        
        error_log('🚀 ЗАПУСК ДЕТАЛЬНОГО ТЕСТИРОВАНИЯ API СДЭК');
        
        $cdek_api = new CdekAPI();
        $result = $cdek_api->test_cdek_api_detailed();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => '✅ Детальное тестирование завершено успешно! Стоимость: ' . $result['delivery_sum'] . ' руб.',
                'result' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => '❌ Детальное тестирование не прошло. Проверьте логи для диагностики.'
            ));
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
            
            // Интеграция с блоками отключена для упрощения
            // add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        }
    }
    
    /**
     * Регистрация интеграции с WooCommerce Blocks
     */
    public function register_blocks_integration() {
        // Blocks integration отключена для упрощения
        error_log('CDEK: Blocks integration отключена для упрощения');
        return;
    }
    
    /**
     * Загружает расширение Store API для СДЭК
     */
    public function load_store_api_extension() {
        // Store API отключен для упрощения и стабильности
        error_log('CDEK: Store API отключен для упрощения');
        return;
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
        error_log('CDEK DEBUG: update_order_shipping_cost вызван для заказа: ' . $order_id);
        
        $delivery_cost = 0;
        $point_data = null;
        
        // Пытаемся получить данные из POST
        if (isset($_POST['cdek_delivery_cost']) && !empty($_POST['cdek_delivery_cost'])) {
            $delivery_cost = floatval($_POST['cdek_delivery_cost']);
            error_log('CDEK DEBUG: Стоимость из POST: ' . $delivery_cost);
        }
        
        if (isset($_POST['cdek_selected_point_data']) && !empty($_POST['cdek_selected_point_data'])) {
            $point_data = json_decode(stripslashes($_POST['cdek_selected_point_data']), true);
            error_log('CDEK DEBUG: Данные ПВЗ из POST найдены');
        }
        
        // Если в POST нет данных, пытаемся получить из сессии
        if ((!$delivery_cost || !$point_data) && WC()->session) {
            if (!$delivery_cost) {
                $session_cost = WC()->session->get('cdek_delivery_cost');
                if ($session_cost) {
                    $delivery_cost = floatval($session_cost);
                    error_log('CDEK DEBUG: Стоимость из WC сессии: ' . $delivery_cost);
                }
            }
            
            if (!$point_data) {
                $session_point = WC()->session->get('cdek_selected_point');
                if ($session_point && isset($session_point['data'])) {
                    $point_data = $session_point['data'];
                    error_log('CDEK DEBUG: Данные ПВЗ из WC сессии найдены');
                }
            }
        }
        
        // Если есть стоимость доставки, обновляем заказ
        if ($delivery_cost > 0) {
            error_log('CDEK DEBUG: Обновляем стоимость доставки в заказе: ' . $delivery_cost);
            
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Ищем метод доставки СДЭК в заказе
                $shipping_methods = $order->get_shipping_methods();
                
                foreach ($shipping_methods as $shipping_method) {
                    if (strpos($shipping_method->get_method_id(), 'cdek_delivery') !== false) {
                        // Обновляем стоимость доставки
                        $shipping_method->set_total($delivery_cost);
                        
                        // Обновляем название метода доставки с выбранным пунктом
                        if ($point_data && isset($point_data['name'])) {
                            $point_name = $point_data['name'];
                            $city = isset($point_data['location']['city']) ? $point_data['location']['city'] : '';
                            
                            $new_title = $city ? $city . ', ' . $point_name : $point_name;
                            $shipping_method->set_method_title($new_title);
                            error_log('CDEK DEBUG: Обновлено название метода доставки: ' . $new_title);
                        }
                        
                        $shipping_method->save();
                        break;
                    }
                }
                
                // Пересчитываем общую стоимость заказа
                $order->calculate_totals();
                $order->save();
                error_log('CDEK DEBUG: Заказ пересчитан и сохранен');
            }
        } else {
            error_log('CDEK DEBUG: Стоимость доставки не найдена или равна 0');
        }
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
        // Store API support отключен для упрощения
        error_log('CDEK: Store API support отключен для упрощения');
        return;
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
    
    public function test_cdek_api_detailed() {
        error_log('🧪 ТЕСТ API СДЭК: Начинаем детальное тестирование');
        
        // 1. Проверяем настройки
        $account = get_option('cdek_account');
        $password = get_option('cdek_password');
        $sender_city = get_option('cdek_sender_city', '354');
        
        error_log('🧪 ТЕСТ: Account: ' . substr($account, 0, 10) . '...');
        error_log('🧪 ТЕСТ: Password: ' . (empty($password) ? 'НЕ ЗАДАН' : 'ЗАДАН'));
        error_log('🧪 ТЕСТ: Sender City: ' . $sender_city);
        
        if (empty($account) || empty($password)) {
            error_log('❌ ТЕСТ: Не заданы учетные данные API');
            return false;
        }
        
        // 2. Проверяем получение токена
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('❌ ТЕСТ: Не удалось получить токен');
            return false;
        }
        error_log('✅ ТЕСТ: Токен получен: ' . substr($token, 0, 20) . '...');
        
        // 3. Тестируем расчет к известному ПВЗ в Саратове
        $test_point_data = array(
            'code' => 'SAR96',
            'location' => array(
                'city_code' => 354,
                'city' => 'Саратов',
                'postal_code' => '410000'
            )
        );
        
        $test_dimensions = array(
            'length' => 20,
            'width' => 15,
            'height' => 10
        );
        
        error_log('🧪 ТЕСТ: Тестируем расчет к ПВЗ SAR96 в Саратове');
        
        $result = $this->calculate_delivery_cost_to_point('SAR96', $test_point_data, 0.5, $test_dimensions, 1000, 1);
        
        if ($result && isset($result['delivery_sum']) && $result['delivery_sum'] > 0) {
            error_log('✅ ТЕСТ: Успешный расчет стоимости: ' . $result['delivery_sum'] . ' руб.');
            return $result;
        } else {
            error_log('❌ ТЕСТ: Неудачный расчет. Результат: ' . print_r($result, true));
            
            // 4. Дополнительный тест - простой запрос тарификации
            error_log('🧪 ТЕСТ: Пробуем простой запрос к API');
                         $simple_data = array(
                'date' => date('Y-m-d\TH:i:sO'),
                'type' => 1,
                'currency' => 1,
                'lang' => 'rus',
                'tariff_code' => 136,
                'from_location' => array('code' => 354), // САРАТОВ - ЖЕСТКО!
                'to_location' => array('code' => 354), // САРАТОВ - ВНУТРИ ГОРОДА
                'packages' => array(
                    array(
                        'weight' => 500,
                        'length' => 20,
                        'width' => 15,
                        'height' => 10
                    )
                )
            );
            
            $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($simple_data),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $code = wp_remote_retrieve_response_code($response);
                error_log('🧪 ТЕСТ: Простой запрос - HTTP код: ' . $code);
                error_log('🧪 ТЕСТ: Простой запрос - Ответ: ' . $body);
            } else {
                error_log('❌ ТЕСТ: Ошибка простого запроса: ' . $response->get_error_message());
            }
            
            return false;
        }
    }
    
    /**
     * Получить код города Курск из API СДЭК
     */
    private function get_kursk_city_code($token) {
        $url = 'https://api.cdek.ru/v2/location/cities?' . http_build_query(array(
            'city' => 'Курск',
            'country_codes' => 'RU',
            'size' => 10
        ));
        
        error_log('🏙️ КУРСК: Запрашиваем код города Курск из API: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($body, true);
                
                if (is_array($data) && !empty($data)) {
                    foreach ($data as $city_info) {
                        if (isset($city_info['city']) && isset($city_info['code'])) {
                            if (strtolower(trim($city_info['city'])) === 'курск') {
                                error_log('🏙️ КУРСК: Найден код города Курск: ' . $city_info['code']);
                                return intval($city_info['code']);
                            }
                        }
                    }
                    
                    // Если точного совпадения нет, берем первый результат
                    if (isset($data[0]['code'])) {
                        error_log('🏙️ КУРСК: Приблизительное совпадение для Курска: ' . $data[0]['code']);
                        return intval($data[0]['code']);
                    }
                }
            }
        }
        
        error_log('❌ КУРСК: Не удалось получить код города Курск, используем резервный 195');
        return 195; // Резервный код
    }

    public function ajax_test_saratov_kursk() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_saratov_kursk')) {
            wp_die('Security check failed');
        }
        
        error_log('🧪 ТЕСТ САРАТОВ-КУРСК: Начинаем специальный тест расчета');
        
        $cdek_api = new CdekAPI();
        
        // Тестируем пункт KRS13 (Курск)
        $test_point_data = array(
            'code' => 'KRS13',
            'location' => array(
                'city' => 'Курск',
                'postal_code' => '305000'
            ),
            'name' => 'Курск, Пункт выдачи KRS13'
        );
        
        $test_dimensions = array(
            'length' => 20,
            'width' => 15,
            'height' => 10
        );
        
        error_log('🧪 ТЕСТ: Рассчитываем доставку Саратов → Курск (пункт KRS13)');
        
        $result = $cdek_api->calculate_delivery_cost_to_point('KRS13', $test_point_data, 0.5, $test_dimensions, 1000, 1);
        
        if ($result && isset($result['delivery_sum']) && $result['delivery_sum'] > 0) {
            $message = '✅ Тест Саратов-Курск успешен! Стоимость: ' . $result['delivery_sum'] . ' руб.';
            if (isset($result['alternative_tariff'])) {
                $message .= ' (альтернативный тариф: ' . $result['alternative_tariff'] . ')';
            }
            wp_send_json_success($message);
        } else {
            $message = '❌ Тест Саратов-Курск не прошел. Проверьте логи для диагностики.';
            wp_send_json_error(array(
                'message' => $message,
                'result' => $result
            ));
        }
    }

    public function ajax_super_debug() {
        if (!wp_verify_nonce($_POST['nonce'], 'super_debug')) {
            wp_die('Security check failed');
        }
        
        error_log('💥💥💥 СУПЕР ДЕБАГ: НАЧИНАЕМ ПОЛНУЮ ДИАГНОСТИКУ 💥💥💥');
        
        // 1. Проверяем настройки
        $account = get_option('cdek_account');
        $password = get_option('cdek_password');
        $sender_city = get_option('cdek_sender_city', '354');
        
        error_log('💥 СУПЕР ДЕБАГ: ========== НАСТРОЙКИ ==========');
        error_log('💥 СУПЕР ДЕБАГ: cdek_account: ' . $account);
        error_log('💥 СУПЕР ДЕБАГ: cdek_password: ' . $password);
        error_log('💥 СУПЕР ДЕБАГ: cdek_sender_city: ' . $sender_city);
        
        // 2. Создаем API объект и тестируем токен
        $cdek_api = new CdekAPI();
        error_log('💥 СУПЕР ДЕБАГ: ========== ТЕСТ АВТОРИЗАЦИИ ==========');
        
        // Очищаем кэш токена для чистого теста
        delete_transient('cdek_auth_token');
        
        $token = $cdek_api->get_auth_token();
        
        if (!$token) {
            wp_send_json_error('❌ СУПЕР ДЕБАГ: Не удалось получить токен авторизации! Проверьте логи.');
            return;
        }
        
        // 3. Тестируем простой API запрос
        error_log('💥 СУПЕР ДЕБАГ: ========== ТЕСТ ПРОСТОГО ЗАПРОСА ==========');
        
        $simple_data = array(
            'date' => date('Y-m-d\TH:i:sO'),
            'type' => 1,
            'currency' => 1, 
            'lang' => 'rus',
            'tariff_code' => 136,
            'from_location' => array('code' => 354), // Саратов
            'to_location' => array('code' => 44),   // Москва
            'packages' => array(
                array(
                    'weight' => 500,
                    'length' => 20,
                    'width' => 15,
                    'height' => 10
                )
            )
        );
        
        error_log('💥 СУПЕР ДЕБАГ: Данные простого запроса: ' . json_encode($simple_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        $response = wp_remote_post('https://api.cdek.ru/v2/calculator/tariff', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($simple_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('💥 СУПЕР ДЕБАГ: ❌ Ошибка простого запроса: ' . $response->get_error_message());
            wp_send_json_error('❌ СУПЕР ДЕБАГ: Ошибка простого запроса! Проверьте логи.');
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('💥 СУПЕР ДЕБАГ: Простой запрос - HTTP код: ' . $response_code);
        error_log('💥 СУПЕР ДЕБАГ: Простой запрос - Ответ: ' . $body);
        
        if ($response_code === 200) {
            $parsed = json_decode($body, true);
            if (isset($parsed['delivery_sum'])) {
                error_log('💥 СУПЕР ДЕБАГ: ✅ Простой запрос успешен! Стоимость: ' . $parsed['delivery_sum']);
                wp_send_json_success('✅ СУПЕР ДЕБАГ завершен! API работает. Простой запрос Саратов-Москва: ' . $parsed['delivery_sum'] . ' руб. Проверьте логи для деталей.');
            } else {
                error_log('💥 СУПЕР ДЕБАГ: ❌ В ответе нет delivery_sum: ' . print_r($parsed, true));
                wp_send_json_error('❌ СУПЕР ДЕБАГ: API отвечает, но нет delivery_sum! Проверьте логи.');
            }
        } else {
            error_log('💥 СУПЕР ДЕБАГ: ❌ Неправильный HTTP код: ' . $response_code);
            wp_send_json_error('💥 СУПЕР ДЕБАГ: Неправильный HTTP код ' . $response_code . '! Проверьте логи.');
        }
    }
}

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
            error_log('🔑 СДЭК AUTH: =================== ПОЛУЧЕНИЕ ТОКЕНА ===================');
            error_log('🔑 СДЭК AUTH: Кэшированный токен отсутствует, получаем новый');
            error_log('🔑 СДЭК AUTH: URL: ' . $this->base_url . '/oauth/token');
            error_log('🔑 СДЭК AUTH: Client ID: ' . $this->account);
            error_log('🔑 СДЭК AUTH: Client Secret: ' . substr($this->password, 0, 8) . '...');
            error_log('🔑 СДЭК AUTH: Client Secret полный (для диагностики): ' . $this->password);
            
            $auth_data = array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account,
                'client_secret' => $this->password
            );
            
            error_log('🔑 СДЭК AUTH: Данные авторизации: ' . print_r($auth_data, true));
            error_log('🔑 СДЭК AUTH: Заголовки запроса: Content-Type=application/x-www-form-urlencoded');
            error_log('🔑 СДЭК AUTH: Параметры WordPress: timeout=30, sslverify=true');
            
            $auth_headers = array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress/CDEK-Plugin'
            );
            
            error_log('🔑 СДЭК AUTH: Отправляем POST запрос...');
            
            $response = wp_remote_post($this->base_url . '/oauth/token', array(
                'headers' => $auth_headers,
                'body' => $auth_data,
                'timeout' => 30,
                'sslverify' => true
            ));
            
            error_log('🔑 СДЭК AUTH: Запрос отправлен, обрабатываем ответ...');
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $headers = wp_remote_retrieve_headers($response);
                
                error_log('🔑 СДЭК AUTH: ================= ОТВЕТ АВТОРИЗАЦИИ =================');
                error_log('🔑 СДЭК AUTH: HTTP код: ' . $response_code);
                error_log('🔑 СДЭК AUTH: Заголовки ответа: ' . print_r($headers, true));
                error_log('🔑 СДЭК AUTH: Тело ответа RAW: ' . $body);
                error_log('🔑 СДЭК AUTH: Длина ответа: ' . strlen($body) . ' байт');
                
                if (empty($body)) {
                    error_log('💥 СДЭК AUTH: ПУСТОЙ ОТВЕТ!');
                    return false;
                }
                
                $json_error_before = json_last_error();
                $parsed_body = json_decode($body, true);
                $json_error_after = json_last_error();
                
                error_log('🔑 СДЭК AUTH: JSON ошибка до декодирования: ' . $json_error_before);
                error_log('🔑 СДЭК AUTH: JSON ошибка после декодирования: ' . $json_error_after);
                
                if ($json_error_after !== JSON_ERROR_NONE) {
                    error_log('💥 СДЭК AUTH: ОШИБКА ПАРСИНГА JSON!');
                    error_log('💥 СДЭК AUTH: Описание ошибки: ' . json_last_error_msg());
                    return false;
                }
                
                error_log('🔑 СДЭК AUTH: Декодированный ответ: ' . print_r($parsed_body, true));
                
                if ($response_code === 200 && isset($parsed_body['access_token'])) {
                    $token = $parsed_body['access_token'];
                    $expires_in = isset($parsed_body['expires_in']) ? intval($parsed_body['expires_in']) : 3600;
                    
                    // Проверяем что токен не пустой
                    if (empty($token)) {
                        error_log('🔑 СДЭК AUTH: ❌ Получен пустой токен!');
                        return false;
                    }
                    
                    // Кэшируем токен с запасом времени
                    $cache_time = max(300, $expires_in - 300);
                    set_transient($cache_key, $token, $cache_time);
                    
                    error_log('🔑 СДЭК AUTH: ✅ Токен получен успешно!');
                    error_log('🔑 СДЭК AUTH: ✅ Длина токена: ' . strlen($token) . ' символов');
                    error_log('🔑 СДЭК AUTH: ✅ Действует: ' . $expires_in . ' сек, кэш: ' . $cache_time . ' сек');
                    error_log('🔑 СДЭК AUTH: ✅ Токен (первые 30 символов): ' . substr($token, 0, 30) . '...');
                } else {
                    error_log('🔑 СДЭК AUTH: ❌ Ошибка получения токена!');
                    error_log('🔑 СДЭК AUTH: ❌ HTTP код: ' . $response_code);
                    error_log('🔑 СДЭК AUTH: ❌ Доступные ключи: ' . (is_array($parsed_body) ? implode(', ', array_keys($parsed_body)) : 'не массив'));
                    
                    if (isset($parsed_body['error'])) {
                        error_log('🔑 СДЭК AUTH: ❌ Ошибка API: ' . $parsed_body['error']);
                        
                        // Специальная обработка ошибок
                        switch ($parsed_body['error']) {
                            case 'invalid_client':
                                error_log('💡 СДЭК AUTH: Неверные учетные данные (client_id или client_secret)');
                                error_log('💡 СДЭК AUTH: Проверьте настройки аккаунта СДЭК');
                                break;
                            case 'invalid_grant':
                                error_log('💡 СДЭК AUTH: Неверный тип авторизации');
                                break;
                            case 'access_denied':
                                error_log('💡 СДЭК AUTH: Доступ запрещен');
                                break;
                        }
                    }
                    
                    if (isset($parsed_body['error_description'])) {
                        error_log('🔑 СДЭК AUTH: ❌ Описание ошибки: ' . $parsed_body['error_description']);
                    }
                    
                    return false;
                }
            } else {
                error_log('💥 СДЭК AUTH: ❌ Ошибка HTTP запроса!');
                error_log('💥 СДЭК AUTH: ❌ Код ошибки: ' . $response->get_error_code());
                error_log('💥 СДЭК AUTH: ❌ Сообщение: ' . $response->get_error_message());
                error_log('💥 СДЭК AUTH: ❌ Все ошибки: ' . print_r($response->get_error_messages(), true));
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
        error_log('🎯 СДЭК РАСЧЕТ: Данные пункта: ' . print_r($point_data, true));
        error_log('🎯 СДЭК РАСЧЕТ: Параметры груза - Вес: ' . $cart_weight . ', Габариты: ' . print_r($cart_dimensions, true) . ', Стоимость: ' . $cart_value);
        
        $token = $this->get_auth_token();
        if (!$token) {
            error_log('❌ СДЭК расчет: Не удалось получить токен авторизации');
            error_log('❌ СДЭК расчет: Проверьте учетные данные API в настройках');
            error_log('❌ СДЭК расчет: Account: ' . $this->account);
            error_log('❌ СДЭК расчет: Password длина: ' . strlen($this->password));
            return false;
        }
        
        error_log('✅ СДЭК РАСЧЕТ: Токен авторизации получен: ' . substr($token, 0, 20) . '...');
        error_log('✅ СДЭК РАСЧЕТ: Длина токена: ' . strlen($token) . ' символов');
        
        // ЖЕСТКО ФИКСИРУЕМ САРАТОВ - ТОЛЬКО САРАТОВ!
        $sender_city_code = 354; // САРАТОВ - НЕ МЕНЯЕТСЯ!
        error_log('🏭 СДЭК РАСЧЕТ: ЖЕСТКО ЗАФИКСИРОВАН САРАТОВ - КОД 354');
        
        $from_location = array(
            'code' => 354 // САРАТОВ - ЖЕСТКО ЗАФИКСИРОВАН!
        );
        
        // Определяем локацию назначения
        $to_location = array();
        
        // Для расчета до пункта выдачи используем данные пункта
        if ($point_code && $point_data) {
            error_log('СДЭК API: Данные пункта для определения локации: ' . print_r($point_data, true));
            
            // Множественные способы определения локации
            $location_found = false;
            
            // Способ 1: city_code (ПРИОРИТЕТНЫЙ)
            if (isset($point_data['location']['city_code']) && !empty($point_data['location']['city_code'])) {
                $city_code = intval($point_data['location']['city_code']);
                // Проверяем что код города валидный (больше 0)
                if ($city_code > 0) {
                    $to_location['code'] = $city_code;
                    error_log('СДЭК API: ✅ Используем city_code: ' . $city_code);
                    $location_found = true;
                }
            }
            
            // Способ 2: Определение по коду пункта (если city_code не найден)
            if (!$location_found) {
                $city_codes = array(
                    'MSK' => 44,   // Москва
                    'SPB' => 137,  // Санкт-Петербург
                    'NSK' => 270,  // Новосибирск
                    'EKB' => 51,   // Екатеринбург
                    'KZN' => 172,  // Казань
                    'NN' => 276,   // Нижний Новгород
                    'CHE' => 56,   // Челябинск
                    'SAM' => 350,  // Самара
                    'UFA' => 414,  // Уфа
                    'ROV' => 335,  // Ростов-на-Дону
                    'KRD' => 93,   // Краснодар
                    'PERM' => 296, // Пермь
                    'VRN' => 432,  // Воронеж
                    'VGG' => 438,  // Волгоград
                    'SRT' => 354,  // Саратов
                    'SAR' => 354,  // Саратов (альтернативный)
                    'TYU' => 409,  // Тюмень
                    'KRS' => 207   // Красноярск (по умолчанию для KRS)
                );
                
                // Специальная обработка для Курска vs Красноярска
                if (stripos($point_code, 'KRS') === 0) {
                    $number = intval(substr($point_code, 3));
                    if ($number <= 99) {
                        // Курск - получаем код динамически
                        $kursk_code = $this->get_kursk_city_code($token);
                        if ($kursk_code > 0) {
                            $to_location['code'] = $kursk_code;
                            error_log('🏙️ СДЭК API: ✅ Определен Курск (пункт ' . $point_code . '), код: ' . $kursk_code);
                            $location_found = true;
                        }
                    } else {
                        $to_location['code'] = 207; // Красноярск
                        error_log('🏙️ СДЭК API: ✅ Определен Красноярск (пункт ' . $point_code . '), код: 207');
                        $location_found = true;
                    }
                } else {
                    // Обычная обработка по префиксу
                    foreach ($city_codes as $prefix => $city_code) {
                        if (stripos($point_code, $prefix) === 0) {
                            $to_location['code'] = $city_code;
                            error_log('🏙️ СДЭК API: ✅ Найден город по префиксу ' . $prefix . ', код: ' . $city_code);
                            $location_found = true;
                            break;
                        }
                    }
                }
            }
            
            // Способ 3: postal_code (если код города не найден)
            if (!$location_found && isset($point_data['location']['postal_code']) && !empty($point_data['location']['postal_code'])) {
                $postal_code = trim($point_data['location']['postal_code']);
                if (strlen($postal_code) === 6 && is_numeric($postal_code)) {
                    $to_location['postal_code'] = $postal_code;
                    error_log('СДЭК API: ✅ Используем postal_code: ' . $postal_code);
                    $location_found = true;
                }
            }
            
            // Способ 4: city name (последний резерв)
            if (!$location_found && isset($point_data['location']['city']) && !empty($point_data['location']['city'])) {
                $city_name = trim($point_data['location']['city']);
                if (!empty($city_name)) {
                    $to_location['city'] = $city_name;
                    error_log('СДЭК API: ✅ Используем city: ' . $city_name);
                    $location_found = true;
                }
            }
            
            // Способ 5: извлечение из name пункта (если все предыдущие не сработали)
            if (!$location_found && isset($point_data['name'])) {
                $name_parts = explode(',', $point_data['name']);
                if (count($name_parts) >= 2) {
                    $city_from_name = trim($name_parts[1]);
                    if (!empty($city_from_name)) {
                        $to_location['city'] = $city_from_name;
                        error_log('СДЭК API: ✅ Извлекли город из name: ' . $city_from_name);
                        $location_found = true;
                    }
                }
            }
            
            // Способ 6: извлечение из полного адреса (последний резерв)
            if (!$location_found && isset($point_data['location']['address_full'])) {
                $address_parts = explode(',', $point_data['location']['address_full']);
                foreach ($address_parts as $part) {
                    $part = trim($part);
                    // Ищем часть с "Москва", "Санкт-Петербург" и т.д.
                    if (preg_match('/^(г\.?\s*)?([А-Яа-я\-\s]+)$/u', $part, $matches)) {
                        $city_candidate = trim($matches[2]);
                        $known_cities = ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород', 'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж', 'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'];
                        if (in_array($city_candidate, $known_cities)) {
                            $to_location['city'] = $city_candidate;
                            error_log('СДЭК API: ✅ Извлекли известный город из адреса: ' . $city_candidate);
                            $location_found = true;
                            break;
                        }
                    }
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
        
        // Подготавливаем данные о посылках с расширенной валидацией
        $weight = max(100, intval($cart_weight * 1000)); // Переводим в граммы, минимум 100г
        $length = max(1, min(1500, intval($cart_dimensions['length']))); // 1-1500см согласно API
        $width = max(1, min(1500, intval($cart_dimensions['width']))); // 1-1500см согласно API  
        $height = max(1, min(1500, intval($cart_dimensions['height']))); // 1-1500см согласно API
        
        // Проверяем максимальный вес (30кг = 30000г)
        if ($weight > 30000) {
            error_log('⚠️ СДЭК РАСЧЕТ: Вес превышает максимальный лимит 30кг, устанавливаем 30кг');
            $weight = 30000;
        }
        
        error_log('📦 СДЭК РАСЧЕТ: Исходный вес (кг): ' . $cart_weight . ', итоговый вес (г): ' . $weight);
        error_log('📦 СДЭК РАСЧЕТ: Исходные габариты (см): ' . $cart_dimensions['length'] . 'x' . $cart_dimensions['width'] . 'x' . $cart_dimensions['height']);
        error_log('📦 СДЭК РАСЧЕТ: Итоговые габариты (см): ' . $length . 'x' . $width . 'x' . $height);
        
        // Проверяем объемный вес (длина * ширина * высота / 5000)
        $volume_weight = ($length * $width * $height) / 5000;
        error_log('📦 СДЭК РАСЧЕТ: Объемный вес (г): ' . intval($volume_weight));
        
        $packages = array(
            array(
                'weight' => $weight,
                'length' => $length,
                'width' => $width,
                'height' => $height
            )
        );
        
        error_log('📦 СДЭК API: Подготовленная посылка: ' . print_r($packages[0], true));
        
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
        
        // Валидация обязательных полей
        $validation_errors = array();
        
        if (!isset($from_location['code']) || empty($from_location['code'])) {
            $validation_errors[] = 'Не указан код города отправителя';
        }
        
        if (empty($to_location)) {
            $validation_errors[] = 'Не указана локация получателя';
        } elseif (!isset($to_location['code']) && !isset($to_location['postal_code']) && !isset($to_location['city'])) {
            $validation_errors[] = 'Не указан код города, почтовый индекс или название города получателя';
        }
        
        if (empty($packages) || !is_array($packages)) {
            $validation_errors[] = 'Не указаны данные о посылках';
        } else {
            foreach ($packages as $i => $package) {
                if (!isset($package['weight']) || $package['weight'] < 100) {
                    $validation_errors[] = "Посылка $i: некорректный вес (минимум 100г)";
                }
                if (!isset($package['length']) || $package['length'] < 1) {
                    $validation_errors[] = "Посылка $i: некорректная длина (минимум 1см)";
                }
                if (!isset($package['width']) || $package['width'] < 1) {
                    $validation_errors[] = "Посылка $i: некорректная ширина (минимум 1см)";
                }
                if (!isset($package['height']) || $package['height'] < 1) {
                    $validation_errors[] = "Посылка $i: некорректная высота (минимум 1см)";
                }
            }
        }
        
        if (!empty($validation_errors)) {
            error_log('❌ СДЭК API: Ошибки валидации данных:');
            foreach ($validation_errors as $error) {
                error_log('❌ СДЭК API: - ' . $error);
            }
            return false;
        }
        
        error_log('📋 СДЭК API: ✅ Валидация пройдена успешно');
        error_log('📋 СДЭК API: Используем тариф ' . $tariff_code . ' от города ' . $from_location['code'] . ' до города ' . (isset($to_location['code']) ? $to_location['code'] : (isset($to_location['city']) ? $to_location['city'] : 'не определен')));
        
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
        error_log('🔑 СДЭК API: Полный токен для диагностики: ' . $token);
        error_log('📍 СДЭК API: URL запроса: ' . $this->base_url . '/calculator/tariff');
        error_log('📍 СДЭК API: Заголовки: Authorization=Bearer [токен], Content-Type=application/json');
        error_log('📍 СДЭК API: Тело запроса RAW: ' . json_encode($data));
        
        $request_headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        );
        
        $request_body = json_encode($data);
        
        error_log('🔧 СДЭК API: Финальные заголовки: ' . print_r($request_headers, true));
        error_log('🔧 СДЭК API: Финальное тело: ' . $request_body);
        
        $response = wp_remote_post($this->base_url . '/calculator/tariff', array(
            'headers' => $request_headers,
            'body' => $request_body,
            'timeout' => 30 // Увеличиваем таймаут
        ));
        
        if (is_wp_error($response)) {
            error_log('💥 СДЭК расчет: Ошибка HTTP запроса!');
            error_log('💥 СДЭК расчет: Код ошибки: ' . $response->get_error_code());
            error_log('💥 СДЭК расчет: Сообщение ошибки: ' . $response->get_error_message());
            error_log('💥 СДЭК расчет: Все ошибки: ' . print_r($response->get_error_messages(), true));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        error_log('📥 СДЭК API: =================== ПОЛНЫЙ ОТВЕТ ОТ API ===================');
        error_log('📥 СДЭК API: HTTP код ответа: ' . $response_code);
        error_log('📥 СДЭК API: Заголовки ответа: ' . print_r($headers, true));
        error_log('📥 СДЭК API: Тело ответа RAW: ' . $body);
        error_log('📥 СДЭК API: Длина тела ответа: ' . strlen($body) . ' байт');
        
        if (empty($body)) {
            error_log('💥 СДЭК API: ПУСТОЕ ТЕЛО ОТВЕТА!');
            return false;
        }
        
        // Дополнительная диагностика для отладки
        if ($response_code !== 200) {
            error_log('❌ СДЭК API: Некорректный HTTP код: ' . $response_code);
            
            switch ($response_code) {
                case 401:
                    error_log('💡 СДЭК API: HTTP 401 - Проблемы с авторизацией');
                    error_log('💡 СДЭК API: Токен устарел или неверен, очищаем кэш');
                    delete_transient('cdek_auth_token');
                    
                    // Автоматически пробуем альтернативный расчет с новым токеном
                    error_log('🔄 СДЭК API: Пытаемся получить новый токен для повторного запроса...');
                    $new_token = $this->get_auth_token();
                    if ($new_token) {
                        error_log('✅ СДЭК API: Новый токен получен, делегируем альтернативному расчету');
                        return $this->try_alternative_calculation($data, $new_token);
                    }
                    break;
                case 400:
                    error_log('💡 СДЭК API: HTTP 400 - Неверные параметры запроса');
                    error_log('💡 СДЭК API: Проверьте корректность данных: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
                    break;
                case 403:
                    error_log('💡 СДЭК API: HTTP 403 - Доступ запрещен');
                    error_log('💡 СДЭК API: Проверьте права аккаунта СДЭК');
                    break;
                case 422:
                    error_log('💡 СДЭК API: HTTP 422 - Ошибка валидации данных');
                    break;
                case 500:
                case 502:
                case 503:
                    error_log('💡 СДЭК API: HTTP ' . $response_code . ' - Ошибка на стороне сервера СДЭК');
                    error_log('💡 СДЭК API: Попробуйте повторить запрос позже');
                    break;
                default:
                    error_log('💡 СДЭК API: Неизвестный HTTP код: ' . $response_code);
            }
        }
        
        error_log('🔍 СДЭК API: ================= АНАЛИЗ JSON ОТВЕТА =================');
        
        $json_error_before = json_last_error();
        $parsed_body = json_decode($body, true);
        $json_error_after = json_last_error();
        
        error_log('🔍 СДЭК API: JSON ошибка до декодирования: ' . $json_error_before);
        error_log('🔍 СДЭК API: JSON ошибка после декодирования: ' . $json_error_after);
        error_log('🔍 СДЭК API: Константы JSON ошибок - JSON_ERROR_NONE=' . JSON_ERROR_NONE . ', JSON_ERROR_SYNTAX=' . JSON_ERROR_SYNTAX);
        
        if ($json_error_after !== JSON_ERROR_NONE) {
            error_log('💥 СДЭК API: ОШИБКА ПАРСИНГА JSON!');
            error_log('💥 СДЭК API: Код ошибки JSON: ' . $json_error_after);
            error_log('💥 СДЭК API: Описание ошибки JSON: ' . json_last_error_msg());
            error_log('💥 СДЭК API: Первые 500 символов ответа: ' . substr($body, 0, 500));
            return false;
        }
        
        if ($parsed_body === null) {
            error_log('💥 СДЭК API: Parsed body равен NULL!');
            return false;
        }
        
        error_log('🔍 СДЭК API: JSON успешно декодирован. Тип: ' . gettype($parsed_body));
        error_log('🔍 СДЭК API: Количество элементов в ответе: ' . (is_array($parsed_body) ? count($parsed_body) : 'не массив'));
        error_log('🔍 СДЭК API: Ключи верхнего уровня: ' . (is_array($parsed_body) ? implode(', ', array_keys($parsed_body)) : 'нет ключей'));
        
        if ($response_code === 200 && $parsed_body) {
            error_log('✅ СДЭК API: Успешный HTTP ответ 200, JSON декодирован');
            error_log('✅ СДЭК API: Полный декодированный ответ: ' . print_r($parsed_body, true));
            
            // Проверяем наличие ошибок в ответе в первую очередь
            if (isset($parsed_body['errors']) && !empty($parsed_body['errors'])) {
                error_log('❌ СДЭК API: API вернул ошибки: ' . print_r($parsed_body['errors'], true));
                
                // Анализируем ошибки для понимания проблемы
                foreach ($parsed_body['errors'] as $error) {
                    if (isset($error['code']) && isset($error['message'])) {
                        error_log('❌ СДЭК API: Ошибка ' . $error['code'] . ': ' . $error['message']);
                        
                        // Специальная обработка распространенных ошибок
                        switch ($error['code']) {
                            case 'v2_entity_not_found':
                                error_log('💡 СДЭК API: Пункт выдачи или город не найден в базе СДЭК');
                                error_log('💡 СДЭК API: Проверьте код города отправителя: ' . $from_location['code']);
                                error_log('💡 СДЭК API: Проверьте данные получателя: ' . print_r($to_location, true));
                                break;
                            case 'v2_tariff_not_found':
                                error_log('💡 СДЭК API: Тариф ' . $tariff_code . ' не доступен для направления ' . $from_location['code'] . ' -> ' . (isset($to_location['code']) ? $to_location['code'] : 'undefined'));
                                return $this->try_alternative_calculation($data, $token);
                                break;
                            case 'invalid_token':
                                error_log('💡 СДЭК API: Токен авторизации истек или неверен, очищаем кэш');
                                delete_transient('cdek_auth_token');
                                
                                // Пробуем получить новый токен и повторить запрос
                                error_log('🔄 СДЭК API: Пытаемся получить новый токен...');
                                $new_token = $this->get_auth_token();
                                if ($new_token && $new_token !== $token) {
                                    error_log('✅ СДЭК API: Новый токен получен, повторяем запрос');
                                    
                                    $retry_response = wp_remote_post($this->base_url . '/calculator/tariff', array(
                                        'headers' => array(
                                            'Authorization' => 'Bearer ' . $new_token,
                                            'Content-Type' => 'application/json'
                                        ),
                                        'body' => json_encode($data),
                                        'timeout' => 30
                                    ));
                                    
                                    if (!is_wp_error($retry_response)) {
                                        $retry_code = wp_remote_retrieve_response_code($retry_response);
                                        $retry_body = wp_remote_retrieve_body($retry_response);
                                        
                                        if ($retry_code === 200) {
                                            $retry_parsed = json_decode($retry_body, true);
                                            if (isset($retry_parsed['delivery_sum']) && $retry_parsed['delivery_sum'] > 0) {
                                                error_log('🎉 СДЭК API: Успех после обновления токена!');
                                                return array(
                                                    'delivery_sum' => intval($retry_parsed['delivery_sum']),
                                                    'period_min' => isset($retry_parsed['period_min']) ? $retry_parsed['period_min'] : null,
                                                    'period_max' => isset($retry_parsed['period_max']) ? $retry_parsed['period_max'] : null,
                                                    'total_sum' => isset($retry_parsed['total_sum']) ? intval($retry_parsed['total_sum']) : intval($retry_parsed['delivery_sum']),
                                                    'api_success' => true,
                                                    'token_refreshed' => true
                                                );
                                            }
                                        }
                                    }
                                }
                                return false;
                                break;
                            case 'v2_validation_error':
                                error_log('💡 СДЭК API: Ошибка валидации данных запроса');
                                error_log('💡 СДЭК API: Проверьте корректность параметров: ' . print_r($data, true));
                                break;
                            default:
                                error_log('💡 СДЭК API: Неизвестная ошибка ' . $error['code'] . ', проверьте документацию API');
                        }
                    }
                }
                
                // Пробуем альтернативный способ расчета
                return $this->try_alternative_calculation($data, $token);
            } elseif (isset($parsed_body['delivery_sum']) && $parsed_body['delivery_sum'] > 0) {
                // Успешный расчет стоимости
                error_log('🎉 СДЭК API: Успешно получена стоимость от API: ' . $parsed_body['delivery_sum'] . ' руб.');
                
                // Проверяем предупреждения если есть
                if (isset($parsed_body['warnings']) && !empty($parsed_body['warnings'])) {
                    error_log('⚠️ СДЭК API: Предупреждения: ' . print_r($parsed_body['warnings'], true));
                }
                
                return array(
                    'delivery_sum' => intval($parsed_body['delivery_sum']),
                    'period_min' => isset($parsed_body['period_min']) ? $parsed_body['period_min'] : null,
                    'period_max' => isset($parsed_body['period_max']) ? $parsed_body['period_max'] : null,
                    'total_sum' => isset($parsed_body['total_sum']) ? intval($parsed_body['total_sum']) : intval($parsed_body['delivery_sum']),
                    'currency' => isset($parsed_body['currency']) ? $parsed_body['currency'] : 'RUB',
                    'weight_calc' => isset($parsed_body['weight_calc']) ? $parsed_body['weight_calc'] : $weight,
                    'api_success' => true
                );
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
        error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ РАСЧЕТ: Пробуем альтернативные тарифы');
        
        // Попробуем разные тарифы для ПВЗ и курьерской доставки (в порядке приоритета)
        $alternative_tariffs = [
            136 => 'Посылка склад-постамат/ПВЗ',
            233 => 'Эконом посылка склад-дверь', 
            234 => 'Стандарт посылка склад-дверь',
            138 => 'Посылка дверь-постамат',
            62 => 'Магистральный экспресс склад-склад',
            63 => 'Магистральный экспресс склад-дверь',
            366 => 'Посылка склад-склад',
            368 => 'Посылка дверь-дверь'
        ];
        
        foreach ($alternative_tariffs as $tariff => $tariff_name) {
            error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Пробуем тариф ' . $tariff . ' (' . $tariff_name . ')');
            
            $data = $original_data;
            $data['tariff_code'] = $tariff;
            
            // Валидация обязательных полей согласно API
            $data['date'] = isset($data['date']) ? $data['date'] : date('Y-m-d\TH:i:sO');
            $data['type'] = isset($data['type']) ? $data['type'] : 1;
            $data['currency'] = isset($data['currency']) ? $data['currency'] : 1;
            $data['lang'] = isset($data['lang']) ? $data['lang'] : 'rus';
            
            // Если нет кода города назначения, попробуем с почтовым индексом или названием
            if (!isset($data['to_location']['code']) && !isset($data['to_location']['postal_code']) && !isset($data['to_location']['city'])) {
                error_log('⚠️ СДЭК АЛЬТЕРНАТИВНЫЙ: Не указана локация назначения, пропускаем тариф ' . $tariff);
                continue;
            }
            
            error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Данные для тарифа ' . $tariff . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            
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
                $response_body = wp_remote_retrieve_body($response);
                error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Ответ для тарифа ' . $tariff . ' - HTTP: ' . $response_code . ', Тело: ' . substr($response_body, 0, 300) . '...');
                
                if ($response_code === 200) {
                    $body = json_decode($response_body, true);
                    
                    // Проверяем ошибки в первую очередь
                    if (isset($body['errors']) && !empty($body['errors'])) {
                        error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Тариф ' . $tariff . ' вернул ошибки: ' . print_r($body['errors'], true));
                        continue; // Пробуем следующий тариф
                    }
                    
                    if (isset($body['delivery_sum']) && $body['delivery_sum'] > 0) {
                        error_log('✅ СДЭК АЛЬТЕРНАТИВНЫЙ: Успешный расчет с тарифом ' . $tariff . ' (' . $tariff_name . '): ' . $body['delivery_sum'] . ' руб.');
                        return array(
                            'delivery_sum' => intval($body['delivery_sum']),
                            'period_min' => isset($body['period_min']) ? $body['period_min'] : null,
                            'period_max' => isset($body['period_max']) ? $body['period_max'] : null,
                            'total_sum' => isset($body['total_sum']) ? intval($body['total_sum']) : intval($body['delivery_sum']),
                            'api_success' => true,
                            'alternative_tariff' => $tariff,
                            'tariff_name' => $tariff_name
                        );
                    } else {
                        error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Тариф ' . $tariff . ' не вернул delivery_sum: ' . print_r($body, true));
                    }
                } else {
                    error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Тариф ' . $tariff . ' - некорректный HTTP код: ' . $response_code);
                }
            } else {
                error_log('🔄 СДЭК АЛЬТЕРНАТИВНЫЙ: Ошибка HTTP для тарифа ' . $tariff . ': ' . $response->get_error_message());
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
}

// Инициализируем плагин
new CdekDeliveryPlugin();
