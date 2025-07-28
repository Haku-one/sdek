<?php
/**
 * Логирование для диагностики проблем с формой оформления заказа
 */

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutFormLogger {
    
    private static $log_file;
    
    public static function init() {
        self::$log_file = WP_CONTENT_DIR . '/checkout-form-debug.log';
        
        // Добавляем хуки для логирования
        add_action('wp_ajax_log_checkout_event', array(__CLASS__, 'ajax_log_event'));
        add_action('wp_ajax_nopriv_log_checkout_event', array(__CLASS__, 'ajax_log_event'));
        
        // Логируем события WooCommerce
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_checkout_process'));
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'log_order_processed'));
        add_action('woocommerce_checkout_order_created', array(__CLASS__, 'log_order_created'));
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'log_create_order'));
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'log_create_order_line_item'));
        
        // Логируем ошибки валидации
        add_action('woocommerce_checkout_process', array(__CLASS__, 'log_validation_errors'));
        
        // Логируем AJAX запросы
        add_action('wp_ajax_woocommerce_checkout', array(__CLASS__, 'log_checkout_ajax'));
        add_action('wp_ajax_nopriv_woocommerce_checkout', array(__CLASS__, 'log_checkout_ajax'));
        
        // Логируем ошибки JavaScript
        add_action('wp_footer', array(__CLASS__, 'add_js_error_logging'));
    }
    
    public static function log($message, $type = 'INFO') {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        
        error_log($log_entry, 3, self::$log_file);
    }
    
    public static function ajax_log_event() {
        check_ajax_referer('checkout_logger_nonce', 'nonce');
        
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
    
    public static function log_checkout_process() {
        self::log('Checkout process started');
        
        // Логируем данные формы
        $post_data = $_POST;
        unset($post_data['woocommerce-process-checkout-nonce']); // Убираем nonce для безопасности
        self::log('Form data: ' . json_encode($post_data));
        
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
            
            self::log('Cart data: ' . json_encode($cart_data));
        }
    }
    
    public static function log_order_processed($order_id) {
        self::log("Order processed successfully. Order ID: {$order_id}");
    }
    
    public static function log_order_created($order) {
        $order_id = $order->get_id();
        $order_status = $order->get_status();
        $order_total = $order->get_total();
        
        self::log("Order created. ID: {$order_id}, Status: {$order_status}, Total: {$order_total}");
    }
    
    public static function log_create_order($order, $data) {
        $order_id = $order->get_id();
        self::log("Creating order. ID: {$order_id}");
        
        // Логируем данные заказа
        $order_data = array(
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => $order->get_payment_method(),
            'shipping_method' => $order->get_shipping_method()
        );
        
        self::log('Order data: ' . json_encode($order_data));
    }
    
    public static function log_create_order_line_item($item, $cart_item_key, $values, $order) {
        $product_id = $values['product_id'];
        $quantity = $values['quantity'];
        $line_total = $values['line_total'];
        
        self::log("Order line item: Product ID {$product_id}, Qty {$quantity}, Total {$line_total}");
    }
    
    public static function log_validation_errors() {
        if (wc_notice_count('error') > 0) {
            $errors = wc_get_notices('error');
            self::log('Validation errors: ' . json_encode($errors), 'ERROR');
        }
    }
    
    public static function log_checkout_ajax() {
        self::log('Checkout AJAX request received');
        
        // Логируем заголовки запроса
        $headers = getallheaders();
        self::log('Request headers: ' . json_encode($headers));
        
        // Логируем метод запроса
        self::log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    public static function add_js_error_logging() {
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
                action: 'log_checkout_event',
                event: 'JavaScript Error',
                data: errorData,
                type: 'ERROR',
                nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
            });
        });
        
        // Логирование необработанных отклонений промисов
        window.addEventListener('unhandledrejection', function(e) {
            var errorData = {
                reason: e.reason,
                promise: e.promise
            };
            
            jQuery.post(ajaxurl, {
                action: 'log_checkout_event',
                event: 'Unhandled Promise Rejection',
                data: errorData,
                type: 'ERROR',
                nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
            });
        });
        
        // Логирование событий формы
        jQuery(document).ready(function($) {
            // Логируем отправку формы
            $(document.body).on('submit_checkout', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_event',
                    event: 'Form Submit Started',
                    nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                });
            });
            
            // Логируем успешную отправку
            $(document.body).on('checkout_error', function(event, xhr, data) {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_event',
                    event: 'Checkout Error',
                    data: {xhr: xhr, data: data},
                    type: 'ERROR',
                    nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                });
            });
            
            // Логируем успешное завершение
            $(document.body).on('updated_checkout', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_event',
                    event: 'Checkout Updated',
                    nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                });
            });
            
            // Логируем клики по кнопке размещения заказа
            $(document).on('click', '.wc-block-components-checkout-place-order-button, .place-order button', function() {
                jQuery.post(ajaxurl, {
                    action: 'log_checkout_event',
                    event: 'Place Order Button Clicked',
                    data: {
                        button_text: $(this).text(),
                        button_class: $(this).attr('class')
                    },
                    nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                });
            });
            
            // Логируем изменения в полях формы
            $(document).on('change', 'input, select, textarea', function() {
                var field_name = $(this).attr('name') || $(this).attr('id');
                var field_value = $(this).val();
                
                // Логируем только важные поля
                if (field_name && (field_name.includes('email') || field_name.includes('phone') || 
                    field_name.includes('address') || field_name.includes('name'))) {
                    jQuery.post(ajaxurl, {
                        action: 'log_checkout_event',
                        event: 'Field Changed',
                        data: {
                            field: field_name,
                            value: field_value ? field_value.substring(0, 50) + '...' : 'empty'
                        },
                        nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                    });
                }
            });
            
            // Логируем состояние валидации
            setInterval(function() {
                var errors = $('.woocommerce-error, .wc-block-components-notices__notice--error');
                if (errors.length > 0) {
                    var error_texts = [];
                    errors.each(function() {
                        error_texts.push($(this).text().trim());
                    });
                    
                    jQuery.post(ajaxurl, {
                        action: 'log_checkout_event',
                        event: 'Validation Errors Detected',
                        data: {errors: error_texts},
                        type: 'WARNING',
                        nonce: '<?php echo wp_create_nonce('checkout_logger_nonce'); ?>'
                    });
                }
            }, 5000);
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
CheckoutFormLogger::init();