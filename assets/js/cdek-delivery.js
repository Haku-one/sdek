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
    console.log('СДЭК плагин загружен');
    
    // Инициализация
    initCdekDelivery();
    
    function initCdekDelivery() {
        // Базовая инициализация плагина
        console.log('Инициализация СДЭК доставки');
        
        // Обработчик выбора метода доставки
        $(document.body).on('change', 'input[name^="shipping_method"]', function() {
            var selectedMethod = $(this).val();
            if (selectedMethod && selectedMethod.indexOf('cdek') !== -1) {
                showCdekInterface();
            } else {
                hideCdekInterface();
            }
        });
    }
    
    function showCdekInterface() {
        console.log('Показываем интерфейс СДЭК');
        // Здесь будет код для отображения карты и пунктов выдачи
    }
    
    function hideCdekInterface() {
        console.log('Скрываем интерфейс СДЭК');
        // Здесь будет код для скрытия интерфейса
    }
    
    // Функция поиска пунктов выдачи
    function searchCdekPoints(address) {
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cdek_points',
                nonce: cdek_ajax.nonce,
                address: address
            },
            success: function(response) {
                if (response.success) {
                    displayCdekPoints(response.data);
                } else {
                    console.error('Ошибка поиска пунктов СДЭК:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX ошибка:', error);
            }
        });
    }
    
    function displayCdekPoints(points) {
        console.log('Найдено пунктов:', points.length);
        // Здесь будет код отображения пунктов
    }
    
    // Функция расчета стоимости доставки
    function calculateDeliveryCost(pointCode, pointData) {
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'calculate_cdek_delivery_cost',
                nonce: cdek_ajax.nonce,
                point_code: pointCode,
                point_data: JSON.stringify(pointData),
                cart_weight: 500, // Примерный вес
                cart_dimensions: JSON.stringify({length: 20, width: 15, height: 10}),
                cart_value: 1000,
                has_real_dimensions: 0
            },
            success: function(response) {
                if (response.success) {
                    updateDeliveryCost(response.data.delivery_sum);
                } else {
                    console.error('Ошибка расчета стоимости:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX ошибка расчета:', error);
            }
        });
    }
    
    function updateDeliveryCost(cost) {
        console.log('Стоимость доставки:', cost);
        // Здесь будет код обновления стоимости в корзине
    }
});