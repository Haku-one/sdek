<?php
/**
 * АГРЕССИВНОЕ ИСПРАВЛЕНИЕ ЦЕН - для самых сложных случаев
 * Скопируйте в functions.php если обычные методы не работают
 */

// Основная функция
function aggressive_price_fix() {
    if (!class_exists('WooCommerce')) return;
    
    // Максимальный приоритет для всех хуков
    add_filter('wc_price', 'aggressive_fix_price', 9999, 4);
    add_filter('woocommerce_cart_item_price', 'aggressive_fix_cart_price', 9999, 3);
    add_filter('woocommerce_cart_item_subtotal', 'aggressive_fix_cart_subtotal', 9999, 3);
    add_filter('woocommerce_cart_subtotal', 'aggressive_fix_cart_total', 9999, 3);
    add_filter('woocommerce_cart_total', 'aggressive_fix_final_total', 9999);
    add_filter('woocommerce_get_price_html', 'aggressive_fix_product_price', 9999, 2);
    
    // Буферизация всего контента
    add_action('wp_head', 'start_aggressive_buffer', 1);
    add_action('wp_footer', 'end_aggressive_buffer', 9999);
}
add_action('plugins_loaded', 'aggressive_price_fix', 1);

// Коррекция цены
function fix_price_aggressive($price) {
    $price = floatval($price);
    
    // Все цены от 1 до 999 умножаем на 100
    if ($price >= 1 && $price <= 999) {
        return $price * 100;
    }
    
    return $price;
}

// Исправление через wc_price
function aggressive_fix_price($return, $price, $args, $unformatted_price) {
    if (empty($price) || $price == 0) return $return;
    
    $corrected = fix_price_aggressive($price);
    if ($corrected != $price) {
        // Принудительно создаем новый HTML
        $formatted = number_format($corrected, 0, ',', ' ');
        return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi></span>';
    }
    
    return $return;
}

// Исправление цен в корзине
function aggressive_fix_cart_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['data'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected = fix_price_aggressive($product_price);
        
        if ($corrected != $product_price) {
            $formatted = number_format($corrected, 0, ',', ' ');
            return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi></span>';
        }
    }
    return $price;
}

// Исправление подытога в корзине
function aggressive_fix_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
    if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
        $product = $cart_item['data'];
        $product_price = $product->get_price();
        $corrected = fix_price_aggressive($product_price);
        
        if ($corrected != $product_price) {
            $quantity = $cart_item['quantity'];
            // Исправляем количество если больше 1000
            if ($quantity > 1000) {
                if ($quantity % 1000 == 0) $quantity = $quantity / 1000;
                else if ($quantity % 100 == 0) $quantity = $quantity / 100;
            }
            
            $line_total = $corrected * $quantity;
            $formatted = number_format($line_total, 0, ',', ' ');
            return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi></span>';
        }
    }
    return $subtotal;
}

// Исправление общего подытога
function aggressive_fix_cart_total($cart_subtotal, $compound, $cart) {
    $total = 0;
    $corrected = false;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
            $product = $cart_item['data'];
            $product_price = $product->get_price();
            $corrected_price = fix_price_aggressive($product_price);
            
            if ($corrected_price != $product_price) {
                $corrected = true;
            }
            
            $quantity = $cart_item['quantity'];
            if ($quantity > 1000) {
                if ($quantity % 1000 == 0) $quantity = $quantity / 1000;
                else if ($quantity % 100 == 0) $quantity = $quantity / 100;
            }
            
            $total += $corrected_price * $quantity;
        }
    }
    
    if ($corrected) {
        $formatted = number_format($total, 0, ',', ' ');
        return '<span class="woocommerce-Price-amount amount">' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></span>';
    }
    
    return $cart_subtotal;
}

// Исправление финальной суммы
function aggressive_fix_final_total($total) {
    global $woocommerce;
    
    if ($woocommerce && $woocommerce->cart) {
        $cart_total = 0;
        $corrected = false;
        
        foreach ($woocommerce->cart->get_cart() as $cart_item) {
            if (isset($cart_item['data']) && isset($cart_item['quantity'])) {
                $product = $cart_item['data'];
                $product_price = $product->get_price();
                $corrected_price = fix_price_aggressive($product_price);
                
                if ($corrected_price != $product_price) {
                    $corrected = true;
                }
                
                $quantity = $cart_item['quantity'];
                if ($quantity > 1000) {
                    if ($quantity % 1000 == 0) $quantity = $quantity / 1000;
                    else if ($quantity % 100 == 0) $quantity = $quantity / 100;
                }
                
                $cart_total += $corrected_price * $quantity;
            }
        }
        
        if ($corrected) {
            $formatted = number_format($cart_total, 0, ',', ' ');
            return '<span class="woocommerce-Price-amount amount">' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></span>';
        }
    }
    
    return $total;
}

// Исправление цены товара
function aggressive_fix_product_price($price, $product) {
    $product_price = $product->get_price();
    $corrected = fix_price_aggressive($product_price);
    
    if ($corrected != $product_price) {
        $formatted = number_format($corrected, 0, ',', ' ');
        return '<span class="woocommerce-Price-amount amount"><bdi>' . $formatted . '&nbsp;<span class="woocommerce-Price-currencySymbol">руб.</span></bdi></span>';
    }
    
    return $price;
}

