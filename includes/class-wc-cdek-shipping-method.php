<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Cdek_Shipping_Method extends WC_Shipping_Method {
    
    public function __construct($instance_id = 0) {
        $this->id = 'cdek_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('СДЭК Доставка', 'cdek-delivery');
        $this->method_description = __('Доставка через СДЭК с выбором пункта выдачи', 'cdek-delivery');
        
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->init();
        
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
        $this->title = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
    }
    
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Отключить', 'cdek-delivery'),
                'type' => 'checkbox',
                'description' => __('Включить этот способ доставки', 'cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название метода', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Название, которое покупатель видит во время оформления заказа.', 'cdek-delivery'),
                'default' => __('СДЭК - Пункт выдачи', 'cdek-delivery'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Описание', 'cdek-delivery'),
                'type' => 'textarea',
                'description' => __('Описание метода доставки, которое покупатель видит во время оформления заказа.', 'cdek-delivery'),
                'default' => __('Доставка в пункт выдачи СДЭК', 'cdek-delivery'),
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Стоимость', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Фиксированная стоимость доставки или оставьте пустым для расчета через API', 'cdek-delivery'),
                'default' => '',
                'desc_tip' => true,
            ),
            'sender_city_code' => array(
                'title' => __('Код города отправления', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Код города СДЭК откуда отправляются посылки', 'cdek-delivery'),
                'default' => '44', // Москва
                'desc_tip' => true,
            ),
        );
    }
    
    public function calculate_shipping($package = array()) {
        if (!$this->is_available($package)) {
            return;
        }
        
        $cost = $this->get_option('cost');
        
        if (empty($cost)) {
            // Расчет стоимости через API СДЭК
            $cost = $this->calculate_cdek_cost($package);
        }
        
        if ($cost !== false) {
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $cost,
                'package' => $package,
            );
            
            $this->add_rate($rate);
        }
    }
    
    private function calculate_cdek_cost($package) {
        $cdek_api = new CdekAPI();
        
        // Подготавливаем данные для расчета
        $from_location = array(
            'code' => $this->get_option('sender_city_code', '44')
        );
        
        // Получаем адрес доставки
        $destination = $package['destination'];
        $address = isset($destination['address_1']) ? $destination['address_1'] : '';
        
        if (empty($address)) {
            return false;
        }
        
        // Извлекаем город из адреса
        $city_name = $this->extract_city_from_address($address);
        
        $to_location = array(
            'city' => $city_name
        );
        
        // Подготавливаем данные о посылках
        $packages_data = array();
        $total_weight = 0;
        
        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $weight = $product->get_weight();
            $quantity = $values['quantity'];
            
            if ($weight) {
                $total_weight += floatval($weight) * $quantity;
            }
        }
        
        // Если вес не указан, используем минимальный вес
        if ($total_weight == 0) {
            $total_weight = 0.1; // 100 грамм
        }
        
        $packages_data[] = array(
            'weight' => intval($total_weight * 1000), // Переводим в граммы
            'length' => 20,
            'width' => 20,
            'height' => 10
        );
        
        $result = $cdek_api->calculate_delivery_cost($from_location, $to_location, $packages_data);
        
        if ($result && isset($result['delivery_sum'])) {
            return $result['delivery_sum'];
        }
        
        return 200; // Базовая стоимость доставки, если API недоступен
    }
    
    private function extract_city_from_address($address) {
        $parts = explode(',', $address);
        return trim($parts[0]);
    }
    
    public function is_available($package) {
        return $this->enabled === 'yes';
    }
}