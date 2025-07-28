<?php
// ЧИСТАЯ ВЕРСИЯ БЕЗ ЛОГОВ - заполняем поля и добавляем кнопку

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

// 3. ДОБАВЛЯЕМ КНОПКУ И СКРИПТ
add_action('wp_footer', 'add_discuss_button');
function add_discuss_button() {
    if (is_checkout()) {
        ?>
        <style>
        #billing-city, #billing-state, #billing-postcode,
        #shipping-city, #shipping-state, #shipping-postcode,
        [id*="billing_city"], [id*="billing_state"], [id*="billing_postcode"],
        [id*="shipping_city"], [id*="shipping_state"], [id*="shipping_postcode"] {
            display: none !important;
        }
        
        .discuss-delivery-button {
            background: #ff6b35;
            color: white;
            border: 2px solid #ff6b35;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 0;
            display: block;
            width: 100%;
            text-align: center;
        }
        
        .discuss-delivery-selected {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }
        
        .shipping-hidden .wc-block-checkout__shipping-option,
        .shipping-hidden .wp-block-cdek-checkout-map-block,
        .shipping-hidden #cdek-map-container,
        .shipping-hidden fieldset#shipping-option {
            display: none !important;
        }
        </style>
        
        <script>
        function fillFields() {
            // Заполняем все поля
            const fields = [
                {selectors: ['#billing-city', '#shipping-city'], value: 'М'},
                {selectors: ['#billing-state', '#shipping-state'], value: 'М'},
                {selectors: ['#billing-postcode', '#shipping-postcode'], value: '000000'}
            ];
            
            fields.forEach(field => {
                field.selectors.forEach(selector => {
                    const el = document.querySelector(selector);
                    if (el) {
                        el.value = field.value;
                        el.removeAttribute('required');
                        el.setAttribute('aria-invalid', 'false');
                        el.classList.remove('wc-invalid');
                        el.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                });
            });
            
            // Скрываем ошибки
            document.querySelectorAll('.wc-block-components-validation-error').forEach(error => {
                error.style.display = 'none';
            });
        }
        
        function addButton() {
            const container = document.querySelector('.wc-block-checkout__shipping-method-container');
            if (container && !document.getElementById('discuss-btn')) {
                const button = document.createElement('div');
                button.id = 'discuss-btn';
                button.className = 'discuss-delivery-button';
                button.innerHTML = 'Обсудить доставку с менеджером';
                
                button.onclick = function() {
                    this.classList.add('discuss-delivery-selected');
                    this.innerHTML = '✅ Доставка будет обсуждена с менеджером';
                    
                    // Скрываем секцию доставки
                    document.body.classList.add('shipping-hidden');
                    
                    // Добавляем скрытое поле
                    if (!document.getElementById('discuss_selected')) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.id = 'discuss_selected';
                        hidden.name = 'discuss_delivery_selected';
                        hidden.value = '1';
                        document.body.appendChild(hidden);
                    }
                    
                    fillFields();
                };
                
                container.appendChild(button);
            }
        }
        
        // Запуск
        document.addEventListener('DOMContentLoaded', function() {
            fillFields();
            setTimeout(addButton, 500);
            setInterval(fillFields, 1000);
            
            new MutationObserver(function() {
                addButton();
                fillFields();
            }).observe(document.body, {childList: true, subtree: true});
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