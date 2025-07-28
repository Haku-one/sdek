<?php
/**
 * ПОЛНОЕ УДАЛЕНИЕ полей WooCommerce для всех заказов
 * Добавить этот код в functions.php вашей темы
 */

// ПОЛНОСТЬЮ УБИРАЕМ поля биллинга
add_filter('woocommerce_billing_fields', 'remove_billing_fields_completely');
function remove_billing_fields_completely($fields) {
    // Удаляем поля полностью
    unset($fields['billing_postcode']);
    unset($fields['billing_state']);
    unset($fields['billing_city']);
    
    return $fields;
}

// ПОЛНОСТЬЮ УБИРАЕМ поля доставки
add_filter('woocommerce_shipping_fields', 'remove_shipping_fields_completely');
function remove_shipping_fields_completely($fields) {
    // Удаляем поля полностью
    unset($fields['shipping_postcode']);
    unset($fields['shipping_state']);
    unset($fields['shipping_city']);
    
    return $fields;
}

// Удаляем поля из WooCommerce Blocks через REST API
add_filter('woocommerce_store_api_checkout_fields', 'remove_checkout_fields_from_blocks');
function remove_checkout_fields_from_blocks($fields) {
    // Удаляем из billing
    if (isset($fields['billing'])) {
        unset($fields['billing']['postcode']);
        unset($fields['billing']['state']); 
        unset($fields['billing']['city']);
    }
    
    // Удаляем из shipping
    if (isset($fields['shipping'])) {
        unset($fields['shipping']['postcode']);
        unset($fields['shipping']['state']);
        unset($fields['shipping']['city']);
    }
    
    return $fields;
}

// Устанавливаем значения по умолчанию при создании заказа
add_action('woocommerce_checkout_create_order', 'set_default_address_values');
function set_default_address_values($order) {
    // Устанавливаем значения по умолчанию для всех заказов
    $order->set_billing_postcode('000000');
    $order->set_billing_state('Не требуется');
    $order->set_billing_city('Не требуется');
    $order->set_shipping_postcode('000000');
    $order->set_shipping_state('Не требуется');
    $order->set_shipping_city('Не требуется');
}

// Удаляем поля из схемы данных WooCommerce Blocks
add_filter('woocommerce_store_api_checkout_update_order_from_request', 'remove_fields_from_order_update', 10, 2);
function remove_fields_from_order_update($order, $request) {
    // Принудительно устанавливаем значения по умолчанию
    $order->set_billing_postcode('000000');
    $order->set_billing_state('Не требуется');
    $order->set_billing_city('Не требуется');
    $order->set_shipping_postcode('000000');
    $order->set_shipping_state('Не требуется');
    $order->set_shipping_city('Не требуется');
    
    return $order;
}