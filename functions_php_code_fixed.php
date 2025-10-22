<?php
// ИСПРАВЛЕННЫЙ КОД для functions.php - отключение валидации адресных полей и добавление кнопки обсуждения доставки

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

// 3. СКРЫВАЕМ ПОЛЯ АДРЕСА И ОШИБКИ ВАЛИДАЦИИ
add_action('wp_head', 'hide_address_fields_css');
function hide_address_fields_css() {
    if (is_checkout()) {
        echo '<style>
            /* Скрываем поля адреса */
            .wc-block-components-address-form__city,
            .wc-block-components-address-form__state,
            .wc-block-components-address-form__postcode,
            #shipping-city, #shipping-state, #shipping-postcode,
            #billing-city, #billing-state, #billing-postcode {
                display: none !important;
            }
            
            /* СКРЫВАЕМ ВСЕ ОШИБКИ ВАЛИДАЦИИ ДЛЯ АДРЕСНЫХ ПОЛЕЙ */
            .wc-block-components-address-form__city.has-error,
            .wc-block-components-address-form__state.has-error,
            .wc-block-components-address-form__postcode.has-error,
            .wc-block-components-validation-error,
            [id*="validate-error-billing_city"],
            [id*="validate-error-billing-state"],
            [id*="validate-error-billing_postcode"],
            [id*="validate-error-shipping_city"],
            [id*="validate-error-shipping-state"],
            [id*="validate-error-shipping_postcode"] {
                display: none !important;
            }
            
            /* Стили для кнопки обсуждения доставки */
            .discuss-delivery-button-blocks {
                transition: all 0.3s ease !important;
            }
            
            .discuss-delivery-button-blocks:hover {
                background-color: #f0f0f0 !important;
            }
            
            .discuss-delivery-selected-state {
                background-color: #28a745 !important;
                color: white !important;
            }
            
            .discuss-delivery-selected-state:hover {
                background-color: #218838 !important;
            }
            
            /* Скрываем способы доставки когда выбрано обсуждение */
            .shipping-methods-hidden {
                display: none !important;
            }
            
            /* Скрываем блоки доставки */
            .hide-shipping-blocks {
                display: none !important;
            }
        </style>';
    }
}

// 4. ДОБАВЛЯЕМ JAVASCRIPT ДЛЯ РАБОТЫ С WOOCOMMERCE BLOCKS
add_action('wp_footer', 'add_discuss_delivery_blocks_script');
function add_discuss_delivery_blocks_script() {
    if (is_checkout()) {
        echo '<script>
        let discussDeliverySelected = false;
        
        function selectDiscussDelivery() {
            console.log("selectDiscussDelivery вызван");
            discussDeliverySelected = true;
            
            // Находим кнопку обсуждения доставки
            const discussButton = document.querySelector(".discuss-delivery-button-blocks");
            if (discussButton) {
                // Убираем выделение с других кнопок доставки
                const allShippingButtons = document.querySelectorAll(".wc-block-checkout__shipping-method-option");
                allShippingButtons.forEach(function(btn) {
                    btn.classList.remove("wc-block-checkout__shipping-method-option--selected");
                    btn.setAttribute("aria-checked", "false");
                });
                
                // Выделяем нашу кнопку
                discussButton.classList.add("wc-block-checkout__shipping-method-option--selected", "discuss-delivery-selected-state");
                discussButton.setAttribute("aria-checked", "true");
                
                // Меняем содержимое кнопки
                discussButton.innerHTML = `
                    <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                        <span style="font-size: 28px; margin-right: 10px;">✅</span>
                        <span class="wc-block-checkout__shipping-method-option-title">Доставка будет обсуждена с менеджером</span>
                    </span>
                `;
                
                // Скрываем другие способы доставки
                const shippingContainer = document.querySelector(".wc-block-checkout__shipping-method-container");
                if (shippingContainer) {
                    const otherMethods = shippingContainer.querySelectorAll(".wc-block-checkout__shipping-method-option:not(.discuss-delivery-button-blocks)");
                    otherMethods.forEach(function(method) {
                        method.style.display = "none";
                    });
                }
                
                // Скрываем блок места выдачи
                const pickupBlock = document.querySelector(".wp-block-woocommerce-checkout-pickup-options-block");
                if (pickupBlock) {
                    pickupBlock.style.display = "none";
                }
                
                // Создаем или обновляем скрытое поле
                let hiddenField = document.querySelector("input[name=\"discuss_delivery_selected\"]");
                if (!hiddenField) {
                    hiddenField = document.createElement("input");
                    hiddenField.type = "hidden";
                    hiddenField.name = "discuss_delivery_selected";
                    const form = document.querySelector("form.wc-block-checkout__form");
                    if (form) {
                        form.appendChild(hiddenField);
                    }
                }
                hiddenField.value = "1";
                
                console.log("✅ Выбрано: Обсудить доставку с менеджером");
                
                // Заполняем адресные поля для прохождения валидации
                fillAddressFields();
            }
        }
        
        function fillAddressFields() {
            // Заполняем все возможные адресные поля
            const addressFields = [
                "billing-city", "billing-state", "billing-postcode",
                "shipping-city", "shipping-state", "shipping-postcode"
            ];
            
            addressFields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (field && !field.value) {
                    if (fieldId.includes("city")) {
                        field.value = "Не указано";
                    } else if (fieldId.includes("state")) {
                        field.value = "Не указано";
                    } else if (fieldId.includes("postcode")) {
                        field.value = "000000";
                    }
                    field.setAttribute("aria-invalid", "false");
                }
            });
            
            // Скрываем все ошибки валидации
            const validationErrors = document.querySelectorAll(".wc-block-components-validation-error");
            validationErrors.forEach(function(error) {
                error.style.display = "none";
            });
        }
        
        // Добавляем кнопку при загрузке страницы
        function addDiscussDeliveryButton() {
            const shippingContainer = document.querySelector(".wc-block-checkout__shipping-method-container");
            
            if (shippingContainer && !document.querySelector(".discuss-delivery-button-blocks")) {
                console.log("Добавляем кнопку обсуждения доставки");
                
                const button = document.createElement("div");
                button.className = "wc-block-checkout__shipping-method-option discuss-delivery-button-blocks";
                button.setAttribute("role", "radio");
                button.setAttribute("tabindex", "0");
                button.setAttribute("aria-checked", "false");
                button.style.cursor = "pointer";
                button.innerHTML = `
                    <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                        <span style="font-size: 28px; margin-right: 10px;">📞</span>
                        <span class="wc-block-checkout__shipping-method-option-title">Обсудить доставку с менеджером</span>
                    </span>
                `;
                
                // Добавляем обработчик клика
                button.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log("Клик по кнопке обсуждения доставки");
                    selectDiscussDelivery();
                });
                
                // Добавляем кнопку в контейнер
                shippingContainer.appendChild(button);
                console.log("✅ Кнопка добавлена успешно");
            }
        }
        
        // Инициализация при загрузке DOM
        document.addEventListener("DOMContentLoaded", function() {
            console.log("DOM загружен, добавляем кнопку через 1 секунду");
            setTimeout(addDiscussDeliveryButton, 1000);
            
            // Дополнительная попытка через 3 секунды
            setTimeout(addDiscussDeliveryButton, 3000);
            
            // Заполняем адресные поля при загрузке
            setTimeout(fillAddressFields, 2000);
        });
        
        // Перехватываем отправку формы
        document.addEventListener("submit", function(e) {
            if (discussDeliverySelected) {
                console.log("Форма отправляется с выбранным обсуждением доставки");
                fillAddressFields(); // Финальное заполнение полей
            }
        });
        
        // Дополнительная инициализация для случаев когда DOM изменяется
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    setTimeout(addDiscussDeliveryButton, 500);
                }
            });
        });
        
        // Наблюдаем за изменениями в checkout форме
        const checkoutForm = document.querySelector(".wc-block-checkout");
        if (checkoutForm) {
            observer.observe(checkoutForm, {
                childList: true,
                subtree: true
            });
        }
        </script>';
    }
}

