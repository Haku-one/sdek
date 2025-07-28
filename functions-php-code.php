<?php
/**
 * БЫСТРОЕ ИСПРАВЛЕНИЕ ЦЕН WOOCOMMERCE
 * Скопируйте этот код в functions.php вашей темы
 */

// Основная функция исправления цен
function fix_woocommerce_prices() {
    if (!class_exists('WooCommerce')) return;
    
    // Исправляем отображение цен
    add_filter('wc_price', 'correct_price_display', 10, 4);
    add_filter('woocommerce_cart_item_price', 'correct_cart_price', 10, 3);
    add_filter('woocommerce_cart_item_subtotal', 'correct_cart_subtotal', 10, 3);
    add_filter('woocommerce_get_price_html', 'correct_product_price', 10, 2);
    add_filter('woocommerce_currency_symbol', 'fix_currency_symbol', 10, 2);
}
add_action('init', 'fix_woocommerce_prices');

// Исправляет цену если она слишком маленькая
function correct_price_value($price) {
    $price = floatval($price);
    
    // Если цена меньше 100 рублей - умножаем на 100
    if ($price > 0 && $price < 100) {
        return $price * 100;
    }
    
    return $price;
}

// Исправляет отображение цены
function correct_price_display($return, $price, $args, $unformatted_price) {
    if (empty($price) || $price == 0) return $return;
    
    $corrected_price = correct_price_value($price);
    
    if ($corrected_price != $price) {
        return wc_price($corrected_price);
    }
    
    return $return;
}

// Исправляет цену в корзине
function correct_cart_price($price, $cart_item, $cart_item_key) {
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

// Исправляет подытог в корзине
function correct_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
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

// Исправляет цену товара на странице
function correct_product_price($price, $product) {
    $product_price = $product->get_price();
    $corrected_price = correct_price_value($product_price);
    
    if ($corrected_price != $product_price) {
        return wc_price($corrected_price);
    }
    
    return $price;
}

// Исправляет символ валюты
function fix_currency_symbol($currency_symbol, $currency) {
    if ($currency === 'RUB' || empty($currency)) {
        return 'руб.';
    }
    return $currency_symbol;
}

// JavaScript для исправления цен на фронтенде
function add_price_fix_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        function fixPrices() {
            $('.woocommerce-Price-amount, .wc-block-formatted-money-amount').each(function() {
                var $this = $(this);
                var text = $this.text();
                var match = text.match(/(\d+)/);
                if (match) {
                    var price = parseInt(match[1]);
                    if (price > 0 && price < 100) {
                        var correctedPrice = price * 100;
                        var newText = text.replace(/\d+/, correctedPrice.toLocaleString('ru-RU'));
                        $this.html(newText);
                    }
                }
            });
        }
        
        fixPrices();
        $(document).on('updated_cart_totals updated_checkout', fixPrices);
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_price_fix_js');
?>