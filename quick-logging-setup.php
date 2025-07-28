<?php
/**
 * Быстрое подключение логирования валидации формы оформления заказа
 * 
 * Добавьте этот код в functions.php вашей темы или в основной файл плагина
 */

// Подключаем систему логирования
if (!class_exists('CheckoutValidationLogger')) {
    require_once(dirname(__FILE__) . '/checkout-validation-logger.php');
}

// Добавляем страницу в админку WordPress
add_action('admin_menu', 'add_checkout_validation_logs_page');

function add_checkout_validation_logs_page() {
    add_management_page(
        'Логи валидации заказов',
        'Логи валидации',
        'manage_options',
        'checkout-validation-logs',
        'display_checkout_validation_logs_page'
    );
}

function display_checkout_validation_logs_page() {
    include_once(dirname(__FILE__) . '/view-validation-logs.php');
}

// Добавляем дополнительное логирование для вашего кода
add_action('woocommerce_checkout_process', 'log_custom_checkout_data');

function log_custom_checkout_data() {
    // Логируем выбор "Обсудить доставку с менеджером"
    if (isset($_POST['discuss_delivery_selected'])) {
        CheckoutValidationLogger::log('Выбрано: Обсудить доставку с менеджером', 'INFO');
    }
    
    // Логируем заполнение скрытых полей
    $hidden_fields = array(
        'billing_city', 'billing_state', 'billing_postcode',
        'shipping_city', 'shipping_state', 'shipping_postcode'
    );
    
    foreach ($hidden_fields as $field) {
        $value = isset($_POST[$field]) ? $_POST[$field] : 'не заполнено';
        CheckoutValidationLogger::log("Скрытое поле {$field}: {$value}", 'INFO');
    }
    
    // Логируем состояние валидации
    if (wc_notice_count('error') > 0) {
        $errors = wc_get_notices('error');
        foreach ($errors as $error) {
            CheckoutValidationLogger::log("Ошибка валидации: {$error}", 'ERROR');
        }
    } else {
        CheckoutValidationLogger::log('Валидация прошла успешно', 'INFO');
    }
}

// Логируем успешное создание заказа
add_action('woocommerce_checkout_order_processed', 'log_successful_order');

function log_successful_order($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
        
        CheckoutValidationLogger::log("Заказ {$order_id} создан успешно", 'INFO');
        CheckoutValidationLogger::log("Статус заказа: {$order->get_status()}", 'INFO');
        CheckoutValidationLogger::log("Сумма заказа: {$order->get_total()}", 'INFO');
        
        if ($discuss_delivery) {
            CheckoutValidationLogger::log("Заказ {$order_id}: выбрано обсуждение доставки", 'INFO');
        }
    }
}

// Добавляем логирование в консоль браузера для отладки
add_action('wp_footer', 'add_debug_console_logging');

function add_debug_console_logging() {
    if (!is_checkout()) {
        return;
    }
    
    ?>
    <script>
    console.log('🔍 Логирование валидации активировано');
    
    // Логируем состояние формы каждые 5 секунд
    setInterval(function() {
        var formData = {};
        jQuery('form.checkout input, form.woocommerce-checkout input').each(function() {
            var name = jQuery(this).attr('name');
            var value = jQuery(this).val();
            if (name && value) {
                formData[name] = value;
            }
        });
        
        console.log('📝 Состояние формы:', formData);
        
        // Проверяем ошибки валидации
        var errors = jQuery('.woocommerce-error, .wc-block-components-notices__notice--error');
        if (errors.length > 0) {
            console.log('❌ Ошибки валидации:', errors.map(function() {
                return jQuery(this).text().trim();
            }).get());
        }
        
        // Проверяем скрытые поля
        var hiddenFields = ['billing-city', 'billing-state', 'billing-postcode', 
                           'shipping-city', 'shipping-state', 'shipping-postcode'];
        
        hiddenFields.forEach(function(fieldId) {
            var field = document.getElementById(fieldId);
            if (field) {
                console.log('🔒 Скрытое поле ' + fieldId + ':', field.value);
            }
        });
        
    }, 5000);
    
    // Логируем клики по кнопке заказа
    jQuery(document).on('click', '.wc-block-components-checkout-place-order-button, .place-order button', function() {
        console.log('🖱️ Клик по кнопке размещения заказа');
        console.log('Текст кнопки:', jQuery(this).text());
        console.log('Классы кнопки:', jQuery(this).attr('class'));
    });
    
    // Логируем отправку формы
    jQuery(document.body).on('submit_checkout', function() {
        console.log('📤 Начало отправки формы');
    });
    
    // Логируем ошибки отправки
    jQuery(document.body).on('checkout_error', function(event, xhr, data) {
        console.log('❌ Ошибка отправки формы:', {event: event, xhr: xhr, data: data});
    });
    
    // Логируем успешную отправку
    jQuery(document.body).on('updated_checkout', function() {
        console.log('✅ Форма обновлена');
    });
    </script>
    <?php
}

// Добавляем уведомление в админке о наличии логов
add_action('admin_notices', 'show_checkout_logs_notice');

function show_checkout_logs_notice() {
    $log_file = WP_CONTENT_DIR . '/checkout-validation-debug.log';
    
    if (file_exists($log_file) && filesize($log_file) > 0) {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'tools_page_checkout-validation-logs' || $screen->id === 'woocommerce_page_wc-orders')) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong>🔍 Логирование валидации активно!</strong> 
                    <a href="<?php echo admin_url('tools.php?page=checkout-validation-logs'); ?>">Просмотреть логи валидации</a>
                </p>
            </div>
            <?php
        }
    }
}

// Добавляем виджет в админке для быстрого доступа к логам
add_action('wp_dashboard_setup', 'add_checkout_logs_dashboard_widget');

function add_checkout_logs_dashboard_widget() {
    wp_add_dashboard_widget(
        'checkout_validation_logs_widget',
        'Логи валидации заказов',
        'display_checkout_logs_dashboard_widget'
    );
}

function display_checkout_logs_dashboard_widget() {
    $log_file = WP_CONTENT_DIR . '/checkout-validation-debug.log';
    
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $error_count = 0;
        $process_count = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, '[ERROR]') !== false) $error_count++;
            if (strpos($line, '=== НАЧАЛО ПРОЦЕССА') !== false) $process_count++;
        }
        
        echo '<p><strong>Статистика:</strong></p>';
        echo '<ul>';
        echo '<li>Попыток оформления: <strong>' . $process_count . '</strong></li>';
        echo '<li>Ошибок: <strong style="color: ' . ($error_count > 0 ? 'red' : 'green') . ';">' . $error_count . '</strong></li>';
        echo '</ul>';
        
        echo '<p><a href="' . admin_url('tools.php?page=checkout-validation-logs') . '" class="button button-primary">Просмотреть все логи</a></p>';
    } else {
        echo '<p>Файл логов не найден. Логирование может быть неактивно.</p>';
    }
}

echo "✅ Система логирования валидации подключена успешно!";
echo "<br>📁 Логи будут сохраняться в: " . WP_CONTENT_DIR . '/checkout-validation-debug.log';
echo "<br>🔗 Просмотр логов: <a href='" . admin_url('tools.php?page=checkout-validation-logs') . "'>Админка → Инструменты → Логи валидации</a>";
?>