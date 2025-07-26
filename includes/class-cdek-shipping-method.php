<?php
/**
 * CDEK Shipping Method for WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDEK_Shipping_Method extends WC_Shipping_Method {
    
    public function __construct($instance_id = 0) {
        $this->id = 'cdek_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('СДЭК доставка', 'cdek-shipping');
        $this->method_description = __('Доставка через службу СДЭК с выбором пункта выдачи', 'cdek-shipping');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
    }
    
    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Отключить', 'cdek-shipping'),
                'type' => 'checkbox',
                'description' => __('Включить доставку СДЭК', 'cdek-shipping'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название метода', 'cdek-shipping'),
                'type' => 'text',
                'description' => __('Название, которое увидит покупатель при выборе доставки', 'cdek-shipping'),
                'default' => __('Доставка СДЭК', 'cdek-shipping'),
                'desc_tip' => true,
            ),
            'sender_city_code' => array(
                'title' => __('Код города отправления', 'cdek-shipping'),
                'type' => 'text',
                'description' => __('Код города отправления в системе СДЭК', 'cdek-shipping'),
                'default' => '44', // Москва
                'desc_tip' => true,
            ),
            'tariff_code' => array(
                'title' => __('Тариф', 'cdek-shipping'),
                'type' => 'select',
                'description' => __('Выберите тариф доставки', 'cdek-shipping'),
                'default' => '136',
                'options' => array(
                    '136' => 'Посылка склад-склад',
                    '138' => 'Посылка склад-дверь',
                    '233' => 'Экономичная посылка склад-склад',
                    '234' => 'Экономичная посылка склад-дверь'
                ),
                'desc_tip' => true,
            ),
        );
    }
    
    public function calculate_shipping($package = array()) {
        // Получаем город доставки
        $destination = $package['destination'];
        $city = isset($destination['city']) ? trim($destination['city']) : '';
        
        if (empty($city)) {
            return;
        }
        
        // Получаем код города назначения
        if (!class_exists('CDEK_API')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CDEK Plugin: CDEK_API class not found in shipping method');
            }
            return;
        }
        
        $cdek_api = new CDEK_API();
        $to_city_code = $cdek_api->get_city_code($city);
        
        if (!$to_city_code) {
            return;
        }
        
        // Подготавливаем данные о посылках
        $packages_data = $this->prepare_packages_data($package);
        
        // Рассчитываем стоимость доставки
        $from_city_code = $this->get_option('sender_city_code', '44');
        $shipping_cost = $cdek_api->calculate_shipping($from_city_code, $to_city_code, $packages_data);
        
        if ($shipping_cost && isset($shipping_cost['tariff_codes'])) {
            $tariff_code = $this->get_option('tariff_code', '136');
            $selected_tariff = null;
            
            foreach ($shipping_cost['tariff_codes'] as $tariff) {
                if ($tariff['tariff_code'] == $tariff_code) {
                    $selected_tariff = $tariff;
                    break;
                }
            }
            
            if ($selected_tariff) {
                $rate = array(
                    'id' => $this->id . '_' . $tariff_code,
                    'label' => $this->title . ' (' . $selected_tariff['tariff_name'] . ')',
                    'cost' => $selected_tariff['delivery_sum'],
                    'meta_data' => array(
                        'cdek_tariff_code' => $tariff_code,
                        'cdek_delivery_mode' => $selected_tariff['delivery_mode'],
                        'cdek_period_min' => $selected_tariff['period_min'],
                        'cdek_period_max' => $selected_tariff['period_max'],
                        'cdek_city' => $city,
                        'cdek_to_city_code' => $to_city_code
                    )
                );
                
                $this->add_rate($rate);
            }
        }
    }
    
    private function prepare_packages_data($package) {
        $packages_data = array();
        $total_weight = 0;
        $total_length = 0;
        $total_width = 0;
        $total_height = 0;
        
        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $quantity = $values['quantity'];
            
            // Получаем вес товара (в граммах)
            $weight = $product->get_weight();
            if ($weight) {
                $weight_in_grams = $this->convert_weight_to_grams($weight);
                $total_weight += $weight_in_grams * $quantity;
            }
            
            // Получаем размеры товара (в см)
            $length = $product->get_length();
            $width = $product->get_width();
            $height = $product->get_height();
            
            if ($length && $width && $height) {
                $length_in_cm = $this->convert_dimension_to_cm($length);
                $width_in_cm = $this->convert_dimension_to_cm($width);
                $height_in_cm = $this->convert_dimension_to_cm($height);
                
                $total_length = max($total_length, $length_in_cm);
                $total_width = max($total_width, $width_in_cm);
                $total_height += $height_in_cm * $quantity;
            }
        }
        
        // Если размеры не указаны, используем значения по умолчанию
        if (!$total_length || !$total_width || !$total_height) {
            $total_length = 10;
            $total_width = 10;
            $total_height = 10;
        }
        
        // Если вес не указан, используем минимальный вес
        if (!$total_weight) {
            $total_weight = 100; // 100 грамм
        }
        
        $packages_data[] = array(
            'weight' => $total_weight,
            'length' => $total_length,
            'width' => $total_width,
            'height' => $total_height
        );
        
        return $packages_data;
    }
    
    private function convert_weight_to_grams($weight) {
        $weight_unit = get_option('woocommerce_weight_unit');
        
        switch (strtolower($weight_unit)) {
            case 'kg':
                return $weight * 1000;
            case 'g':
                return $weight;
            case 'lbs':
                return $weight * 453.592;
            case 'oz':
                return $weight * 28.3495;
            default:
                return $weight * 1000; // По умолчанию считаем кг
        }
    }
    
    private function convert_dimension_to_cm($dimension) {
        $dimension_unit = get_option('woocommerce_dimension_unit');
        
        switch (strtolower($dimension_unit)) {
            case 'cm':
                return $dimension;
            case 'mm':
                return $dimension / 10;
            case 'm':
                return $dimension * 100;
            case 'in':
                return $dimension * 2.54;
            case 'yd':
                return $dimension * 91.44;
            default:
                return $dimension; // По умолчанию считаем см
        }
    }
}