// КРИТИЧЕСКИЙ ПАТЧ: Перехватываем jQuery.text() для исправления дублированных сумм
if (typeof $ !== 'undefined' && $.fn.text) {
    var originalText = $.fn.text;
    $.fn.text = function(value) {
        // Если устанавливается значение
        if (arguments.length > 0 && typeof value === 'string') {
            // Проверяем, не является ли это итоговой суммой с дублированием
            if (this.hasClass('wc-block-components-totals-item__value') && 
                this.closest('.wc-block-components-totals-footer-item').length > 0) {
                
                var match = value.match(/(\d+)/);
                if (match && match[1].length >= 6) {
                    var num = match[1];
                    var corrected = null;
                    
                    // Проверяем 3+остальное
                    if (num.length >= 6) {
                        var firstPart = num.substring(0, 3);
                        var secondPart = num.substring(3);
                        
                        if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                            corrected = secondPart;
                        }
                    }
                    
                    // Проверяем 4+остальное
                    if (!corrected && num.length >= 7) {
                        var firstPart = num.substring(0, 4);
                        var secondPart = num.substring(4);
                        
                        if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                            corrected = secondPart;
                        }
                    }
                    
                    if (corrected) {
                        value = value.replace(num, corrected);
                        console.log('🔥 ПЕРЕХВАЧЕНО jQuery.text():', arguments[0], '->', value);
                    }
                }
            }
        }
        
        return originalText.apply(this, arguments.length > 0 ? [value] : []);
    };
}

// КРИТИЧЕСКИЙ ПАТЧ: Перехватываем нативные DOM методы
if (typeof HTMLElement !== 'undefined') {
    // Патчим textContent
    var originalTextContentDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'textContent') || 
                                       Object.getOwnPropertyDescriptor(Element.prototype, 'textContent');
    
    if (originalTextContentDescriptor && originalTextContentDescriptor.set) {
        Object.defineProperty(HTMLElement.prototype, 'textContent', {
            set: function(value) {
                if (typeof value === 'string' && 
                    this.classList.contains('wc-block-components-totals-item__value') &&
                    this.closest('.wc-block-components-totals-footer-item')) {
                    
                    var match = value.match(/(\d+)/);
                    if (match && match[1].length >= 6) {
                        var num = match[1];
                        var corrected = null;
                        
                        // Проверяем 3+остальное
                        if (num.length >= 6) {
                            var firstPart = num.substring(0, 3);
                            var secondPart = num.substring(3);
                            
                            if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                corrected = secondPart;
                            }
                        }
                        
                        // Проверяем 4+остальное
                        if (!corrected && num.length >= 7) {
                            var firstPart = num.substring(0, 4);
                            var secondPart = num.substring(4);
                            
                            if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                corrected = secondPart;
                            }
                        }
                        
                        if (corrected) {
                            value = value.replace(num, corrected);
                            console.log('🔥 ПЕРЕХВАЧЕНО textContent:', arguments[0], '->', value);
                        }
                    }
                }
                originalTextContentDescriptor.set.call(this, value);
            },
            get: originalTextContentDescriptor.get
        });
    }
    
    // Патчим innerHTML
    var originalInnerHTMLDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'innerHTML') || 
                                     Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    
    if (originalInnerHTMLDescriptor && originalInnerHTMLDescriptor.set) {
        Object.defineProperty(HTMLElement.prototype, 'innerHTML', {
            set: function(value) {
                if (typeof value === 'string' && 
                    this.classList.contains('wc-block-components-totals-item__value') &&
                    this.closest('.wc-block-components-totals-footer-item')) {
                    
                    var match = value.match(/(\d+)/);
                    if (match && match[1].length >= 6) {
                        var num = match[1];
                        var corrected = null;
                        
                        // Проверяем 3+остальное
                        if (num.length >= 6) {
                            var firstPart = num.substring(0, 3);
                            var secondPart = num.substring(3);
                            
                            if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                corrected = secondPart;
                            }
                        }
                        
                        // Проверяем 4+остальное
                        if (!corrected && num.length >= 7) {
                            var firstPart = num.substring(0, 4);
                            var secondPart = num.substring(4);
                            
                            if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                corrected = secondPart;
                            }
                        }
                        
                        if (corrected) {
                            value = value.replace(num, corrected);
                            console.log('🔥 ПЕРЕХВАЧЕНО innerHTML:', arguments[0], '->', value);
                        }
                    }
                }
                originalInnerHTMLDescriptor.set.call(this, value);
            },
            get: originalInnerHTMLDescriptor.get
        });
    }
}

