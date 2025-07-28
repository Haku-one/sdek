<?php
// ПРОСТОЕ РЕШЕНИЕ - ОТКЛЮЧАЕМ WooCommerce Blocks и используем классический checkout с отключенной валидацией.

// 1. ПОЛНОСТЬЮ ОТКЛЮЧАЕМ WooCommerce Blocks
add_action('wp_enqueue_scripts', 'disable_woocommerce_blocks_completely');
function disable_woocommerce_blocks_completely() {
    if (is_checkout()) {
        // Отключаем все scripts блоков
        wp_dequeue_script('wc-blocks-checkout');
        wp_dequeue_script('wc-blocks-vendors');
        wp_dequeue_script('wc-blocks-registry');
        wp_dequeue_script('wc-settings');
        
        // Отключаем стили блоков
        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');
        
        echo '<style>
        /* Принудительно скрываем блоки */
        .wp-block-woocommerce-checkout,
        .wc-block-checkout,
        [data-block-name="woocommerce/checkout"] {
            display: none !important;
        }
        
        /* Показываем классический checkout */
        .woocommerce-checkout {
            display: block !important;
        }
        </style>';
    }
}

// 2. ПРИНУЖДАЕМ ИСПОЛЬЗОВАТЬ КЛАССИЧЕСКИЙ CHECKOUT
add_filter('woocommerce_feature_enabled', 'force_classic_checkout', 10, 2);
function force_classic_checkout($enabled, $feature) {
    if ($feature === 'checkout_blocks') {
        return false;
    }
    return $enabled;
}

// 3. ОТКЛЮЧАЕМ CHECKOUT BLOCKS ЧЕРЕЗ ФИЛЬТР
add_filter('woocommerce_checkout_shortcode_atts', 'disable_blocks_in_shortcode');
function disable_blocks_in_shortcode($atts) {
    $atts['blocks'] = false;
    return $atts;
}

// 4. ПОЛНОСТЬЮ ОТКЛЮЧАЕМ ВАЛИДАЦИЮ АДРЕСНЫХ ПОЛЕЙ
add_action('woocommerce_checkout_process', 'disable_address_validation_completely');
function disable_address_validation_completely() {
    // Заполняем все поля принудительно
    $_POST['billing_city'] = 'Не указано';
    $_POST['billing_state'] = 'Не указано';
    $_POST['billing_postcode'] = '000000';
    $_POST['shipping_city'] = 'Не указано';
    $_POST['shipping_state'] = 'Не указано';
    $_POST['shipping_postcode'] = '000000';
}

// 5. УБИРАЕМ REQUIRED С ПОЛЕЙ
add_filter('woocommerce_checkout_fields', 'make_address_fields_optional');
function make_address_fields_optional($fields) {
    $address_fields = ['city', 'state', 'postcode'];
    
    foreach (['billing', 'shipping'] as $type) {
        foreach ($address_fields as $field) {
            $key = $type . '_' . $field;
            if (isset($fields[$type][$key])) {
                $fields[$type][$key]['required'] = false;
                $fields[$type][$key]['validate'] = array();
                $fields[$type][$key]['class'] = array('form-row-wide', 'address-field');
                $fields[$type][$key]['custom_attributes'] = array(
                    'style' => 'display: none !important;'
                );
            }
        }
    }
    return $fields;
}

// 6. ДОБАВЛЯЕМ КНОПКУ "ОБСУДИТЬ ДОСТАВКУ" В КЛАССИЧЕСКИЙ CHECKOUT
add_action('woocommerce_review_order_before_shipping', 'add_discuss_delivery_option');
function add_discuss_delivery_option() {
    echo '<tr class="discuss-delivery-row">
        <th>Обсудить доставку</th>
        <td>
            <label>
                <input type="radio" name="shipping_method[0]" value="discuss_delivery" id="discuss_delivery_method" />
                📞 Обсудить доставку с менеджером
            </label>
        </td>
    </tr>';
}

