<?php
// Код для functions.php - отключение валидации адресных полей и добавление кнопки обсуждения доставки

// 1. ПОЛНОЕ ОТКЛЮЧЕНИЕ ВАЛИДАЦИИ ДЛЯ ПОЛЕЙ АДРЕСА
add_action('woocommerce_checkout_process', 'disable_address_validation_completely');
function disable_address_validation_completely() {
    // Заполняем пустые поля значениями по умолчанию
    if (empty($_POST['shipping_city'])) {
        $_POST['shipping_city'] = 'Не указано';
    }
    if (empty($_POST['shipping_state'])) {
        $_POST['shipping_state'] = 'Не указано';
    }
    if (empty($_POST['shipping_postcode'])) {
        $_POST['shipping_postcode'] = '000000';
    }
    if (empty($_POST['billing_city'])) {
        $_POST['billing_city'] = 'Не указано';
    }
    if (empty($_POST['billing_state'])) {
        $_POST['billing_state'] = 'Не указано';
    }
    if (empty($_POST['billing_postcode'])) {
        $_POST['billing_postcode'] = '000000';
    }
}

// 2. УБИРАЕМ ОБЯЗАТЕЛЬНОСТЬ ПОЛЕЙ АДРЕСА
add_filter('woocommerce_checkout_fields', 'make_address_fields_not_required');
function make_address_fields_not_required($fields) {
    // Делаем поля не обязательными
    if (isset($fields['shipping']['shipping_city'])) {
        $fields['shipping']['shipping_city']['required'] = false;
    }
    if (isset($fields['shipping']['shipping_state'])) {
        $fields['shipping']['shipping_state']['required'] = false;
    }
    if (isset($fields['shipping']['shipping_postcode'])) {
        $fields['shipping']['shipping_postcode']['required'] = false;
    }
    if (isset($fields['billing']['billing_city'])) {
        $fields['billing']['billing_city']['required'] = false;
    }
    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['required'] = false;
    }
    if (isset($fields['billing']['billing_postcode'])) {
        $fields['billing']['billing_postcode']['required'] = false;
    }
    
    return $fields;
}

// 3. СКРЫВАЕМ ПОЛЯ АДРЕСА В CSS
add_action('wp_head', 'hide_address_fields_css');
function hide_address_fields_css() {
    if (is_checkout()) {
        echo '<style>
            .wc-block-components-address-form__city,
            .wc-block-components-address-form__state,
            .wc-block-components-address-form__postcode,
            #shipping-city, #shipping-state, #shipping-postcode,
            #billing-city, #billing-state, #billing-postcode {
                display: none !important;
            }
            
            /* Стили для кнопки обсуждения доставки */
            .discuss-delivery-button {
                background: #28a745 !important;
                color: white !important;
                border: none !important;
                padding: 12px 20px !important;
                border-radius: 5px !important;
                cursor: pointer !important;
                font-size: 14px !important;
                margin: 10px 0 !important;
                width: 100% !important;
                text-align: center !important;
                transition: background-color 0.3s !important;
            }
            
            .discuss-delivery-button:hover {
                background: #218838 !important;
            }
            
            .discuss-delivery-selected {
                background: #007cba !important;
                color: white !important;
                padding: 10px !important;
                border-radius: 5px !important;
                margin: 10px 0 !important;
                text-align: center !important;
            }
            
            .shipping-methods-hidden {
                display: none !important;
            }
        </style>';
    }
}

// 4. ДОБАВЛЯЕМ КНОПКУ "ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ"
add_action('woocommerce_review_order_before_shipping', 'add_discuss_delivery_button');
function add_discuss_delivery_button() {
    echo '<tr class="discuss-delivery-row">
        <td colspan="2">
            <button type="button" class="discuss-delivery-button" onclick="selectDiscussDelivery()">
                📞 Обсудить доставку с менеджером
            </button>
            <div id="discuss-delivery-selected" class="discuss-delivery-selected" style="display: none;">
                ✅ Выбрано: Доставка будет обсуждена с менеджером
                <input type="hidden" name="discuss_delivery_selected" value="1" />
            </div>
        </td>
    </tr>';
}