// 5. СОХРАНЯЕМ ИНФОРМАЦИЮ О ВЫБОРЕ В ЗАКАЗЕ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_delivery_info');
function save_discuss_delivery_info($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        // Добавляем заметку к заказу
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('⚠️ ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером". Необходимо связаться с клиентом для обсуждения условий доставки.');
        }
    }
}

// 6. ПОКАЗЫВАЕМ ИНФОРМАЦИЮ В АДМИНКЕ
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

// 7. ДОБАВЛЯЕМ ИНФОРМАЦИЮ В EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'add_discuss_delivery_to_email', 10, 4);
function add_discuss_delivery_to_email($order, $sent_to_admin, $plain_text, $email) {
    $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    
    if ($discuss_delivery == 'Да') {
        if ($plain_text) {
            echo "\n" . "📞 ДОСТАВКА: Обсуждается с менеджером" . "\n";
            echo "Клиент выбрал опцию обсуждения доставки с менеджером." . "\n\n";
        } else {
            echo '<div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px; border: 2px solid #ff9800;">
                <h3 style="margin: 0 0 10px 0; color: #e65100;">📞 ДОСТАВКА: Обсуждается с менеджером</h3>
                <p style="margin: 0; font-weight: bold; color: #e65100;">Клиент выбрал опцию обсуждения доставки с менеджером.</p>
            </div>';
        }
    }
}

// 8. ДОПОЛНИТЕЛЬНО - ОТКЛЮЧАЕМ ВАЛИДАЦИЮ ЧЕРЕЗ FILTER
add_filter('woocommerce_checkout_fields', 'remove_billing_fields_validation', 9999);
function remove_billing_fields_validation($fields) {
    // Принудительно убираем required для всех адресных полей
    $address_fields = ['city', 'state', 'postcode'];
    
    foreach (['billing', 'shipping'] as $field_type) {
        foreach ($address_fields as $field) {
            $field_key = $field_type . '_' . $field;
            if (isset($fields[$field_type][$field_key])) {
                $fields[$field_type][$field_key]['required'] = false;
                $fields[$field_type][$field_key]['validate'] = array();
            }
        }
    }
    
    return $fields;
}

// 9. ОТКЛЮЧАЕМ JAVASCRIPT ВАЛИДАЦИЮ НА FRONTEND
add_action('wp_footer', 'disable_frontend_validation');
function disable_frontend_validation() {
    if (is_checkout()) {
        echo '<script>
        // Отключаем валидацию адресных полей
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                // Убираем required атрибуты
                const addressFields = document.querySelectorAll("input[id*=\"city\"], input[id*=\"state\"], input[id*=\"postcode\"]");
                addressFields.forEach(function(field) {
                    field.removeAttribute("required");
                    field.setAttribute("aria-invalid", "false");
                });
                
                // Скрываем все ошибки валидации
                const errors = document.querySelectorAll(".wc-block-components-validation-error");
                errors.forEach(function(error) {
                    error.style.display = "none";
                });
            }, 1000);
        });
        </script>';
    }
}

?>