// 7. СКРЫВАЕМ АДРЕСНЫЕ ПОЛЯ И ДОБАВЛЯЕМ JAVASCRIPT
add_action('wp_footer', 'classic_checkout_scripts');
function classic_checkout_scripts() {
    if (is_checkout()) {
        echo '<style>
        /* Скрываем адресные поля в классическом checkout */
        #billing_city_field, #billing_state_field, #billing_postcode_field,
        #shipping_city_field, #shipping_state_field, #shipping_postcode_field,
        .address-field {
            display: none !important;
        }
        
        /* Стили для кнопки обсуждения */
        .discuss-delivery-row td {
            padding: 10px 0;
        }
        
        .discuss-delivery-row label {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .discuss-delivery-row label:hover {
            border-color: #28a745;
            background-color: #f8f9fa;
        }
        
        .discuss-delivery-row input[type="radio"]:checked + span {
            color: #28a745;
            font-weight: bold;
        }
        
        .discuss-delivery-row input[type="radio"] {
            margin-right: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log("🔥 Классический checkout загружен");
            
            // Заполняем скрытые поля значениями по умолчанию
            function fillHiddenFields() {
                $("#billing_city").val("Не указано");
                $("#billing_state").val("Не указано");
                $("#billing_postcode").val("000000");
                $("#shipping_city").val("Не указано");
                $("#shipping_state").val("Не указано");
                $("#shipping_postcode").val("000000");
                
                console.log("✅ Адресные поля заполнены");
            }
            
            // Заполняем поля при загрузке
            fillHiddenFields();
            
            // Обработчик выбора "Обсудить доставку"
            $(document).on("change", "#discuss_delivery_method", function() {
                if ($(this).is(":checked")) {
                    console.log("✅ Выбрано: Обсудить доставку с менеджером");
                    
                    // Скрываем стандартные методы доставки
                    $(".shipping_method:not(#discuss_delivery_method)").closest("tr").hide();
                    
                    // Добавляем скрытое поле для отправки
                    if ($("#discuss_delivery_selected").length === 0) {
                        $("<input>").attr({
                            type: "hidden",
                            id: "discuss_delivery_selected", 
                            name: "discuss_delivery_selected",
                            value: "1"
                        }).appendTo("form.checkout");
                    }
                    
                    // Заполняем адресные поля
                    fillHiddenFields();
                }
            });
            
            // Постоянно заполняем поля (на всякий случай)
            setInterval(fillHiddenFields, 1000);
            
            // Перед отправкой формы
            $("form.checkout").on("submit", function() {
                console.log("🚀 Отправка классического checkout");
                fillHiddenFields();
                return true; // Разрешаем отправку
            });
        });
        </script>';
    }
}

// 8. СОХРАНЯЕМ ВЫБОР ОБСУЖДЕНИЯ ДОСТАВКИ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_delivery_classic');
function save_discuss_delivery_classic($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('📞 ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером"!');
        }
    }
}

// 9. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'show_discuss_delivery_admin_classic');
function show_discuss_delivery_admin_classic($order) {
    $discuss = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    if ($discuss == 'Да') {
        echo '<div style="background: #ffeb3b; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ff9800;">
            <h4 style="color: #e65100; margin: 0 0 10px 0;">📞 ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ</h4>
            <p style="color: #e65100; font-weight: bold; margin: 0;">
                Клиент выбрал опцию "Обсудить доставку с менеджером"!<br>
                Необходимо связаться с клиентом для обсуждения условий доставки!
            </p>
        </div>';
    }
}

// 10. EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'add_discuss_delivery_email_classic', 10, 4);
function add_discuss_delivery_email_classic($order, $sent_to_admin, $plain_text, $email) {
    $discuss = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    if ($discuss == 'Да') {
        if ($plain_text) {
            echo "\n📞 ДОСТАВКА: Обсуждается с менеджером\n";
            echo "Клиент выбрал опцию обсуждения доставки с менеджером.\n\n";
        } else {
            echo '<div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px; border: 2px solid #ff9800;">
                <h3 style="color: #e65100; margin: 0 0 10px 0;">📞 ДОСТАВКА: Обсуждается с менеджером</h3>
                <p style="color: #e65100; font-weight: bold; margin: 0;">Клиент выбрал опцию обсуждения доставки с менеджером.</p>
            </div>';
        }
    }
}

// 11. ДОПОЛНИТЕЛЬНАЯ ЗАЩИТА - ОТКЛЮЧАЕМ BLOCKS ЧЕРЕЗ КОНСТАНТУ
if (!defined('WC_BLOCKS_IS_FEATURE_PLUGIN')) {
    define('WC_BLOCKS_IS_FEATURE_PLUGIN', false);
}

?>