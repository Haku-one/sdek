<?php
// ИСПРАВЛЕННЫЙ КОД для functions.php - БЕЗ ПЕРЕХВАТА ОТПРАВКИ ФОРМЫ

// 1. ОТКЛЮЧАЕМ ВСЮ ВАЛИДАЦИЮ ЧЕРЕЗ JAVASCRIPT (БЕЗ ПЕРЕХВАТА SUBMIT)
add_action('wp_footer', 'fixed_validation_hack');
function fixed_validation_hack() {
    if (is_checkout()) {
        echo '<script>
        console.log("🔥 ИСПРАВЛЕННЫЙ ВЗЛОМ ВАЛИДАЦИИ ЗАПУЩЕН");
        
        // ПРИНУДИТЕЛЬНО ЗАПОЛНЯЕМ ВСЕ ПОЛЯ КАЖДЫЕ 50МС
        let validationInterval = setInterval(function() {
            // Заполняем все адресные поля
            const fields = [
                "billing-city", "billing-state", "billing-postcode",
                "shipping-city", "shipping-state", "shipping-postcode"
            ];
            
            let fieldsFound = 0;
            fields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (field) {
                    fieldsFound++;
                    if (fieldId.includes("city")) field.value = "Не указано";
                    else if (fieldId.includes("state")) field.value = "Не указано";
                    else if (fieldId.includes("postcode")) field.value = "000000";
                    
                    field.removeAttribute("required");
                    field.removeAttribute("aria-invalid");
                    field.setAttribute("aria-invalid", "false");
                    field.classList.remove("wc-invalid", "has-error");
                    field.classList.add("wc-valid");
                }
            });
            
            // Создаем поля если их нет
            if (fieldsFound === 0) {
                fields.forEach(function(fieldId) {
                    if (!document.getElementById(fieldId)) {
                        const field = document.createElement("input");
                        field.type = "hidden";
                        field.id = fieldId;
                        field.name = fieldId.replace("-", "_");
                        
                        if (fieldId.includes("city")) field.value = "Не указано";
                        else if (fieldId.includes("state")) field.value = "Не указано";
                        else if (fieldId.includes("postcode")) field.value = "000000";
                        
                        field.style.display = "none";
                        field.classList.add("wc-valid");
                        field.setAttribute("aria-invalid", "false");
                        
                        const form = document.querySelector("form.wc-block-checkout__form");
                        if (form) {
                            form.appendChild(field);
                            console.log("✅ Создано поле:", fieldId);
                        }
                    }
                });
            }
            
            // АГРЕССИВНО УДАЛЯЕМ ВСЕ ОШИБКИ
            const errors = document.querySelectorAll(`
                .wc-block-components-validation-error,
                [class*="error"],
                [class*="has-error"],
                [id*="validate-error"],
                .woocommerce-error,
                .wc-block-components-notices .components-notice
            `);
            
            errors.forEach(function(error) {
                error.remove();
            });
            
            // Убираем класс has-error со всех элементов
            document.querySelectorAll(".has-error").forEach(function(el) {
                el.classList.remove("has-error");
            });
            
        }, 50);
        
        // ДОБАВЛЯЕМ КНОПКУ ОБСУЖДЕНИЯ
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
                
                button.addEventListener("click", function() {
                    // Убираем выделение с других кнопок
                    document.querySelectorAll(".wc-block-checkout__shipping-method-option").forEach(function(btn) {
                        btn.classList.remove("wc-block-checkout__shipping-method-option--selected");
                        btn.setAttribute("aria-checked", "false");
                    });
                    
                    // Выделяем нашу кнопку
                    this.classList.add("wc-block-checkout__shipping-method-option--selected");
                    this.setAttribute("aria-checked", "true");
                    this.style.backgroundColor = "#28a745";
                    this.style.color = "white";
                    
                    // Меняем текст
                    this.innerHTML = `
                        <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                            <span style="font-size: 28px; margin-right: 10px;">✅</span>
                            <span class="wc-block-checkout__shipping-method-option-title">Доставка будет обсуждена с менеджером</span>
                        </span>
                    `;
                    
                    // Скрываем другие методы
                    document.querySelectorAll(".wc-block-checkout__shipping-method-option:not(.discuss-delivery-button-blocks)").forEach(function(method) {
                        method.style.display = "none";
                    });
                    
                    // Скрываем блок выдачи
                    const pickupBlock = document.querySelector(".wp-block-woocommerce-checkout-pickup-options-block");
                    if (pickupBlock) pickupBlock.style.display = "none";
                    
                    // Создаем скрытое поле для отправки
                    let hiddenField = document.querySelector("input[name=\"discuss_delivery_selected\"]");
                    if (!hiddenField) {
                        hiddenField = document.createElement("input");
                        hiddenField.type = "hidden";
                        hiddenField.name = "discuss_delivery_selected";
                        hiddenField.value = "1";
                        document.querySelector("form.wc-block-checkout__form").appendChild(hiddenField);
                    } else {
                        hiddenField.value = "1";
                    }
                    
                    console.log("✅ Выбрано обсуждение доставки с менеджером");
                });
                
                container.appendChild(button);
                console.log("✅ Кнопка обсуждения добавлена");
            }
        }
        
        // БЛОКИРУЕМ ТОЛЬКО СОБЫТИЯ ВАЛИДАЦИИ (НЕ SUBMIT!)
        function interceptValidationEvents() {
            const validationEvents = ["invalid", "change", "blur", "input"];
            
            validationEvents.forEach(function(eventType) {
                document.addEventListener(eventType, function(e) {
                    if (e.target && e.target.id && 
                        (e.target.id.includes("city") || e.target.id.includes("state") || e.target.id.includes("postcode"))) {
                        
                        console.log("🚫 Блокируем событие валидации:", eventType, e.target.id);
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        // Принудительно делаем поле валидным
                        e.target.setCustomValidity("");
                        e.target.removeAttribute("aria-invalid");
                        e.target.setAttribute("aria-invalid", "false");
                        e.target.classList.remove("wc-invalid", "has-error");
                        e.target.classList.add("wc-valid");
                        
                        return false;
                    }
                }, true);
            });
        }
        
        // МОДИФИЦИРУЕМ ТОЛЬКО AJAX ЗАПРОСЫ ВАЛИДАЦИИ
        function hackValidationRequests() {
            // Перехватываем fetch запросы ТОЛЬКО для валидации
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                const url = args[0];
                
                // Если это запрос валидации - модифицируем его
                if (typeof url === "string" && (url.includes("checkout") || url.includes("batch"))) {
                    const options = args[1] || {};
                    
                    if (options.body && typeof options.body === "string") {
                        // Добавляем наши поля в запрос
                        options.body += "&billing_city=Не указано&billing_state=Не указано&billing_postcode=000000";
                        options.body += "&shipping_city=Не указано&shipping_state=Не указано&shipping_postcode=000000";
                        console.log("🔧 Модифицирован запрос валидации");
                    }
                    
                    args[1] = options;
                }
                
                return originalFetch.apply(this, args);
            };
            
            // Аналогично для XMLHttpRequest
            const originalXHR = window.XMLHttpRequest.prototype.send;
            window.XMLHttpRequest.prototype.send = function(data) {
                if (data && typeof data === "string" && (data.includes("checkout") || data.includes("billing") || data.includes("shipping"))) {
                    console.log("🔧 Модифицирован XHR запрос");
                    data += "&billing_city=Не указано&billing_state=Не указано&billing_postcode=000000";
                    data += "&shipping_city=Не указано&shipping_state=Не указано&shipping_postcode=000000";
                }
                return originalXHR.call(this, data);
            };
        }
        
        // ИНИЦИАЛИЗАЦИЯ БЕЗ ПЕРЕХВАТА SUBMIT
        setTimeout(function() {
            hackValidationRequests();
            interceptValidationEvents();
            addDiscussButton();
            console.log("🔥 Все хаки активированы (БЕЗ перехвата submit)!");
        }, 1000);
        
        // Дополнительные попытки добавления кнопки
        setTimeout(addDiscussButton, 2000);
        setTimeout(addDiscussButton, 3000);
        
        </script>';
    }
}

