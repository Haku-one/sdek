// Функция для извлечения цены из текста
function extractSinglePrice(priceText) {
    var price = 0;
    var pricePattern = /(\d+(?:\.\d+)?)\s*руб\.?/g;
    var priceMatches = [];
    var match;
    
    while ((match = pricePattern.exec(priceText)) !== null) {
        priceMatches.push(parseFloat(match[1]));
    }
    
    if (priceMatches.length > 0) {
        price = parseInt(priceMatches[0]) || 0;
    } else {
        var cleanPriceText = priceText.replace(/[^\d.]/g, '');
        if (cleanPriceText.length > 0) {
            var numbers = cleanPriceText.split('.');
            var mainNumber = numbers[0];
            
            if (mainNumber.length >= 6 && mainNumber.length % 2 === 0) {
                var halfLength = mainNumber.length / 2;
                var firstHalf = mainNumber.substring(0, halfLength);
                var secondHalf = mainNumber.substring(halfLength);
                
                if (firstHalf === secondHalf) {
                    price = parseInt(firstHalf) || 0;
                } else {
                    price = parseInt(mainNumber) || 0;
                }
            } else {
                price = parseInt(mainNumber) || 0;
            }
        } else {
            var numberMatch = priceText.match(/(\d+)/);
            if (numberMatch) {
                price = parseInt(numberMatch[1]) || 0;
            }
        }
    }
    
    return price;
}

jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false;
    var observerActive = false;
    
    // Глобальные переменные для поиска
    window.currentSearchCity = null;
    window.currentSearchStreet = null;
    
    function getCartData() {
        var cartWeight = 0;
        var cartValue = 0;
        var maxLength = 0;
        var maxWidth = 0;
        var maxHeight = 0;
        var totalVolume = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        
        // Получение данных из скрытых полей
        $('#wc-cart-data .cart-item-data').each(function() {
            var $item = $(this);
            var quantity = parseInt($item.find('.quantity').text()) || 1;
            totalItems += quantity;
            
            var length = parseFloat($item.find('.length').text()) || 0;
            var width = parseFloat($item.find('.width').text()) || 0;
            var height = parseFloat($item.find('.height').text()) || 0;
            var weight = parseFloat($item.find('.weight').text()) || 0;
            var price = parseFloat($item.find('.price').text()) || 0;
            
            if (length > 0 && width > 0 && height > 0) {
                maxLength = Math.max(maxLength, length);
                maxWidth = Math.max(maxWidth, width);
                maxHeight = Math.max(maxHeight, height);
                totalVolume += (length * width * height) * quantity;
                hasValidDimensions = true;
            }
            
            cartWeight += weight * quantity;
            cartValue += price * quantity;
        });
        
        // Получение данных из WooCommerce блоков
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = 1;
            
            if (quantityElement.length > 0) {
                quantity = parseInt(quantityElement.text()) || 1;
            }
            
            totalItems += quantity;
            
            // Получение габаритов
            var dimensionsElement = $item.find('.wc-block-components-product-details__value:contains("×")');
            if (dimensionsElement.length > 0) {
                var dimensionsText = dimensionsElement.text().replace(/[^\d×\.\,\s]/g, '');
                var parts = dimensionsText.split('×');
                
                if (parts.length >= 3) {
                    var length = parseFloat(parts[0].trim()) || 0;
                    var width = parseFloat(parts[1].trim()) || 0;
                    var height = parseFloat(parts[2].trim()) || 0;
                    
                    if (length > 0 && width > 0 && height > 0) {
                        maxLength = Math.max(maxLength, length);
                        maxWidth = Math.max(maxWidth, width);
                        maxHeight = Math.max(maxHeight, height);
                        totalVolume += (length * width * height) * quantity;
                        hasValidDimensions = true;
                    }
                }
            }
            
            // Получение веса
            var weightElement = $item.find('.wc-block-components-product-details__value:contains("гр")');
            if (weightElement.length > 0) {
                var weightText = weightElement.text().replace(/[^0-9\.]/g, '');
                var weight = parseFloat(weightText) || 0;
                cartWeight += weight * quantity;
            }
            
            // Получение цены - сначала итоговая, потом за единицу
            var totalPriceElement = $item.find('.wc-block-components-order-summary-item__total-price .wc-block-components-product-price__value');
            var priceElement = $item.find('.wc-block-components-product-price__value');
            
            if (totalPriceElement.length > 0) {
                var totalPriceText = totalPriceElement.text().trim();
                var totalPriceMatch = totalPriceText.match(/(\d+(?:\.\d+)?)\s*руб\.?/);
                if (totalPriceMatch) {
                    var totalPrice = parseInt(totalPriceMatch[1]) || 0;
                    cartValue += totalPrice;
                }
            } else if (priceElement.length > 0) {
                var priceText = priceElement.text().trim();
                var price = extractSinglePrice(priceText);
                cartValue += price * quantity;
            }
        });
        
        // Расчет размеров упаковки
        if (!hasValidDimensions && cartValue > 0) {
            var estimatedLength = Math.min(50, Math.max(20, Math.sqrt(cartValue / 100)));
            var estimatedWidth = Math.min(40, Math.max(15, Math.sqrt(cartValue / 150)));
            var estimatedHeight = Math.min(30, Math.max(10, Math.sqrt(cartValue / 200)));
            
            maxLength = estimatedLength;
            maxWidth = estimatedWidth;
            maxHeight = estimatedHeight;
            totalVolume = estimatedLength * estimatedWidth * estimatedHeight;
        }
        
        if (hasValidDimensions && totalItems > 1) {
            var volumePerItem = totalVolume / totalItems;
            var cubicRoot = Math.cbrt(volumePerItem);
            var packingEfficiency = 0.7;
            var requiredVolume = totalVolume / packingEfficiency;
            var packageCubicRoot = Math.cbrt(requiredVolume);
            
            maxLength = Math.max(maxLength, packageCubicRoot);
            maxWidth = Math.max(maxWidth, packageCubicRoot * 0.8);
            maxHeight = Math.max(maxHeight, packageCubicRoot * 0.6);
        }
        
        return {
            weight: Math.max(100, cartWeight),
            dimensions: {
                length: Math.max(10, Math.round(maxLength)),
                width: Math.max(10, Math.round(maxWidth)),
                height: Math.max(5, Math.round(maxHeight))
            },
            value: cartValue,
            hasRealDimensions: hasValidDimensions
        };
    }
    
    function calculateDelivery(pointCode, pointData) {
        var cartData = getCartData();
        
        if (cartData.value <= 0) {
            console.error('СДЭК: Не удалось определить стоимость товаров');
            return;
        }
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'calculate_cdek_delivery_cost',
                point_code: pointCode,
                point_data: JSON.stringify(pointData),
                cart_weight: cartData.weight,
                cart_dimensions: JSON.stringify(cartData.dimensions),
                cart_value: cartData.value,
                has_real_dimensions: cartData.hasRealDimensions ? 1 : 0,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.api_success) {
                    updateShippingBlock(pointData.name, response.data.delivery_sum);
                } else {
                    console.error('СДЭК: Ошибка расчета доставки', response.data ? response.data.message : 'Неизвестная ошибка');
                }
            },
            error: function(xhr, status, error) {
                console.error('СДЭК: Ошибка AJAX запроса:', error);
            }
        });
    }
    
    function updateShippingBlock(pointName, deliveryCost) {
        var strategies = [
            function() { return $('.wc-block-components-totals-shipping'); },
            function() { return $('.wp-block-woocommerce-checkout-order-summary-shipping-block'); },
            function() { 
                return $('.wc-block-components-totals-item__label').filter(function() {
                    return $(this).text().includes('выдачи') || $(this).text().includes('доставки');
                }).closest('.wc-block-components-totals-item');
            }
        ];
        
        var $shippingBlocks = $();
        strategies.forEach(function(strategy) {
            $shippingBlocks = $shippingBlocks.add(strategy());
        });
        
        if ($shippingBlocks.length > 0) {
            $shippingBlocks.each(function() {
                var $block = $(this);
                var $label = $block.find('.wc-block-components-totals-item__label');
                var $value = $block.find('.wc-block-components-totals-item__value');
                
                if ($label.length && $value.length) {
                    $label.html('СДЭК до пункта<br><small>' + pointName + '</small>');
                    $value.html('<span class="wc-block-formatted-money-amount">' + deliveryCost + ' руб.</span>');
                }
            });
        }
    }
    
    // Остальной код без изменений...
    // [Здесь должен быть остальной код для инициализации виджета и обработки событий]
});