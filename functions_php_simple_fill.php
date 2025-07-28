<?php
// ПРОСТОЕ РЕШЕНИЕ - просто заполняем поля и убираем required

// 1. УБИРАЕМ REQUIRED С ПОЛЕЙ И ЗАПОЛНЯЕМ ИХ
add_filter('woocommerce_checkout_fields', 'fill_and_hide_address_fields');
function fill_and_hide_address_fields($fields) {
    $address_fields = ['city', 'state', 'postcode'];
    
    foreach (['billing', 'shipping'] as $type) {
        foreach ($address_fields as $field) {
            $key = $type . '_' . $field;
            if (isset($fields[$type][$key])) {
                $fields[$type][$key]['required'] = false;
                $fields[$type][$key]['validate'] = array();
                $fields[$type][$key]['default'] = 'М'; // Заполняем значением
                $fields[$type][$key]['custom_attributes'] = array(
                    'style' => 'display: none !important;',
                    'value' => 'М'
                );
            }
        }
    }
    return $fields;
}

// 2. ЗАПОЛНЯЕМ ПОЛЯ НА УРОВНЕ PHP
add_action('woocommerce_checkout_process', 'force_fill_address_fields');
function force_fill_address_fields() {
    $_POST['billing_city'] = 'М';
    $_POST['billing_state'] = 'М';
    $_POST['billing_postcode'] = '000000';
    $_POST['shipping_city'] = 'М';
    $_POST['shipping_state'] = 'М';
    $_POST['shipping_postcode'] = '000000';
}

// 3. ДОБАВЛЯЕМ КНОПКУ И JAVASCRIPT ДЛЯ ЗАПОЛНЕНИЯ ПОЛЕЙ
add_action('wp_footer', 'add_discuss_delivery_and_fill_fields');
function add_discuss_delivery_and_fill_fields() {
    if (is_checkout()) {
        echo '<style>
        /* Скрываем адресные поля */
        #billing-city, #billing-state, #billing-postcode,
        #shipping-city, #shipping-state, #shipping-postcode,
        [id*="billing_city"], [id*="billing_state"], [id*="billing_postcode"],
        [id*="shipping_city"], [id*="shipping_state"], [id*="shipping_postcode"],
        .wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__city,
        .wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__state,
        .wp-block-woocommerce-checkout-billing-address-block .wc-block-components-address-form__postcode,
        .wp-block-woocommerce-checkout-shipping-address-block .wc-block-components-address-form__city,
        .wp-block-woocommerce-checkout-shipping-address-block .wc-block-components-address-form__state,
        .wp-block-woocommerce-checkout-shipping-address-block .wc-block-components-address-form__postcode {
            display: none !important;
        }
        
        /* Стили для кнопки обсуждения доставки */
        .discuss-delivery-button {
            background: #ff6b35;
            color: white;
            border: 2px solid #ff6b35;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 0;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .discuss-delivery-button:hover {
            background: #e55a2b;
            border-color: #e55a2b;
        }
        
        .discuss-delivery-selected {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }
        
        .shipping-methods-hidden .wc-block-checkout__shipping-method-option:not(.discuss-delivery-option) {
            display: none !important;
        }
        </style>
        
        <script>
        function fillAllAddressFields() {
            console.log("🔥 Заполняем все адресные поля...");
            
            // Все возможные селекторы для полей
            const selectors = [
                "#billing-city", "#billing_city", "input[name=billing_city]",
                "#billing-state", "#billing_state", "input[name=billing_state]", 
                "#billing-postcode", "#billing_postcode", "input[name=billing_postcode]",
                "#shipping-city", "#shipping_city", "input[name=shipping_city]",
                "#shipping-state", "#shipping_state", "input[name=shipping_state]",
                "#shipping-postcode", "#shipping_postcode", "input[name=shipping_postcode]"
            ];
            
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    if (el) {
                        if (selector.includes("postcode")) {
                            el.value = "000000";
                        } else {
                            el.value = "М";
                        }
                        el.removeAttribute("required");
                        el.setAttribute("aria-invalid", "false");
                        
                        // Убираем классы ошибок
                        el.classList.remove("wc-invalid");
                        el.classList.remove("has-error");
                        
                        // Скрываем поле
                        el.style.display = "none";
                        
                        // Триггерим события для обновления
                        el.dispatchEvent(new Event("input", { bubbles: true }));
                        el.dispatchEvent(new Event("change", { bubbles: true }));
                        
                        console.log("✅ Заполнено:", selector, "значением:", el.value);
                    }
                });
            });
            
            // Скрываем ошибки валидации
            document.querySelectorAll(".wc-block-components-validation-error, .woocommerce-error, .wc-invalid").forEach(error => {
                error.style.display = "none";
            });
        }
        
        function addDiscussDeliveryButton() {
            const shippingContainer = document.querySelector(".wc-block-checkout__shipping-method-container, .wc-block-components-radio-control");
            
            if (shippingContainer && !document.getElementById("discuss-delivery-btn")) {
                const button = document.createElement("div");
                button.id = "discuss-delivery-btn";
                button.className = "discuss-delivery-button wc-block-checkout__shipping-method-option";
                button.innerHTML = "📞 Обсудить доставку с менеджером";
                button.style.cssText = "display: block; width: 100%; text-align: center; margin: 10px 0;";
                
                button.addEventListener("click", function() {
                    console.log("✅ Нажата кнопка: Обсудить доставку");
                    
                    // Меняем стиль кнопки
                    this.classList.add("discuss-delivery-selected");
                    this.innerHTML = "✅ Доставка будет обсуждена с менеджером";
                    
                    // Скрываем другие методы доставки
                    document.querySelectorAll(".wc-block-checkout__shipping-method-option:not(#discuss-delivery-btn)").forEach(method => {
                        method.style.display = "none";
                    });
                    
                    // Добавляем скрытое поле
                    let hiddenField = document.getElementById("discuss_delivery_selected");
                    if (!hiddenField) {
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.id = "discuss_delivery_selected";
                        hiddenField.name = "discuss_delivery_selected";
                        hiddenField.value = "1";
                        document.body.appendChild(hiddenField);
                    }
                    
                    // Заполняем поля
                    fillAllAddressFields();
                });
                
                shippingContainer.appendChild(button);
                console.log("✅ Кнопка добавлена");
            }
        }
        
        // Запускаем при загрузке
        document.addEventListener("DOMContentLoaded", function() {
            console.log("🚀 Начинаем заполнение полей...");
            
            // Заполняем поля сразу
            fillAllAddressFields();
            
            // Добавляем кнопку
            setTimeout(addDiscussDeliveryButton, 500);
            
            // Заполняем поля каждые 500ms
            setInterval(fillAllAddressFields, 500);
            
            // Следим за изменениями DOM для кнопки
            const observer = new MutationObserver(function() {
                addDiscussDeliveryButton();
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
        </script>';
    }
}

// 4. СОХРАНЯЕМ ВЫБОР ОБСУЖДЕНИЯ ДОСТАВКИ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_delivery_info');
function save_discuss_delivery_info($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('📞 ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером"!');
        }
    }
}

// 5. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_discuss_delivery_in_admin');
function display_discuss_delivery_in_admin($order) {
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

// 6. EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'add_discuss_delivery_to_email', 10, 4);
function add_discuss_delivery_to_email($order, $sent_to_admin, $plain_text, $email) {
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

?>