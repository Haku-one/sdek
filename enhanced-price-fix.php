<?php
/**
 * УСИЛЕННОЕ ИСПРАВЛЕНИЕ ЦЕН WOOCOMMERCE
 * Работает с Elementor, AJAX, всеми темами
 * Добавить в functions.php темы
 */

if (!defined('ABSPATH')) exit;

// Инициализация
function enhanced_price_fix_init() {
    if (!class_exists('WooCommerce')) return;
    
    // Все хуки WooCommerce
    add_filter('wc_price', 'enhanced_fix_price', 999, 4);
    add_filter('woocommerce_cart_item_price', 'enhanced_fix_cart_price', 999, 3);
    add_filter('woocommerce_cart_item_subtotal', 'enhanced_fix_cart_subtotal', 999, 3);
    add_filter('woocommerce_cart_subtotal', 'enhanced_fix_cart_total', 999, 3);
    add_filter('woocommerce_cart_total', 'enhanced_fix_cart_final_total', 999);
    add_filter('woocommerce_get_price_html', 'enhanced_fix_product_price', 999, 2);
    add_filter('woocommerce_currency_symbol', 'enhanced_fix_currency', 999, 2);
    
    // Дополнительные хуки для заказов
    add_filter('woocommerce_order_formatted_line_subtotal', 'enhanced_fix_order_line', 999, 3);
    add_filter('woocommerce_get_formatted_order_total', 'enhanced_fix_order_total', 999, 2);
    
    // Хуки для мини-корзины
    add_action('woocommerce_widget_shopping_cart_before_buttons', 'fix_mini_cart_prices');
    add_action('woocommerce_before_mini_cart', 'fix_mini_cart_prices');
    
    // Буферизация для обработки всего контента
    add_action('wp_head', 'start_price_buffer', 1);
    add_action('wp_footer', 'end_price_buffer', 999);
    
    // AJAX хуки
    add_action('wp_ajax_woocommerce_add_to_cart', 'fix_ajax_prices', 1);
    add_action('wp_ajax_nopriv_woocommerce_add_to_cart', 'fix_ajax_prices', 1);
    add_action('wp_ajax_woocommerce_get_refreshed_fragments', 'fix_ajax_prices', 1);
    add_action('wp_ajax_nopriv_woocommerce_get_refreshed_fragments', 'fix_ajax_prices', 1);
}
add_action('init', 'enhanced_price_fix_init', 1);

// Основная функция коррекции цен
function correct_price_amount($price) {
    $price = floatval($price);
    
    // Если цена от 1 до 999 - умножаем на 100
    if ($price >= 1 && $price <= 999) {
        return $price * 100;
    }
    
    // Если цена меньше 1 но больше 0 - умножаем на 10000
    if ($price > 0 && $price < 1) {
        return $price * 10000;
    }
    
    return $price;
}

// Исправляет базовую функцию wc_price
function enhanced_fix_price($return, $price, $args, $unformatted_price) {
    if (empty($price) || $price == 0) return $return;
    
    $corrected_price = correct_price_amount($price);
    
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

// Исправляет цены в корзине
function enhanced_fix_cart_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['data'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected_price = correct_price_amount($product_price);
        
        if ($corrected_price != $product_price) {
            return wc_price($corrected_price);
        }
    }
    return $price;
}

// Исправляет подытог товара в корзине
function enhanced_fix_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
    if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected_price = correct_price_amount($product_price);
        
        if ($corrected_price != $product_price) {
            // Исправляем количество если оно слишком большое
            $quantity = $cart_item['quantity'];
            if ($quantity > 1000) {
                $quantity = fix_quantity($quantity);
            }
            
            $line_subtotal = $corrected_price * $quantity;
            return wc_price($line_subtotal);
        }
    }
    return $subtotal;
}

// Исправляет общий подытог корзины
function enhanced_fix_cart_total($cart_subtotal, $compound, $cart) {
    $subtotal = 0;
    $corrected = false;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
            $product = $cart_item['data'];
            $product_price = $product->get_price();
            $corrected_price = correct_price_amount($product_price);
            
            if ($corrected_price != $product_price) {
                $corrected = true;
            }
            
            $quantity = $cart_item['quantity'];
            if ($quantity > 1000) {
                $quantity = fix_quantity($quantity);
            }
            
            $subtotal += $corrected_price * $quantity;
        }
    }
    
    if ($corrected) {
        return wc_price($subtotal);
    }
    
    return $cart_subtotal;
}

