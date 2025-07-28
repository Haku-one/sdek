<?php
// МАКСИМАЛЬНО АГРЕССИВНЫЙ КОД для functions.php - 100% отключение валидации

// 1. ОТКЛЮЧАЕМ ВСЮ ВАЛИДАЦИЮ НА УРОВНЕ PHP
add_action('woocommerce_checkout_process', 'force_disable_all_validation', 1);
function force_disable_all_validation() {
    // Принудительно заполняем ВСЕ возможные поля
    $_POST['shipping_city'] = 'Не указано';
    $_POST['shipping_state'] = 'Не указано';  
    $_POST['shipping_postcode'] = '000000';
    $_POST['billing_city'] = 'Не указано';
    $_POST['billing_state'] = 'Не указано';
    $_POST['billing_postcode'] = '000000';
    
    // Убираем все ошибки валидации
    remove_all_actions('woocommerce_checkout_process');
    add_action('woocommerce_checkout_process', 'force_disable_all_validation', 1);
}

// 2. УБИРАЕМ REQUIRED С ПОЛЕЙ НА ВСЕХ УРОВНЯХ
add_filter('woocommerce_checkout_fields', 'remove_all_required_fields', 1);
function remove_all_required_fields($fields) {
    $address_fields = ['city', 'state', 'postcode', 'address_1', 'address_2'];
    
    foreach (['billing', 'shipping'] as $type) {
        foreach ($address_fields as $field) {
            $key = $type . '_' . $field;
            if (isset($fields[$type][$key])) {
                $fields[$type][$key]['required'] = false;
                $fields[$type][$key]['validate'] = array();
                $fields[$type][$key]['class'] = array('form-row-wide');
                unset($fields[$type][$key]['custom_attributes']['required']);
            }
        }
    }
    return $fields;
}

// 3. ДОПОЛНИТЕЛЬНОЕ ОТКЛЮЧЕНИЕ ВАЛИДАЦИИ
add_filter('woocommerce_checkout_fields', 'disable_field_validation_completely', 9999);
function disable_field_validation_completely($fields) {
    foreach ($fields as $fieldset_key => $fieldset) {
        foreach ($fieldset as $key => $field) {
            if (strpos($key, 'city') !== false || strpos($key, 'state') !== false || strpos($key, 'postcode') !== false) {
                $fields[$fieldset_key][$key]['required'] = false;
                $fields[$fieldset_key][$key]['validate'] = array();
            }
        }
    }
    return $fields;
}

// 4. ОТКЛЮЧАЕМ ВАЛИДАЦИЮ ЧЕРЕЗ ХУКИ WOOCOMMERCE
add_action('init', 'disable_woocommerce_validation');
function disable_woocommerce_validation() {
    remove_action('woocommerce_checkout_process', 'woocommerce_checkout_process');
    add_action('woocommerce_checkout_process', 'custom_checkout_process');
}

function custom_checkout_process() {
    // Заполняем поля и пропускаем валидацию
    $_POST['billing_city'] = 'Не указано';
    $_POST['billing_state'] = 'Не указано';
    $_POST['billing_postcode'] = '000000';
    $_POST['shipping_city'] = 'Не указано';
    $_POST['shipping_state'] = 'Не указано';
    $_POST['shipping_postcode'] = '000000';
}

// 5. ПОЛНОСТЬЮ СКРЫВАЕМ ПОЛЯ И ОШИБКИ
add_action('wp_head', 'aggressive_hide_validation');
function aggressive_hide_validation() {
    if (is_checkout()) {
        echo '<style>
            /* Скрываем ВСЕ поля адреса */
            .wc-block-components-address-form__city,
            .wc-block-components-address-form__state,
            .wc-block-components-address-form__postcode,
            #shipping-city, #shipping-state, #shipping-postcode,
            #billing-city, #billing-state, #billing-postcode,
            input[id*="city"], input[id*="state"], input[id*="postcode"] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* АГРЕССИВНО СКРЫВАЕМ ВСЕ ОШИБКИ */
            .wc-block-components-validation-error,
            .woocommerce-error,
            .woocommerce-message,
            .wc-block-components-notices,
            [class*="error"][class*="city"],
            [class*="error"][class*="state"], 
            [class*="error"][class*="postcode"],
            [id*="validate-error"],
            [class*="has-error"],
            .wc-block-components-address-form__city.has-error,
            .wc-block-components-address-form__state.has-error,
            .wc-block-components-address-form__postcode.has-error {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                position: absolute !important;
                left: -9999px !important;
            }
            
            /* Стили для кнопки */
            .discuss-delivery-selected-state {
                background-color: #28a745 !important;
                color: white !important;
            }
        </style>';
    }
}

