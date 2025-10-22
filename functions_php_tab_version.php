<?php
// ВЕРСИЯ С ВКЛАДКОЙ - кнопка как отдельный метод доставки

// 1. УБИРАЕМ REQUIRED С ПОЛЕЙ
add_filter('woocommerce_checkout_fields', 'make_address_optional');
function make_address_optional($fields) {
    $address_fields = ['city', 'state', 'postcode'];
    
    foreach (['billing', 'shipping'] as $type) {
        foreach ($address_fields as $field) {
            $key = $type . '_' . $field;
            if (isset($fields[$type][$key])) {
                $fields[$type][$key]['required'] = false;
                $fields[$type][$key]['validate'] = array();
                $fields[$type][$key]['custom_attributes'] = array(
                    'style' => 'display: none !important;'
                );
            }
        }
    }
    return $fields;
}

// 2. ЗАПОЛНЯЕМ ПОЛЯ НА PHP УРОВНЕ
add_action('woocommerce_checkout_process', 'fill_address_php');
function fill_address_php() {
    $_POST['billing_city'] = 'М';
    $_POST['billing_state'] = 'М';
    $_POST['billing_postcode'] = '000000';
    $_POST['shipping_city'] = 'М';
    $_POST['shipping_state'] = 'М';
    $_POST['shipping_postcode'] = '000000';
}

