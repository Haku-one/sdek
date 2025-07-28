<?php
/**
 * Отключение обязательных полей WooCommerce для доставки СДЭК
 * Добавить этот код в functions.php вашей темы
 */

// Отключаем обязательные поля биллинга для доставки СДЭК
add_filter('woocommerce_billing_fields', 'disable_billing_fields_for_cdek');
function disable_billing_fields_for_cdek($fields) {
    // Проверяем, выбрана ли доставка СДЭК
    if (is_cdek_delivery_selected()) {
        // Делаем поля необязательными
        if (isset($fields['billing_postcode'])) {
            $fields['billing_postcode']['required'] = false;
        }
        if (isset($fields['billing_state'])) {
            $fields['billing_state']['required'] = false;
        }
        if (isset($fields['billing_city'])) {
            $fields['billing_city']['required'] = false;
        }
    }
    return $fields;
}

// Отключаем обязательные поля доставки для СДЭК
add_filter('woocommerce_shipping_fields', 'disable_shipping_fields_for_cdek');
function disable_shipping_fields_for_cdek($fields) {
    if (is_cdek_delivery_selected()) {
        if (isset($fields['shipping_postcode'])) {
            $fields['shipping_postcode']['required'] = false;
        }
        if (isset($fields['shipping_state'])) {
            $fields['shipping_state']['required'] = false;
        }
        if (isset($fields['shipping_city'])) {
            $fields['shipping_city']['required'] = false;
        }
    }
    return $fields;
}

// Функция проверки выбрана ли доставка СДЭК
function is_cdek_delivery_selected() {
    // Проверяем в сессии WooCommerce
    if (WC()->session) {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (is_array($chosen_methods)) {
            foreach ($chosen_methods as $method) {
                if (strpos($method, 'cdek') !== false || strpos($method, 'СДЭК') !== false) {
                    return true;
                }
            }
        }
    }
    
    // Проверяем POST данные
    if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
        foreach ($_POST['shipping_method'] as $method) {
            if (strpos($method, 'cdek') !== false || strpos($method, 'СДЭК') !== false) {
                return true;
            }
        }
    }
    
    // Проверяем выбранный пункт СДЭК
    if (isset($_POST['cdek_selected_point_code']) && !empty($_POST['cdek_selected_point_code'])) {
        return true;
    }
    
    return false;
}

// Скрываем поля через CSS для блоков WooCommerce
add_action('wp_head', 'hide_billing_fields_css_for_cdek');
function hide_billing_fields_css_for_cdek() {
    ?>
    <style id="cdek-hide-fields">
    /* Скрываем ненужные поля биллинга при доставке СДЭК */
    .wc-block-checkout__use-address-for-billing:checked ~ .wc-block-components-address-form .wc-block-components-address-form__postcode,
    .wc-block-checkout__use-address-for-billing:checked ~ .wc-block-components-address-form .wc-block-components-address-form__state,
    .wc-block-checkout__use-address-for-billing:checked ~ .wc-block-components-address-form .wc-block-components-address-form__city {
        display: none !important;
    }
    
    /* Скрываем для блоков при выборе СДЭК */
    body.cdek-delivery-selected .wc-block-components-address-form__postcode,
    body.cdek-delivery-selected .wc-block-components-address-form__state,
    body.cdek-delivery-selected .wc-block-components-address-form__city {
        display: none !important;
    }
    
    /* Скрываем ошибки валидации для скрытых полей */
    body.cdek-delivery-selected .wc-block-components-address-form__postcode .wc-block-components-validation-error,
    body.cdek-delivery-selected .wc-block-components-address-form__state .wc-block-components-validation-error,
    body.cdek-delivery-selected .wc-block-components-address-form__city .wc-block-components-validation-error {
        display: none !important;
    }
    </style>
    <?php
}