// 5. ДОБАВЛЯЕМ JAVASCRIPT ДЛЯ РАБОТЫ КНОПКИ
add_action('wp_footer', 'add_discuss_delivery_script');
function add_discuss_delivery_script() {
    if (is_checkout()) {
        echo '<script>
        function selectDiscussDelivery() {
            // Скрываем кнопку и показываем подтверждение
            document.querySelector(".discuss-delivery-button").style.display = "none";
            document.querySelector("#discuss-delivery-selected").style.display = "block";
            
            // Скрываем все способы доставки
            var shippingMethods = document.querySelector("#shipping-method, .wc-block-checkout__shipping-method-container, .woocommerce-shipping-methods");
            if (shippingMethods) {
                shippingMethods.classList.add("shipping-methods-hidden");
            }
            
            // Скрываем блоки доставки в новом checkout
            var shippingBlocks = document.querySelectorAll(".wp-block-woocommerce-checkout-shipping-method-block, .wc-block-checkout__shipping-method");
            shippingBlocks.forEach(function(block) {
                block.style.display = "none";
            });
            
            // Создаем скрытое поле для отправки информации
            var hiddenField = document.createElement("input");
            hiddenField.type = "hidden";
            hiddenField.name = "discuss_delivery_selected";
            hiddenField.value = "1";
            document.querySelector("form.checkout, form.woocommerce-checkout").appendChild(hiddenField);
            
            console.log("Выбрана опция: Обсудить доставку с менеджером");
        }
        
        // Для WooCommerce Blocks - добавляем кнопку динамически
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                var shippingContainer = document.querySelector(".wc-block-checkout__shipping-method-container");
                if (shippingContainer && !document.querySelector(".discuss-delivery-button-blocks")) {
                    var button = document.createElement("div");
                    button.className = "wc-block-checkout__shipping-method-option discuss-delivery-button-blocks";
                    button.setAttribute("role", "radio");
                    button.setAttribute("tabindex", "0");
                    button.style.cursor = "pointer";
                    button.innerHTML = `
                        <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                            <span style="font-size: 28px; margin-right: 10px;">📞</span>
                            <span class="wc-block-checkout__shipping-method-option-title">Обсудить доставку с менеджером</span>
                        </span>
                    `;
                    
                    button.addEventListener("click", function() {
                        selectDiscussDelivery();
                        
                        // Убираем выделение с других кнопок
                        var otherButtons = document.querySelectorAll(".wc-block-checkout__shipping-method-option");
                        otherButtons.forEach(function(btn) {
                            btn.classList.remove("wc-block-checkout__shipping-method-option--selected");
                            btn.setAttribute("aria-checked", "false");
                        });
                        
                        // Выделяем нашу кнопку
                        this.classList.add("wc-block-checkout__shipping-method-option--selected");
                        this.setAttribute("aria-checked", "true");
                        
                        // Показываем сообщение
                        this.innerHTML = `
                            <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                                <span style="font-size: 28px; margin-right: 10px;">✅</span>
                                <span class="wc-block-checkout__shipping-method-option-title">Доставка будет обсуждена с менеджером</span>
                            </span>
                        `;
                    });
                    
                    shippingContainer.appendChild(button);
                }
            }, 1000);
        });
        </script>';
    }
}

// 6. СОХРАНЯЕМ ИНФОРМАЦИЮ О ВЫБОРЕ В ЗАКАЗЕ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_delivery_info');
function save_discuss_delivery_info($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        // Добавляем заметку к заказу
        $order = wc_get_order($order_id);
        $order->add_order_note('⚠️ ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером". Необходимо связаться с клиентом для обсуждения условий доставки.');
    }
}

// 7. ПОКАЗЫВАЕМ ИНФОРМАЦИЮ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_discuss_delivery_in_admin');
function display_discuss_delivery_in_admin($order) {
    $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    
    if ($discuss_delivery == 'Да') {
        echo '<div style="background: #ffeb3b; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ff9800;">
            <h4 style="margin: 0 0 10px 0; color: #e65100;">📞 ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ</h4>
            <p style="margin: 0; font-weight: bold; color: #e65100;">
                Клиент выбрал опцию "Обсудить доставку с менеджером".<br>
                Необходимо связаться с клиентом для обсуждения условий доставки!
            </p>
        </div>';
    }
}

// 8. ДОБАВЛЯЕМ ИНФОРМАЦИЮ В EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'add_discuss_delivery_to_email', 10, 4);
function add_discuss_delivery_to_email($order, $sent_to_admin, $plain_text, $email) {
    $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    
    if ($discuss_delivery == 'Да') {
        if ($plain_text) {
            echo "\n" . "ДОСТАВКА: Обсуждается с менеджером" . "\n";
            echo "Клиент выбрал опцию обсуждения доставки с менеджером." . "\n\n";
        } else {
            echo '<div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px;">
                <h3 style="margin: 0 0 10px 0;">📞 ДОСТАВКА: Обсуждается с менеджером</h3>
                <p style="margin: 0;"><strong>Клиент выбрал опцию обсуждения доставки с менеджером.</strong></p>
            </div>';
        }
    }
}

// 9. AJAX обработчик для динамического добавления кнопки (опционально)
add_action('wp_ajax_add_discuss_delivery', 'handle_discuss_delivery_ajax');
add_action('wp_ajax_nopriv_add_discuss_delivery', 'handle_discuss_delivery_ajax');
function handle_discuss_delivery_ajax() {
    wp_send_json_success(array('message' => 'Выбрана доставка с обсуждением'));
}

?>