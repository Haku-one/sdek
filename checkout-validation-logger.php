<?php
/**
 * Логирование валидации формы оформления заказа
 */

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutValidationLogger {
    
    private static $log_file;
    
    public static function init() {
        self::$log_file = WP_CONTENT_DIR . '/checkout-validation-debug.log';
        
        // Логируем процесс валидации
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_checkout_process_start'));
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_validation_errors'), 20);
        
        // Логируем создание заказа
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'log_order_creation'));
        add_action('woocommerce_checkout_order_created', array(__CLASS__, 'log_order_created'));
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'log_order_processed'));
        
        // Логируем AJAX запросы
        add_action('wp_ajax_woocommerce_checkout', array(__CLASS__, 'log_checkout_ajax'));
        add_action('wp_ajax_nopriv_woocommerce_checkout', array(__CLASS__, 'log_checkout_ajax'));
        
        // Логируем ошибки валидации полей
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_field_validation'));
        
        // Логируем заполнение полей
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_field_filling'));
        
        // AJAX для логирования с фронтенда
        add_action('wp_ajax_log_checkout_validation', array(__CLASS__, 'ajax_log_event'));
        add_action('wp_ajax_nopriv_log_checkout_validation', array(__CLASS__, 'ajax_log_event'));
        
        // Добавляем JavaScript логирование
        add_action('wp_footer', array(__CLASS__, 'add_js_logging'));
    }
    
    public static function log($message, $type = 'INFO') {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        
        error_log($log_entry, 3, self::$log_file);
    }
    
    public static function ajax_log_event() {
        check_ajax_referer('checkout_validation_nonce', 'nonce');
        
        $event = sanitize_text_field($_POST['event']);
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'INFO';
        
        $message = "JS Event: {$event}";
        if (!empty($data)) {
            $message .= " | Data: " . json_encode($data);
        }
        
        self::log($message, $type);
        
        wp_send_json_success();
    }
    
    public static function log_checkout_process_start() {
        self::log('=== НАЧАЛО ПРОЦЕССА ОФОРМЛЕНИЯ ЗАКАЗА ===');
        
        // Логируем данные формы
        $post_data = $_POST;
        unset($post_data['woocommerce-process-checkout-nonce']); // Убираем nonce для безопасности
        
        self::log('POST данные: ' . json_encode($post_data));
        
        // Логируем корзину
        if (WC()->cart) {
            $cart_data = array(
                'cart_total' => WC()->cart->get_total(),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_items' => array()
            );
            
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $cart_data['cart_items'][] = array(
                    'product_id' => $cart_item['product_id'],
                    'quantity' => $cart_item['quantity'],
                    'line_total' => $cart_item['line_total']
                );
            }
            
            self::log('Данные корзины: ' . json_encode($cart_data));
        }
    }
    
    public static function log_validation_errors() {
        if (wc_notice_count('error') > 0) {
            $errors = wc_get_notices('error');
            self::log('ОШИБКИ ВАЛИДАЦИИ: ' . json_encode($errors), 'ERROR');
            
            // Логируем каждую ошибку отдельно
            foreach ($errors as $error) {
                self::log('Ошибка валидации: ' . $error, 'ERROR');
            }
        } else {
            self::log('Ошибок валидации не найдено');
        }
    }
    
    public static function log_field_validation() {
        $required_fields = array(
            'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
            'shipping_first_name', 'shipping_last_name', 'shipping_address_1'
        );
        
        foreach ($required_fields as $field) {
            $value = isset($_POST[$field]) ? $_POST[$field] : '';
            $is_empty = empty(trim($value));
            
            if ($is_empty) {
                self::log("Поле {$field} пустое", 'WARNING');
            } else {
                self::log("Поле {$field} заполнено: " . substr($value, 0, 50) . '...');
            }
        }
    }
    
    public static function log_field_filling() {
        // Логируем заполнение скрытых полей
        $hidden_fields = array(
            'billing_city', 'billing_state', 'billing_postcode',
            'shipping_city', 'shipping_state', 'shipping_postcode'
        );
        
        foreach ($hidden_fields as $field) {
            $value = isset($_POST[$field]) ? $_POST[$field] : '';
            self::log("Скрытое поле {$field}: {$value}");
        }
        
        // Логируем выбор доставки
        if (isset($_POST['discuss_delivery_selected'])) {
            self::log('Выбрано: Обсудить доставку с менеджером');
        }
    }
    
    public static function log_order_creation($order) {
        $order_id = $order->get_id();
        self::log("Создание заказа. ID: {$order_id}");
        
        // Логируем данные заказа
        $order_data = array(
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => $order->get_payment_method(),
            'shipping_method' => $order->get_shipping_method()
        );
        
        self::log('Данные заказа: ' . json_encode($order_data));
    }
    
    public static function log_order_created($order) {
        $order_id = $order->get_id();
        $order_status = $order->get_status();
        $order_total = $order->get_total();
        
        self::log("Заказ создан. ID: {$order_id}, Статус: {$order_status}, Сумма: {$order_total}");
    }
    
    public static function log_order_processed($order_id) {
        self::log("Заказ обработан. ID: {$order_id}");
        
        // Проверяем мета-данные
        $discuss_delivery = get_post_meta($order_id, '_discuss_delivery_selected', true);
        if ($discuss_delivery) {
            self::log("Заказ {$order_id}: выбрано обсуждение доставки");
        }
    }
    
    public static function log_checkout_ajax() {
        self::log('AJAX запрос оформления заказа получен');
        
        // Логируем заголовки
        $headers = getallheaders();
        self::log('Заголовки запроса: ' . json_encode($headers));
        
        // Логируем метод
        self::log('Метод запроса: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    public static function add_js_logging() {
        if (!is_checkout()) {
            return;
        }
        
        ?>
        <script>
        // Логирование ошибок JavaScript
        window.addEventListener('error', function(e) {
            var errorData = {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                stack: e.error ? e.error.stack : null
            };
            
            jQuery.post(ajaxurl, {
                action: 'log_checkout_validation',
                event: 'JavaScript Error',
                data: errorData,
                type: 'ERROR',
                nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
            });
        });
        
        // Логирование необработанных отклонений промисов
        window.addEventListener('unhandledrejection', function(e) {
            var errorData = {
                reason: e.reason,
                promise: e.promise
            };
            
            jQuery.post(ajaxurl, {
                action: 'log_checkout_validation',
                event: 'Unhandled Promise Rejection',
                data: errorData,
                type: 'ERROR',
                nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
            });
        });
        
        // Логирование событий формы
        jQuery(document).ready(function($) {
            // Логируем отправку формы
            $(document.body).on('submit_checkout', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Form Submit Started',
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
            
            // Логируем ошибки оформления
            $(document.body).on('checkout_error', function(event, xhr, data) {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Checkout Error',
                    data: {xhr: xhr, data: data},
                    type: 'ERROR',
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
            
            // Логируем успешное обновление
            $(document.body).on('updated_checkout', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Checkout Updated',
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
            
            // Логируем клики по кнопке размещения заказа
            $(document).on('click', '.wc-block-components-checkout-place-order-button, .place-order button', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Place Order Button Clicked',
                    data: {
                        button_text: $(this).text(),
                        button_class: $(this).attr('class')
                    },
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
            
            // Логируем изменения в важных полях
            $(document).on('change', 'input[name*="email"], input[name*="phone"], input[name*="name"], input[name*="address"]', function() {
                var field_name = $(this).attr('name');
                var field_value = $(this).val();
                
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Important Field Changed',
                    data: {
                        field: field_name,
                        value: field_value ? field_value.substring(0, 50) + '...' : 'empty'
                    },
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
            
            // Логируем состояние валидации каждые 5 секунд
            setInterval(function() {
                var errors = $('.woocommerce-error, .wc-block-components-notices__notice--error');
                var warnings = $('.woocommerce-message, .wc-block-components-notices__notice--warning');
                
                if (errors.length > 0) {
                    var error_texts = [];
                    errors.each(function() {
                        error_texts.push($(this).text().trim());
                    });
                    
                    jQuery.post(ajaxurl, {
                        action: 'log_checkout_validation',
                        event: 'Validation Errors Detected',
                        data: {errors: error_texts},
                        type: 'WARNING',
                        nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                    });
                }
                
                if (warnings.length > 0) {
                    var warning_texts = [];
                    warnings.each(function() {
                        warning_texts.push($(this).text().trim());
                    });
                    
                    jQuery.post(ajaxurl, {
                        action: 'log_checkout_validation',
                        event: 'Validation Warnings Detected',
                        data: {warnings: warning_texts},
                        type: 'WARNING',
                        nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                    });
                }
            }, 5000);
            
            // Логируем заполнение скрытых полей
            function logHiddenFields() {
                var hiddenFields = ['billing-city', 'billing-state', 'billing-postcode', 
                                   'shipping-city', 'shipping-state', 'shipping-postcode'];
                
                hiddenFields.forEach(function(fieldId) {
                    var field = document.getElementById(fieldId);
                    if (field) {
                        jQuery.post(ajaxurl, {
                            action: 'log_checkout_validation',
                            event: 'Hidden Field Status',
                            data: {
                                field: fieldId,
                                value: field.value,
                                required: field.hasAttribute('required'),
                                visible: field.style.display !== 'none'
                            },
                            nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                        });
                    }
                });
            }
            
            // Логируем состояние скрытых полей каждые 10 секунд
            setInterval(logHiddenFields, 10000);
            
            // Логируем выбор метода доставки
            $(document).on('change', 'input[name*="shipping_method"], input[value*="discuss"]', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_validation',
                    event: 'Shipping Method Changed',
                    data: {
                        method: $(this).val(),
                        name: $(this).attr('name'),
                        checked: $(this).is(':checked')
                    },
                    nonce: '<?php echo wp_create_nonce('checkout_validation_nonce'); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    public static function get_log_contents() {
        if (file_exists(self::$log_file)) {
            return file_get_contents(self::$log_file);
        }
        return 'Log file not found';
    }
    
    public static function clear_log() {
        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
    }
}

// Инициализируем логирование
CheckoutValidationLogger::init();