// Функция для извлечения цены из текста
function extractSinglePrice(priceText) {
    var price = 0;
    
    // Специальная обработка дублированных цен
    var pricePattern = /(\d+(?:\.\d+)?)\s*руб\.?/g;
    var priceMatches = [];
    var match;
    
    while ((match = pricePattern.exec(priceText)) !== null) {
        priceMatches.push(parseFloat(match[1]));
    }
    
    if (priceMatches.length > 0) {
        // Берем первую найденную цену (исключаем дубли)
        price = parseInt(priceMatches[0]) || 0;
    } else {
        // Fallback: удаляем все символы кроме цифр и ищем числа
        var cleanPriceText = priceText.replace(/[^\d.]/g, '');
        
        if (cleanPriceText.length > 0) {
            var numbers = cleanPriceText.split('.');
            var mainNumber = numbers[0];
            
            // Проверяем на дублирование типа "180180"
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
            // Последний fallback: ищем любое число
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
    window.currentSearchCoordinates = null;
    window.addressSuggestions = [];
    
    // Переменные для дебаунсинга и кэширования
    var searchTimeout = null;
    var isSearching = false;
    var cdekPointsCache = null;
    var lastSearchTime = 0;
    var cacheExpiry = 5 * 60 * 1000; // 5 минут
    
    // ====== ФУНКЦИИ ДЛЯ РАБОТЫ С ГАБАРИТАМИ ТОВАРОВ ======
    
    function getCartDataForCalculation() {
        var cartWeight = 0;
        var cartValue = 0;
        var totalVolume = 0;
        var maxLength = 0, maxWidth = 0, maxHeight = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        
        console.log('Получение данных корзины для расчета...');
        
        // ПРИОРИТЕТ 1: Получаем данные из скрытых полей с точными значениями
        $('#wc-cart-data .cart-item-data').each(function() {
            var $item = $(this);
            var length = parseFloat($item.attr('data-length')) || 0;
            var width = parseFloat($item.attr('data-width')) || 0;
            var height = parseFloat($item.attr('data-height')) || 0;
            var weight = parseFloat($item.attr('data-weight')) || 0;
            var quantity = parseInt($item.attr('data-quantity')) || 1;
            var price = parseFloat($item.attr('data-price')) || 0;
            
            console.log('Обработка товара из WC данных:', {
                length: length, width: width, height: height, 
                weight: weight, quantity: quantity, price: price
            });
            
            if (length > 0 && width > 0 && height > 0) {
                // Рассчитываем объем
                var itemVolume = length * width * height * quantity;
                totalVolume += itemVolume;
                totalItems += quantity;
                
                // Обновляем максимальные размеры
                maxLength = Math.max(maxLength, length);
                maxWidth = Math.max(maxWidth, width);
                maxHeight = Math.max(maxHeight, height);
                
                hasValidDimensions = true;
                console.log('✅ Найдены точные габариты из WC:', {length: length, width: width, height: height, quantity: quantity});
            }
            
            if (weight > 0) {
                cartWeight += weight * quantity;
                console.log('✅ Найден точный вес из WC:', weight, 'г, количество:', quantity);
            }
            
            cartValue += price * quantity;
        });
        
        // ПРИОРИТЕТ 2: Если не нашли в скрытых полях, пробуем из визуального блока
        if (!hasValidDimensions) {
            $('#product-dimensions-info .product-dimensions').each(function() {
                var $item = $(this);
                var dimensionsText = $item.find('span').text();
                
                console.log('Обработка товара из визуального блока:', dimensionsText);
                
                // Извлекаем габариты из текста "📏 Габариты: 10×20×30 см"
                var dimensionsMatch = dimensionsText.match(/📏\s*Габариты:\s*(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?)\s*см/);
                if (dimensionsMatch) {
                    var length = parseFloat(dimensionsMatch[1]);
                    var width = parseFloat(dimensionsMatch[2]);
                    var height = parseFloat(dimensionsMatch[3]);
                    
                    // Определяем количество товара из заголовка
                    var titleText = $item.find('strong').text();
                    var quantityMatch = titleText.match(/\(×(\d+)\)/);
                    var quantity = quantityMatch ? parseInt(quantityMatch[1]) : 1;
                    
                    console.log('Найдены габариты из визуального блока:', {length: length, width: width, height: height, quantity: quantity});
                    
                    // Рассчитываем объем
                    var itemVolume = length * width * height * quantity;
                    totalVolume += itemVolume;
                    totalItems += quantity;
                    
                    // Обновляем максимальные размеры
                    maxLength = Math.max(maxLength, length);
                    maxWidth = Math.max(maxWidth, width);
                    maxHeight = Math.max(maxHeight, height);
                    
                    hasValidDimensions = true;
                }
                
                // Извлекаем вес из текста "⚖️ Вес: 500 г"
                var weightMatch = dimensionsText.match(/⚖️\s*Вес:\s*(\d+(?:\.\d+)?)\s*г/);
                if (weightMatch) {
                    var weight = parseFloat(weightMatch[1]);
                    var quantity = 1;
                    
                    // Определяем количество из заголовка
                    var titleText = $item.find('strong').text();
                    var quantityMatch = titleText.match(/\(×(\d+)\)/);
                    if (quantityMatch) {
                        quantity = parseInt(quantityMatch[1]);
                    }
                    
                    console.log('Найден вес из визуального блока:', weight, 'г, количество:', quantity);
                    cartWeight += weight * quantity;
                }
            });
        }
        
        console.log('Статистика габаритов после блока product-dimensions:', {
            hasValidDimensions: hasValidDimensions,
            totalVolume: totalVolume,
            maxDimensions: {length: maxLength, width: maxWidth, height: maxHeight},
            totalItems: totalItems,
            cartWeight: cartWeight
        });
        
        // ПРИОРИТЕТ 3.1: Пробуем получить общую стоимость заказа из итогового блока
        var totalOrderElement = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value, .wc-block-components-totals-footer-item-tax-value, .wc-block-components-totals-footer-item .wc-block-formatted-money-amount');
        var orderTotalFromFooter = 0;
        
        if (totalOrderElement.length > 0) {
            var totalText = totalOrderElement.first().text().trim();
            console.log('Найдена итоговая сумма заказа:', totalText);
            
            // Проверяем на дублирование в итоговой сумме (например, "180628" вместо "628")
            var totalMatch = totalText.match(/(\d+)/);
            if (totalMatch) {
                var totalNumber = totalMatch[1];
                console.log('Число из итоговой суммы:', totalNumber);
                
                // Проверяем на дублирование в итоговой сумме
                if (totalNumber.length >= 6) {
                    // Попробуем разные варианты дублирования
                    // Случай 1: 180628 -> проверяем 180 + 628 = 808, но сумма может быть 628
                    // Случай 2: полное дублирование типа 180180 -> 180
                    
                    var halfLength = Math.floor(totalNumber.length / 2);
                    var firstHalf = totalNumber.substring(0, halfLength);
                    var secondHalf = totalNumber.substring(halfLength);
                    
                    // Полное дублирование (180180 -> 180)
                    if (firstHalf === secondHalf && halfLength >= 3) {
                        orderTotalFromFooter = parseInt(firstHalf);
                        console.log('🔍 Полное дублирование в итоговой сумме:', totalNumber, '-> исправлено на:', orderTotalFromFooter);
                    }
                    // Частичное дублирование (например, 874268 -> 1268, где 873 - цена товара)
                    else if (totalNumber.length >= 6) {
                        // Проверяем разные варианты разделения
                        var found = false;
                        
                        // Попробуем разделить на 3+3, 3+4, 4+3, 4+4 символа
                        var splits = [
                            {prefix: totalNumber.substring(0, 3), suffix: totalNumber.substring(3)},
                            {prefix: totalNumber.substring(0, 4), suffix: totalNumber.substring(4)}
                        ];
                        
                        for (var i = 0; i < splits.length && !found; i++) {
                            var itemPrice = parseInt(splits[i].prefix);
                            var possibleTotal = parseInt(splits[i].suffix);
                            
                            // Проверяем, может ли это быть дублированием цены товара
                            if (itemPrice >= 100 && itemPrice <= 9999 && possibleTotal >= 200 && possibleTotal <= 99999) {
                                // Дополнительная проверка - возможная цена товара не должна быть больше итоговой суммы
                                if (itemPrice < possibleTotal) {
                                    orderTotalFromFooter = possibleTotal;
                                    console.log('🔍 Частичное дублирование в итоговой сумме:', totalNumber, '(цена товара:', itemPrice, ') -> итого:', orderTotalFromFooter);
                                    found = true;
                                }
                            }
                        }
                        
                        if (!found) {
                            orderTotalFromFooter = parseInt(totalNumber);
                        }
                    } else {
                        orderTotalFromFooter = parseInt(totalNumber);
                    }
                } else {
                    orderTotalFromFooter = parseInt(totalNumber) || 0;
                }
            }
        }
        
        // ПРИОРИТЕТ 3.2: Если не нашли в скрытых полях, пробуем из WC блоков товаров
        var processedItems = new Set(); // Для предотвращения дублирования
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            
            // Создаем уникальный идентификатор товара для предотвращения дублирования
            var itemName = $item.find('.wc-block-components-product-name').text().trim();
            var itemId = itemName + '_' + $item.index();
            
            if (processedItems.has(itemId)) {
                console.log('⚠️ Пропускаем дублированный товар:', itemName);
                return; // Пропускаем уже обработанный товар
            }
            processedItems.add(itemId);
            
            // Получаем количество товара
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = parseInt(quantityElement.text()) || 1;
            
            console.log('Обработка товара из WC блока, количество:', quantity);
            
            // Ищем габариты в метаданных товара - новый формат "Габариты (Д×Ш×В): 10×10×10 см"
            var dimensionsElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                var labelText = siblingLabel.text();
                return labelText.indexOf('Габариты') !== -1 || labelText.indexOf('Размеры') !== -1;
            });
            
            if (dimensionsElement.length > 0) {
                var dimensionsText = dimensionsElement.text().trim();
                console.log('Найдены габариты в блоке товара:', dimensionsText);
                
                // Парсим габариты в формате "10×10×10 см" или "10x10x10"
                var dimensionsMatch = dimensionsText.match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/);
                
                if (dimensionsMatch) {
                    var length = parseFloat(dimensionsMatch[1]);
                    var width = parseFloat(dimensionsMatch[2]);
                    var height = parseFloat(dimensionsMatch[3]);
                    
                    console.log('✅ Найдены габариты из WC блока товара:', {length: length, width: width, height: height, quantity: quantity});
                    
                    var itemVolume = length * width * height * quantity;
                    totalVolume += itemVolume;
                    totalItems += quantity;
                    
                    maxLength = Math.max(maxLength, length);
                    maxWidth = Math.max(maxWidth, width);
                    maxHeight = Math.max(maxHeight, height);
                    
                    hasValidDimensions = true;
                }
            }
            
            // Получаем вес товара - обновленный поиск
            var weightElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                return siblingLabel.text().indexOf('Вес') !== -1;
            });
            
            if (weightElement.length > 0) {
                var weightText = weightElement.text().trim();
                console.log('Найден вес в блоке товара:', weightText);
                
                var weightMatch = weightText.match(/(\d+(?:\.\d+)?)/);
                
                if (weightMatch) {
                    var weight = parseFloat(weightMatch[1]);
                    
                    // Конвертируем в граммы если нужно
                    if (weightText.includes('кг')) {
                        weight = weight * 1000;
                    }
                    
                    cartWeight += weight * quantity;
                    console.log('✅ Найден вес из WC блока товара:', weight, 'г, количество:', quantity);
                }
            }
            
            // Сначала ищем итоговую цену за весь товар
            var totalPriceElement = $item.find('.wc-block-components-order-summary-item__total-price .wc-block-components-product-price__value');
            var priceElement = $item.find('.wc-block-components-product-price__value');
            
            if (totalPriceElement.length > 0) {
                // Используем итоговую цену (с учетом количества и скидок)
                var totalPriceText = totalPriceElement.text().trim();
                console.log('Найдена итоговая цена товара:', totalPriceText);
                
                var totalPriceMatch = totalPriceText.match(/(\d+(?:\.\d+)?)\s*руб\.?/);
                if (totalPriceMatch) {
                    var totalPrice = parseInt(totalPriceMatch[1]) || 0;
                    cartValue += totalPrice; // Добавляем итоговую цену (уже с учетом количества)
                    console.log('✅ Используем итоговую цену товара:', totalPrice, 'руб. (с учетом количества', quantity + ')');
                } else {
                    console.log('⚠️ Не удалось извлечь итоговую цену, используем цену за единицу');
                    // Fallback к цене за единицу
                    if (priceElement.length > 0) {
                        var priceText = priceElement.text().trim();
                        console.log('Исходный текст цены за единицу:', priceText);
                        
                        var price = extractSinglePrice(priceText);
                        cartValue += price * quantity;
                        console.log('✅ Итоговая цена товара (расчетная):', price, 'руб., количество:', quantity);
                    }
                }
            } else if (priceElement.length > 0) {
                var priceText = priceElement.text().trim();
                console.log('Исходный текст цены за единицу:', priceText);
                
                var price = extractSinglePrice(priceText);
                cartValue += price * quantity;
                console.log('✅ Итоговая цена товара (расчетная):', price, 'руб., количество:', quantity);
            }
        });
        
        // Если все еще нет габаритов, пробуем получить из глобальных переменных WooCommerce
        if (!hasValidDimensions) {
            console.log('Пробуем получить габариты из глобальных данных WooCommerce...');
            
            // Проверяем наличие данных о товарах в window объекте
            if (typeof wc_checkout_params !== 'undefined' || typeof woocommerce_params !== 'undefined') {
                console.log('Найдены параметры WooCommerce');
            }
            
            // Ищем скрытые поля с габаритами
            $('input[name*="dimensions"], input[name*="weight"]').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                console.log('Найдено скрытое поле:', name, '=', value);
            });
            
            // Последняя попытка - устанавливаем базовые габариты товара
            if (cartValue > 0) {
                // Примерные габариты в зависимости от стоимости
                var estimatedLength = Math.min(50, Math.max(20, Math.sqrt(cartValue / 100)));
                var estimatedWidth = Math.min(40, Math.max(15, Math.sqrt(cartValue / 150)));
                var estimatedHeight = Math.min(30, Math.max(10, Math.sqrt(cartValue / 200)));
                
                maxLength = estimatedLength;
                maxWidth = estimatedWidth;
                maxHeight = estimatedHeight;
                totalVolume = estimatedLength * estimatedWidth * estimatedHeight;
                hasValidDimensions = true;
                
                console.log('Установлены примерные габариты на основе стоимости:', {
                    length: estimatedLength,
                    width: estimatedWidth, 
                    height: estimatedHeight,
                    volume: totalVolume
                });
            }
        }
        
        // Рассчитываем итоговые размеры упаковки
        var dimensions;
        if (hasValidDimensions && totalVolume > 0) {
            console.log('Расчет размеров упаковки на основе товаров:', {
                totalVolume: totalVolume,
                maxLength: maxLength,
                maxWidth: maxWidth,
                maxHeight: maxHeight,
                totalItems: totalItems
            });
            
            // Для одного товара или небольшого количества - используем реальные размеры с небольшой наценкой
            if (totalItems <= 2) {
                dimensions = {
                    length: Math.ceil(maxLength * 1.05), // 5% запас на упаковку
                    width: Math.ceil(maxWidth * 1.05),
                    height: Math.ceil(maxHeight * 1.05)
                };
            } else {
                // Для большого количества товаров используем алгоритм упаковки
                var volumeRatio = Math.pow(totalVolume / (maxLength * maxWidth * maxHeight), 1/3);
                
                dimensions = {
                    length: Math.ceil(maxLength * Math.max(volumeRatio, 1) * 1.1),
                    width: Math.ceil(maxWidth * Math.max(volumeRatio, 1) * 1.1),
                    height: Math.ceil(maxHeight * Math.max(volumeRatio, 1) * 1.1)
                };
            }
            
            // Ограничиваем размерами (СДЭК лимиты)
            dimensions.length = Math.max(10, Math.min(dimensions.length, 150));
            dimensions.width = Math.max(10, Math.min(dimensions.width, 150));
            dimensions.height = Math.max(5, Math.min(dimensions.height, 150));
            
            // КРИТИЧЕСКИ ВАЖНО: Проверяем объем упаковки (лимит СДЭК = 300 см)
            var volume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
            if (volume > 300) {
                console.log('⚠️ Объем упаковки превышает лимит СДЭК:', volume, 'см > 300 см. Корректируем размеры.');
                
                // Пропорционально уменьшаем все размеры, чтобы объем не превышал 300 см
                var scaleFactor = 290 / volume; // 290 для небольшого запаса
                dimensions.length = Math.ceil(dimensions.length * scaleFactor);
                dimensions.width = Math.ceil(dimensions.width * scaleFactor);
                dimensions.height = Math.ceil(dimensions.height * scaleFactor);
                
                var newVolume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
                console.log('✅ Размеры скорректированы. Новый объем:', newVolume, 'см');
            }
            
            console.log('Рассчитанные размеры упаковки:', dimensions);
        } else {
            console.log('Используем размеры по умолчанию (нет реальных габаритов)');
            // Размеры по умолчанию
            dimensions = {
                length: 30,
                width: 20,
                height: 15
            };
        }
        
        // Минимальный вес
        if (cartWeight === 0) {
            cartWeight = 500;
        }
        
        // Используем итоговую сумму заказа если она найдена и больше суммы по товарам
        if (orderTotalFromFooter > 0) {
            console.log('💰 Используем итоговую сумму заказа:', orderTotalFromFooter, 'руб. (вместо суммы по товарам:', cartValue, 'руб.)');
            cartValue = orderTotalFromFooter;
        } else if (cartValue === 0) {
            // Получаем общую стоимость если не удалось по товарам
            var subtotalElement = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
            });
            
            if (subtotalElement.length > 0) {
                var subtotalText = subtotalElement.find('.wc-block-components-totals-item__value').text();
                cartValue = parseInt(subtotalText.replace(/[^\d]/g, '')) || 1000;
            }
        }
        
        console.log('Данные корзины для расчета:', {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions
        });
        
        return {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions
        };
    }
    
    // ====== ФУНКЦИИ ДЛЯ РАСЧЕТА СТОИМОСТИ ДОСТАВКИ ======
    
    function calculateDeliveryCost(point, callback) {
        var cartData = getCartDataForCalculation();
        
        if (typeof cdek_ajax === 'undefined' || !cdek_ajax.ajax_url) {
            console.error('CDEK AJAX не инициализирован');
            callback(calculateFallbackCost(point, cartData));
            return;
        }
        
        if (!point || !point.code) {
            console.error('Не указан пункт выдачи или его код');
            callback(calculateFallbackCost(point, cartData));
            return;
        }
        
        console.log('Запрос расчета стоимости доставки для пункта:', point.code);
        console.log('Данные корзины:', cartData);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000, // Увеличиваем таймаут
            data: {
                action: 'calculate_cdek_delivery_cost',
                point_code: point.code,
                point_data: JSON.stringify(point),
                cart_weight: cartData.weight,
                cart_dimensions: JSON.stringify(cartData.dimensions),
                cart_value: cartData.value,
                has_real_dimensions: cartData.hasRealDimensions ? 1 : 0,
                nonce: cdek_ajax.nonce || ''
            },
            success: function(response) {
                console.log('Ответ API расчета стоимости:', response);
                
                if (response && response.success && response.data && response.data.delivery_sum) {
                    var deliveryCost = parseInt(response.data.delivery_sum);
                    
                    if (response.data.fallback) {
                        console.warn('⚠️ Используется резервный расчет:', deliveryCost, 'руб.');
                        console.log('Причина:', response.data.message);
                    } else if (response.data.api_success) {
                        console.log('✅ Успешно получена стоимость из настоящего API СДЭК:', deliveryCost, 'руб.');
                        if (response.data.alternative_tariff) {
                            console.log('Использован альтернативный тариф:', response.data.alternative_tariff);
                        }
                    } else {
                        console.log('💰 Получена стоимость доставки:', deliveryCost, 'руб.');
                    }
                    
                    callback(deliveryCost);
                } else if (!response.success) {
                    // API вернул ошибку - нет fallback
                    console.error('❌ API СДЭК вернул ошибку:', response.data ? response.data.message : 'Неизвестная ошибка');
                    console.error('🔍 Данные для отладки:', response.data ? response.data.debug_info : response);
                    console.error('🔍 ПОЛНЫЙ ответ от сервера:', response);
                    
                    // Показываем детальную информацию об ошибке
                    if (response.data && response.data.api_response) {
                        console.error('🔍 Ответ от API СДЭК:', response.data.api_response);
                    }
                    
                    // Показываем пользователю сообщение об ошибке  
                    alert('Ошибка расчета стоимости доставки СДЭК. Попробуйте выбрать другой пункт выдачи или обновите страницу.');
                    return; // НЕ вызываем callback
                } else {
                    console.error('❌ API СДЭК вернул некорректный ответ');
                    console.error('🔍 Детали ответа:', response);
                    
                    alert('Ошибка получения стоимости доставки. Попробуйте обновить страницу.');
                    return; // НЕ вызываем callback
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Критическая ошибка запроса к API СДЭК:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                // Показываем детальную ошибку
                alert('Ошибка соединения с API СДЭК. Проверьте интернет-соединение и попробуйте снова.');
                return; // НЕ вызываем callback
            }
        });
    }
    
    function calculateFallbackCost(point, cartData) {
        var baseCost = 300;
        
        if (!cartData) {
            return baseCost;
        }
        
        if (cartData.weight > 500) {
            var extraWeight = Math.ceil((cartData.weight - 500) / 500);
            baseCost += extraWeight * 35;
        }
        
        if (cartData.hasRealDimensions && cartData.dimensions) {
            var volume = cartData.dimensions.length * cartData.dimensions.width * cartData.dimensions.height;
            if (volume > 12000) {
                var extraVolume = Math.ceil((volume - 12000) / 6000);
                baseCost += extraVolume * 50;
            }
        }
        
        if (cartData.value > 3000) {
            baseCost += Math.ceil((cartData.value - 3000) / 1000) * 20;
        }
        
        return baseCost;
    }
    
    // ====== ФУНКЦИИ ДЛЯ РАБОТЫ С АДРЕСАМИ ======
    
    function parseAddress(address) {
        var result = {
            city: '',
            street: ''
        };
        
        if (!address || address.trim() === '') {
            return result;
        }
        
        var parts = address.split(/[,\s]+/);
        
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (!part) continue;
            
            if (!result.city && !result.street) {
                result.city = part;
            } else if (result.city && !result.street) {
                result.street = parts.slice(i).join(' ');
                break;
            }
        }
        
        return result;
    }
    
    function initAddressAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        $('#address-select').remove();
        $('#address-suggestions').remove();
        
        setupBasicAutocomplete();
    }
    
    function setupBasicAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        var suggestionsContainer = $('<div id="address-suggestions" style="position: absolute; background: white; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>');
        addressInput.parent().css('position', 'relative');
        addressInput.parent().append(suggestionsContainer);
        
        addressInput.on('input', function() {
            var query = $(this).val().trim();
            
            if (query.length >= 2) {
                var suggestions = generateAddressSuggestions(query);
                
                if (suggestions.length > 0) {
                    showAddressSuggestions(suggestions);
                } else {
                    hideAddressSuggestions();
                }
            } else {
                hideAddressSuggestions();
            }
        });
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#address-suggestions, #shipping-address_1').length) {
                hideAddressSuggestions();
            }
        });
    }
    
    function generateAddressSuggestions(query) {
        var suggestions = [];
        var queryLower = query.toLowerCase();
        
        var cities = [
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул'
        ];
        
        cities.forEach(function(city) {
            if (city.toLowerCase().indexOf(queryLower) !== -1) {
                suggestions.push(city);
            }
        });
        
        return suggestions.slice(0, 10);
    }
    
    function showAddressSuggestions(suggestions) {
        var container = $('#address-suggestions');
        container.empty();
        
        suggestions.forEach(function(suggestion) {
            var item = $('<div class="suggestion-item" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;" data-address="' + suggestion + '">' + suggestion + '</div>');
            
            item.on('click', function() {
                var address = $(this).data('address');
                $('#shipping-address_1, input[name="shipping_address_1"]').val(address);
                hideAddressSuggestions();
                
                searchCdekPoints(address);
            });
            
            item.on('mouseenter', function() {
                $(this).css('background-color', '#f0f0f0');
            });
            
            item.on('mouseleave', function() {
                $(this).css('background-color', 'white');
            });
            
            container.append(item);
        });
        
        container.show();
    }
    
    function hideAddressSuggestions() {
        $('#address-suggestions').hide();
    }
    
    // ====== ФУНКЦИИ ДЛЯ РАБОТЫ С КАРТОЙ ======
    
    function initYandexMap() {
        if (cdekMap) {
            return;
        }
        
        if (typeof ymaps === 'undefined') {
            setTimeout(initYandexMap, 1000);
            return;
        }
        
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) {
            setTimeout(initYandexMap, 500);
            return;
        }
        
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important;';
        
        var checkContainer = function() {
            if (mapContainer.offsetWidth > 0 && mapContainer.offsetHeight > 0) {
                try {
                    cdekMap = new ymaps.Map(mapContainer, {
                        center: [55.753994, 37.622093],
                        zoom: 10,
                        controls: ['zoomControl', 'searchControl']
                    });
                
                    if (cdekPoints && cdekPoints.length > 0) {
                        displayCdekPoints(cdekPoints);
                    }
                } catch (error) {
                    setTimeout(function() {
                        cdekMap = null;
                        initYandexMap();
                    }, 1000);
                }
            } else {
                setTimeout(checkContainer, 300);
            }
        };
        
        setTimeout(checkContainer, 200);
    }
    
    function geocodeAddress(address, callback) {
        if (typeof ymaps !== 'undefined') {
            ymaps.geocode(address, {
                results: 1
            }).then(function(res) {
                if (res.geoObjects.getLength() > 0) {
                    var firstGeoObject = res.geoObjects.get(0);
                    var coords = firstGeoObject.geometry.getCoordinates();
                    callback(coords);
                } else {
                    callback(null);
                }
            }).catch(function(error) {
                callback(null);
            });
        } else {
            callback(null);
        }
    }
    
    function calculateDistance(lat1, lon1, lat2, lon2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        var distance = R * c;
        return distance;
    }
    
    // ====== ФУНКЦИИ ДЛЯ РАБОТЫ С ПУНКТАМИ ВЫДАЧИ ======
    
    function searchCdekPoints(address) {
        var parsedAddress = parseAddress(address);
        
        // Если город изменился, сбрасываем выбранный пункт
        if (window.currentSearchCity && window.currentSearchCity !== parsedAddress.city) {
            clearSelectedPoint();
        }
        
        window.currentSearchCity = parsedAddress.city;
        window.currentSearchStreet = parsedAddress.street;
        
        if (typeof cdek_ajax === 'undefined') {
            return;
        }
        
        geocodeAddress(address, function(coords) {
            window.currentSearchCoordinates = coords;
            performCdekSearch();
        });
    }
    
    function performCdekSearch() {
        var currentTime = Date.now();
        if (cdekPointsCache && (currentTime - lastSearchTime) < cacheExpiry) {
            displayCdekPoints(cdekPointsCache);
            return;
        }
        
        if (isSearching) {
            return;
        }
        
        isSearching = true;
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'get_cdek_points',
                address: 'Россия',
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    cdekPointsCache = response.data;
                    lastSearchTime = currentTime;
                    displayCdekPoints(response.data);
                }
                isSearching = false;
            },
            error: function(xhr, status, error) {
                isSearching = false;
                
                if (cdekPointsCache) {
                    displayCdekPoints(cdekPointsCache);
                }
                
                if (xhr.status === 0 || status === 'error') {
                    setTimeout(function() {
                        if (!isSearching) {
                            performCdekSearch();
                        }
                    }, 5000);
                }
            }
        });
    }
    
    function displayCdekPoints(points) {
        cdekPoints = points;
        
        if (!cdekMap || typeof ymaps === 'undefined') {
            setTimeout(function() {
                displayCdekPoints(points);
            }, 1000);
            return;
        }
        
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            $('#cdek-points-count').text(`Пункты выдачи не найдены${cityInfo}`);
            return;
        }
        
        // Фильтруем пункты по городу
        var filteredPoints = points.filter(function(point) {
            // Убираем фильтрацию по типу - показываем все пункты выдачи
            // if (point.type !== 'PVZ' && point.type) {
            //     return false;
            // }
            
            if (window.currentSearchCity) {
                var pointCity = '';
                
                // Пытаемся получить город из разных полей
                if (point.location && point.location.city) {
                    pointCity = point.location.city.trim();
                }
                
                // Если не нашли в location.city, ищем в address
                if (!pointCity && point.location && point.location.address) {
                    var addressParts = point.location.address.split(',');
                    if (addressParts.length > 0) {
                        pointCity = addressParts[0].trim();
                    }
                }
                
                // Если не нашли в address, ищем в name
                if (!pointCity && point.name && point.name.includes(',')) {
                    var nameParts = point.name.split(',');
                    if (nameParts.length >= 2) {
                        pointCity = nameParts[1].trim();
                    }
                }
                
                // Если не нашли в name, ищем в полном адресе
                if (!pointCity && point.address_comment) {
                    var commentParts = point.address_comment.split(',');
                    if (commentParts.length > 0) {
                        pointCity = commentParts[0].trim();
                    }
                }
                
                if (pointCity) {
                    pointCity = pointCity.replace(/^(г\.?\s*|город\s+)/i, '').trim();
                }
                
                var searchCityLower = window.currentSearchCity.toLowerCase().trim();
                var pointCityLower = pointCity.toLowerCase().trim();
                
                // Более гибкое сравнение - проверяем вхождение
                if (pointCityLower && searchCityLower) {
                    if (pointCityLower !== searchCityLower && 
                        !pointCityLower.includes(searchCityLower) && 
                        !searchCityLower.includes(pointCityLower)) {
                        return false;
                    }
                }
            }
            
            return true;
        });
        
        // Сортируем по расстоянию
        if (window.currentSearchCoordinates && filteredPoints.length > 0) {
            filteredPoints.sort(function(a, b) {
                var distA = calculateDistance(
                    window.currentSearchCoordinates[0], 
                    window.currentSearchCoordinates[1],
                    a.location.latitude, 
                    a.location.longitude
                );
                var distB = calculateDistance(
                    window.currentSearchCoordinates[0], 
                    window.currentSearchCoordinates[1],
                    b.location.latitude, 
                    b.location.longitude
                );
                return distA - distB;
            });
        }
        
        var maxPoints = 380;
        var pointsToShow = filteredPoints.slice(0, maxPoints);
        
        var pointsInfo = '';
        if (filteredPoints.length > 0) {
            var locationInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            pointsInfo = `Найдено ${filteredPoints.length} пунктов выдачи${locationInfo}`;
            if (filteredPoints.length > maxPoints) {
                pointsInfo += ` (показано ${maxPoints} ближайших)`;
            }
        } else {
            var locationInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            pointsInfo = `Пункты выдачи не найдены${locationInfo}`;
        }
        $('#cdek-points-count').text(pointsInfo);
        
        var bounds = [];
        
        pointsToShow.forEach(function(point, index) {
            if (point.location && point.location.latitude && point.location.longitude) {
                var coords = [point.location.latitude, point.location.longitude];
                bounds.push(coords);
                
                var placemark = new ymaps.Placemark(coords, {
                    balloonContent: formatPointInfo(point),
                    hintContent: point.name
                }, {
                    preset: 'islands#redIcon'
                });
                
                placemark.events.add('click', function() {
                    selectCdekPoint(point);
                });
                
                cdekMap.geoObjects.add(placemark);
            }
        });
        
        // Подгоняем масштаб карты
        if (bounds.length > 0) {
            if (bounds.length === 1) {
                cdekMap.setCenter(bounds[0], 14);
            } else {
                var minLat = Math.min.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var maxLat = Math.max.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var minLon = Math.min.apply(null, bounds.map(function(coord) { return coord[1]; }));
                var maxLon = Math.max.apply(null, bounds.map(function(coord) { return coord[1]; }));
                
                var centerLat = (minLat + maxLat) / 2;
                var centerLon = (minLon + maxLon) / 2;
                
                var latDiff = maxLat - minLat;
                var lonDiff = maxLon - minLon;
                var maxDiff = Math.max(latDiff, lonDiff);
                
                var zoom = 12;
                if (maxDiff < 0.01) zoom = 15;
                else if (maxDiff < 0.05) zoom = 13;
                else if (maxDiff < 0.1) zoom = 12;
                else if (maxDiff < 0.5) zoom = 10;
                else zoom = 8;
                
                cdekMap.setCenter([centerLat, centerLon], zoom);
            }
        } else if (window.currentSearchCoordinates) {
            cdekMap.setCenter(window.currentSearchCoordinates, 12);
        }
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        if (cdekMap && point.location) {
            cdekMap.setCenter([point.location.latitude, point.location.longitude], 15);
        }
        
        // Сохраняем выбранный пункт
        if ($('#cdek-selected-point-code').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'cdek-selected-point-code',
                name: 'cdek_selected_point_code',
                value: point.code
            }).appendTo('form.checkout, form.woocommerce-checkout');
        } else {
            $('#cdek-selected-point-code').val(point.code);
        }
        
        if ($('#cdek-selected-point-data').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'cdek-selected-point-data',
                name: 'cdek_selected_point_data',
                value: JSON.stringify(point)
            }).appendTo('form.checkout, form.woocommerce-checkout');
        } else {
            $('#cdek-selected-point-data').val(JSON.stringify(point));
        }
        
        updateOrderSummary(point);
    }
    
    function clearSelectedPoint() {
        selectedPoint = null;
        $('#cdek-selected-point').hide();
        $('#cdek-point-info').html('');
        
        // Удаляем скрытые поля
        $('#cdek-selected-point-code').remove();
        $('#cdek-selected-point-data').remove();
        
        // Сбрасываем информацию о доставке
        resetCdekShippingToDefault();
    }
    
    function formatPointInfo(point) {
        var pointName = point.name || 'Пункт выдачи';
        if (pointName.includes(',')) {
            pointName = pointName.split(',').slice(1).join(',').trim();
        }
        
        var html = `<strong>${pointName}</strong><br>`;
        
        if (point.location && point.location.address_full) {
            html += `Адрес: ${point.location.address_full}<br>`;
        } else if (point.address) {
            html += `Адрес: ${point.address}<br>`;
        }
        
        if (point.phones && Array.isArray(point.phones) && point.phones.length > 0) {
            var phoneNumbers = point.phones.map(function(phone) {
                return phone.number || phone;
            }).join(', ');
            html += `Телефон: ${phoneNumbers}<br>`;
        } else if (point.phone) {
            html += `Телефон: ${point.phone}<br>`;
        }
        
        html += `Режим работы: ${formatWorkTime(point.work_time, point.work_time_list)}<br>`;
        
        if (point.code) {
            html += `Код: ${point.code}<br>`;
        }
        
        return html;
    }
    
    function formatWorkTime(workTime, workTimeList) {
        if (workTimeList && Array.isArray(workTimeList) && workTimeList.length > 0) {
            var days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            var schedule = '';
            
            workTimeList.forEach(function(time) {
                if (time.day !== undefined && time.time) {
                    schedule += days[time.day - 1] + ': ' + time.time + ' ';
                }
            });
            
            return schedule || 'Не указан';
        }
        
        if (workTime) {
            if (typeof workTime === 'string') {
                return workTime;
            }
        }
        
        return 'Не указан';
    }
    
    function updateOrderSummary(point) {
        showDeliveryCalculationLoader();
        
        calculateDeliveryCost(point, function(deliveryCost) {
            hideDeliveryCalculationLoader();
            
            // Ищем ВСЕ блоки доставки - используем несколько стратегий поиска
            var allShippingBlocks = $();
            
            // Стратегия 1: Прямой поиск в блоке доставки
            var shippingBlocks1 = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            console.log('Стратегия 1 - найдено блоков в shipping:', shippingBlocks1.length);
            
            // Стратегия 2: Поиск в обертке заказа
            var shippingBlocks2 = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
            console.log('Стратегия 2 - найдено блоков в order-summary:', shippingBlocks2.length);
            
            // Стратегия 3: Глобальный поиск по содержимому
            var shippingBlocks3 = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                var isShippingBlock = labelText.indexOf('СДЭК') !== -1 || 
                       labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                       labelText.indexOf('Махачкала') !== -1 ||
                       labelText.indexOf('Москва') !== -1 ||
                       labelText.indexOf('Санкт-Петербург') !== -1 ||
                       labelText.match(/^[А-Яа-я\s,\.\-]+$/) && labelText.includes(',');
                       
                if (isShippingBlock) {
                    console.log('Найден блок доставки по содержимому:', labelText);
                }
                return isShippingBlock;
            });
            console.log('Стратегия 3 - найдено блоков по содержимому:', shippingBlocks3.length);
            
            // Объединяем все найденные блоки
            allShippingBlocks = shippingBlocks1.add(shippingBlocks2).add(shippingBlocks3);
            
            // Убираем дубликаты
            allShippingBlocks = allShippingBlocks.filter(function(index, element) {
                return allShippingBlocks.index(element) === index;
            });
            
            console.log('Найдено блоков доставки для обновления:', allShippingBlocks.length);
            
            // Обновляем каждый найденный блок
            allShippingBlocks.each(function() {
                updateShippingBlock($(this), point, deliveryCost);
            });
            
            updateOrderTotal(deliveryCost);
        });
    }
    
    function updateShippingBlock(block, point, deliveryCost) {
        var pointName = point.name || 'Пункт выдачи';
        if (pointName.includes(',')) {
            pointName = pointName.split(',').slice(1).join(',').trim();
        }
        
        // Обновляем название с более понятной информацией
        var displayName = pointName;
        if (point.location && point.location.city) {
            displayName = point.location.city + ', ' + pointName.replace(point.location.city, '').replace(/^[,\s]+/, '');
        }
        
        // Получаем адрес
        var address = '';
        if (point.location && point.location.address_full) {
            address = point.location.address_full;
        } else if (point.location && point.location.address) {
            address = point.location.address;
        } else if (point.address) {
            address = point.address;
        }
        
        // Обновляем элементы блока
        var labelElement = block.find('.wc-block-components-totals-item__label');
        var valueElement = block.find('.wc-block-components-totals-item__value');
        var descriptionElement = block.find('.wc-block-components-totals-item__description');
        
        console.log('Обновляем блок доставки:', {
            oldLabel: labelElement.text(),
            newLabel: displayName,
            cost: deliveryCost,
            address: address
        });
        
        // Обновляем название
        labelElement.text(displayName);
        
        // Обновляем стоимость
        valueElement.text(deliveryCost + ' руб.');
        
        // Обновляем или создаем описание с адресом
        if (address) {
            if (descriptionElement.length === 0) {
                descriptionElement = $('<div class="wc-block-components-totals-item__description"></div>');
                block.append(descriptionElement);
            } else if (descriptionElement.length > 1) {
                // Если есть дубликаты description, удаляем лишние
                descriptionElement.slice(1).remove();
                descriptionElement = descriptionElement.first();
            }
            descriptionElement.html('<small style="color: #666;">' + address + '</small>');
        }
        
        // Сохраняем стоимость доставки для правильного пересчета
        window.currentDeliveryCost = deliveryCost;
        
        // Принудительно обновляем событие для WooCommerce
        $(document.body).trigger('updated_checkout');
        $(document.body).trigger('updated_cart_totals');
        
        // Дополнительно ищем блоки через короткий интервал (DOM может измениться)
        setTimeout(function() {
            updateAllCdekShippingBlocks(displayName, deliveryCost, address);
        }, 100);
        
        setTimeout(function() {
            updateAllCdekShippingBlocks(displayName, deliveryCost, address);
        }, 500);
    }
    
    // Функция для принудительного обновления всех блоков СДЭК
    function updateAllCdekShippingBlocks(displayName, deliveryCost, address) {
        // Ищем все возможные блоки доставки
        var allBlocks = $('.wc-block-components-totals-item, .wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
        
        allBlocks.each(function() {
            var $block = $(this);
            var labelText = $block.find('.wc-block-components-totals-item__label').text();
            
            // Проверяем является ли это блоком СДЭК доставки
            var isCdekBlock = labelText.indexOf('СДЭК') !== -1 || 
                             labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                             labelText.indexOf('Махачкала') !== -1 ||
                             labelText.indexOf('Москва') !== -1 ||
                             labelText.indexOf('Санкт-Петербург') !== -1 ||
                             (labelText.match(/^[А-Яа-я\s,\.\-]+$/) && labelText.includes(','));
            
            if (isCdekBlock) {
                console.log('🔄 Принудительно обновляем блок:', labelText);
                
                // Обновляем все элементы блока
                $block.find('.wc-block-components-totals-item__label').text(displayName);
                $block.find('.wc-block-components-totals-item__value').text(deliveryCost + ' руб.');
                
                if (address) {
                    var desc = $block.find('.wc-block-components-totals-item__description');
                    if (desc.length === 0) {
                        desc = $('<div class="wc-block-components-totals-item__description"></div>');
                        $block.append(desc);
                    } else if (desc.length > 1) {
                        // Если есть дубликаты description, удаляем лишние
                        desc.slice(1).remove();
                        desc = desc.first();
                    }
                    desc.html('<small style="color: #666;">' + address + '</small>');
                }
            }
        });
    }
    
    function showDeliveryCalculationLoader() {
        var shippingBlocks = $('.wc-block-components-totals-item, .wc-block-components-totals-shipping .wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('СДЭК') !== -1 || labelText.indexOf('Выберите пункт выдачи') !== -1;
        });
        
        shippingBlocks.find('.wc-block-components-totals-item__value').html('<span style="color: #666;">Расчет...</span>');
    }
    
    function hideDeliveryCalculationLoader() {
        // Индикатор загрузки будет скрыт при обновлении стоимости
    }
    
    function updateOrderTotal(deliveryCost) {
        var totalBlock = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
        });
        
        if (totalBlock.length > 0) {
            // Убираем дубликаты - используем только первый элемент
            totalBlock = totalBlock.first();
            
            var subtotalBlock = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
            });
            
            if (subtotalBlock.length > 0) {
                var subtotalText = subtotalBlock.find('.wc-block-components-totals-item__value').text();
                var subtotal = parseInt(subtotalText.replace(/[^\d]/g, ''));
                
                var taxBlock = $('.wc-block-components-totals-taxes .wc-block-components-totals-item__value');
                var tax = 0;
                if (taxBlock.length > 0) {
                    var taxText = taxBlock.text();
                    tax = parseInt(taxText.replace(/[^\d]/g, '')) || 0;
                }
                
                var newTotal = subtotal + deliveryCost + tax;
                
                var totalValueElement = totalBlock.find('.wc-block-components-totals-item__value');
                
                // Проверяем, не создаем ли мы дубликат значения
                var currentText = totalValueElement.text().trim();
                var newText = newTotal + ' руб.';
                
                // Обновляем только если значение действительно изменилось
                if (currentText !== newText) {
                    totalValueElement.text(newText);
                    console.log('💰 Обновлена итоговая сумма:', newText);
                }
            }
        }
    }
    
    // ====== ФУНКЦИИ УПРАВЛЕНИЯ ИНТЕРФЕЙСОМ ======
    
    function removeDuplicateTotalElements() {
        // Удаляем дублированные элементы итоговой суммы
        var totalBlocks = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
        });
        
        if (totalBlocks.length > 1) {
            console.log('🔍 Найдено дублированных элементов итоговой суммы:', totalBlocks.length);
            // Оставляем только первый элемент, остальные удаляем
            totalBlocks.slice(1).remove();
            console.log('✅ Дублированные элементы итоговой суммы удалены');
        }
        
        // Также проверяем дублированные wrapper элементы
        var wrappers = $('.wc-block-components-totals-wrapper');
        if (wrappers.length > 1) {
            console.log('🔍 Найдено дублированных wrapper элементов:', wrappers.length);
            // Проверяем, есть ли в них одинаковое содержимое
            var firstWrapper = wrappers.first();
            var firstContent = firstWrapper.find('.wc-block-components-totals-item__label:contains("Итого")').length;
            
            wrappers.slice(1).each(function() {
                var wrapper = $(this);
                var content = wrapper.find('.wc-block-components-totals-item__label:contains("Итого")').length;
                if (content > 0 && firstContent > 0) {
                    console.log('🗑️ Удаляем дублированный wrapper с итоговой суммой');
                    wrapper.remove();
                }
            });
        }
        
        // Исправляем дублированную итоговую сумму в существующих элементах
        fixDuplicatedTotalValue();
    }
    
    function fixDuplicatedTotalValue() {
        var totalBlocks = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
        });
        
        totalBlocks.each(function() {
            var $block = $(this);
            var valueElement = $block.find('.wc-block-components-totals-item__value');
            var currentText = valueElement.text().trim();
            
            // Извлекаем число из текста
            var totalMatch = currentText.match(/(\d+)/);
            if (totalMatch) {
                var totalNumber = totalMatch[1];
                
                // Проверяем на дублирование (например, 874268 -> 1268)
                if (totalNumber.length >= 6) {
                    var found = false;
                    
                    // Попробуем разделить на 3+остальное
                    var splits = [
                        {prefix: totalNumber.substring(0, 3), suffix: totalNumber.substring(3)},
                        {prefix: totalNumber.substring(0, 4), suffix: totalNumber.substring(4)}
                    ];
                    
                    for (var i = 0; i < splits.length && !found; i++) {
                        var itemPrice = parseInt(splits[i].prefix);
                        var possibleTotal = parseInt(splits[i].suffix);
                        
                        // Проверяем логичность разделения
                        if (itemPrice >= 100 && itemPrice <= 9999 && 
                            possibleTotal >= 200 && possibleTotal <= 99999 && 
                            itemPrice < possibleTotal) {
                            
                            var newText = currentText.replace(totalNumber, possibleTotal);
                            valueElement.text(newText);
                            console.log('🔧 Исправлена дублированная итоговая сумма:', currentText, '->', newText);
                            found = true;
                        }
                    }
                }
            }
        });
    }
    
    function hideCdekShippingBlock() {
        if (window.lastHideCall && (Date.now() - window.lastHideCall) < 1000) {
            return;
        }
        window.lastHideCall = Date.now();
        
        var cdekInputs = $('input[value*="cdek_delivery"]');
        
        cdekInputs.each(function(index, element) {
            var $this = $(this);
            var radioControl = $this.closest('.wc-block-components-radio-control');
            var package = $this.closest('.wc-block-components-shipping-rates-control__package');
            var control = $this.closest('.wc-block-components-shipping-rates-control');
            var label = $this.closest('label');
            
            if (radioControl.length) radioControl.hide();
            if (package.length) package.hide();
            if (control.length) control.hide();
            if (label.length) label.hide();
            
            $this.css({
                'display': 'none !important',
                'visibility': 'hidden !important',
                'position': 'absolute',
                'left': '-9999px'
            });
        });
    }
    
    function hideCdekMap() {
        $('#cdek-map-container').hide();
    }
    
    function resetCdekShippingToDefault() {
        $('.wc-block-components-totals-item').each(function() {
            var $item = $(this);
            var labelElement = $item.find('.wc-block-components-totals-item__label');
            var labelText = labelElement.text();
            
            if (labelText.indexOf('СДЭК') !== -1 || 
                labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                labelText.indexOf('Москва') !== -1 ||
                labelText.indexOf('Санкт-Петербург') !== -1 ||
                labelText.includes('пункт выдачи')) {
                
                labelElement.text('Выберите пункт выдачи');
                
                var valueElement = $item.find('.wc-block-components-totals-item__value');
                valueElement.text('');
                
                var descriptionElement = $item.find('.wc-block-components-totals-item__description');
                descriptionElement.html('');
            }
        });
        
        // Сбрасываем сохраненную стоимость
        window.currentDeliveryCost = 0;
        
        // Пересчитываем общую сумму без доставки
        updateOrderTotal(0);
    }
    
    function initCdekDelivery() {
        if (isInitialized) {
            return;
        }
        
        // Удаляем дублированные элементы итоговой суммы (особенно важно для мобильных устройств)
        removeDuplicateTotalElements();
        
        hideCdekShippingBlock();
        
        if ($('#cdek-map-container').length === 0) {
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px; display: block !important;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
                    <div id="cdek-points-info" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                        <strong>Информация:</strong>
                        <div id="cdek-points-count">Введите город в поле адреса выше для поиска пунктов выдачи</div>
                    </div>
                    <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
                        <strong>Выбранный пункт:</strong>
                        <div id="cdek-point-info"></div>
                    </div>
                    <div id="cdek-map" style="width: 100%; height: 450px; border: 1px solid #ddd; border-radius: 6px; display: block !important;"></div>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        💡 Введите город в поле адреса выше, затем выберите пункт выдачи на карте
                    </p>
                </div>
            `;
            
            var mapBlock = $('.wp-block-cdek-checkout-map-block');
            var insertTarget = null;
            
            if (mapBlock.length > 0) {
                insertTarget = mapBlock;
                insertTarget.html(mapHtml);
            } else {
                var addressForm = $('.wc-block-components-address-form');
                var shippingBlock = $('.wp-block-woocommerce-checkout-shipping-address-block');
                var shippingControl = $('.wc-block-components-shipping-rates-control');
                
                insertTarget = addressForm.length ? addressForm : 
                    shippingBlock.length ? shippingBlock :
                    shippingControl.first();
                    
                if (insertTarget.length > 0) {
                    insertTarget.after(mapHtml);
                }
            }
        }
        
        $('#cdek-map-container').show();
        
        setTimeout(function() {
            initYandexMap();
        }, 500);
        
        setTimeout(function() {
            initAddressAutocomplete();
        }, 1000);
        
        var currentAddress = $('#shipping-address_1').val();
        
        if (currentAddress) {
            var city = currentAddress.split(',')[0].trim();
            
            if (city.length > 2) {
                setTimeout(function() {
                    searchCdekPoints(city);
                }, 1000);
            }
        }
        
        isInitialized = true;
        
        // Дополнительная проверка на дубликаты через 2 секунды (для медленных устройств)
        setTimeout(function() {
            removeDuplicateTotalElements();
            fixDuplicatedTotalValue();
        }, 2000);
        
        console.log('✅ СДЭК доставка инициализирована');
    }
    
    // ====== ФУНКЦИИ ДЛЯ СКРЫТИЯ ПОЛЕЙ ======
    
    function hideUnnecessaryFields() {
        // Скрываем поля города, области и индекса
        var fieldsToHide = [
            '#shipping-city', '#shipping-state', '#shipping-postcode',
            '#billing-city', '#billing-state', '#billing-postcode',
            'input[name="shipping_city"]', 'input[name="shipping_state"]', 'input[name="shipping_postcode"]',
            'input[name="billing_city"]', 'input[name="billing_state"]', 'input[name="billing_postcode"]'
        ];
        
        fieldsToHide.forEach(function(selector) {
            $(selector).hide().closest('.wc-block-components-text-input').hide();
        });
        
        // Скрываем контейнеры по классам
        $('.wc-block-components-address-form__city, .wc-block-components-address-form__state, .wc-block-components-address-form__postcode').hide();
        
        // Скрываем по содержимому текста
        $('label').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.includes('город') && !text.includes('адрес') || 
                text.includes('область') || 
                text.includes('район') || 
                text.includes('индекс') || 
                text.includes('почтовый')) {
                $(this).closest('.wc-block-components-text-input').hide();
            }
        });
    }
    
    // ====== ИНИЦИАЛИЗАЦИЯ И ОБРАБОТЧИКИ СОБЫТИЙ ======
    
    // Инициализация при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        var selectedValue = $(this).val();
        var isChecked = $(this).is(':checked');
        
        console.log('🔄 Переключение способа доставки:', selectedValue, 'Выбран:', isChecked);
        
        if (selectedValue && selectedValue.indexOf('cdek_delivery') !== -1 && isChecked) {
            console.log('✅ Выбрана доставка CDEK - показываем карту');
            setTimeout(function() {
                initCdekDelivery();
            }, 100);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1 && isChecked) {
            var mapContainer = $('#cdek-map-container');
            if (mapContainer.length > 0) {
                console.log('🙈 Выбран другой способ доставки - скрываем карту CDEK');
                hideCdekMap();
                resetCdekShippingToDefault();
            }
        }
    });
    
    // Обработчик для блоков WooCommerce (новая система оформления)
    $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
        var titleElement = $(this).find('.wc-block-checkout__shipping-method-option-title');
        var title = titleElement.text().trim();
        var isSelected = $(this).hasClass('wc-block-checkout__shipping-method-option--selected');
        
        console.log('🔄 Клик по способу доставки в блоке:', title, 'Выбран:', isSelected);
        
        setTimeout(function() {
            var mapContainer = $('#cdek-map-container');
            
            if (title.includes('СДЭК') || title.includes('Выберите пункт выдачи') || title.includes('Доставка')) {
                console.log('✅ Выбрана доставка CDEK через блок - показываем карту');
                if (mapContainer.length > 0) {
                    mapContainer.show();
                }
                initCdekDelivery();
            } else {
                console.log('🙈 Выбран другой способ доставки через блок - скрываем карту CDEK');
                if (mapContainer.length > 0) {
                    hideCdekMap();
                    resetCdekShippingToDefault();
                }
            }
        }, 100);
    });
    
    // Дополнительная инициализация для блоков WooCommerce
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        setTimeout(function() {
            initCdekDelivery();
        }, 200);
    });
    
    // Отслеживание изменений в поле адреса
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        var city = address.split(',')[0].trim();
        
        if (city.length > 2) {
            searchTimeout = setTimeout(function() {
                if (!isSearching) {
                    searchCdekPoints(city);
                }
            }, 500);
        }
    });
    
    // Наблюдатель за изменениями DOM
    var observer = new MutationObserver(function(mutations) {
        if (observerActive) return;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Скрываем ненужные поля при любых изменениях DOM
                hideUnnecessaryFields();
                
                $('.wc-block-components-totals-item__label').each(function() {
                    var text = $(this).text();
                    if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                        $(this).text('Выберите пункт выдачи');
                    }
                });
                
                var cdekMethod = $('input[value*="cdek_delivery"]');
                
                if (cdekMethod.length > 0 && !isInitialized) {
                    observerActive = true;
                    
                    hideCdekShippingBlock();
                    
                    var cdekSelected = $('input[value*="cdek_delivery"]:checked');
                    
                    if (cdekSelected.length > 0 && $('#cdek-map-container').length === 0) {
                        setTimeout(function() {
                            initCdekDelivery();
                            observerActive = false;
                        }, 500);
                    } else if ($('#cdek-map-container').length === 0) {
                        setTimeout(function() {
                            initCdekDelivery();
                            observerActive = false;
                        }, 500);
                    } else {
                        observerActive = false;
                    }
                }
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Начальная инициализация
    setTimeout(function() {
        hideUnnecessaryFields(); // Скрываем поля сразу
        
        $('.wc-block-components-totals-item__label').each(function() {
            var text = $(this).text();
            if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                $(this).text('Выберите пункт выдачи');
            }
        });
    }, 100);
    
    setTimeout(function() {
        hideUnnecessaryFields(); // Повторно скрываем поля
        initAddressAutocomplete();
        
        var cdekMethod = $('input[value*="cdek_delivery"]');
        
        if (cdekMethod.length > 0) {
            hideCdekShippingBlock();
            initCdekDelivery();
        }
    }, 2000);
    
    setTimeout(function() {
        hideUnnecessaryFields(); // Еще раз скрываем поля
        
        if ($('#address-select').length === 0 && $('#address-suggestions').length === 0) {
            initAddressAutocomplete();
        }
    }, 5000);
    
    setTimeout(function() {
        hideUnnecessaryFields(); // Финальная проверка
        
        if ($('input[value*="cdek_delivery"]').length > 0 && $('#cdek-map-container').length === 0 && !isInitialized) {
            initCdekDelivery();
        }
        
        // Финальная очистка дубликатов
        removeDuplicateTotalElements();
        fixDuplicatedTotalValue();
    }, 4000);
    
    // Обработчик для автоматического удаления дубликатов при изменении DOM
    $(document).on('DOMNodeInserted', function(e) {
        var target = $(e.target);
        if (target.hasClass('wc-block-components-totals-wrapper') || 
            target.find('.wc-block-components-totals-wrapper').length > 0) {
            setTimeout(function() {
                removeDuplicateTotalElements();
                fixDuplicatedTotalValue();
            }, 100);
        }
    });
    
    // Периодическая проверка и исправление дублированных значений каждые 1 секунду
    setInterval(function() {
        fixDuplicatedTotalValue();
    }, 1000);
    
    // Дополнительная агрессивная проверка каждые 500мс
    setInterval(function() {
        var totalElements = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value');
        totalElements.each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            var match = text.match(/(\d+)/);
            if (match && match[1].length >= 6) {
                var num = match[1];
                
                // Универсальная логика исправления дублирования
                // Попробуем разные варианты разделения
                var corrected = null;
                
                // Проверяем 3+остальное (например: 873+1196 = 8731196)
                if (num.length >= 6) {
                    var firstPart = num.substring(0, 3);
                    var secondPart = num.substring(3);
                    
                    // Проверяем логичность (первая часть должна быть меньше второй)
                    if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                        corrected = secondPart;
                    }
                }
                
                // Если не подошло, проверяем 4+остальное
                if (!corrected && num.length >= 7) {
                    var firstPart = num.substring(0, 4);
                    var secondPart = num.substring(4);
                    
                    if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                        corrected = secondPart;
                    }
                }
                
                if (corrected) {
                    var newText = text.replace(num, corrected);
                    $el.text(newText);
                    console.log('🚨 ПРИНУДИТЕЛЬНО исправлена сумма:', text, '->', newText);
                }
            }
        });
    }, 500);
    
    // Перехватываем все попытки изменить DOM
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    setTimeout(function() {
                        var totalElements = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value');
                        totalElements.each(function() {
                            var $el = $(this);
                            var text = $el.text().trim();
                            var match = text.match(/(\d+)/);
                            if (match && match[1].length >= 6) {
                                var num = match[1];
                                var corrected = null;
                                
                                // Проверяем 3+остальное
                                if (num.length >= 6) {
                                    var firstPart = num.substring(0, 3);
                                    var secondPart = num.substring(3);
                                    
                                    if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                        corrected = secondPart;
                                    }
                                }
                                
                                // Проверяем 4+остальное
                                if (!corrected && num.length >= 7) {
                                    var firstPart = num.substring(0, 4);
                                    var secondPart = num.substring(4);
                                    
                                    if (parseInt(firstPart) >= 100 && parseInt(firstPart) < parseInt(secondPart)) {
                                        corrected = secondPart;
                                    }
                                }
                                
                                if (corrected) {
                                    var newText = text.replace(num, corrected);
                                    $el.text(newText);
                                    console.log('🔥 ПЕРЕХВАЧЕНО через MutationObserver:', text, '->', newText);
                                }
                            }
                        });
                    }, 10);
                }
            });
        });
        
        // Наблюдаем за изменениями во всем документе
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }
});