// 2. ПРИНУДИТЕЛЬНО ЗАПОЛНЯЕМ ПОЛЯ НА PHP УРОВНЕ
add_action('woocommerce_checkout_process', 'force_fill_fields', 1);
function force_fill_fields() {
    $_POST['billing_city'] = 'Не указано';
    $_POST['billing_state'] = 'Не указано';
    $_POST['billing_postcode'] = '000000';
    $_POST['shipping_city'] = 'Не указано';
    $_POST['shipping_state'] = 'Не указано';
    $_POST['shipping_postcode'] = '000000';
}

// 3. ДОПОЛНИТЕЛЬНАЯ ПРОВЕРКА ПЕРЕД СОЗДАНИЕМ ЗАКАЗА
add_action('woocommerce_checkout_create_order', 'ensure_address_fields');
function ensure_address_fields($order) {
    // Заполняем адресные поля в заказе
    $order->set_billing_city('Не указано');
    $order->set_billing_state('Не указано');
    $order->set_billing_postcode('000000');
    $order->set_shipping_city('Не указано');
    $order->set_shipping_state('Не указано');
    $order->set_shipping_postcode('000000');
}

// 4. ОТКЛЮЧАЕМ СТАНДАРТНУЮ ВАЛИДАЦИЮ ПОЛЕЙ
add_filter('woocommerce_checkout_fields', 'remove_field_validation', 9999);
function remove_field_validation($fields) {
    $address_fields = ['city', 'state', 'postcode'];
    
    foreach (['billing', 'shipping'] as $type) {
        foreach ($address_fields as $field) {
            $key = $type . '_' . $field;
            if (isset($fields[$type][$key])) {
                $fields[$type][$key]['required'] = false;
                $fields[$type][$key]['validate'] = array();
                unset($fields[$type][$key]['custom_attributes']['required']);
            }
        }
    }
    return $fields;
}

// 5. СКРЫВАЕМ ВСЕ ОШИБКИ И ПОЛЯ
add_action('wp_head', 'hide_all_errors');
function hide_all_errors() {
    if (is_checkout()) {
        echo '<style>
        .wc-block-components-validation-error,
        .wc-block-components-address-form__city,
        .wc-block-components-address-form__state,
        .wc-block-components-address-form__postcode,
        [class*="error"],
        [class*="has-error"],
        [id*="validate-error"],
        input[id*="city"]:not([type="hidden"]),
        input[id*="state"]:not([type="hidden"]),
        input[id*="postcode"]:not([type="hidden"]) {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            position: absolute !important;
            left: -9999px !important;
        }
        </style>';
    }
}

// 6. СОХРАНЯЕМ ДАННЫЕ О ВЫБОРЕ
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_choice');
function save_discuss_choice($order_id) {
    if (isset($_POST['discuss_delivery_selected']) && $_POST['discuss_delivery_selected'] == '1') {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        update_post_meta($order_id, '_shipping_method_title', 'Доставка обсуждается с менеджером');
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('📞 ВНИМАНИЕ: Клиент выбрал "Обсудить доставку с менеджером"!');
        }
    }
}

// 7. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'show_discuss_admin');
function show_discuss_admin($order) {
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

// 8. EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'add_discuss_email', 10, 4);
function add_discuss_email($order, $sent_to_admin, $plain_text, $email) {
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