// Исправляет финальную сумму корзины
function enhanced_fix_cart_final_total($total) {
    global $woocommerce;
    
    if ($woocommerce && $woocommerce->cart) {
        $cart_total = 0;
        $corrected = false;
        
        foreach ($woocommerce->cart->get_cart() as $cart_item) {
            if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
                $product = $cart_item['data'];
                $product_price = $product->get_price();
                $corrected_price = correct_price_amount($product_price);
                
                if ($corrected_price != $product_price) {
                    $corrected = true;
                }
                
                $quantity = $cart_item['quantity'];
                if ($quantity > 1000) {
                    $quantity = fix_quantity($quantity);
                }
                
                $cart_total += $corrected_price * $quantity;
            }
        }
        
        if ($corrected) {
            $cart_total += $woocommerce->cart->get_shipping_total();
            $cart_total += $woocommerce->cart->get_cart_tax();
            return wc_price($cart_total);
        }
    }
    
    return $total;
}

// Исправляет цену товара
function enhanced_fix_product_price($price, $product) {
    $product_price = $product->get_price();
    $corrected_price = correct_price_amount($product_price);
    
    if ($corrected_price != $product_price) {
        return wc_price($corrected_price);
    }
    
    return $price;
}

// Исправляет символ валюты
function enhanced_fix_currency($currency_symbol, $currency) {
    if ($currency === 'RUB' || empty($currency)) {
        return 'руб.';
    }
    return $currency_symbol;
}

// Исправляет строки заказа
function enhanced_fix_order_line($subtotal, $item, $order) {
    if ($item instanceof WC_Order_Item_Product) {
        $product = $item->get_product();
        if ($product) {
            $product_price = $product->get_price();
            $corrected_price = correct_price_amount($product_price);
            
            if ($corrected_price != $product_price) {
                $quantity = $item->get_quantity();
                if ($quantity > 1000) {
                    $quantity = fix_quantity($quantity);
                }
                $line_subtotal = $corrected_price * $quantity;
                return wc_price($line_subtotal);
            }
        }
    }
    return $subtotal;
}

// Исправляет общую сумму заказа
function enhanced_fix_order_total($formatted_total, $order) {
    $total = 0;
    $corrected = false;
    
    foreach ($order->get_items() as $item) {
        if ($item instanceof WC_Order_Item_Product) {
            $product = $item->get_product();
            if ($product) {
                $product_price = $product->get_price();
                $corrected_price = correct_price_amount($product_price);
                
                if ($corrected_price != $product_price) {
                    $corrected = true;
                }
                
                $quantity = $item->get_quantity();
                if ($quantity > 1000) {
                    $quantity = fix_quantity($quantity);
                }
                $total += $corrected_price * $quantity;
            }
        }
    }
    
    if ($corrected) {
        $total += $order->get_shipping_total();
        $total += $order->get_total_tax();
        return wc_price($total);
    }
    
    return $formatted_total;
}

// Исправляет количество
function fix_quantity($quantity) {
    if ($quantity > 1000) {
        if ($quantity % 1000 == 0) {
            return $quantity / 1000;
        } else if ($quantity % 100 == 0) {
            return $quantity / 100;
        } else if ($quantity % 10 == 0) {
            return $quantity / 10;
        }
    }
    return $quantity;
}

// Исправляет мини-корзину
function fix_mini_cart_prices() {
    ob_start();
}

// Буферизация для обработки всего HTML
function start_price_buffer() {
    ob_start();
}

function end_price_buffer() {
    $content = ob_get_clean();
    
    // Обрабатываем весь контент страницы
    $content = fix_all_prices_in_content($content);
    
    echo $content;
}

// Исправляет все цены в HTML контенте
function fix_all_prices_in_content($content) {
    // Паттерн для поиска всех цен
    $patterns = array(
        // Стандартные цены WooCommerce
        '/(<span[^>]*woocommerce-Price-amount[^>]*><bdi>)(\d+)(&nbsp;<span[^>]*woocommerce-Price-currencySymbol[^>]*>руб\.<\/span><\/bdi><\/span>)/',
        // Цены без BDI
        '/(<span[^>]*woocommerce-Price-amount[^>]*>)(\d+)(&nbsp;<span[^>]*woocommerce-Price-currencySymbol[^>]*>руб\.<\/span><\/span>)/',
        // Простые цены
        '/(\d+)(&nbsp;руб\.)/i',
        // Цены в Elementor
        '/(<[^>]*elementor[^>]*>[\s\S]*?)(\d+)(&nbsp;руб\.)/i',
        // Количество товаров
        '/(<strong[^>]*product-quantity[^>]*>×&nbsp;)(\d{3,})(<\/strong>)/',
        '/(<span[^>]*product-quantity[^>]*>)(\d{3,})( ×<\/span>)/',
    );
    
    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            if (count($matches) >= 3) {
                $number = intval($matches[2]);
                
                // Для количества товаров (если больше 1000)
                if (isset($matches[1]) && (strpos($matches[1], 'quantity') !== false) && $number > 1000) {
                    $corrected_number = fix_quantity($number);
                    return $matches[1] . $corrected_number . $matches[3];
                }
                
                // Для цен
                $corrected_price = correct_price_amount($number);
                
                if ($corrected_price != $number) {
                    $formatted_price = number_format($corrected_price, 0, ',', ' ');
                    return $matches[1] . $formatted_price . (isset($matches[3]) ? $matches[3] : '');
                }
            }
            
            return $matches[0];
        }, $content);
    }
    
    return $content;
}

