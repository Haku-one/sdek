<?php
/**
 * WooCommerce Store API Extension для СДЭК Доставки
 *
 * @package CdekDelivery
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

/**
 * Класс расширения Store API для СДЭК
 */
class WC_Cdek_Store_API_Extension {

    /**
     * Stores Rest Extending instance.
     *
     * @var ExtendSchema
     */
    private static $extend;

    /**
     * Plugin Identifier, unique to each plugin.
     *
     * @var string
     */
    const IDENTIFIER = 'cdek-delivery';

    /**
     * Bootstraps the class and hooks required actions & filters.
     */
    public static function init() {
        add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_blocks_loaded'));
    }

    /**
     * Integrates with the blocks packages when they are loaded.
     */
    public static function woocommerce_blocks_loaded() {
        if (!class_exists('\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema')) {
            error_log('CDEK Store API: ExtendSchema не найден');
            return;
        }

        self::$extend = \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::get_instance();
        
        error_log('CDEK Store API: Инициализируем расширение Store API');
        
        self::extend_store();
    }

    /**
     * Registers the actual extension
     */
    public static function extend_store() {
        try {
            // Проверяем что ExtendSchema доступен
            if (!self::$extend) {
                error_log('CDEK Store API: ExtendSchema не инициализирован');
                return;
            }

            if (is_callable([self::$extend, 'register_endpoint_data'])) {
                self::$extend->register_endpoint_data(
                    array(
                        'endpoint'        => CheckoutSchema::IDENTIFIER,
                        'namespace'       => self::IDENTIFIER,
                        'data_callback'   => array(__CLASS__, 'data_callback'),
                        'schema_callback' => array(__CLASS__, 'schema_callback'),
                        'schema_type'     => ARRAY_A,
                    )
                );
                error_log('CDEK Store API: register_endpoint_data зарегистрирован');
            } else {
                error_log('CDEK Store API: register_endpoint_data недоступен');
            }

            if (is_callable([self::$extend, 'register_update_callback'])) {
                self::$extend->register_update_callback(
                    array(
                        'namespace' => self::IDENTIFIER,
                        'callback'  => array(__CLASS__, 'update_callback'),
                    )
                );
                error_log('CDEK Store API: register_update_callback зарегистрирован');
            } else {
                error_log('CDEK Store API: register_update_callback недоступен');
            }

            // Альтернативный метод регистрации для совместимости
            add_action('woocommerce_store_api_checkout_update_order_meta', array(__CLASS__, 'save_cdek_order_meta'));
            add_filter('woocommerce_store_api_checkout_order_received_object', array(__CLASS__, 'add_cdek_data_to_response'), 10, 3);

            error_log('CDEK Store API: Все обработчики зарегистрированы');
            
        } catch (Exception $e) {
            error_log('CDEK Store API: Ошибка регистрации расширения: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('CDEK Store API: Фатальная ошибка регистрации расширения: ' . $e->getMessage());
        }
    }

    /**
     * Returns data for the extension
     */
    public static function data_callback() {
        return array(
            'point_code' => '',
            'point_data' => null,
            'delivery_cost' => 0,
            'selected_city' => ''
        );
    }

    /**
     * Returns the schema for the extension data
     */
    public static function schema_callback() {
        return array(
            'point_code' => array(
                'description' => 'Код выбранного пункта выдачи СДЭК',
                'type'        => 'string',
                'context'     => array('view', 'edit'),
                'readonly'    => false,
            ),
            'point_data' => array(
                'description' => 'Данные выбранного пункта выдачи СДЭК',
                'type'        => 'object',
                'context'     => array('view', 'edit'),
                'readonly'    => false,
            ),
            'delivery_cost' => array(
                'description' => 'Стоимость доставки СДЭК',
                'type'        => 'number',
                'context'     => array('view', 'edit'),
                'readonly'    => false,
            ),
            'selected_city' => array(
                'description' => 'Выбранный город доставки',
                'type'        => 'string',
                'context'     => array('view', 'edit'),
                'readonly'    => false,
            ),
        );
    }

    /**
     * Update callback when checkout is processed
     */
    public static function update_callback($data) {
        try {
            error_log('CDEK Store API: update_callback вызван с данными: ' . print_r($data, true));
            
            // Сохраняем данные в сессию WooCommerce
            if (function_exists('WC') && WC() && WC()->session) {
                if (isset($data['point_code']) && !empty($data['point_code'])) {
                    WC()->session->set('cdek_selected_point_code', $data['point_code']);
                    error_log('CDEK Store API: Сохранен point_code в сессию: ' . $data['point_code']);
                }
                
                if (isset($data['point_data']) && !empty($data['point_data'])) {
                    WC()->session->set('cdek_selected_point_data', $data['point_data']);
                    error_log('CDEK Store API: Сохранены point_data в сессию');
                }
                
                if (isset($data['delivery_cost']) && is_numeric($data['delivery_cost'])) {
                    WC()->session->set('cdek_delivery_cost', floatval($data['delivery_cost']));
                    error_log('CDEK Store API: Сохранена delivery_cost в сессию: ' . $data['delivery_cost']);
                }
                
                if (isset($data['selected_city']) && !empty($data['selected_city'])) {
                    WC()->session->set('cdek_selected_city', $data['selected_city']);
                    error_log('CDEK Store API: Сохранен selected_city в сессию: ' . $data['selected_city']);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('CDEK Store API: Ошибка в update_callback: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save CDEK order meta
     */
    public static function save_cdek_order_meta($order) {
        try {
            if (!$order || !method_exists($order, 'get_id')) {
                error_log('CDEK Store API: Некорректный объект заказа в save_cdek_order_meta');
                return;
            }

            $order_id = $order->get_id();
            error_log('CDEK Store API: Сохраняем мета для заказа ID: ' . $order_id);

            // Получаем данные из сессии
            if (function_exists('WC') && WC() && WC()->session) {
                $point_code = WC()->session->get('cdek_selected_point_code');
                $point_data = WC()->session->get('cdek_selected_point_data');
                $delivery_cost = WC()->session->get('cdek_delivery_cost');
                $selected_city = WC()->session->get('cdek_selected_city');

                if (!empty($point_code)) {
                    update_post_meta($order_id, '_cdek_point_code', sanitize_text_field($point_code));
                    error_log('CDEK Store API: Сохранен _cdek_point_code: ' . $point_code);
                }

                if (!empty($point_data)) {
                    update_post_meta($order_id, '_cdek_point_data', $point_data);
                    error_log('CDEK Store API: Сохранены _cdek_point_data');
                }

                if (!empty($delivery_cost) && is_numeric($delivery_cost)) {
                    update_post_meta($order_id, '_cdek_delivery_cost', floatval($delivery_cost));
                    error_log('CDEK Store API: Сохранена _cdek_delivery_cost: ' . $delivery_cost);
                }

                if (!empty($selected_city)) {
                    update_post_meta($order_id, '_cdek_selected_city', sanitize_text_field($selected_city));
                    error_log('CDEK Store API: Сохранен _cdek_selected_city: ' . $selected_city);
                }
            }

        } catch (Exception $e) {
            error_log('CDEK Store API: Ошибка в save_cdek_order_meta: ' . $e->getMessage());
        }
    }

    /**
     * Add CDEK data to order response
     */
    public static function add_cdek_data_to_response($response, $order, $request) {
        try {
            if ($order && is_object($order) && method_exists($order, 'get_id')) {
                $order_id = $order->get_id();
                
                $cdek_point_code = get_post_meta($order_id, '_cdek_point_code', true);
                $cdek_point_data = get_post_meta($order_id, '_cdek_point_data', true);
                $cdek_delivery_cost = get_post_meta($order_id, '_cdek_delivery_cost', true);
                $cdek_selected_city = get_post_meta($order_id, '_cdek_selected_city', true);
                
                if ($cdek_point_code || $cdek_point_data || $cdek_delivery_cost || $cdek_selected_city) {
                    if (!isset($response['extensions'])) {
                        $response['extensions'] = array();
                    }
                    
                    $response['extensions']['cdek-delivery'] = array(
                        'point_code' => $cdek_point_code,
                        'point_data' => $cdek_point_data,
                        'delivery_cost' => $cdek_delivery_cost,
                        'selected_city' => $cdek_selected_city
                    );
                    
                    error_log('CDEK Store API: Добавлены данные СДЭК в ответ заказа');
                }
            }
        } catch (Exception $e) {
            error_log('CDEK Store API: Ошибка добавления данных в ответ: ' . $e->getMessage());
        }
        
        return $response;
    }
}

// Инициализация расширения Store API выполняется в основном плагине