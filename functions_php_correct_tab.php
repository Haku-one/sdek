<?php
// ПРАВИЛЬНАЯ ВЕРСИЯ - вкладка как у WooCommerce Blocks

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

// 3. ДОБАВЛЯЕМ ПРАВИЛЬНУЮ ВКЛАДКУ
add_action('wp_footer', 'add_proper_tab');
function add_proper_tab() {
    if (is_checkout()) {
        ?>
        <style>
        #billing-city, #billing-state, #billing-postcode,
        #shipping-city, #shipping-state, #shipping-postcode,
        [id*="billing_city"], [id*="billing_state"], [id*="billing_postcode"],
        [id*="shipping_city"], [id*="shipping_state"], [id*="shipping_postcode"] {
            display: none !important;
        }
        </style>
        
        <script>
        function fillAllFields() {
            const fieldMappings = [
                {ids: ['billing-city', 'shipping-city'], value: 'М'},
                {ids: ['billing-state', 'shipping-state'], value: 'М'},
                {ids: ['billing-postcode', 'shipping-postcode'], value: '000000'}
            ];
            
            fieldMappings.forEach(mapping => {
                mapping.ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = mapping.value;
                        el.setAttribute('value', mapping.value);
                        el.defaultValue = mapping.value;
                        el.removeAttribute('required');
                        el.setAttribute('aria-invalid', 'false');
                        el.classList.remove('wc-invalid', 'has-error');
                        
                        const parent = el.closest('.wc-block-components-text-input');
                        if (parent) {
                            parent.classList.remove('has-error');
                        }
                        
                        ['input', 'change', 'blur'].forEach(eventType => {
                            el.dispatchEvent(new Event(eventType, {bubbles: true}));
                        });
                    }
                });
            });
            
            document.querySelectorAll('.wc-block-components-validation-error').forEach(error => {
                error.style.display = 'none';
            });
            
            document.querySelectorAll('.has-error').forEach(el => {
                el.classList.remove('has-error');
            });
        }
        
        function addDiscussTab() {
            const container = document.querySelector('.wc-block-checkout__shipping-method-container');
            if (container && !document.getElementById('discuss-tab')) {
                
                // Создаем вкладку ТОЧНО как у WooCommerce
                const tab = document.createElement('div');
                tab.id = 'discuss-tab';
                tab.setAttribute('role', 'radio');
                tab.setAttribute('aria-checked', 'false');
                tab.setAttribute('tabindex', '0');
                tab.className = 'wc-block-checkout__shipping-method-option';
                
                // Иконка менеджера (телефон)
                const phoneIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" class="wc-block-checkout__shipping-method-option-icon" aria-hidden="true" focusable="false">
                    <path d="M17.707 12.293a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414L8.293 2.879a.999.999 0 0 0-1.414 0L5.636 4.122c-.58.58-.62 1.56-.08 2.139 1.5 1.6 3.97 4.9 7.08 8.01s6.41 5.58 8.01 7.08c.58.54 1.56.5 2.139-.08l1.243-1.243a.999.999 0 0 0 0-1.414l-6.414-6.414z"/>
                </svg>`;
                
                tab.innerHTML = `
                    <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                        ${phoneIcon}
                        <span class="wc-block-checkout__shipping-method-option-title">Обсудить доставку с менеджером</span>
                    </span>
                `;
                
                // Обработчик клика
                tab.addEventListener('click', function() {
                    // Убираем выделение с других вкладок
                    container.querySelectorAll('.wc-block-checkout__shipping-method-option').forEach(option => {
                        option.setAttribute('aria-checked', 'false');
                        option.classList.remove('wc-block-checkout__shipping-method-option--selected');
                    });
                    
                    // Выделяем нашу вкладку
                    this.setAttribute('aria-checked', 'true');
                    this.classList.add('wc-block-checkout__shipping-method-option--selected');
                    
                    // Скрываем карту СДЭК
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
                    
                    fillAllFields();
                });
                
                // Добавляем в контейнер
                container.appendChild(tab);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            fillAllFields();
            setTimeout(addDiscussTab, 500);
            setInterval(fillAllFields, 1000);
            
            new MutationObserver(function() {
                addDiscussTab();
                setTimeout(fillAllFields, 100);
            }).observe(document.body, {
                childList: true, 
                subtree: true
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