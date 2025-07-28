<?php
/**
 * WooCommerce Price Fix Functions
 * Современное решение для исправления проблем с ценами
 * Добавить в functions.php темы или использовать как плагин
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Инициализация исправлений цен
 */
function wc_price_fix_init() {
    // Проверяем что WooCommerce активен
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Добавляем все хуки для исправления цен
    add_wc_price_hooks();
}
add_action('init', 'wc_price_fix_init');

/**
 * Добавляем все необходимые хуки для исправления цен
 */
function add_wc_price_hooks() {
    // Основные хуки для форматирования цен
    add_filter('woocommerce_price_format', 'fix_price_format', 10, 2);
    add_filter('wc_price', 'fix_wc_price_display', 10, 4);
    add_filter('woocommerce_currency_symbol', 'fix_currency_symbol', 10, 2);
    
    // Хуки для корзины и чекаута
    add_filter('woocommerce_cart_item_price', 'fix_cart_item_price', 10, 3);
    add_filter('woocommerce_cart_item_subtotal', 'fix_cart_item_subtotal', 10, 3);
    add_filter('woocommerce_cart_subtotal', 'fix_cart_subtotal', 10, 3);
    add_filter('woocommerce_cart_total', 'fix_cart_total', 10, 1);
    
    // Хуки для страницы товара
    add_filter('woocommerce_get_price_html', 'fix_product_price_html', 10, 2);
    add_filter('woocommerce_format_sale_price', 'fix_sale_price_format', 10, 3);
    
    // Хуки для заказов
    add_filter('woocommerce_order_formatted_line_subtotal', 'fix_order_line_subtotal', 10, 3);
    add_filter('woocommerce_get_formatted_order_total', 'fix_order_total', 10, 2);
    
    // Хуки для email уведомлений
    add_filter('woocommerce_email_order_item_quantity', 'fix_email_item_quantity', 10, 3);
    add_action('woocommerce_email_before_order_table', 'fix_email_prices_start');
    add_action('woocommerce_email_after_order_table', 'fix_email_prices_end');
    
    // Дополнительные хуки для блоков Gutenberg
    add_filter('woocommerce_blocks_product_price_format', 'fix_blocks_price_format', 10, 3);
}

/**
 * Исправляет базовое форматирование цен
 */
function fix_price_format($format, $currency_pos) {
    $currency_symbol = get_woocommerce_currency_symbol();
    
    switch ($currency_pos) {
        case 'left':
            $format = '%1$s%2$s';
            break;
        case 'right':
            $format = '%2$s&nbsp;%1$s';
            break;
        case 'left_space':
            $format = '%1$s&nbsp;%2$s';
            break;
        case 'right_space':
            $format = '%2$s&nbsp;%1$s';
            break;
        default:
            $format = '%2$s&nbsp;%1$s';
            break;
    }
    
    return $format;
}

/**
 * Основная функция исправления отображения цен
 */
function fix_wc_price_display($return, $price, $args, $unformatted_price) {
    // Если цена пустая или ноль, возвращаем как есть
    if (empty($price) || $price == 0) {
        return $return;
    }
    
    // Проверяем и исправляем некорректные цены
    $corrected_price = correct_price_value($price);
    
    if ($corrected_price != $price) {
        $args = wp_parse_args($args, array(
            'currency'           => '',
            'decimal_separator'  => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals'           => wc_get_price_decimals(),
            'price_format'       => get_woocommerce_price_format(),
        ));
        
        $negative = $corrected_price < 0;
        $corrected_price = apply_filters('raw_woocommerce_price', floatval($negative ? $corrected_price * -1 : $corrected_price));
        $corrected_price = apply_filters('formatted_woocommerce_price', number_format($corrected_price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator']), $corrected_price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator']);
        
        if (apply_filters('woocommerce_price_trim_zeros', false) && $args['decimals'] > 0) {
            $corrected_price = wc_trim_zeros($corrected_price);
        }
        
        $formatted_price = ($negative ? '-' : '') . sprintf($args['price_format'], '<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol($args['currency']) . '</span>', $corrected_price);
        $return = '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted_price . '</bdi></span>';
    }
    
    return $return;
}

/**
 * Определяет и исправляет некорректные значения цен
 */
function correct_price_value($price) {
    $price = floatval($price);
    
    // Если цена меньше 1 и больше 0, возможно это цена в другой валюте
    if ($price > 0 && $price < 1) {
        // Проверяем, может это цена в копейках/центах
        $potential_price = $price * 100;
        if ($potential_price > 10) { // Минимальная разумная цена
            return $potential_price;
        }
    }
    
    // Если цена кажется слишком маленькой для рублей
    if ($price > 0 && $price < 100) {
        // Возможно нужно умножить на 100
        $potential_price = $price * 100;
        if ($potential_price <= 1000000) { // Максимальная разумная цена
            return $potential_price;
        }
    }
    
    // Проверяем количество в заказе для корректировки
    global $woocommerce;
    if ($woocommerce && $woocommerce->cart) {
        foreach ($woocommerce->cart->get_cart() as $cart_item) {
            if (isset($cart_item['quantity']) && $cart_item['quantity'] > 100) {
                // Если количество очень большое, возможно цена указана за единицу в другом масштабе
                $adjusted_price = $price / $cart_item['quantity'] * 100;
                if ($adjusted_price > 10 && $adjusted_price < 10000) {
                    return $adjusted_price;
                }
            }
        }
    }
    
    return $price;
}

/**
 * Исправляет символ валюты
 */
function fix_currency_symbol($currency_symbol, $currency) {
    if ($currency === 'RUB' || empty($currency)) {
        return 'руб.';
    }
    return $currency_symbol;
}

/**
 * Исправляет цену товара в корзине
 */
function fix_cart_item_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['data'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected_price = correct_price_value($product_price);
        
        if ($corrected_price != $product_price) {
            return wc_price($corrected_price);
        }
    }
    return $price;
}