// 6. МАКСИМАЛЬНО АГРЕССИВНЫЙ JAVASCRIPT
add_action('wp_footer', 'aggressive_javascript_fix');
function aggressive_javascript_fix() {
    if (is_checkout()) {
        echo '<script>
        let discussSelected = false;
        
        // Заполняем поля каждые 100мс
        setInterval(function() {
            const fields = [
                "billing-city", "billing-state", "billing-postcode",
                "shipping-city", "shipping-state", "shipping-postcode"
            ];
            
            fields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (field) {
                    if (fieldId.includes("city")) field.value = "Не указано";
                    else if (fieldId.includes("state")) field.value = "Не указано";
                    else if (fieldId.includes("postcode")) field.value = "000000";
                    
                    field.removeAttribute("required");
                    field.setAttribute("aria-invalid", "false");
                    field.classList.remove("wc-invalid");
                    field.classList.add("wc-valid");
                }
            });
            
            // Скрываем ВСЕ ошибки
            document.querySelectorAll(".wc-block-components-validation-error, [class*=\"error\"], [id*=\"validate-error\"]").forEach(function(el) {
                el.style.display = "none";
                el.style.visibility = "hidden";
                el.style.opacity = "0";
                el.style.height = "0";
            });
        }, 100);
        
        function selectDiscussDelivery() {
            discussSelected = true;
            console.log("Выбрано обсуждение доставки");
            
            const discussButton = document.querySelector(".discuss-delivery-button-blocks");
            if (discussButton) {
                // Убираем выделение с других кнопок
                document.querySelectorAll(".wc-block-checkout__shipping-method-option").forEach(function(btn) {
                    btn.classList.remove("wc-block-checkout__shipping-method-option--selected");
                    btn.setAttribute("aria-checked", "false");
                });
                
                // Выделяем нашу кнопку
                discussButton.classList.add("wc-block-checkout__shipping-method-option--selected", "discuss-delivery-selected-state");
                discussButton.setAttribute("aria-checked", "true");
                
                // Меняем текст
                discussButton.innerHTML = `
                    <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                        <span style="font-size: 28px; margin-right: 10px;">✅</span>
                        <span class="wc-block-checkout__shipping-method-option-title">Доставка будет обсуждена с менеджером</span>
                    </span>
                `;
                
                // Скрываем другие методы доставки
                document.querySelectorAll(".wc-block-checkout__shipping-method-option:not(.discuss-delivery-button-blocks)").forEach(function(method) {
                    method.style.display = "none";
                });
                
                // Скрываем блок места выдачи
                const pickupBlock = document.querySelector(".wp-block-woocommerce-checkout-pickup-options-block");
                if (pickupBlock) pickupBlock.style.display = "none";
                
                // Создаем скрытое поле
                let hiddenField = document.querySelector("input[name=\"discuss_delivery_selected\"]");
                if (!hiddenField) {
                    hiddenField = document.createElement("input");
                    hiddenField.type = "hidden";
                    hiddenField.name = "discuss_delivery_selected";
                    document.querySelector("form").appendChild(hiddenField);
                }
                hiddenField.value = "1";
                
                // Принудительно заполняем все поля
                setTimeout(function() {
                    const allFields = document.querySelectorAll("input[id*=\"city\"], input[id*=\"state\"], input[id*=\"postcode\"]");
                    allFields.forEach(function(field) {
                        if (field.id.includes("city")) field.value = "Не указано";
                        else if (field.id.includes("state")) field.value = "Не указано";
                        else if (field.id.includes("postcode")) field.value = "000000";
                        
                        field.removeAttribute("required");
                        field.setAttribute("aria-invalid", "false");
                    });
                }, 100);
            }
        }
        
        // Добавляем кнопку
        function addDiscussButton() {
            const container = document.querySelector(".wc-block-checkout__shipping-method-container");
            if (container && !document.querySelector(".discuss-delivery-button-blocks")) {
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
                
                button.addEventListener("click", selectDiscussDelivery);
                container.appendChild(button);
                console.log("Кнопка добавлена");
            }
        }
        
        // Инициализация
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(addDiscussButton, 1000);
            setTimeout(addDiscussButton, 3000);
        });
        
        // Перехватываем отправку формы
        document.addEventListener("submit", function(e) {
            console.log("Отправка формы");
            
            // ПРИНУДИТЕЛЬНО заполняем ВСЕ поля перед отправкой
            const fieldsToFill = [
                "billing-city", "billing-state", "billing-postcode",
                "shipping-city", "shipping-state", "shipping-postcode"
            ];
            
            fieldsToFill.forEach(function(fieldId) {
                let field = document.getElementById(fieldId);
                if (!field) {
                    // Создаем поле если его нет
                    field = document.createElement("input");
                    field.type = "hidden";
                    field.id = fieldId;
                    field.name = fieldId.replace("-", "_");
                    document.querySelector("form").appendChild(field);
                }
                
                if (fieldId.includes("city")) field.value = "Не указано";
                else if (fieldId.includes("state")) field.value = "Не указано";
                else if (fieldId.includes("postcode")) field.value = "000000";
            });
            
            // Скрываем ВСЕ ошибки
            document.querySelectorAll(".wc-block-components-validation-error, [class*=\"error\"]").forEach(function(el) {
                el.remove();
            });
            
            console.log("Все поля заполнены, ошибки удалены");
        });
        
        // Наблюдатель за изменениями DOM
        const observer = new MutationObserver(function() {
            // Постоянно скрываем ошибки
            document.querySelectorAll(".wc-block-components-validation-error").forEach(function(el) {
                el.style.display = "none";
            });
            
            // Добавляем кнопку если её нет
            setTimeout(addDiscussButton, 100);
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        </script>';
    }
}

