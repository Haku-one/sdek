/**
 * СДЭК Доставка - Оптимизированная версия
 * Исправлены проблемы производительности и дублирования цен
 * Добавлен умный поиск и современные оптимизации
 */

// ========== УТИЛИТЫ ДЛЯ ОПТИМИЗАЦИИ ==========

// Мемоизация с TTL
class Memoizer {
    constructor(ttl = 300000) { // 5 минут по умолчанию
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    memoize(fn) {
        return (...args) => {
            const key = JSON.stringify(args);
            const cached = this.cache.get(key);
            
            if (cached && Date.now() - cached.timestamp < this.ttl) {
                return cached.value;
            }
            
            const result = fn.apply(this, args);
            this.cache.set(key, { value: result, timestamp: Date.now() });
            
            // Очистка старых записей
            if (this.cache.size > 100) {
                const oldestKey = this.cache.keys().next().value;
                this.cache.delete(oldestKey);
            }
            
            return result;
        };
    }
    
    clear() {
        this.cache.clear();
    }
}

// Умный дебаунсер с приоритетами
class SmartDebouncer {
    constructor() {
        this.timers = new Map();
        this.priorities = new Map();
    }
    
    debounce(key, fn, delay, priority = 0) {
        // Высокий приоритет выполняется сразу
        if (priority > 5) {
            this.cancel(key);
            return fn();
        }
        
        this.cancel(key);
        
        const timer = setTimeout(() => {
            fn();
            this.timers.delete(key);
            this.priorities.delete(key);
        }, delay);
        
        this.timers.set(key, timer);
        this.priorities.set(key, priority);
    }
    
    cancel(key) {
        if (this.timers.has(key)) {
            clearTimeout(this.timers.get(key));
            this.timers.delete(key);
            this.priorities.delete(key);
        }
    }
}

// Батчинг DOM операций
class DOMBatcher {
    constructor() {
        this.operations = [];
        this.scheduled = false;
    }
    
    add(operation) {
        this.operations.push(operation);
        if (!this.scheduled) {
            this.scheduled = true;
            requestAnimationFrame(() => this.flush());
        }
    }
    
    flush() {
        // Выполняем все операции за один раз
        this.operations.forEach(op => {
            try {
                op();
            } catch (error) {
                console.error('DOM operation error:', error);
            }
        });
        this.operations = [];
        this.scheduled = false;
    }
}

// Исправление дублированных цен - КРИТИЧЕСКИ ВАЖНО!
class PriceFormatter {
    static fixDuplicatedPrice(priceText) {
        if (!priceText || typeof priceText !== 'string') {
            return priceText;
        }
        
        // Извлекаем все числа из текста
        const numbers = priceText.match(/\d+/g);
        if (!numbers || numbers.length === 0) {
            return priceText;
        }
        
        const mainNumber = numbers[0];
        
        // Проверяем на дублирование типа "6020602" -> "20602"
        if (mainNumber.length >= 6) {
            // Анализируем паттерны дублирования
            const patterns = [
                // Паттерн: ABC + DEFGH = ABCDEFGH (602 + 20602 = 6020602)
                { prefixLen: 3, check: (prefix, suffix) => parseInt(prefix) < parseInt(suffix) && parseInt(prefix) >= 100 },
                // Паттерн: ABCD + EFGH = ABCDEFGH  
                { prefixLen: 4, check: (prefix, suffix) => parseInt(prefix) < parseInt(suffix) && parseInt(prefix) >= 1000 },
                // Паттерн полного дублирования: ABCABC -> ABC
                { prefixLen: Math.floor(mainNumber.length / 2), check: (prefix, suffix) => prefix === suffix }
            ];
            
            for (const pattern of patterns) {
                if (mainNumber.length >= pattern.prefixLen * 2) {
                    const prefix = mainNumber.substring(0, pattern.prefixLen);
                    const suffix = mainNumber.substring(pattern.prefixLen);
                    
                    if (pattern.check(prefix, suffix)) {
                        const correctedNumber = pattern.prefixLen === Math.floor(mainNumber.length / 2) ? prefix : suffix;
                        const correctedText = priceText.replace(mainNumber, correctedNumber);
                        
                        console.log(`🔧 Исправлена дублированная цена: ${priceText} -> ${correctedText}`);
                        return correctedText;
                    }
                }
            }
        }
        
        return priceText;
    }
    
    static extractCleanPrice(priceText) {
        const fixed = this.fixDuplicatedPrice(priceText);
        const match = fixed.match(/(\d+(?:\.\d+)?)/);
        return match ? parseFloat(match[1]) : 0;
    }
}

// ========== УМНЫЙ ПОИСК АДРЕСОВ ==========

class SmartAddressSearch {
    constructor() {
        this.cache = new Map();
        this.debouncer = new SmartDebouncer();
        this.userLocation = null;
        this.popularCities = [
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 
            'Казань', 'Нижний Новгород', 'Челябинск', 'Самара', 'Омск',
            'Ростов-на-Дону', 'Уфа', 'Красноярск', 'Воронеж', 'Пермь',
            'Волгоград', 'Краснодар', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск'
        ];
        
        this.initUserLocation();
    }
    