// Добавляем JavaScript для динамического скрытия полей
add_action('wp_footer', 'cdek_dynamic_field_hiding_script');
function cdek_dynamic_field_hiding_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        function toggleCdekFields() {
            // Проверяем, выбрана ли доставка СДЭК
            var isCdekSelected = false;
            
            // Проверяем по названию метода доставки
            $('.wc-block-components-totals-item').each(function() {
                var label = $(this).find('.wc-block-components-totals-item__label').text();
                if (label.indexOf('СДЭК') !== -1 || label.indexOf('Махачкала') !== -1 || 
                    label.indexOf('Москва') !== -1 || label.indexOf('Санкт-Петербург') !== -1) {
                    isCdekSelected = true;
                    return false;
                }
            });
            
            // Проверяем по выбранному пункту СДЭК
            if ($('#cdek-selected-point-code').length > 0 && $('#cdek-selected-point-code').val()) {
                isCdekSelected = true;
            }
            
            // Добавляем/убираем класс для CSS
            if (isCdekSelected) {
                $('body').addClass('cdek-delivery-selected');
                
                // Очищаем значения скрытых полей чтобы избежать ошибок валидации
                $('#billing-postcode, #shipping-postcode').val('000000');
                $('#billing-state, #shipping-state').val('Не требуется');
                $('#billing-city, #shipping-city').val('Не требуется');
                
                // Убираем ошибки валидации
                $('.wc-block-components-address-form__postcode .wc-block-components-validation-error').hide();
                $('.wc-block-components-address-form__state .wc-block-components-validation-error').hide();
                $('.wc-block-components-address-form__city .wc-block-components-validation-error').hide();
                
                console.log('🎯 СДЭК доставка выбрана - скрываем поля биллинга');
            } else {
                $('body').removeClass('cdek-delivery-selected');
                
                // Очищаем заполненные значения при отключении СДЭК
                $('#billing-postcode, #shipping-postcode').val('');
                $('#billing-state, #shipping-state').val('');
                $('#billing-city, #shipping-city').val('');
            }
        }
        
        // Проверяем при загрузке страницы
        toggleCdekFields();
        
        // Проверяем при изменениях в форме
        $(document.body).on('updated_checkout updated_cart_totals', function() {
            setTimeout(toggleCdekFields, 500);
        });
        
        // Проверяем при выборе пункта СДЭК
        $(document).on('cdek_point_selected', function() {
            setTimeout(toggleCdekFields, 100);
        });
        
        // Периодическая проверка для надежности
        setInterval(toggleCdekFields, 2000);
    });
    </script>
    <?php
}

// Отключаем валидацию для скрытых полей на стороне сервера
add_action('woocommerce_after_checkout_validation', 'skip_validation_for_cdek_fields', 10, 2);
function skip_validation_for_cdek_fields($data, $errors) {
    if (is_cdek_delivery_selected()) {
        // Убираем ошибки для полей, которые мы скрываем
        $errors->remove('billing_postcode');
        $errors->remove('billing_state'); 
        $errors->remove('billing_city');
        $errors->remove('shipping_postcode');
        $errors->remove('shipping_state');
        $errors->remove('shipping_city');
        
        // Устанавливаем значения по умолчанию
        $_POST['billing_postcode'] = '000000';
        $_POST['billing_state'] = 'Не требуется';
        $_POST['billing_city'] = 'Не требуется';
        $_POST['shipping_postcode'] = '000000';
        $_POST['shipping_state'] = 'Не требуется'; 
        $_POST['shipping_city'] = 'Не требуется';
    }
}

// Для WooCommerce Blocks - отключаем валидацию через REST API
add_filter('woocommerce_store_api_checkout_update_order_from_request', 'cdek_modify_checkout_data', 10, 2);
function cdek_modify_checkout_data($order, $request) {
    $billing_address = $request->get_param('billing_address');
    $shipping_address = $request->get_param('shipping_address');
    
    // Проверяем, есть ли данные о выбранном пункте СДЭК
    $cdek_point = $request->get_param('cdek_selected_point_code');
    
    if (!empty($cdek_point)) {
        // Устанавливаем значения по умолчанию для биллинга
        if (isset($billing_address)) {
            $billing_address['postcode'] = '000000';
            $billing_address['state'] = 'Не требуется';
            $billing_address['city'] = 'Не требуется';
            $order->set_billing_postcode('000000');
            $order->set_billing_state('Не требуется');
            $order->set_billing_city('Не требуется');
        }
        
        // Устанавливаем значения по умолчанию для доставки
        if (isset($shipping_address)) {
            $shipping_address['postcode'] = '000000';
            $shipping_address['state'] = 'Не требуется';
            $shipping_address['city'] = 'Не требуется';
            $order->set_shipping_postcode('000000');
            $order->set_shipping_state('Не требуется');
            $order->set_shipping_city('Не требуется');
        }
    }
    
    return $order;
}