/**
 * Исправляет подытог товара в корзине
 */
function fix_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected_price = correct_price_value($product_price);
        
        if ($corrected_price != $product_price) {
            $line_subtotal = $corrected_price * $cart_item['quantity'];
            return wc_price($line_subtotal);
        }
    }
    return $subtotal;
}

/**
 * Исправляет общий подытог корзины
 */
function fix_cart_subtotal($cart_subtotal, $compound, $cart) {
    $subtotal = 0;
    $corrected = false;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
            $product = $cart_item['data'];
            $product_price = $product->get_price();
            $corrected_price = correct_price_value($product_price);
            
            if ($corrected_price != $product_price) {
                $corrected = true;
            }
            
            $subtotal += $corrected_price * $cart_item['quantity'];
        }
    }
    
    if ($corrected) {
        return wc_price($subtotal);
    }
    
    return $cart_subtotal;
}

/**
 * Исправляет общую сумму корзины
 */
function fix_cart_total($total) {
    global $woocommerce;
    
    if ($woocommerce && $woocommerce->cart) {
        $cart_total = 0;
        $corrected = false;
        
        foreach ($woocommerce->cart->get_cart() as $cart_item) {
            if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
                $product = $cart_item['data'];
                $product_price = $product->get_price();
                $corrected_price = correct_price_value($product_price);
                
                if ($corrected_price != $product_price) {
                    $corrected = true;
                }
                
                $cart_total += $corrected_price * $cart_item['quantity'];
            }
        }
        
        if ($corrected) {
            // Добавляем налоги и доставку если есть
            $cart_total += $woocommerce->cart->get_shipping_total();
            $cart_total += $woocommerce->cart->get_cart_tax();
            
            return wc_price($cart_total);
        }
    }
    
    return $total;
}

/**
 * Исправляет HTML цены товара
 */
function fix_product_price_html($price, $product) {
    $product_price = $product->get_price();
    $corrected_price = correct_price_value($product_price);
    
    if ($corrected_price != $product_price) {
        if ($product->is_type('variable')) {
            $min_price = $product->get_variation_price('min');
            $max_price = $product->get_variation_price('max');
            
            $corrected_min = correct_price_value($min_price);
            $corrected_max = correct_price_value($max_price);
            
            if ($corrected_min === $corrected_max) {
                return wc_price($corrected_min);
            } else {
                return wc_format_price_range($corrected_min, $corrected_max);
            }
        } else {
            return wc_price($corrected_price);
        }
    }
    
    return $price;
}

/**
 * Исправляет формат цены со скидкой
 */
function fix_sale_price_format($price, $regular_price, $sale_price) {
    $corrected_regular = correct_price_value($regular_price);
    $corrected_sale = correct_price_value($sale_price);
    
    if ($corrected_regular != $regular_price || $corrected_sale != $sale_price) {
        return '<del aria-hidden="true">' . wc_price($corrected_regular) . '</del> <ins>' . wc_price($corrected_sale) . '</ins>';
    }
    
    return $price;
}

/**
 * Исправляет подытог строки заказа
 */
function fix_order_line_subtotal($subtotal, $item, $order) {
    if ($item instanceof WC_Order_Item_Product) {
        $product = $item->get_product();
        if ($product) {
            $product_price = $product->get_price();
            $corrected_price = correct_price_value($product_price);
            
            if ($corrected_price != $product_price) {
                $quantity = $item->get_quantity();
                $line_subtotal = $corrected_price * $quantity;
                return wc_price($line_subtotal);
            }
        }
    }
    
    return $subtotal;
}

/**
 * Исправляет общую сумму заказа
 */