// Буферизация контента
function start_aggressive_buffer() {
    ob_start();
}

function end_aggressive_buffer() {
    $content = ob_get_clean();
    
    // Агрессивная обработка всего HTML
    $content = aggressive_fix_html_prices($content);
    
    echo $content;
}

// Обработка HTML с ценами
function aggressive_fix_html_prices($content) {
    // Все возможные паттерны цен
    $patterns = array(
        // Основной паттерн с bdi
        '/(<span[^>]*woocommerce-Price-amount[^>]*><bdi>)(\d{1,3})(&nbsp;<span[^>]*woocommerce-Price-currencySymbol[^>]*>руб\.<\/span><\/bdi><\/span>)/i',
        // Без bdi
        '/(<span[^>]*woocommerce-Price-amount[^>]*>)(\d{1,3})(&nbsp;<span[^>]*woocommerce-Price-currencySymbol[^>]*>руб\.<\/span><\/span>)/i',
        // Простые паттерны
        '/(\d{1,3})(&nbsp;руб\.)/i',
        // В таблицах
        '/(<td[^>]*>[\s\S]*?)(\d{1,3})(&nbsp;руб\.)/i',
        // В div элементах
        '/(<div[^>]*>[\s\S]*?)(\d{1,3})(&nbsp;руб\.)/i',
    );
    
    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            if (count($matches) >= 3) {
                $price = intval($matches[2]);
                $corrected = fix_price_aggressive($price);
                
                if ($corrected != $price) {
                    $formatted = number_format($corrected, 0, ',', ' ');
                    return $matches[1] . $formatted . $matches[3];
                }
            }
            
            return $matches[0];
        }, $content);
    }
    
    // Исправляем количество товаров
    $qty_patterns = array(
        '/(<strong[^>]*product-quantity[^>]*>×&nbsp;)(\d{3,})(<\/strong>)/i',
        '/(<span[^>]*product-quantity[^>]*>)(\d{3,})( ×<\/span>)/i',
    );
    
    foreach ($qty_patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) {
            if (count($matches) >= 3) {
                $qty = intval($matches[2]);
                if ($qty > 1000) {
                    if ($qty % 1000 == 0) $corrected_qty = $qty / 1000;
                    else if ($qty % 100 == 0) $corrected_qty = $qty / 100;
                    else $corrected_qty = $qty;
                    
                    return $matches[1] . $corrected_qty . $matches[3];
                }
            }
            
            return $matches[0];
        }, $content);
    }
    
    return $content;
}

// Принудительный JavaScript на каждой странице
add_action('wp_footer', function() {
    ?>
    <script>
    // Агрессивное исправление цен JavaScript
    (function() {
        function aggressiveFixPrices() {
            // Ищем все элементы с ценами
            var priceElements = document.querySelectorAll('.woocommerce-Price-amount, .elementor-menu-cart__subtotal span, td span');
            
            priceElements.forEach(function(element) {
                var text = element.textContent || element.innerText;
                var match = text.match(/(\d{1,3})\s*руб\./);
                
                if (match) {
                    var price = parseInt(match[1]);
                    if (price >= 1 && price <= 999) {
                        var correctedPrice = price * 100;
                        var newText = text.replace(match[1], correctedPrice.toLocaleString('ru-RU'));
                        
                        // Обновляем содержимое
                        if (element.innerHTML.includes('<')) {
                            element.innerHTML = element.innerHTML.replace(match[1], correctedPrice.toLocaleString('ru-RU'));
                        } else {
                            element.textContent = newText;
                        }
                    }
                }
            });
            
            // Исправляем количество
            var qtyElements = document.querySelectorAll('.product-quantity, strong');
            qtyElements.forEach(function(element) {
                var text = element.textContent || element.innerText;
                var match = text.match(/(\d{3,})/);
                
                if (match && text.includes('×')) {
                    var qty = parseInt(match[1]);
                    if (qty > 1000) {
                        var correctedQty;
                        if (qty % 1000 === 0) correctedQty = qty / 1000;
                        else if (qty % 100 === 0) correctedQty = qty / 100;
                        else correctedQty = qty;
                        
                        element.innerHTML = element.innerHTML.replace(match[1], correctedQty);
                    }
                }
            });
        }
        
        // Запускаем исправление
        aggressiveFixPrices();
        
        // Повторяем каждые 2 секунды
        setInterval(aggressiveFixPrices, 2000);
        
        // При любых изменениях DOM
        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                setTimeout(aggressiveFixPrices, 100);
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }
        
        // При AJAX запросах
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ajaxComplete(function() {
                setTimeout(aggressiveFixPrices, 200);
            });
        }
    })();
    </script>
    <?php
});

// Добавляем CSS для скрытия проблемных элементов на время исправления
add_action('wp_head', function() {
    ?>
    <style>
    /* Временно скрываем цены пока они не исправятся */
    .woocommerce-Price-amount:not([data-fixed]) {
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .woocommerce-Price-amount[data-fixed] {
        opacity: 1;
    }
    </style>
    <script>
    // Показываем цены после исправления
    setTimeout(function() {
        var prices = document.querySelectorAll('.woocommerce-Price-amount');
        prices.forEach(function(price) {
            price.setAttribute('data-fixed', 'true');
        });
    }, 1000);
    </script>
    <?php
});
?>