// 3. ДОБАВЛЯЕМ КНОПКУ КАК ВКЛАДКУ И СКРИПТ
add_action('wp_footer', 'add_discuss_tab');
function add_discuss_tab() {
    if (is_checkout()) {
        ?>
        <style>
        #billing-city, #billing-state, #billing-postcode,
        #shipping-city, #shipping-state, #shipping-postcode,
        [id*="billing_city"], [id*="billing_state"], [id*="billing_postcode"],
        [id*="shipping_city"], [id*="shipping_state"], [id*="shipping_postcode"] {
            display: none !important;
        }
        
        .discuss-delivery-tab {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
            background: white;
            transition: all 0.3s ease;
        }
        
        .discuss-delivery-tab:hover {
            border-color: #ff6b35;
            background-color: #fff5f3;
        }
        
        .discuss-delivery-tab.selected {
            border-color: #ff6b35;
            background-color: #ff6b35;
            color: white;
        }
        
        .discuss-delivery-tab input[type="radio"] {
            margin-right: 10px;
        }
        
        .discuss-delivery-tab .tab-title {
            font-weight: bold;
        }
        </style>
        
        <script>
        function aggressiveFillFields() {
            // Более агрессивное заполнение полей
            const fieldMappings = [
                {ids: ['billing-city', 'shipping-city'], value: 'М'},
                {ids: ['billing-state', 'shipping-state'], value: 'М'},
                {ids: ['billing-postcode', 'shipping-postcode'], value: '000000'}
            ];
            
            fieldMappings.forEach(mapping => {
                mapping.ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        // Устанавливаем значение всеми способами
                        el.value = mapping.value;
                        el.setAttribute('value', mapping.value);
                        el.defaultValue = mapping.value;
                        
                        // Убираем ошибки и required
                        el.removeAttribute('required');
                        el.setAttribute('aria-invalid', 'false');
                        el.classList.remove('wc-invalid', 'has-error');
                        
                        // Убираем has-error с родительского элемента
                        const parent = el.closest('.wc-block-components-text-input');
                        if (parent) {
                            parent.classList.remove('has-error');
                        }
                        
                        // Триггерим события
                        ['input', 'change', 'blur'].forEach(eventType => {
                            el.dispatchEvent(new Event(eventType, {bubbles: true, cancelable: true}));
                        });
                    }
                });
            });
            
            // Скрываем все ошибки валидации
            document.querySelectorAll('.wc-block-components-validation-error').forEach(error => {
                error.style.display = 'none';
                error.remove();
            });
            
            // Убираем has-error классы
            document.querySelectorAll('.has-error').forEach(el => {
                el.classList.remove('has-error');
            });
        }
        
        function addDiscussTab() {
            const shippingContainer = document.querySelector('.wc-block-checkout__shipping-method-container');
            if (shippingContainer && !document.getElementById('discuss-tab')) {
                
                // Создаем вкладку как радио кнопку
                const tabDiv = document.createElement('div');
                tabDiv.id = 'discuss-tab';
                tabDiv.className = 'discuss-delivery-tab wc-block-checkout__shipping-method-option';
                
                tabDiv.innerHTML = `
                    <input type="radio" name="shipping_method_radio" value="discuss_delivery" id="discuss-radio">
                    <span class="tab-title">Обсудить доставку с менеджером</span>
                `;
                
                // Обработчик клика
                tabDiv.onclick = function() {
                    // Убираем выделение с других вкладок
                    document.querySelectorAll('.wc-block-checkout__shipping-method-option').forEach(option => {
                        option.classList.remove('selected');
                        const radio = option.querySelector('input[type="radio"]');
                        if (radio) radio.checked = false;
                    });
                    
                    // Выделяем нашу вкладку
                    this.classList.add('selected');
                    document.getElementById('discuss-radio').checked = true;
                    
                    // Скрываем карту СДЭК и другие элементы доставки
                    document.querySelectorAll('.wp-block-cdek-checkout-map-block, #cdek-map-container').forEach(el => {
                        el.style.display = 'none';
                    });
                    
                    // Добавляем скрытое поле
                    let hiddenField = document.getElementById('discuss_selected');
                    if (!hiddenField) {
                        hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.id = 'discuss_selected';
                        hiddenField.name = 'discuss_delivery_selected';
                        hiddenField.value = '1';
                        document.body.appendChild(hiddenField);
                    }
                    
                    // Заполняем поля
                    aggressiveFillFields();
                };
                
                // Добавляем вкладку в контейнер
                shippingContainer.appendChild(tabDiv);
            }
        }
        
        // Запуск
        document.addEventListener('DOMContentLoaded', function() {
            // Сразу заполняем поля
            aggressiveFillFields();
            
            // Добавляем вкладку
            setTimeout(addDiscussTab, 500);
            
            // Постоянно заполняем поля
            setInterval(aggressiveFillFields, 500);
            
            // Следим за изменениями DOM
            const observer = new MutationObserver(function() {
                addDiscussTab();
                setTimeout(aggressiveFillFields, 100);
            });
            
            observer.observe(document.body, {
                childList: true, 
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'value']
            });
            
            // Заполняем поля при любых событиях
            ['click', 'focus', 'blur'].forEach(eventType => {
                document.addEventListener(eventType, function() {
                    setTimeout(aggressiveFillFields, 50);
                }, true);
            });
        });
        </script>
        <?php
    }
}

// 4. СОХРАНЯЕМ ВЫБОР
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_choice');
function save_discuss_choice($order_id) {
    if (isset($_POST['discuss_delivery_selected'])) {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
        }
    }
}

// 5. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'show_discuss_admin');
function show_discuss_admin($order) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        ?>
        <div style="background: #ffeb3b; padding: 15px; margin: 10px 0; border-radius: 5px;">
            <h4 style="color: #e65100; margin: 0;">ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ</h4>
            <p style="color: #e65100; font-weight: bold; margin: 5px 0 0 0;">
                Необходимо связаться с клиентом для обсуждения доставки!
            </p>
        </div>
        <?php
    }
}

// 6. EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'email_discuss_info', 10, 4);
function email_discuss_info($order, $sent_to_admin, $plain_text, $email) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        if ($plain_text) {
            echo "\nДОСТАВКА: Обсуждается с менеджером\n\n";
        } else {
            ?>
            <div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px;">
                <h3 style="color: #e65100; margin: 0;">ДОСТАВКА: Обсуждается с менеджером</h3>
            </div>
            <?php
        }
    }
}