// 7. СОХРАНЯЕМ ДАННЫЕ О ВЫБОРЕ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_delivery_choice');
function save_discuss_delivery_choice($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('⚠️ ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером". Необходимо связаться с клиентом!');
        }
    }
}

// 8. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'show_discuss_delivery_admin');
function show_discuss_delivery_admin($order) {
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

// 9. ДОБАВЛЯЕМ В EMAIL
add_action('woocommerce_email_order_details', 'add_discuss_delivery_email', 10, 4);
function add_discuss_delivery_email($order, $sent_to_admin, $plain_text, $email) {
    $discuss_delivery = get_post_meta($order->get_id(), '_discuss_delivery_selected', true);
    
    if ($discuss_delivery == 'Да') {
        if ($plain_text) {
            echo "\n📞 ДОСТАВКА: Обсуждается с менеджером\n";
            echo "Клиент выбрал опцию обсуждения доставки с менеджером.\n\n";
        } else {
            echo '<div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px; border: 2px solid #ff9800;">
                <h3 style="margin: 0 0 10px 0; color: #e65100;">📞 ДОСТАВКА: Обсуждается с менеджером</h3>
                <p style="margin: 0; font-weight: bold; color: #e65100;">Клиент выбрал опцию обсуждения доставки с менеджером.</p>
            </div>';
        }
    }
}

// 10. ФИНАЛЬНЫЙ ХУК - ОТКЛЮЧАЕМ ВСЕ ПРОВЕРКИ WOOCOMMERCE
add_filter('woocommerce_checkout_posted_data', 'force_valid_checkout_data');
function force_valid_checkout_data($data) {
    $data['billing_city'] = 'Не указано';
    $data['billing_state'] = 'Не указано';
    $data['billing_postcode'] = '000000';
    $data['shipping_city'] = 'Не указано';
    $data['shipping_state'] = 'Не указано';
    $data['shipping_postcode'] = '000000';
    return $data;
}

?>