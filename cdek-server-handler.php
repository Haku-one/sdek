<?php
/**
 * Обработчик AJAX запросов для расчета стоимости доставки СДЭК
 * Добавьте этот код в functions.php вашей темы или в плагин
 */

// Обработчики AJAX запросов
add_action('wp_ajax_calculate_cdek_delivery_cost', 'handle_cdek_delivery_calculation');
add_action('wp_ajax_nopriv_calculate_cdek_delivery_cost', 'handle_cdek_delivery_calculation');

function handle_cdek_delivery_calculation() {
    // Проверяем nonce для безопасности (если есть)
    if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'cdek_ajax_nonce')) {
            wp_send_json_error('Неверный nonce');
        }
    }
    
    // Получаем данные из запроса
    $point_code = sanitize_text_field($_POST['point_code'] ?? '');
    $cart_weight = intval($_POST['cart_weight'] ?? 0); // вес в граммах
    $cart_value = intval($_POST['cart_value'] ?? 0); // стоимость в рублях
    $cart_dimensions_json = stripslashes($_POST['cart_dimensions'] ?? '{}');
    $cart_dimensions = json_decode($cart_dimensions_json, true);
    $has_real_dimensions = intval($_POST['has_real_dimensions'] ?? 0);
    
    // Валидация данных
    if (empty($point_code)) {
        wp_send_json_error('Не указан код пункта выдачи');
    }
    
    if ($cart_weight <= 0) {
        $cart_weight = 500; // минимальный вес 500г
    }
    
    if (!is_array($cart_dimensions) || empty($cart_dimensions)) {
        $cart_dimensions = array(
            'length' => 30,
            'width' => 20,
            'height' => 10
        );
    }
    
    // Пробуем рассчитать через API СДЭК (если настроен)
    $delivery_cost = calculate_cdek_cost_via_api($point_code, $cart_weight, $cart_dimensions, $cart_value);
    
    if ($delivery_cost !== false && $delivery_cost > 0) {
        // Успешный расчет через API
        wp_send_json_success(array(
            'delivery_sum' => $delivery_cost,
            'calculation_method' => 'api'
        ));
    } else {
        // Fallback расчет если API недоступен
        $fallback_cost = calculate_fallback_delivery_cost($cart_weight, $cart_value, $cart_dimensions, $has_real_dimensions);
        wp_send_json_success(array(
            'delivery_sum' => $fallback_cost,
            'calculation_method' => 'fallback'
        ));
    }
}

/**
 * Расчет стоимости доставки через API СДЭК
 * ВАЖНО: Здесь нужно реализовать подключение к реальному API СДЭК
 */
function calculate_cdek_cost_via_api($point_code, $weight, $dimensions, $value) {
    // TODO: Здесь должна быть реализация запроса к API СДЭК
    // Пример структуры запроса:
    
    /*
    $api_url = 'https://api.cdek.ru/v2/calculator/tariff';
    $api_token = get_option('cdek_api_token'); // Токен API
    
    $request_data = array(
        'type' => 1, // тип заказа (интернет-магазин)
        'currency' => 1, // рубли
        'tariff_code' => 136, // код тарифа (пункт выдачи)
        'from_location' => array(
            'city' => 'Саратов' // город отправления
        ),
        'to_location' => array(
            'code' => $point_code // код пункта выдачи
        ),
        'packages' => array(
            array(
                'weight' => $weight, // вес в граммах
                'length' => $dimensions['length'], // длина в см
                'width' => $dimensions['width'], // ширина в см
                'height' => $dimensions['height'] // высота в см
            )
        )
    );
    
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_data),
        'timeout' => 10
    ));
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['delivery_sum'])) {
            return intval($data['delivery_sum']);
        }
    }
    */
    
    // Пока API не настроен, возвращаем false для использования fallback
    return false;
}

/**
 * Fallback расчет стоимости доставки
 */
function calculate_fallback_delivery_cost($weight, $value, $dimensions, $has_real_dimensions) {
    $base_cost = 250; // базовая стоимость
    
    // Дополнительная стоимость за вес свыше 500г
    if ($weight > 500) {
        $extra_weight = ceil(($weight - 500) / 500);
        $base_cost += $extra_weight * 30;
    }
    
    // Дополнительная стоимость за габариты (если есть реальные размеры)
    if ($has_real_dimensions && is_array($dimensions)) {
        $volume = ($dimensions['length'] ?? 30) * ($dimensions['width'] ?? 20) * ($dimensions['height'] ?? 10);
        if ($volume > 12000) { // больше 12 литров
            $extra_volume = ceil(($volume - 12000) / 6000);
            $base_cost += $extra_volume * 40;
        }
    }
    
    // Дополнительная стоимость за высокую стоимость заказа (страховка)
    if ($value > 3000) {
        $base_cost += ceil(($value - 3000) / 1000) * 15;
    }
    
    // Ограничиваем максимальную стоимость
    return min($base_cost, 2000);
}

/**
 * Подключение скриптов с AJAX URL и nonce
 */
function cdek_enqueue_scripts() {
    wp_enqueue_script('cdek-delivery', get_template_directory_uri() . '/js/cdek-delivery.js', array('jquery'), '1.0.0', true);
    wp_enqueue_script('cdek-cart', get_template_directory_uri() . '/js/cdek-cart.js', array('jquery'), '1.0.0', true);
    
    wp_localize_script('cdek-delivery', 'cdek_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cdek_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'cdek_enqueue_scripts');
?>