// AJAX обработка цен
function fix_ajax_prices() {
    add_action('wp_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Перехватываем AJAX ответы
            $(document).ajaxComplete(function(event, xhr, settings) {
                setTimeout(function() {
                    fixAllPricesOnPage();
                }, 100);
            });
            
            // Функция исправления всех цен на странице
            function fixAllPricesOnPage() {
                // Исправляем цены
                $('.woocommerce-Price-amount, .wc-block-formatted-money-amount, [class*="price"]').each(function() {
                    var $element = $(this);
                    var text = $element.html() || $element.text();
                    
                    // Ищем числа в тексте
                    var priceMatch = text.match(/(\d+)(\s*&nbsp;\s*<span[^>]*>руб\.<\/span>|\s*руб\.)/i);
                    if (priceMatch) {
                        var price = parseInt(priceMatch[1]);
                        var correctedPrice = correctPriceJS(price);
                        
                        if (correctedPrice !== price) {
                            var newText = text.replace(priceMatch[1], correctedPrice.toLocaleString('ru-RU'));
                            if ($element.html() !== $element.text()) {
                                $element.html(newText);
                            } else {
                                $element.text(newText);
                            }
                        }
                    }
                });
                
                // Исправляем количество
                $('.product-quantity, [class*="quantity"]').each(function() {
                    var $element = $(this);
                    var text = $element.html() || $element.text();
                    
                    var qtyMatch = text.match(/(\d{3,})/);
                    if (qtyMatch) {
                        var qty = parseInt(qtyMatch[1]);
                        var correctedQty = fixQuantityJS(qty);
                        
                        if (correctedQty !== qty) {
                            var newText = text.replace(qtyMatch[1], correctedQty);
                            if ($element.html() !== $element.text()) {
                                $element.html(newText);
                            } else {
                                $element.text(newText);
                            }
                        }
                    }
                });
            }
            
            // JavaScript версия коррекции цен
            function correctPriceJS(price) {
                if (price >= 1 && price <= 999) {
                    return price * 100;
                }
                if (price > 0 && price < 1) {
                    return price * 10000;
                }
                return price;
            }
            
            // JavaScript версия коррекции количества
            function fixQuantityJS(qty) {
                if (qty > 1000) {
                    if (qty % 1000 === 0) {
                        return qty / 1000;
                    } else if (qty % 100 === 0) {
                        return qty / 100;
                    } else if (qty % 10 === 0) {
                        return qty / 10;
                    }
                }
                return qty;
            }
            
            // Исправляем цены при загрузке страницы
            fixAllPricesOnPage();
            
            // Исправляем цены при обновлении корзины
            $(document).on('updated_cart_totals updated_checkout wc_fragments_refreshed', function() {
                setTimeout(fixAllPricesOnPage, 100);
            });
            
            // Исправляем цены при добавлении в корзину
            $(document).on('added_to_cart', function() {
                setTimeout(fixAllPricesOnPage, 500);
            });
            
            // MutationObserver для отслеживания изменений DOM
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var shouldFix = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    if (node.className && (
                                        node.className.includes('woocommerce-Price') ||
                                        node.className.includes('elementor-menu-cart') ||
                                        node.className.includes('product-price') ||
                                        node.className.includes('cart_item')
                                    )) {
                                        shouldFix = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });
                    
                    if (shouldFix) {
                        setTimeout(fixAllPricesOnPage, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        });
        </script>
        <?php
    });
}

// CSS для правильного отображения
function enhanced_price_fix_styles() {
    ?>
    <style>
    .woocommerce-Price-amount {
        font-weight: normal;
    }
    
    .elementor-menu-cart__product-price {
        white-space: nowrap;
    }
    
    .product-quantity {
        font-weight: bold;
    }
    </style>
    <?php
}
add_action('wp_head', 'enhanced_price_fix_styles');
add_action('admin_head', 'enhanced_price_fix_styles');

// Принудительное исправление при каждой загрузке страницы
add_action('wp_footer', function() {
    ?>
    <script>
    // Дополнительная проверка через 2 секунды после загрузки
    setTimeout(function() {
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                $('.woocommerce-Price-amount').each(function() {
                    var $this = $(this);
                    var text = $this.text();
                    var match = text.match(/(\d+)/);
                    if (match) {
                        var price = parseInt(match[1]);
                        if (price >= 1 && price <= 999) {
                            var correctedPrice = price * 100;
                            var newText = text.replace(match[1], correctedPrice.toLocaleString('ru-RU'));
                            $this.find('bdi').html($this.find('bdi').html().replace(match[1], correctedPrice.toLocaleString('ru-RU')));
                        }
                    }
                });
            });
        }
    }, 2000);
    </script>
    <?php
});
?>