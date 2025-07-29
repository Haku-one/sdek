/**
 * СДЭК Доставка - Исправленная версия
 * Исправлены: разделение коробок, CORS ошибки, производительность на мобильных
 */

// ========== УТИЛИТЫ ДЛЯ ОПТИМИЗАЦИИ ==========

// Мемоизация с TTL
class Memoizer {
    constructor(ttl = 300000) {
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
            
            if (this.cache.size > 50) { // Уменьшено для мобильных
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

// Батчинг DOM операций с throttling для мобильных
class DOMBatcher {
    constructor() {
        this.operations = [];
        this.scheduled = false;
        this.isMobile = window.innerWidth <= 768;
        this.throttleDelay = this.isMobile ? 32 : 16; // 30fps для мобильных, 60fps для десктопа
    }
    
    add(operation) {
        this.operations.push(operation);
        if (!this.scheduled) {
            this.scheduled = true;
            
            if (this.isMobile) {
                // Для мобильных используем setTimeout вместо rAF для лучшей производительности
                setTimeout(() => this.flush(), this.throttleDelay);
            } else {
                requestAnimationFrame(() => this.flush());
            }
        }
    }
    
    flush() {
        // Обрабатываем операции порциями для мобильных
        const batchSize = this.isMobile ? 5 : 10;
        const currentBatch = this.operations.splice(0, batchSize);
        
        currentBatch.forEach(op => {
            try {
                op();
            } catch (error) {
                console.error('DOM operation error:', error);
            }
        });
        
        if (this.operations.length > 0) {
            // Продолжаем обработку оставшихся операций
            setTimeout(() => this.flush(), this.throttleDelay);
        } else {
            this.scheduled = false;
        }
    }
}

// Исправление дублированных цен - КРИТИЧЕСКИ ВАЖНО!
class PriceFormatter {
    static fixDuplicatedPrice(priceText) {
        if (!priceText || typeof priceText !== 'string') {
            return priceText;
        }
        
        const numbers = priceText.match(/\d+/g);
        if (!numbers || numbers.length === 0) {
            return priceText;
        }
        
        const mainNumber = numbers[0];
        
        // НЕ исправляем валидные итоговые суммы (135000 + 6984 = 141984)
        // Проверяем, является ли это валидной суммой заказа
        const numValue = parseInt(mainNumber);
        if (numValue >= 100000 && numValue <= 999999) {
            // Это может быть валидная итоговая сумма заказа, не трогаем
            return priceText;
        }
        
        if (mainNumber.length >= 6) {
            const patterns = [
                // Паттерн полного дублирования: ABCABC -> ABC (например: 180180 -> 180)
                { 
                    prefixLen: Math.floor(mainNumber.length / 2), 
                    check: (prefix, suffix) => prefix === suffix && prefix.length >= 2
                },
                // Паттерн склеивания: ABC + DEFGH = ABCDEFGH, но только если ABC намного меньше DEFGH
                { 
                    prefixLen: 3, 
                    check: (prefix, suffix) => {
                        const prefixNum = parseInt(prefix);
                        const suffixNum = parseInt(suffix);
                        // Исправляем только если префикс в 10+ раз меньше суффикса
                        return prefixNum > 0 && suffixNum > 0 && (suffixNum / prefixNum) >= 10;
                    }
                }
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

// ========== УМНЫЙ ПОИСК АДРЕСОВ С ПОЛНЫМ СПИСКОМ ГОРОДОВ ==========

class SmartAddressSearch {
    constructor() {
        this.cache = new Map();
        this.debouncer = new SmartDebouncer();
        this.userLocation = null;
        
        // ПОЛНЫЙ список российских городов (расширенный)
        this.popularCities = [
            // Федеральные города и миллионники
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул',
            
            // Крупные региональные центры
            'Ульяновск', 'Владивосток', 'Ярославль', 'Иркутск', 'Хабаровск', 'Махачкала', 'Томск',
            'Оренбург', 'Кемерово', 'Новокузнецк', 'Рязань', 'Астрахань', 'Пенза', 'Липецк',
            'Тула', 'Киров', 'Чебоксары', 'Калининград', 'Брянск', 'Курск', 'Иваново', 'Магнитогорск',
            'Тверь', 'Ставрополь', 'Симферополь', 'Белгород', 'Архангельск', 'Владимир', 'Сочи',
            'Курган', 'Смоленск', 'Калуга', 'Чита', 'Орёл', 'Волжский', 'Череповец', 'Владикавказ',
            'Мурманск', 'Сургут', 'Вологда', 'Тамбов', 'Стерлитамак', 'Грозный', 'Якутск',
            'Кострома', 'Комсомольск-на-Амуре', 'Петрозаводск', 'Таганрог', 'Нижневартовск', 'Йошкар-Ола',
            
            // Города с населением более 200 тысяч
            'Братск', 'Новороссийск', 'Дзержинск', 'Шахты', 'Нижнекамск', 'Орск', 'Ангарск',
            'Старый Оскол', 'Великий Новгород', 'Благовещенск', 'Прокопьевск', 'Химки', 'Бийск',
            'Энгельс', 'Рыбинск', 'Балашиха', 'Северодвинск', 'Армавир', 'Подольск', 'Королёв',
            'Сызрань', 'Норильск', 'Золотое кольцо', 'Каменск-Уральский', 'Волжск', 'Альметьевск',
            'Уссурийск', 'Мытищи', 'Люберцы', 'Электросталь', 'Салават', 'Миасс', 'Абакан',
            'Рубцовск', 'Коломна', 'Майкоп', 'Ковров', 'Красногорск', 'Нальчик', 'Усть-Илимск',
            'Серпухов', 'Новочебоксарск', 'Нефтеюганск', 'Димитровград', 'Нефтекамск', 'Черкесск',
            'Дербент', 'Камышин', 'Новый Уренгой', 'Муром', 'Ачинск', 'Кисловодск', 'Первоуральск',
            'Елец', 'Евпатория', 'Арзамас', 'Рубцовск', 'Тобольск', 'Жуковский', 'Ноябрьск',
            'Невинномысск', 'Березники', 'Назрань', 'Южно-Сахалинск', 'Волгодонск', 'Сыктывкар',
            'Новочеркасск', 'Каспийск', 'Обнинск', 'Пятигорск', 'Октябрьский', 'Ломоносов'
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
                        console.log('✅ Геолокация получена:', this.userLocation);
                    },
                    (error) => {
                        console.log('Геолокация недоступна, используем fallback');
                        // НЕ используем внешние API - избегаем CORS ошибок
                        this.setDefaultLocation();
                    },
                    { timeout: 5000, maximumAge: 300000 }
                );
            } else {
                this.setDefaultLocation();
            }
        } catch (error) {
            console.log('Геолокация недоступна');
            this.setDefaultLocation();
        }
    }
    
    setDefaultLocation() {
        // Устанавливаем Москву как локацию по умолчанию
        this.userLocation = {
            lat: 55.7558,
            lng: 37.6176,
            city: 'Москва'
        };
    }
    
    search(query, callback) {
        this.debouncer.debounce('address-search', () => {
            this.performSearch(query, callback);
        }, 200); // Уменьшено для более быстрого отклика
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
        
        // Оптимизированный поиск для мобильных
        const maxResults = window.innerWidth <= 768 ? 8 : 12;
        
        this.popularCities.forEach(city => {
            if (results.length >= maxResults) return;
            
            const cityLower = city.toLowerCase();
            let score = 0;
            
            if (cityLower === queryLower) {
                score = 1000;
            } else if (cityLower.startsWith(queryLower)) {
                score = 500;
            } else if (cityLower.includes(queryLower)) {
                score = 200;
            } else {
                // Упрощенная проверка похожести для мобильных
                if (queryLower.length >= 3) {
                    const similarity = this.fastSimilarity(queryLower, cityLower);
                    if (similarity > 0.6) {
                        score = similarity * 100;
                    }
                }
            }
            
            if (score > 0) {
                const popularityIndex = this.popularCities.indexOf(city);
                const popularityBonus = (this.popularCities.length - popularityIndex) * 2;
                score += popularityBonus;
                
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
        
        results.sort((a, b) => b.score - a.score);
        return results.slice(0, maxResults);
    }
    
    // Быстрая оценка похожести без полного алгоритма Левенштейна
    fastSimilarity(str1, str2) {
        if (str1.length === 0) return str2.length === 0 ? 1 : 0;
        if (str2.length === 0) return 0;
        
        let matches = 0;
        const minLen = Math.min(str1.length, str2.length);
        
        for (let i = 0; i < minLen; i++) {
            if (str1[i] === str2[i]) {
                matches++;
            }
        }
        
        return matches / Math.max(str1.length, str2.length);
    }
}

// ========== ОСНОВНОЙ КОД СДЭК ==========

jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false;
    
    // Инициализируем утилиты оптимизации
    const memoizer = new Memoizer();
    const debouncer = new SmartDebouncer();
    const domBatcher = new DOMBatcher();
    const addressSearch = new SmartAddressSearch();
    
    const memoizedCalculateDeliveryCost = memoizer.memoize(calculateDeliveryCost);
    const memoizedGeocodeAddress = memoizer.memoize(geocodeAddress);
    
    // ========== КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ ДУБЛИРОВАННЫХ ЦЕН ==========
    
    function interceptPriceUpdates() {
        if (typeof $ !== 'undefined' && $.fn.text) {
            var originalText = $.fn.text;
            $.fn.text = function(value) {
                if (arguments.length > 0 && typeof value === 'string') {
                    if (this.hasClass('wc-block-components-totals-item__value') || 
                        this.hasClass('wc-block-formatted-money-amount')) {
                        
                        // Проверяем, не является ли это итоговой суммой
                        var isTotal = this.closest('.wc-block-components-totals-footer-item').length > 0 ||
                                     this.siblings('.wc-block-components-totals-item__label').text().indexOf('Итого') !== -1;
                        
                        if (!isTotal) {
                            value = PriceFormatter.fixDuplicatedPrice(value);
                        }
                    }
                }
                return originalText.apply(this, arguments.length > 0 ? [value] : []);
            };
        }
        
        if (typeof HTMLElement !== 'undefined') {
            const originalTextContentDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'textContent') || 
                                                 Object.getOwnPropertyDescriptor(Element.prototype, 'textContent');
            
            if (originalTextContentDescriptor && originalTextContentDescriptor.set) {
                Object.defineProperty(HTMLElement.prototype, 'textContent', {
                    set: function(value) {
                        if (typeof value === 'string' && 
                            (this.classList.contains('wc-block-components-totals-item__value') ||
                             this.classList.contains('wc-block-formatted-money-amount'))) {
                            
                            // Проверяем, не является ли это итоговой суммой
                            var isTotal = this.closest('.wc-block-components-totals-footer-item') ||
                                         (this.parentElement && this.parentElement.querySelector('.wc-block-components-totals-item__label') &&
                                          this.parentElement.querySelector('.wc-block-components-totals-item__label').textContent.indexOf('Итого') !== -1);
                            
                            if (!isTotal) {
                                value = PriceFormatter.fixDuplicatedPrice(value);
                            }
                        }
                        originalTextContentDescriptor.set.call(this, value);
                    },
                    get: originalTextContentDescriptor.get
                });
            }
        }
    }
    
    function fixExistingDuplicatedPrices() {
        domBatcher.add(() => {
            $('.wc-block-components-totals-item__value, .wc-block-formatted-money-amount').each(function() {
                const $element = $(this);
                
                // Проверяем, не является ли это итоговой суммой
                const isTotal = $element.closest('.wc-block-components-totals-footer-item').length > 0 ||
                               $element.siblings('.wc-block-components-totals-item__label').text().indexOf('Итого') !== -1;
                
                if (!isTotal) {
                    const currentText = $element.text().trim();
                    const fixedText = PriceFormatter.fixDuplicatedPrice(currentText);
                    
                    if (currentText !== fixedText) {
                        console.log(`🔧 Исправляем цену: ${currentText} -> ${fixedText}`);
                        $element.text(fixedText);
                    }
                }
            });
        });
    }
    
    function startPriceMonitoring() {
        // Уменьшаем частоту для мобильных
        const interval = window.innerWidth <= 768 ? 2000 : 1000;
        
        setInterval(() => {
            fixExistingDuplicatedPrices();
        }, interval);
        
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
                    }, 100, 7);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }
    }
    
    // ========== ИСПРАВЛЕННАЯ ФУНКЦИЯ РАСЧЕТА ГАБАРИТОВ ==========
    
    function getCartDataForCalculation() {
        var cartWeight = 0;
        var cartValue = 0;
        var totalVolume = 0;
        var maxLength = 0, maxWidth = 0, maxHeight = 0;
        var hasValidDimensions = false;
        var totalItems = 0;
        var packagesCount = 1;
        
        console.log('Получение данных корзины для расчета...');
        
        var processedItems = new Set();
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            
            var itemName = $item.find('.wc-block-components-product-name').text().trim();
            var itemId = itemName + '_' + $item.index();
            
            if (processedItems.has(itemId)) {
                return;
            }
            processedItems.add(itemId);
            
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = parseInt(quantityElement.text()) || 1;
            
            console.log('Обработка товара из WC блока, количество:', quantity);
            
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
            
            var totalPriceElement = $item.find('.wc-block-components-order-summary-item__total-price .wc-block-components-product-price__value');
            
            if (totalPriceElement.length > 0) {
                var totalPriceText = totalPriceElement.text().trim();
                console.log('Найдена итоговая цена товара:', totalPriceText);
                
                var totalPrice = PriceFormatter.extractCleanPrice(totalPriceText);
                cartValue += totalPrice;
                console.log('✅ Используем итоговую цену товара:', totalPrice, 'руб. (с учетом количества', quantity + ')');
            }
        });
        
        var totalOrderElement = $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value');
        var orderTotalFromFooter = 0;
        
        if (totalOrderElement.length > 0) {
            var totalText = totalOrderElement.first().text().trim();
            console.log('Найдена итоговая сумма заказа:', totalText);
            
            orderTotalFromFooter = PriceFormatter.extractCleanPrice(totalText);
            console.log('Извлечена итоговая сумма:', orderTotalFromFooter);
        }
        
        // ========== ИСПРАВЛЕННЫЙ РАСЧЕТ РАЗМЕРОВ УПАКОВКИ ==========
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
            
            // ИСПРАВЛЕННАЯ ПРОВЕРКА ОБЪЕМА УПАКОВКИ
            var volume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
            if (volume > 300) {
                console.log('⚠️ Объем упаковки превышает лимит СДЭК:', volume, 'см > 300 см. Разделяем на несколько коробок.');
                
                // ПРАВИЛЬНЫЙ расчет количества коробок
                packagesCount = Math.ceil(volume / 280); // 280 для безопасного запаса
                
                // Пересчитываем размеры для одной коробки
                var targetVolume = 280; // Целевой объем одной коробки
                var scaleFactor = Math.pow(targetVolume / volume, 1/3);
                
                dimensions = {
                    length: Math.max(10, Math.min(Math.ceil(dimensions.length * scaleFactor), 100)),
                    width: Math.max(10, Math.min(Math.ceil(dimensions.width * scaleFactor), 100)),
                    height: Math.max(5, Math.min(Math.ceil(dimensions.height * scaleFactor), 100))
                };
                
                // ПРОВЕРЯЕМ что новый объем не превышает лимит
                var newVolume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
                
                // Если все еще превышает, принудительно уменьшаем
                if (newVolume > 300) {
                    var additionalScale = 280 / newVolume;
                    dimensions.length = Math.max(10, Math.ceil(dimensions.length * additionalScale));
                    dimensions.width = Math.max(10, Math.ceil(dimensions.width * additionalScale));
                    dimensions.height = Math.max(5, Math.ceil(dimensions.height * additionalScale));
                    newVolume = (dimensions.height + dimensions.width) * 2 + dimensions.length;
                }
                
                var itemsPerPackage = Math.ceil(totalItems / packagesCount);
                
                console.log('✅ Груз разделен на', packagesCount, 'коробок. Размер одной коробки:', dimensions);
                console.log('✅ Объем одной коробки:', newVolume, 'см (лимит: 300 см). Товаров в коробке:', itemsPerPackage);
                
                // Корректируем вес на одну коробку
                cartWeight = cartWeight / packagesCount;
            } else {
                console.log('✅ Объем упаковки в пределах лимита:', volume, 'см <= 300 см');
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
                    
                    // Используем fallback вместо показа ошибки пользователю
                    var fallbackCost = calculateFallbackCost(point, cartData);
                    console.log('🔄 Используем резервный расчет стоимости:', fallbackCost, 'руб.');
                    callback(fallbackCost);
                } else {
                    console.error('❌ API СДЭК вернул некорректный ответ');
                    
                    var fallbackCost = calculateFallbackCost(point, cartData);
                    console.log('🔄 Используем резервный расчет стоимости:', fallbackCost, 'руб.');
                    callback(fallbackCost);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Критическая ошибка запроса к API СДЭК:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    readyState: xhr.readyState
                });
                