function fix_order_total($formatted_total, $order) {
    $total = 0;
    $corrected = false;
    
    foreach ($order->get_items() as $item) {
        if ($item instanceof WC_Order_Item_Product) {
            $product = $item->get_product();
            if ($product) {
                $product_price = $product->get_price();
                $corrected_price = correct_price_value($product_price);
                
                if ($corrected_price != $product_price) {
                    $corrected = true;
                }
                
                $quantity = $item->get_quantity();
                $total += $corrected_price * $quantity;
            }
        }
    }
    
    if ($corrected) {
        // Добавляем доставку и налоги
        $total += $order->get_shipping_total();
        $total += $order->get_total_tax();
        
        return wc_price($total);
    }
    
    return $formatted_total;
}

/**
 * Исправляет количество товара в email
 */
function fix_email_item_quantity($qty_display, $item, $order) {
    // Если количество слишком большое, возможно это ошибка
    $quantity = $item->get_quantity();
    
    if ($quantity > 1000) {
        // Проверяем, может быть это количество в граммах или другой единице
        if ($quantity % 1000 == 0) {
            $corrected_qty = $quantity / 1000;
            return '<strong class="product-quantity">×&nbsp;' . $corrected_qty . '</strong>';
        } else if ($quantity % 100 == 0) {
            $corrected_qty = $quantity / 100;
            return '<strong class="product-quantity">×&nbsp;' . $corrected_qty . '</strong>';
        }
    }
    
    return $qty_display;
}

/**
 * Начинает буферизацию для исправления цен в email
 */
function fix_email_prices_start() {
    ob_start();
}

/**
 * Завершает буферизацию и исправляет цены в email
 */
function fix_email_prices_end() {
    $content = ob_get_clean();
    
    // Исправляем регулярными выражениями проблемные цены
    $content = preg_replace_callback(
        '/(\d+)&nbsp;<span class="woocommerce-Price-currencySymbol">руб\.<\/span>/',
        function($matches) {
            $price = intval($matches[1]);
            $corrected_price = correct_price_value($price);
            
            if ($corrected_price != $price) {
                return number_format($corrected_price, 0, ',', ' ') . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span>';
            }
            
            return $matches[0];
        },
        $content
    );
    
    echo $content;
}

/**
 * Исправляет формат цен в блоках Gutenberg
 */
function fix_blocks_price_format($price_format, $price, $args) {
    $corrected_price = correct_price_value($price);
    
    if ($corrected_price != $price) {
        return wc_price($corrected_price);
    }
    
    return $price_format;
}

/**
 * Добавляет CSS для правильного отображения цен
 */
function add_price_fix_styles() {
    ?>
    <style>
    .woocommerce-Price-amount {
        font-weight: normal;
    }
    
    .woocommerce-Price-currencySymbol {
        font-weight: normal;
    }
    
    .wc-block-formatted-money-amount {
        white-space: nowrap;
    }
    
    .woocommerce-table__product-total .woocommerce-Price-amount {
        font-weight: bold;
    }
    </style>
    <?php
}
add_action('wp_head', 'add_price_fix_styles');
add_action('admin_head', 'add_price_fix_styles');

/**
 * Добавляет JavaScript для дополнительного исправления цен на фронтенде
 */
function add_price_fix_scripts() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Исправляем цены после загрузки AJAX
        $(document).on('updated_cart_totals updated_checkout', function() {
            fixPricesOnPage();
        });
        
        // Функция исправления цен на странице
        function fixPricesOnPage() {
            $('.woocommerce-Price-amount, .wc-block-formatted-money-amount').each(function() {
                var $this = $(this);
                var text = $this.text() || $this.html();
                
                // Ищем числовые значения
                var match = text.match(/(\d+)/);
                if (match) {
                    var price = parseInt(match[1]);
                    
                    // Исправляем слишком маленькие цены
                    if (price > 0 && price < 100) {
                        var correctedPrice = price * 100;
                        var newText = text.replace(/\d+/, correctedPrice.toLocaleString('ru-RU'));
                        $this.html(newText);
                    }
                }
            });
        }
        
        // Исправляем цены при загрузке страницы
        fixPricesOnPage();
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_price_fix_scripts');

/**
 * Логирование для отладки проблем с ценами
 */
function log_price_corrections($original_price, $corrected_price, $context = '') {
    if (WP_DEBUG && $original_price != $corrected_price) {
        error_log(sprintf(
            'Price correction: %s -> %s (Context: %s)',
            $original_price,
            $corrected_price,
            $context
        ));
    }
}

/**
 * Функция для ручного исправления конкретной цены
 */
function manual_price_correction($price, $multiplier = 100) {
    return floatval($price) * $multiplier;
}

/**
 * Хук для принудительного обновления цен в базе данных
 */
function force_update_product_prices() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish'
    ));
    
    foreach ($products as $product) {
        $current_price = $product->get_regular_price();
        $corrected_price = correct_price_value($current_price);
        
        if ($corrected_price != $current_price) {
            $product->set_regular_price($corrected_price);
            $product->set_price($corrected_price);
            $product->save();
            
            log_price_corrections($current_price, $corrected_price, 'Database update for product ID: ' . $product->get_id());
        }
    }
}

// Добавляем возможность принудительного обновления через админку
add_action('wp_ajax_force_price_update', 'force_update_product_prices');