<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс метода доставки СДЭК
 */
class WC_CDEK_Shipping_Method extends WC_Shipping_Method {
    
    public function __construct($instance_id = 0) {
        $this->id = 'cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('СДЭК', 'cdek-delivery');
        $this->method_description = __('Доставка через службу СДЭК', 'cdek-delivery');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
    }
    
    public function init() {
        // Загружаем настройки
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        
        // Сохранение настроек
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Отключить', 'cdek-delivery'),
                'type' => 'checkbox',
                'description' => __('Включить метод доставки СДЭК', 'cdek-delivery'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название метода', 'cdek-delivery'),
                'type' => 'text',
                'description' => __('Название, которое увидит покупатель', 'cdek-delivery'),
                'default' => __('СДЭК', 'cdek-delivery'),
                'desc_tip' => true,
            ),
        );
    }
    
    public function calculate_shipping($package = array()) {
        // Базовая стоимость доставки
        $cost = 300;
        
        // Добавляем стоимость доставки
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
        );
        
        $this->add_rate($rate);
    }
    
    public function is_available($package) {
        return $this->is_enabled();
    }
}