    async initUserLocation() {
        try {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                    },
                    () => {
                        // Fallback: определяем город по IP
                        this.getCityByIP();
                    },
                    { timeout: 5000, maximumAge: 300000 }
                );
            }
        } catch (error) {
            console.log('Геолокация недоступна');
        }
    }
    
    async getCityByIP() {
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            
            if (data.city && data.latitude && data.longitude) {
                this.userLocation = {
                    lat: data.latitude,
                    lng: data.longitude,
                    city: data.city
                };
            }
        } catch (error) {
            console.log('Не удалось определить местоположение по IP');
        }
    }
    
    search(query, callback) {
        this.debouncer.debounce('address-search', () => {
            this.performSearch(query, callback);
        }, 300);
    }
    
    performSearch(query, callback) {
        if (!query || query.length < 2) {
            callback([]);
            return;
        }
        
        const cacheKey = query.toLowerCase();
        if (this.cache.has(cacheKey)) {
            callback(this.cache.get(cacheKey));
            return;
        }
        
        const results = this.searchInCities(query);
        this.cache.set(cacheKey, results);
        callback(results);
    }
    
    searchInCities(query) {
        const queryLower = query.toLowerCase().trim();
        const results = [];
        
        // Поиск в популярных городах
        this.popularCities.forEach(city => {
            const cityLower = city.toLowerCase();
            let score = 0;
            
            // Точное совпадение
            if (cityLower === queryLower) {
                score = 1000;
            }
            // Начинается с запроса
            else if (cityLower.startsWith(queryLower)) {
                score = 500;
            }
            // Содержит запрос
            else if (cityLower.includes(queryLower)) {
                score = 200;
            }
            // Нечеткое совпадение (для опечаток)
            else {
                const similarity = this.calculateSimilarity(queryLower, cityLower);
                if (similarity > 0.6) {
                    score = similarity * 100;
                }
            }
            
            if (score > 0) {
                // Бонус за популярность
                const popularityBonus = (this.popularCities.length - this.popularCities.indexOf(city)) * 10;
                score += popularityBonus;
                
                // Бонус за близость к пользователю (если известно местоположение)
                if (this.userLocation && this.userLocation.city === city) {
                    score += 200;
                }
                
                results.push({
                    city: city,
                    display: city,
                    score: score,
                    type: 'city'
                });
            }
        });
        
        // Сортируем по релевантности
        results.sort((a, b) => b.score - a.score);
        
        return results.slice(0, 10);
    }
    
    calculateSimilarity(str1, str2) {
        const maxLength = Math.max(str1.length, str2.length);
        if (maxLength === 0) return 1;
        
        const distance = this.levenshteinDistance(str1, str2);
        return (maxLength - distance) / maxLength;
    }
    
    levenshteinDistance(str1, str2) {
        const matrix = [];
        
        for (let i = 0; i <= str2.length; i++) {
            matrix[i] = [i];
        }
        
        for (let j = 0; j <= str1.length; j++) {
            matrix[0][j] = j;
        }
        
        for (let i = 1; i <= str2.length; i++) {
            for (let j = 1; j <= str1.length; j++) {
                if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        
        return matrix[str2.length][str1.length];
    }
}

// ========== ОСНОВНОЙ КОД СДЭК ==========

jQuery(document).ready(function($) {
    // Глобальные переменные
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false;
    
    // Инициализируем утилиты оптимизации
    const memoizer = new Memoizer();
    const debouncer = new SmartDebouncer();
    const domBatcher = new DOMBatcher();
    const addressSearch = new SmartAddressSearch();
    
    // Мемоизированные функции
    const memoizedCalculateDeliveryCost = memoizer.memoize(calculateDeliveryCost);
    const memoizedGeocodeAddress = memoizer.memoize(geocodeAddress);
    
    // ========== КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ ДУБЛИРОВАННЫХ ЦЕН ==========
    
    // Перехватываем все методы изменения DOM для цен
    function interceptPriceUpdates() {
        // Перехватываем jQuery.text()
        if (typeof $ !== 'undefined' && $.fn.text) {
            var originalText = $.fn.text;
            $.fn.text = function(value) {
                if (arguments.length > 0 && typeof value === 'string') {
                    if (this.hasClass('wc-block-components-totals-item__value') || 
                        this.hasClass('wc-block-formatted-money-amount')) {
                        value = PriceFormatter.fixDuplicatedPrice(value);
                    }
                }
                return originalText.apply(this, arguments.length > 0 ? [value] : []);
            };
        }
        
        // Перехватываем нативные DOM методы
        if (typeof HTMLElement !== 'undefined') {
            const originalTextContentDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'textContent') || 
                                                 Object.getOwnPropertyDescriptor(Element.prototype, 'textContent');
            
            if (originalTextContentDescriptor && originalTextContentDescriptor.set) {
                Object.defineProperty(HTMLElement.prototype, 'textContent', {
                    set: function(value) {
                        if (typeof value === 'string' && 
                            (this.classList.contains('wc-block-components-totals-item__value') ||
                             this.classList.contains('wc-block-formatted-money-amount'))) {
                            value = PriceFormatter.fixDuplicatedPrice(value);
                        }
                        originalTextContentDescriptor.set.call(this, value);
                    },
                    get: originalTextContentDescriptor.get
                });
            }
        }
    }
    
    // Функция для исправления существующих дублированных цен
    function fixExistingDuplicatedPrices() {
        domBatcher.add(() => {
            $('.wc-block-components-totals-item__value, .wc-block-formatted-money-amount').each(function() {
                const $element = $(this);
                const currentText = $element.text().trim();
                const fixedText = PriceFormatter.fixDuplicatedPrice(currentText);
                
                if (currentText !== fixedText) {
                    console.log(`🔧 Исправляем цену: ${currentText} -> ${fixedText}`);
                    $element.text(fixedText);
                }
            });
        });
    }
    
    // Периодическая проверка и исправление цен
    function startPriceMonitoring() {
        setInterval(() => {
            fixExistingDuplicatedPrices();
        }, 1000);
        
        // Наблюдатель за изменениями DOM
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver((mutations) => {
                let shouldCheck = false;
                
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        const target = mutation.target;
                        if (target.classList && 
                            (target.classList.contains('wc-block-components-totals-item__value') ||
                             target.classList.contains('wc-block-formatted-money-amount'))) {
                            shouldCheck = true;
                        }
                    }
                });
                
                if (shouldCheck) {
                    debouncer.debounce('price-fix', () => {
                        fixExistingDuplicatedPrices();
                    }, 100, 7); // Высокий приоритет
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ГАБАРИТАМИ ТОВАРОВ ==========
    
    function getCartDataForCalculation() {
        var cartWeight = 0;
        var cartValue = 0;
        var totalVolume = 0;
        var maxLength = 0, maxWidth = 0, maxHeight = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        var packagesCount = 1;
        
        console.log('Получение данных корзины для расчета...');
        
        // Получаем данные из WC блоков товаров с исправлением цен
        var processedItems = new Set();
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            
            var itemName = $item.find('.wc-block-components-product-name').text().trim();
            var itemId = itemName + '_' + $item.index();
            
            if (processedItems.has(itemId)) {
                return;
            }
            processedItems.add(itemId);
            
            // Получаем количество товара
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = parseInt(quantityElement.text()) || 1;
            
            console.log('Обработка товара из WC блока, количество:', quantity);
            
            // Ищем габариты
            var dimensionsElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                var labelText = siblingLabel.text();
                return labelText.indexOf('Габариты') !== -1 || labelText.indexOf('Размеры') !== -1;
            });
            
            if (dimensionsElement.length > 0) {
                var dimensionsText = dimensionsElement.text().trim();
                console.log('Найдены габариты в блоке товара:', dimensionsText);
                
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
            
            // Получаем вес товара
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
                    
                    if (weightText.includes('кг')) {
                        weight = weight * 1000;
                    }
                    
                    cartWeight += weight * quantity;
                    console.log('✅ Найден вес из WC блока товара:', weight, 'г, количество:', quantity);
                }
            }
            
            // Получаем цену товара с исправлением дублирования
            var totalPriceElement = $item.find('.wc-block-components-order-summary-item__total-price .wc-block-components-product-price__value');
            
            if (totalPriceElement.length > 0) {
                var totalPriceText = totalPriceElement.text().trim();
                console.log('Найдена итоговая цена товара:', totalPriceText);
                
                var totalPrice = PriceFormatter.extractCleanPrice(totalPriceText);
                cartValue += totalPrice;
                console.log('✅ Используем итоговую цену товара:', totalPrice, 'руб. (с учетом количества', quantity + ')');
            }
        });
        
        // Получаем общую стоимость заказа с исправлением дублирования
        var totalOrderElement = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value');
        var orderTotalFromFooter = 0;
        
        if (totalOrderElement.length > 0) {
            var totalText = totalOrderElement.first().text().trim();
            console.log('Найдена итоговая сумма заказа:', totalText);
            
            orderTotalFromFooter = PriceFormatter.extractCleanPrice(totalText);
            console.log('Извлечена итоговая сумма:', orderTotalFromFooter);
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
            
            if (totalItems <= 2) {
                dimensions = {
                    length: Math.ceil(maxLength * 1.05),
                    width: Math.ceil(maxWidth * 1.05),
                    height: Math.ceil(maxHeight * 1.05)
                };
            } else {
                var volumeRatio = Math.pow(totalVolume / (maxLength * maxWidth * maxHeight), 1/3);
                
                dimensions = {
                    length: Math.ceil(maxLength * Math.max(volumeRatio, 1) * 1.1),
                    width: Math.ceil(maxWidth * Math.max(volumeRatio, 1) * 1.1),
                    height: Math.ceil(maxHeight * Math.max(volumeRatio, 1) * 1.1)
                };
            }
            
            dimensions.length = Math.max(10, Math.min(dimensions.length, 150));
            dimensions.width = Math.max(10, Math.min(dimensions.width, 150));
            dimensions.height = Math.max(5, Math.min(dimensions.height, 150));
            
            // Проверяем объем упаковки (лимит СДЭК = 300 см)
            var volume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
            if (volume > 300) {
                console.log('⚠️ Объем упаковки превышает лимит СДЭК:', volume, 'см > 300 см. Разделяем на несколько коробок.');
                
                packagesCount = Math.ceil(volume / 290);
                var itemsPerPackage = Math.ceil(totalItems / packagesCount);
                
                var volumePerPackage = totalVolume / packagesCount;
                var volumeRatio = Math.pow(volumePerPackage / (maxLength * maxWidth * maxHeight), 1/3);
                
                dimensions = {
                    length: Math.ceil(maxLength * Math.max(volumeRatio, 1) * 1.1),
                    width: Math.ceil(maxWidth * Math.max(volumeRatio, 1) * 1.1),
                    height: Math.ceil(maxHeight * Math.max(volumeRatio, 1) * 1.1)
                };
                
                dimensions.length = Math.max(10, Math.min(dimensions.length, 150));
                dimensions.width = Math.max(10, Math.min(dimensions.width, 150));
                dimensions.height = Math.max(5, Math.min(dimensions.height, 150));
                
                var newVolume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
                console.log('✅ Груз разделен на', packagesCount, 'коробок. Размер одной коробки:', dimensions);
                console.log('✅ Объем одной коробки:', newVolume, 'см. Товаров в коробке:', itemsPerPackage);
                
                cartWeight = cartWeight / packagesCount;
            }
            
            console.log('Рассчитанные размеры упаковки:', dimensions);
        } else {
            console.log('Используем размеры по умолчанию (нет реальных габаритов)');
            dimensions = {
                length: 30,
                width: 20,
                height: 15
            };
        }
        
        if (cartWeight === 0) {
            cartWeight = 500;
        }
        
        // Используем исправленную итоговую сумму заказа
        if (orderTotalFromFooter > 0) {
            console.log('💰 Используем итоговую сумму заказа:', orderTotalFromFooter, 'руб. (вместо суммы по товарам:', cartValue, 'руб.)');
            cartValue = orderTotalFromFooter;
        } else if (cartValue === 0) {
            var subtotalElement = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
            });
            
            if (subtotalElement.length > 0) {
                var subtotalText = subtotalElement.find('.wc-block-components-totals-item__value').text();
                cartValue = PriceFormatter.extractCleanPrice(subtotalText) || 1000;
            }
        }
        
        console.log('Данные корзины для расчета:', {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions,
            packagesCount: packagesCount
        });
        
        return {
            weight: cartWeight,
            value: cartValue,
            dimensions: dimensions,
            hasRealDimensions: hasValidDimensions,
            packagesCount: packagesCount
        };
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАСЧЕТА СТОИМОСТИ ДОСТАВКИ ==========
    
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
            timeout: 30000,
            data: {
                action: 'calculate_cdek_delivery_cost',
                point_code: point.code,
                point_data: JSON.stringify(point),
                cart_weight: cartData.weight,
                cart_dimensions: JSON.stringify(cartData.dimensions),
                cart_value: cartData.value,
                has_real_dimensions: cartData.hasRealDimensions ? 1 : 0,
                packages_count: cartData.packagesCount || 1,
                nonce: cdek_ajax.nonce || ''
            },
            success: function(response) {
                console.log('Ответ API расчета стоимости:', response);
                
                if (response && response.success && response.data && response.data.delivery_sum) {
                    var deliveryCost = parseInt(response.data.delivery_sum);
                    
                    if (cartData.packagesCount > 1) {
                        var costPerPackage = deliveryCost;
                        deliveryCost = deliveryCost * cartData.packagesCount;
                        console.log('📦 Стоимость пересчитана для', cartData.packagesCount, 'коробок:', costPerPackage, '×', cartData.packagesCount, '=', deliveryCost, 'руб.');
                    }
                    
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
                    console.error('❌ API СДЭК вернул ошибку:', response.data ? response.data.message : 'Неизвестная ошибка');
                    console.error('🔍 Данные для отладки:', response.data ? response.data.debug_info : response);
                    console.error('🔍 ПОЛНЫЙ ответ от сервера:', response);
                    
                    if (response.data && response.data.api_response) {
                        console.error('🔍 Ответ от API СДЭК:', response.data.api_response);
                    }
                    
                    alert('Ошибка расчета стоимости доставки СДЭК. Попробуйте выбрать другой пункт выдачи или обновите страницу.');
                    return;
                } else {
                    console.error('❌ API СДЭК вернул некорректный ответ');
                    console.error('🔍 Детали ответа:', response);
                    
                    alert('Ошибка получения стоимости доставки. Попробуйте обновить страницу.');
                    return;
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Критическая ошибка запроса к API СДЭК:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                alert('Ошибка соединения с API СДЭК. Проверьте интернет-соединение и попробуйте снова.');
                return;
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
        
        if (cartData.packagesCount > 1) {
            baseCost = baseCost * cartData.packagesCount;
            console.log('📦 Fallback стоимость пересчитана для', cartData.packagesCount, 'коробок:', baseCost, 'руб.');
        }
        
        return baseCost;
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С АДРЕСАМИ (УЛУЧШЕННЫЕ) ==========
    
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
        
        setupSmartAutocomplete();
    }
    
    function setupSmartAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        // Создаем контейнер для умных подсказок
        var suggestionsContainer = $(`
            <div id="address-suggestions" class="smart-address-suggestions" style="display: none;">
                <div class="suggestions-header">
                    <span class="suggestions-title">Выберите город</span>
                    <span class="suggestions-count"></span>
                </div>
                <div class="suggestions-list"></div>
                <div class="suggestions-footer">
                    <small>💡 Начните вводить название города</small>
                </div>
            </div>
        `);
        
        addressInput.parent().css('position', 'relative');
        addressInput.parent().append(suggestionsContainer);
        
        // Добавляем стили
        if (!$('#smart-search-styles').length) {
            $('head').append(`
                <style id="smart-search-styles">
                .smart-address-suggestions {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #e1e5e9;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    z-index: 1000;
                    max-height: 300px;
                    overflow-y: auto;
                    margin-top: 4px;
                }
                
                .suggestions-header {
                    padding: 12px 16px;
                    border-bottom: 1px solid #f0f0f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .suggestions-title {
                    font-weight: 600;
                    color: #333;
                    font-size: 14px;
                }
                
                .suggestions-count {
                    font-size: 12px;
                    color: #666;
                }
                
                .suggestion-item {
                    display: flex;
                    align-items: center;
                    padding: 12px 16px;
                    cursor: pointer;
                    transition: background-color 0.15s ease;
                    border-bottom: 1px solid #f5f5f5;
                }
                
                .suggestion-item:hover,
                .suggestion-item.highlighted {
                    background-color: #f8f9fa;
                }
                
                .suggestion-item:last-child {
                    border-bottom: none;
                }
                
                .suggestion-icon {
                    font-size: 16px;
                    margin-right: 12px;
                    opacity: 0.7;
                }
                
                .suggestion-content {
                    flex: 1;
                }
                
                .suggestion-title {
                    font-weight: 500;
                    color: #333;
                    margin-bottom: 2px;
                }
                
                .suggestion-title mark {
                    background-color: #fff3cd;
                    color: #856404;
                    padding: 0 2px;
                    border-radius: 2px;
                }
                
                .suggestion-subtitle {
                    font-size: 12px;
                    color: #666;
                }
                
                .suggestions-footer {
                    padding: 8px 16px;
                    background: #f8f9fa;
                    border-top: 1px solid #f0f0f0;
                    text-align: center;
                }
                
                .suggestions-footer small {
                    color: #666;
                }
                
                @media (max-width: 768px) {
                    .smart-address-suggestions {
                        border-radius: 4px;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
                    }
                    
                    .suggestion-item {
                        padding: 10px 12px;
                    }
                    
                    .suggestions-header {
                        padding: 10px 12px;
                    }
                }
                </style>
            `);
        }
        
        var currentHighlight = -1;
        var currentSuggestions = [];
        
        addressInput.on('input', function() {
            var query = $(this).val().trim();
            
            if (query.length >= 2) {
                addressSearch.search(query, function(suggestions) {
                    currentSuggestions = suggestions;
                    currentHighlight = -1;
                    showAddressSuggestions(suggestions, query);
                });
            } else {
                hideAddressSuggestions();
            }
        });
        
        // Обработка клавиатуры
        addressInput.on('keydown', function(e) {
            if (!suggestionsContainer.is(':visible')) return;
            
            switch(e.keyCode) {
                case 38: // Стрелка вверх
                    e.preventDefault();
                    currentHighlight = Math.max(0, currentHighlight - 1);
                    updateHighlight();
                    break;
                case 40: // Стрелка вниз
                    e.preventDefault();
                    currentHighlight = Math.min(currentSuggestions.length - 1, currentHighlight + 1);
                    updateHighlight();
                    break;
                case 13: // Enter
                    e.preventDefault();
                    if (currentHighlight >= 0 && currentSuggestions[currentHighlight]) {
                        selectSuggestion(currentSuggestions[currentHighlight]);
                    }
                    break;
                case 27: // Escape
                    hideAddressSuggestions();
                    break;
            }
        });
        
        function updateHighlight() {
            suggestionsContainer.find('.suggestion-item').removeClass('highlighted');
            if (currentHighlight >= 0) {
                suggestionsContainer.find('.suggestion-item').eq(currentHighlight).addClass('highlighted');
            }
        }
        
        function showAddressSuggestions(suggestions, query) {
            var container = suggestionsContainer.find('.suggestions-list');
            container.empty();
            
            if (suggestions.length === 0) {
                container.html('<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">Ничего не найдено</div><div class="suggestion-subtitle">Попробуйте изменить запрос</div></div></div>');
                suggestionsContainer.find('.suggestions-count').text('0 результатов');
            } else {
                suggestions.forEach(function(suggestion, index) {
                    var highlightedCity = highlightQuery(suggestion.city, query);
                    
                    var item = $(`
                        <div class="suggestion-item" data-index="${index}">
                            <div class="suggestion-icon">🏙️</div>
                            <div class="suggestion-content">
                                <div class="suggestion-title">${highlightedCity}</div>
                                <div class="suggestion-subtitle">Россия</div>
                            </div>
                        </div>
                    `);
                    
                    item.on('click', function() {
                        selectSuggestion(suggestion);
                    });
                    
                    container.append(item);
                });
                
                suggestionsContainer.find('.suggestions-count').text(`${suggestions.length} результатов`);
            }
            
            suggestionsContainer.show();
        }
        
        function highlightQuery(text, query) {
            if (!query || !text) return text;
            
            var regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        function selectSuggestion(suggestion) {
            addressInput.val(suggestion.city);
            hideAddressSuggestions();
            
            // Сохраняем в недавние поиски
            saveRecentSearch(suggestion);
            
            // Запускаем поиск пунктов СДЭК
            debouncer.debounce('cdek-search', () => {
                searchCdekPoints(suggestion.city);
            }, 100, 6);
        }
        
        function saveRecentSearch(suggestion) {
            try {
                var recentSearches = JSON.parse(localStorage.getItem('cdek_recent_searches') || '[]');
                
                // Удаляем дубликат если есть
                recentSearches = recentSearches.filter(item => item.city !== suggestion.city);
                
                // Добавляем в начало
                recentSearches.unshift({
                    city: suggestion.city,
                    timestamp: Date.now()
                });
                
                // Оставляем только последние 5
                recentSearches = recentSearches.slice(0, 5);
                
                localStorage.setItem('cdek_recent_searches', JSON.stringify(recentSearches));
            } catch (error) {
                console.log('Не удалось сохранить недавний поиск');
            }
        }
        
        function hideAddressSuggestions() {
            suggestionsContainer.hide();
            currentHighlight = -1;
        }
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#address-suggestions, #shipping-address_1').length) {
                hideAddressSuggestions();
            }
        });
    }
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С КАРТОЙ ==========
    
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
    
    // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ПУНКТАМИ ВЫДАЧИ ==========
    
    function searchCdekPoints(address) {
        var parsedAddress = parseAddress(address);
        
        if (window.currentSearchCity && window.currentSearchCity !== parsedAddress.city) {
            clearSelectedPoint();
        }
        
        window.currentSearchCity = parsedAddress.city;
        window.currentSearchStreet = parsedAddress.street;
        
        memoizedGeocodeAddress(address, function(coords) {
            window.currentSearchCoordinates = coords;
            performCdekSearch();
        });
    }
    
    function performCdekSearch() {
        if (typeof cdek_ajax === 'undefined') {
            return;
        }
        
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
                    displayCdekPoints(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка получения пунктов СДЭК:', error);
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
            if (point.type !== 'PVZ' && point.type) {
                return false;
            }
            
            if (window.currentSearchCity) {
                var pointCity = '';
                
                if (point.location && point.location.city) {
                    pointCity = point.location.city.trim();
                }
                
                if (!pointCity && point.name && point.name.includes(',')) {
                    var nameParts = point.name.split(',');
                    if (nameParts.length >= 2) {
                        pointCity = nameParts[1].trim();
                    }
                }
                
                if (pointCity) {
                    pointCity = pointCity.replace(/^(г\.?\s*|город\s+)/i, '').trim();
                }
                
                var searchCityLower = window.currentSearchCity.toLowerCase().trim();
                var pointCityLower = pointCity.toLowerCase().trim();
                
                if (pointCityLower !== searchCityLower) {
                    return false;
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
        
        $('#cdek-selected-point-code').remove();
        $('#cdek-selected-point-data').remove();
        
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
        
        memoizedCalculateDeliveryCost(point, function(deliveryCost) {
            hideDeliveryCalculationLoader();
            
            // Ищем ВСЕ блоки доставки - используем несколько стратегий поиска
            var allShippingBlocks = $();
            
            var shippingBlocks1 = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            console.log('Стратегия 1 - найдено блоков в shipping:', shippingBlocks1.length);
            
            var shippingBlocks2 = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
            console.log('Стратегия 2 - найдено блоков в order-summary:', shippingBlocks2.length);
            
            var shippingBlocks3 = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                var isShippingBlock = labelText.indexOf('СДЭК') !== -1 || 
                       labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                       labelText.indexOf('Махачкала') !== -1 ||
                       labelText.indexOf('Москва') !== -1 ||
                       labelText.indexOf('Санкт-Петербург') !== -1 ||
                       labelText.indexOf('Саратов') !== -1 ||
                       (labelText.match(/^[А-Яа-я\s,\.\-]+$/) && labelText.includes(','));
                       
                if (isShippingBlock) {
                    console.log('Найден блок доставки по содержимому:', labelText);
                }
                return isShippingBlock;
            });
            console.log('Стратегия 3 - найдено блоков по содержимому:', shippingBlocks3.length);
            
            allShippingBlocks = shippingBlocks1.add(shippingBlocks2).add(shippingBlocks3);
            
            allShippingBlocks = allShippingBlocks.filter(function(index, element) {
                return allShippingBlocks.index(element) === index;
            });
            
            console.log('Найдено блоков доставки для обновления:', allShippingBlocks.length);
            
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
        
        var displayName = pointName;
        if (point.location && point.location.city) {
            displayName = point.location.city + ', ' + pointName.replace(point.location.city, '').replace(/^[,\s]+/, '');
        }
        
        var address = '';
        if (point.location && point.location.address_full) {
            address = point.location.address_full;
        } else if (point.location && point.location.address) {
            address = point.location.address;
        } else if (point.address) {
            address = point.address;
        }
        
        domBatcher.add(() => {
            var labelElement = block.find('.wc-block-components-totals-item__label');
            var valueElement = block.find('.wc-block-components-totals-item__value');
            var descriptionElement = block.find('.wc-block-components-totals-item__description');
            
            console.log('Обновляем блок доставки:', {
                oldLabel: labelElement.text(),
                newLabel: displayName,
                cost: deliveryCost,
                address: address
            });
            
            labelElement.text(displayName);
            valueElement.text(deliveryCost + ' руб.');
            
            if (address) {
                if (descriptionElement.length === 0) {
                    descriptionElement = $('<div class="wc-block-components-totals-item__description"></div>');
                    block.append(descriptionElement);
                } else if (descriptionElement.length > 1) {
                    descriptionElement.slice(1).remove();
                    descriptionElement = descriptionElement.first();
                }
                descriptionElement.html('<small style="color: #666;">' + address + '</small>');
            }
        });
        
        window.currentDeliveryCost = deliveryCost;
        
        $(document.body).trigger('updated_checkout');
        $(document.body).trigger('updated_cart_totals');
        
        setTimeout(function() {
            updateAllCdekShippingBlocks(displayName, deliveryCost, address);
        }, 100);
        
        setTimeout(function() {
            updateAllCdekShippingBlocks(displayName, deliveryCost, address);
        }, 500);
    }
    
    function updateAllCdekShippingBlocks(displayName, deliveryCost, address) {
        domBatcher.add(() => {
            var allBlocks = $('.wc-block-components-totals-item, .wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
            
            allBlocks.each(function() {
                var $block = $(this);
                var labelText = $block.find('.wc-block-components-totals-item__label').text();
                
                var isCdekBlock = labelText.indexOf('СДЭК') !== -1 || 
                                 labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                                 labelText.indexOf('Махачкала') !== -1 ||
                                 labelText.indexOf('Москва') !== -1 ||
                                 labelText.indexOf('Санкт-Петербург') !== -1 ||
                                 labelText.indexOf('Саратов') !== -1 ||
                                 (labelText.match(/^[А-Яа-я\s,\.\-]+$/) && labelText.includes(','));
                
                if (isCdekBlock) {
                    console.log('🔄 Принудительно обновляем блок:', labelText);
                    
                    $block.find('.wc-block-components-totals-item__label').text(displayName);
                    $block.find('.wc-block-components-totals-item__value').text(deliveryCost + ' руб.');
                    
                    if (address) {
                        var desc = $block.find('.wc-block-components-totals-item__description');
                        if (desc.length === 0) {
                            desc = $('<div class="wc-block-components-totals-item__description"></div>');
                            $block.append(desc);
                        } else if (desc.length > 1) {
                            desc.slice(1).remove();
                            desc = desc.first();
                        }
                        desc.html('<small style="color: #666;">' + address + '</small>');
                    }
                }
            });
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
        domBatcher.add(() => {
            var totalBlock = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
            });
            
            if (totalBlock.length > 0) {
                totalBlock = totalBlock.first();
                
                var subtotalBlock = $('.wc-block-components-totals-item').filter(function() {
                    var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                    return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
                });
                
                if (subtotalBlock.length > 0) {
                    var subtotalText = subtotalBlock.find('.wc-block-components-totals-item__value').text();
                    var subtotal = PriceFormatter.extractCleanPrice(subtotalText);
                    
                    var taxBlock = $('.wc-block-components-totals-taxes .wc-block-components-totals-item__value');
                    var tax = 0;
                    if (taxBlock.length > 0) {
                        var taxText = taxBlock.text();
                        tax = PriceFormatter.extractCleanPrice(taxText);
                    }
                    
                    var newTotal = subtotal + deliveryCost + tax;
                    
                    var totalValueElement = totalBlock.find('.wc-block-components-totals-item__value');
                    
                    var currentText = totalValueElement.text().trim();
                    var newText = newTotal + ' руб.';
                    
                    if (currentText !== newText) {
                        totalValueElement.text(newText);
                        console.log('💰 Обновлена итоговая сумма:', newText);
                    }
                }
            }
        });
    }
    
    // ========== ФУНКЦИИ УПРАВЛЕНИЯ ИНТЕРФЕЙСОМ ==========
    
    function removeDuplicateTotalElements() {
        domBatcher.add(() => {
            var totalBlocks = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
            });
            
            if (totalBlocks.length > 1) {
                console.log('🔍 Найдено дублированных элементов итоговой суммы:', totalBlocks.length);
                totalBlocks.slice(1).remove();
                console.log('✅ Дублированные элементы итоговой суммы удалены');
            }
            
            var wrappers = $('.wc-block-components-totals-wrapper');
            if (wrappers.length > 1) {
                console.log('🔍 Найдено дублированных wrapper элементов:', wrappers.length);
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
        domBatcher.add(() => {
            $('.wc-block-components-totals-item').each(function() {
                var $item = $(this);
                var labelElement = $item.find('.wc-block-components-totals-item__label');
                var labelText = labelElement.text();
                
                if (labelText.indexOf('СДЭК') !== -1 || 
                    labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                    labelText.indexOf('Москва') !== -1 ||
                    labelText.indexOf('Санкт-Петербург') !== -1 ||
                    labelText.indexOf('Саратов') !== -1 ||
                    labelText.includes('пункт выдачи')) {
                    
                    labelElement.text('Выберите пункт выдачи');
                    
                    var valueElement = $item.find('.wc-block-components-totals-item__value');
                    valueElement.text('');
                    
                    var descriptionElement = $item.find('.wc-block-components-totals-item__description');
                    descriptionElement.html('');
                }
            });
        });
        
        window.currentDeliveryCost = 0;
        updateOrderTotal(0);
    }
    
    function initCdekDelivery() {
        if (isInitialized) {
            return;
        }
        
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
        
        setTimeout(function() {
            removeDuplicateTotalElements();
            fixExistingDuplicatedPrices();
        }, 2000);
        
        console.log('✅ СДЭК доставка инициализирована');
    }
    
    function hideUnnecessaryFields() {
        domBatcher.add(() => {
            var fieldsToHide = [
                '#shipping-city', '#shipping-state', '#shipping-postcode',
                '#billing-city', '#billing-state', '#billing-postcode',
                'input[name="shipping_city"]', 'input[name="shipping_state"]', 'input[name="shipping_postcode"]',
                'input[name="billing_city"]', 'input[name="billing_state"]', 'input[name="billing_postcode"]'
            ];
            
            fieldsToHide.forEach(function(selector) {
                $(selector).hide().closest('.wc-block-components-text-input').hide();
            });
            
            $('.wc-block-components-address-form__city, .wc-block-components-address-form__state, .wc-block-components-address-form__postcode').hide();
            
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
        });
    }
    
    // ========== ИНИЦИАЛИЗАЦИЯ И ОБРАБОТЧИКИ СОБЫТИЙ ==========
    
    // Инициализируем перехватчики цен СРАЗУ
    interceptPriceUpdates();
    startPriceMonitoring();
    
    // Инициализация при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            debouncer.debounce('init-cdek', () => {
                initCdekDelivery();
            }, 100, 8);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1) {
            hideCdekMap();
            resetCdekShippingToDefault();
        }
    });
    
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        debouncer.debounce('init-cdek-click', () => {
            initCdekDelivery();
        }, 200, 7);
    });
    
    // Отслеживание изменений в поле адреса
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        var city = address.split(',')[0].trim();
        
        if (city.length > 2) {
            debouncer.debounce('address-change', () => {
                searchCdekPoints(city);
            }, 500, 4);
        }
    });
    
    // Наблюдатель за изменениями DOM
    var observer = new MutationObserver(function(mutations) {
        var needsUpdate = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                hideUnnecessaryFields();
                
                $('.wc-block-components-totals-item__label').each(function() {
                    var text = $(this).text();
                    if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                        $(this).text('Выберите пункт выдачи');
                    }
                });
                
                var cdekMethod = $('input[value*="cdek_delivery"]');
                
                if (cdekMethod.length > 0 && !isInitialized) {
                    needsUpdate = true;
                }
            }
        });
        
        if (needsUpdate) {
            debouncer.debounce('mutation-init', () => {
                hideCdekShippingBlock();
                
                var cdekSelected = $('input[value*="cdek_delivery"]:checked');
                
                if (cdekSelected.length > 0 && $('#cdek-map-container').length === 0) {
                    initCdekDelivery();
                } else if ($('#cdek-map-container').length === 0) {
                    initCdekDelivery();
                }
            }, 500, 5);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Начальная инициализация
    setTimeout(function() {
        hideUnnecessaryFields();
        
        $('.wc-block-components-totals-item__label').each(function() {
            var text = $(this).text();
            if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                $(this).text('Выберите пункт выдачи');
            }
        });
    }, 100);
    
    setTimeout(function() {
        hideUnnecessaryFields();
        initAddressAutocomplete();
        
        var cdekMethod = $('input[value*="cdek_delivery"]');
        
        if (cdekMethod.length > 0) {
            hideCdekShippingBlock();
            initCdekDelivery();
        }
    }, 2000);
    
    setTimeout(function() {
        hideUnnecessaryFields();
        
        if ($('#address-select').length === 0 && $('#address-suggestions').length === 0) {
            initAddressAutocomplete();
        }
    }, 5000);
    
    setTimeout(function() {
        hideUnnecessaryFields();
        
        if ($('input[value*="cdek_delivery"]').length > 0 && $('#cdek-map-container').length === 0 && !isInitialized) {
            initCdekDelivery();
        }
        
        removeDuplicateTotalElements();
        fixExistingDuplicatedPrices();
    }, 4000);
    
    // Финальная проверка цен каждые 2 секунды
    setInterval(() => {
        fixExistingDuplicatedPrices();
    }, 2000);
    
    console.log('🚀 СДЭК Delivery Optimized загружен');
});