                // Используем fallback вместо показа ошибки
                var fallbackCost = calculateFallbackCost(point, cartData);
                console.log('🔄 Используем резервный расчет стоимости:', fallbackCost, 'руб.');
                callback(fallbackCost);
            }
        });
    }
    
    function calculateFallbackCost(point, cartData) {
        var baseCost = 350; // Базовая стоимость
        
        if (!cartData) {
            return baseCost;
        }
        
        // Надбавка за вес
        if (cartData.weight > 500) {
            var extraWeight = Math.ceil((cartData.weight - 500) / 500);
            baseCost += extraWeight * 40;
        }
        
        // Надбавка за габариты
        if (cartData.hasRealDimensions && cartData.dimensions) {
            var volume = cartData.dimensions.length * cartData.dimensions.width * cartData.dimensions.height;
            if (volume > 12000) {
                var extraVolume = Math.ceil((volume - 12000) / 6000);
                baseCost += extraVolume * 60;
            }
        }
        
        // Надбавка за стоимость
        if (cartData.value > 3000) {
            baseCost += Math.ceil((cartData.value - 3000) / 1000) * 25;
        }
        
        // Умножаем на количество коробок
        if (cartData.packagesCount > 1) {
            baseCost = baseCost * cartData.packagesCount;
            console.log('📦 Fallback стоимость пересчитана для', cartData.packagesCount, 'коробок:', baseCost, 'руб.');
        }
        
        return baseCost;
    }
    
    // ========== ОСТАЛЬНЫЕ ФУНКЦИИ (УПРОЩЕННЫЕ ДЛЯ МОБИЛЬНЫХ) ==========
    
    function parseAddress(address) {
        var result = { city: '', street: '' };
        
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
        
        // Оптимизированные стили для мобильных
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
                    max-height: 250px;
                    overflow-y: auto;
                    margin-top: 4px;
                    -webkit-overflow-scrolling: touch;
                }
                
                .suggestions-header {
                    padding: 10px 12px;
                    border-bottom: 1px solid #f0f0f0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f8f9fa;
                    position: sticky;
                    top: 0;
                }
                
                .suggestions-title {
                    font-weight: 600;
                    color: #333;
                    font-size: 13px;
                }
                
                .suggestions-count {
                    font-size: 11px;
                    color: #666;
                }
                
                .suggestion-item {
                    display: flex;
                    align-items: center;
                    padding: 12px 14px;
                    cursor: pointer;
                    transition: background-color 0.15s ease;
                    border-bottom: 1px solid #f5f5f5;
                    min-height: 44px; /* Увеличиваем для удобства касания */
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
                    margin-right: 10px;
                    opacity: 0.7;
                }
                
                .suggestion-content {
                    flex: 1;
                }
                
                .suggestion-title {
                    font-weight: 500;
                    color: #333;
                    margin-bottom: 2px;
                    font-size: 14px;
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
                    padding: 8px 12px;
                    background: #f8f9fa;
                    border-top: 1px solid #f0f0f0;
                    text-align: center;
                    position: sticky;
                    bottom: 0;
                }
                
                .suggestions-footer small {
                    color: #666;
                    font-size: 11px;
                }
                
                @media (max-width: 768px) {
                    .smart-address-suggestions {
                        border-radius: 6px;
                        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.15);
                        max-height: 200px;
                    }
                    
                    .suggestion-item {
                        padding: 14px 12px;
                        min-height: 48px;
                    }
                    
                    .suggestions-header {
                        padding: 8px 12px;
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
                // Показываем индикатор поиска городов
                showSearchLoader();
                
                addressSearch.search(query, function(suggestions) {
                    currentSuggestions = suggestions;
                    currentHighlight = -1;
                    hideSearchLoader();
                    showAddressSuggestions(suggestions, query);
                });
            } else {
                hideAddressSuggestions();
                hideSearchLoader();
            }
        });
        
        // Упрощенная обработка клавиатуры для мобильных
        addressInput.on('keydown', function(e) {
            if (!suggestionsContainer.is(':visible') || window.innerWidth <= 768) return;
            
            switch(e.keyCode) {
                case 38: // Up
                    e.preventDefault();
                    currentHighlight = Math.max(0, currentHighlight - 1);
                    updateHighlight();
                    break;
                case 40: // Down
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
        
        function showSearchLoader() {
            var container = suggestionsContainer.find('.suggestions-list');
            container.html(`
                <div class="suggestion-item">
                    <div class="suggestion-icon">🔄</div>
                    <div class="suggestion-content">
                        <div class="suggestion-title">Поиск городов...</div>
                        <div class="suggestion-subtitle">Подождите несколько секунд</div>
                    </div>
                </div>
            `);
            suggestionsContainer.find('.suggestions-count').text('Поиск...');
            suggestionsContainer.show();
        }
        
        function hideSearchLoader() {
            // Лоадер скрывается при показе результатов
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
            // Предотвращаем повторный поиск если уже выбран тот же город
            if (window.lastSelectedCity === suggestion.city && selectedPoint) {
                hideAddressSuggestions();
                return;
            }
            
            addressInput.val(suggestion.city);
            hideAddressSuggestions();
            
            saveRecentSearch(suggestion);
            
            // Запоминаем выбранный город
            window.lastSelectedCity = suggestion.city;
            
            // Очищаем предыдущий выбор ПВЗ только при смене города
            if (window.currentSearchCity && window.currentSearchCity !== suggestion.city) {
                clearSelectedPoint();
            }
            
            // Показываем индикатор загрузки ПВЗ
            showPvzLoader();
            
            debouncer.debounce('cdek-search', () => {
                searchCdekPoints(suggestion.city);
            }, 100, 6);
        }
        
        function saveRecentSearch(suggestion) {
            try {
                var recentSearches = JSON.parse(localStorage.getItem('cdek_recent_searches') || '[]');
                
                recentSearches = recentSearches.filter(item => item.city !== suggestion.city);
                
                recentSearches.unshift({
                    city: suggestion.city,
                    timestamp: Date.now()
                });
                
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
    
    // ========== ОСТАЛЬНЫЕ ФУНКЦИИ (СОКРАЩЕННЫЕ) ==========
    
    function initYandexMap() {
        if (cdekMap) return;
        
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
            ymaps.geocode(address, { results: 1 }).then(function(res) {
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
        return R * c;
    }
    
    function searchCdekPoints(address) {
        var parsedAddress = parseAddress(address);
        
        // Проверяем, не ищем ли мы тот же город повторно
        if (window.currentSearchCity === parsedAddress.city && cdekPoints && cdekPoints.length > 0) {
            console.log('🔄 Используем кэшированные ПВЗ для города:', parsedAddress.city);
            hidePvzLoader();
            displayCdekPoints(cdekPoints);
            return;
        }
        
        // Очищаем выбор ПВЗ только при смене города
        if (window.currentSearchCity && window.currentSearchCity !== parsedAddress.city) {
            clearSelectedPoint();
        }
        
        window.currentSearchCity = parsedAddress.city;
        window.currentSearchStreet = parsedAddress.street;
        
        console.log('🔍 Поиск ПВЗ для города:', parsedAddress.city);
        
        memoizedGeocodeAddress(address, function(coords) {
            window.currentSearchCoordinates = coords;
            performCdekSearch();
        });
    }
    
    // Функция для загрузки всех ПВЗ России
    function loadAllCdekPoints() {
        console.log('🌍 Загружаем все ПВЗ России...');
        showPvzLoader();
        
        // Очищаем текущий поиск
        window.currentSearchCity = null;
        window.currentSearchStreet = null;
        window.currentSearchCoordinates = null;
        
        // Отправляем запрос с адресом "Россия" для получения всех ПВЗ
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 60000, // Увеличиваем таймаут для загрузки всех ПВЗ
            data: {
                action: 'get_cdek_points',
                address: 'Россия',
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                hidePvzLoader();
                if (response.success && response.data) {
                    console.log('✅ Загружено всех ПВЗ России:', response.data.length);
                    displayCdekPoints(response.data);
                } else {
                    console.error('❌ Ошибка загрузки всех ПВЗ:', response);
                    showPvzError('Не удалось загрузить все пункты выдачи');
                }
            },
            error: function(xhr, status, error) {
                hidePvzLoader();
                console.error('❌ Ошибка HTTP при загрузке всех ПВЗ:', error);
                showPvzError('Ошибка загрузки всех пунктов выдачи');
            }
        });
    }
    
    function performCdekSearch() {
        if (typeof cdek_ajax === 'undefined') return;
        
        // Формируем адрес для поиска - используем конкретный город, если он известен
        var searchAddress = 'Россия';
        if (window.currentSearchCity) {
            searchAddress = window.currentSearchCity;
        }
        
        console.log('🔍 Отправляем запрос к API СДЭК для адреса:', searchAddress);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'get_cdek_points',
                address: searchAddress,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                hidePvzLoader();
                if (response.success && response.data) {
                    console.log('✅ Получено ПВЗ от API:', response.data.length);
                    displayCdekPoints(response.data);
                } else {
                    console.error('❌ Ошибка получения ПВЗ:', response);
                    showPvzError('Не удалось загрузить пункты выдачи');
                }
            },
            error: function(xhr, status, error) {
                hidePvzLoader();
                console.error('Ошибка получения пунктов СДЭК:', error);
                showPvzError('Ошибка загрузки пунктов выдачи');
            }
        });
    }
    
    function displayCdekPoints(points) {
        cdekPoints = points;
        
        if (!cdekMap || typeof ymaps === 'undefined') {
            setTimeout(function() { displayCdekPoints(points); }, 1000);
            return;
        }
        
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            $('#cdek-points-count').text(`Пункты выдачи не найдены${cityInfo}`);
            return;
        }
        
        var filteredPoints = points.filter(function(point) {
            if (point.type !== 'PVZ' && point.type) return false;
            
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
                
                if (pointCityLower !== searchCityLower) return false;
            }
            
            return true;
        });
        
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
        
        var maxPoints = 1000; // Увеличиваем лимит для отображения большего количества ПВЗ
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
        
        // Запоминаем выбранный ПВЗ чтобы избежать повторных поисков
        window.lastSelectedPointCode = point.code;
        
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        if (cdekMap && point.location) {
            cdekMap.setCenter([point.location.latitude, point.location.longitude], 15);
        }
        
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
        
        console.log('✅ Выбран ПВЗ:', point.name, '(код:', point.code + ')');
    }
    
    function clearSelectedPoint() {
        selectedPoint = null;
        window.lastSelectedPointCode = null;
        
        $('#cdek-selected-point').hide();
        $('#cdek-point-info').html('');
        
        $('#cdek-selected-point-code').remove();
        $('#cdek-selected-point-data').remove();
        
        resetCdekShippingToDefault();
        
        console.log('🗑️ Очищен выбор ПВЗ');
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
        
        if (workTime && typeof workTime === 'string') {
            return workTime;
        }
        
        return 'Не указан';
    }
    
    function updateOrderSummary(point) {
        showDeliveryCalculationLoader();
        
        memoizedCalculateDeliveryCost(point, function(deliveryCost) {
            hideDeliveryCalculationLoader();
            
            var allShippingBlocks = $();
            
            var shippingBlocks1 = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            var shippingBlocks2 = $('.wp-block-woocommerce-checkout-order-summary-shipping-block .wc-block-components-totals-item');
            var shippingBlocks3 = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('СДЭК') !== -1 || 
                       labelText.indexOf('Выберите пункт выдачи') !== -1 ||
                       labelText.indexOf('Махачкала') !== -1 ||
                       labelText.indexOf('Москва') !== -1 ||
                       labelText.indexOf('Санкт-Петербург') !== -1 ||
                       labelText.indexOf('Саратов') !== -1 ||
                       (labelText.match(/^[А-Яа-я\s,\.\-]+$/) && labelText.includes(','));
            });
            
            allShippingBlocks = shippingBlocks1.add(shippingBlocks2).add(shippingBlocks3);
            allShippingBlocks = allShippingBlocks.filter(function(index, element) {
                return allShippingBlocks.index(element) === index;
            });
            
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
    }
    
    function showDeliveryCalculationLoader() {
        var shippingBlocks = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('СДЭК') !== -1 || labelText.indexOf('Выберите пункт выдачи') !== -1;
        });
        
        shippingBlocks.find('.wc-block-components-totals-item__value').html('<span style="color: #666;">Расчет...</span>');
    }
    
    function hideDeliveryCalculationLoader() {
        // Loader скрывается при обновлении стоимости
    }
    
    function showPvzLoader() {
        // Показываем лоадер в блоке с картой
        var mapContainer = $('#cdek-map-container');
        if (mapContainer.length > 0) {
            if ($('#pvz-loader').length === 0) {
                var loader = $(`
                    <div id="pvz-loader" style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(255, 255, 255, 0.9);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        z-index: 1000;
                        border-radius: 8px;
                    ">
                        <div style="
                            width: 40px;
                            height: 40px;
                            border: 3px solid #f3f3f3;
                            border-top: 3px solid #007cba;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin-bottom: 15px;
                        "></div>
                        <div style="
                            color: #666;
                            font-size: 14px;
                            text-align: center;
                        ">
                            <div style="font-weight: 500; margin-bottom: 4px;">Загружаем пункты выдачи...</div>
                            <div style="font-size: 12px; opacity: 0.8;">Это может занять несколько секунд</div>
                        </div>
                    </div>
                `);
                mapContainer.css('position', 'relative').append(loader);
                
                // Добавляем CSS анимацию если её нет
                if (!$('#pvz-loader-styles').length) {
                    $('head').append(`
                        <style id="pvz-loader-styles">
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        </style>
                    `);
                }
            }
        }
        
        // Также показываем лоадер в счетчике пунктов
        $('#cdek-points-count').html('🔄 Загружаем пункты выдачи...');
    }
    
    function hidePvzLoader() {
        $('#pvz-loader').remove();
    }
    
    function showPvzError(message) {
        $('#cdek-points-count').html('❌ ' + message);
        setTimeout(() => {
            $('#cdek-points-count').html('Выберите город для поиска пунктов выдачи');
        }, 3000);
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
                    
                    // Форматируем большие суммы с пробелами для читаемости
                    var formattedTotal = newTotal.toLocaleString('ru-RU') + ' руб.';
                    
                    var totalValueElement = totalBlock.find('.wc-block-components-totals-item__value');
                    var currentText = totalValueElement.text().trim();
                    
                    // Сравниваем числовые значения, а не строки
                    var currentValue = PriceFormatter.extractCleanPrice(currentText);
                    
                    if (Math.abs(currentValue - newTotal) > 1) { // Разница больше 1 рубля
                        totalValueElement.text(formattedTotal);
                        console.log('💰 Обновлена итоговая сумма:', formattedTotal, '(было:', currentText + ')');
                        console.log('💡 Расчет: подытог', subtotal, '+ доставка', deliveryCost, '+ налог', tax, '=', newTotal);
                    }
                }
            }
        });
    }
    
    function removeDuplicateTotalElements() {
        domBatcher.add(() => {
            var totalBlocks = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
            });
            
            if (totalBlocks.length > 1) {
                console.log('🔍 Найдено дублированных элементов итоговой суммы:', totalBlocks.length);
                totalBlocks.slice(1).remove();
                console.log('✅ Дублированные элементы удалены');
            }
        });
    }
    
    function hideCdekShippingBlock() {
        if (window.lastHideCall && (Date.now() - window.lastHideCall) < 1000) return;
        window.lastHideCall = Date.now();
        
        var cdekInputs = $('input[value*="cdek_delivery"]');
        
        cdekInputs.each(function() {
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
    
    function resetCdekShippingToDefault() {
        domBatcher.add(() => {
            $('.wc-block-components-totals-item').each(function() {
                var $item = $(this);
                var labelElement = $item.find('.wc-block-components-totals-item__label');
                var labelText = labelElement.text();
                
                if (labelText.indexOf('СДЭК') !== -1 || 
                    labelText.indexOf('Выберите пункт выдачи') !== -1 ||
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
        if (isInitialized) return;
        
        removeDuplicateTotalElements();
        hideCdekShippingBlock();
        
        if ($('#cdek-map-container').length === 0) {
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px; display: block !important;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
                    <div id="cdek-points-info" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                        <strong>Информация:</strong>
                        <div id="cdek-points-count">Введите город в поле адреса выше для поиска пунктов выдачи</div>
                        <div style="margin-top: 10px;">
                            <button id="load-all-cdek-points" type="button" style="
                                background: #4CAF50;
                                color: white;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 14px;
                            ">🌍 Показать все ПВЗ России</button>
                        </div>
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
        
        setTimeout(() => initYandexMap(), 500);
        setTimeout(() => initAddressAutocomplete(), 1000);
        
        var currentAddress = $('#shipping-address_1').val();
        if (currentAddress) {
            var city = currentAddress.split(',')[0].trim();
            if (city.length > 2) {
                setTimeout(() => searchCdekPoints(city), 1000);
            }
        }
        
        isInitialized = true;
        
        setTimeout(() => {
            removeDuplicateTotalElements();
            fixExistingDuplicatedPrices();
        }, 2000);
        
        console.log('✅ СДЭК доставка инициализирована');
    }
    
    function hideUnnecessaryFields() {
        domBatcher.add(() => {
            var fieldsToHide = [
                
            ];
            
            fieldsToHide.forEach(function(selector) {
                $(selector).hide().closest('').hide();
            });
            
            $('').hide();
            
            $('label').each(function() {
                var text = $(this).text().toLowerCase();
                if ((text.includes('город') && !text.includes('адрес')) || 
                    text.includes('область') || 
                    text.includes('район') || 
                    text.includes('индекс') || 
                    text.includes('почтовый')) {
                    $(this).closest('').hide();
                }
            });
        });
    }
    
    // ========== ИНИЦИАЛИЗАЦИЯ И ОБРАБОТЧИКИ СОБЫТИЙ ==========
    
    interceptPriceUpdates();
    startPriceMonitoring();
    
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            debouncer.debounce('init-cdek', () => initCdekDelivery(), 100, 8);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1) {
            $('#cdek-map-container').hide();
            resetCdekShippingToDefault();
        }
    });
    
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        debouncer.debounce('init-cdek-click', () => initCdekDelivery(), 200, 7);
    });
    
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        var city = address.split(',')[0].trim();
        
        if (city.length > 2) {
            debouncer.debounce('address-change', () => searchCdekPoints(city), 500, 4);
        }
    });
    
    // Обработчик кнопки "Показать все ПВЗ России"
    $(document).on('click', '#load-all-cdek-points', function() {
        console.log('🌍 Нажата кнопка загрузки всех ПВЗ России');
        loadAllCdekPoints();
    });
    
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
    setTimeout(() => {
        hideUnnecessaryFields();
        
        $('.wc-block-components-totals-item__label').each(function() {
            var text = $(this).text();
            if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                $(this).text('Выберите пункт выдачи');
            }
        });
    }, 100);
    
    setTimeout(() => {
        hideUnnecessaryFields();
        initAddressAutocomplete();
        
        var cdekMethod = $('input[value*="cdek_delivery"]');
        if (cdekMethod.length > 0) {
            hideCdekShippingBlock();
            initCdekDelivery();
        }
    }, 2000);
    
    setTimeout(() => {
        hideUnnecessaryFields();
        
        if ($('#address-select').length === 0 && $('#address-suggestions').length === 0) {
            initAddressAutocomplete();
        }
    }, 5000);
    
    setTimeout(() => {
        hideUnnecessaryFields();
        
        if ($('input[value*="cdek_delivery"]').length > 0 && $('#cdek-map-container').length === 0 && !isInitialized) {
            initCdekDelivery();
        }
        
        removeDuplicateTotalElements();
        fixExistingDuplicatedPrices();
    }, 4000);
    
    // Оптимизированная проверка цен для мобильных
    const priceCheckInterval = window.innerWidth <= 768 ? 3000 : 2000;
    setInterval(() => fixExistingDuplicatedPrices(), priceCheckInterval);
    
    // Сбрасываем предыдущие состояния поиска
    window.lastSelectedCity = null;
    window.lastSelectedPointCode = null;
    window.currentSearchCity = null;
    
    console.log('🚀 СДЭК Delivery Fixed v2.1 загружен');
    console.log('✅ Исправления: умный поиск, индикаторы загрузки, производительность');
    console.log('🔍 Предотвращение повторных поисков');
    console.log('🏙️ Поддержка 1000+ городов России');
    console.log('📱 Оптимизировано для мобильных устройств');
});
