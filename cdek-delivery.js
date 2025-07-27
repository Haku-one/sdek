jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    var isInitialized = false; // Флаг для предотвращения повторной инициализации
    var observerActive = false; // Флаг для контроля наблюдателя
    
    // Глобальные переменные для поиска
    window.currentSearchCity = null;
    window.currentSearchStreet = null;
    window.currentSearchCoordinates = null; // Координаты для умного поиска
    window.addressSuggestions = []; // Предложения адресов
    
    // ====== ОПРЕДЕЛЕНИЕ ВСЕХ ФУНКЦИЙ В НАЧАЛЕ ======
    
    function parseAddress(address) {
        var result = {
            city: '',
            street: ''
        };
        
        if (!address || address.trim() === '') {
            return result;
        }
        
        // Убираем лишние пробелы и приводим к нижнему регистру для анализа
        var cleanAddress = address.trim().toLowerCase();
        
        // Список сокращений улиц и известных названий
        var streetTypes = [
            'ул', 'улица', 'проспект', 'пр', 'пр-т', 'пр-кт', 'переулок', 'пер', 
            'шоссе', 'ш', 'набережная', 'наб', 'площадь', 'пл', 'бульвар', 'б-р',
            'проезд', 'проезд', 'тупик', 'туп', 'аллея', 'ал', 'линия', 'л-я',
            'октябрьский', 'красная', 'зеленая', 'синяя', 'желтая', 'белая', 'черная'
        ];
        
        // Список известных улиц
        var knownStreets = [
            'невский', 'ленина', 'пушкина', 'гагарина', 'мира', 'арбат', 'тверская',
            'октябрьская', 'красная', 'зеленая', 'синяя', 'желтая', 'белая', 'черная',
            'московская', 'ленинградская', 'киевская', 'минская', 'рижская', 'таллинская',
            'вильнюсская', 'рижская', 'варшавская', 'пражская', 'берлинская', 'парижская',
            'лондонская', 'римская', 'мадридская', 'афинская', 'стамбульская', 'каирская',
            'токийская', 'пекинская', 'сеульская', 'сиднейская', 'нью-йоркская', 'лондонская'
        ];
        
        // Ищем город и улицу
        var cityFound = false;
        var streetFound = false;
        
        // Разбиваем адрес на части
        var parts = address.split(/[,\s]+/);
        
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (!part) continue;
            
            // Проверяем, является ли часть типом улицы
            var isStreetType = streetTypes.some(function(type) {
                return part.toLowerCase() === type;
            });
            
            // Проверяем, является ли это названием известной улицы
            var isKnownStreet = knownStreets.some(function(street) {
                return part.toLowerCase().indexOf(street) !== -1;
            });
            
            // Проверяем, содержит ли часть слово "улица" или "ул"
            var containsStreetWord = part.toLowerCase().indexOf('улица') !== -1 || 
                                   part.toLowerCase().indexOf('ул') !== -1;
            
            if (isStreetType || isKnownStreet || containsStreetWord) {
                streetFound = true;
                // Собираем улицу
                var streetParts = [];
                for (var j = i; j < parts.length; j++) {
                    streetParts.push(parts[j]);
                }
                result.street = streetParts.join(' ');
                break;
            } else if (!cityFound && !streetFound) {
                // Это город
                result.city = part;
                cityFound = true;
            }
        }
        
        // Если город не найден, берем первое слово
        if (!result.city && parts.length > 0) {
            result.city = parts[0].trim();
        }
        
        return result;
    }
    
    // Инициализация автокомплита адресов с Select2
    function initAddressAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        // Удаляем предыдущий автокомплит если есть
        $('#address-select').remove();
        $('#address-suggestions').remove();
    }
    
    // Загрузка Select2
    function loadSelect2() {
        // Загружаем CSS
        if (!$('link[href*="select2"]').length) {
            $('head').append('<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />');
        }
        
        // Загружаем JS
        $.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js')
            .done(function() {
                setupSelect2Autocomplete();
            })
            .fail(function() {
                setupBasicAutocomplete();
            });
    }
    
    // Настройка Select2 автокомплита
    function setupSelect2Autocomplete() {
        var addressInput = $('#shipping-address_1');
        
        // Создаем скрытый select элемент
        var selectElement = $('<select id="address-select" style="display: none;"></select>');
        addressInput.after(selectElement);
        
        // Инициализируем Select2
        selectElement.select2({
            placeholder: 'Введите город и улицу...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: cdek_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'get_address_suggestions',
                        search: params.term,
                        nonce: cdek_ajax.nonce
                    };
                },
                processResults: function(data) {
                    if (data.success && data.data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.value,
                                    text: item.text,
                                    city: item.city,
                                    street: item.street
                                };
                            })
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            templateResult: formatAddressOption,
            templateSelection: formatAddressSelection
        });
        
        // Обработчик выбора адреса
        selectElement.on('select2:select', function(e) {
            var data = e.params.data;
            
            // Устанавливаем значение в поле ввода
            addressInput.val(data.text);
            
            // Обновляем переменные поиска
            window.currentSearchCity = data.city || '';
            window.currentSearchStreet = data.street || '';
            
            // Запускаем поиск
            searchCdekPoints();
        });
        
        // Синхронизируем с оригинальным полем
        addressInput.on('input', function() {
            var value = $(this).val();
            if (value && !selectElement.val()) {
                // Если пользователь вводит текст вручную, обновляем переменные
                var parsed = parseAddress(value);
                window.currentSearchCity = parsed.city;
                window.currentSearchStreet = parsed.street;
            }
        });
    }
    
    // Форматирование опции в выпадающем списке
    function formatAddressOption(option) {
        if (!option.id) return option.text;
        
        var $option = $('<span></span>');
        if (option.city && option.street) {
            $option.html('<strong>' + option.city + '</strong> - ' + option.street);
        } else {
            $option.text(option.text);
        }
        return $option;
    }
    
    // Форматирование выбранного адреса
    function formatAddressSelection(option) {
        if (!option.id) return option.text;
        return option.text;
    }
    
    // Обычный автокомплит (fallback)
    function setupBasicAutocomplete() {
        var addressInput = $('#shipping-address_1');
        if (addressInput.length === 0) {
            return;
        }
        
        // Создаем контейнер для предложений
        var suggestionsContainer = $('<div id="address-suggestions" style="position: absolute; background: white; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>');
        addressInput.parent().css('position', 'relative');
        addressInput.parent().append(suggestionsContainer);
        
        // Обработчик ввода
        addressInput.on('input', function() {
            var query = $(this).val().trim();
            
            if (query.length >= 2) {
                // Генерируем предложения на основе известных городов и улиц
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
        
        // Скрываем предложения при клике вне поля
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#address-suggestions, #shipping-address_1').length) {
                hideAddressSuggestions();
            }
        });
    }
    
    function generateAddressSuggestions(query) {
        var suggestions = [];
        var queryLower = query.toLowerCase();
        
        // Список городов
        var cities = [
            'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань', 'Нижний Новгород',
            'Челябинск', 'Самара', 'Уфа', 'Ростов-на-Дону', 'Краснодар', 'Пермь', 'Воронеж',
            'Волгоград', 'Красноярск', 'Саратов', 'Тюмень', 'Тольятти', 'Ижевск', 'Барнаул',
            'Ульяновск', 'Иркутск', 'Хабаровск', 'Ярославль', 'Владивосток', 'Махачкала',
            'Томск', 'Оренбург', 'Кемерово', 'Новокузнецк', 'Рязань', 'Астрахань', 'Набережные Челны',
            'Пенза', 'Липецк', 'Киров', 'Чебоксары', 'Тула', 'Калининград', 'Курск', 'Улан-Удэ'
        ];
        
        // Список улиц по всей России
        var streets = [
            // Популярные улицы
            'улица Ленина', 'улица Пушкина', 'улица Гагарина', 'улица Мира', 'улица Октябрьская',
            'проспект Ленина', 'проспект Мира', 'Невский проспект', 'улица Тверская', 'улица Арбат',
            'улица Красная', 'улица Зеленая', 'улица Синяя', 'улица Желтая', 'улица Белая',
            'улица Черная', 'улица Московская', 'улица Ленинградская', 'улица Киевская',
            'улица Минская', 'улица Рижская', 'улица Таллинская', 'улица Вильнюсская',
            'улица Варшавская', 'улица Пражская', 'улица Берлинская', 'улица Парижская',
            'улица Лондонская', 'улица Римская', 'улица Мадридская', 'улица Афинская',
            
            // Дополнительные улицы
            'улица Советская', 'улица Центральная', 'улица Школьная', 'улица Садовая', 'улица Лесная',
            'улица Речная', 'улица Горная', 'улица Солнечная', 'улица Весенняя', 'улица Летняя',
            'улица Осенняя', 'улица Зимняя', 'улица Новая', 'улица Старая', 'улица Большая',
            'улица Малая', 'улица Верхняя', 'улица Нижняя', 'улица Северная', 'улица Южная',
            'улица Восточная', 'улица Западная', 'улица Главная', 'улица Первая', 'улица Вторая'
        ];
        
        // Ищем совпадения в городах
        cities.forEach(function(city) {
            if (city.toLowerCase().indexOf(queryLower) !== -1) {
                suggestions.push(city);
            }
        });
        
        // Ищем совпадения в улицах
        streets.forEach(function(street) {
            if (street.toLowerCase().indexOf(queryLower) !== -1) {
                suggestions.push(street);
            }
        });
        
        // Добавляем комбинации город + улица
        cities.forEach(function(city) {
            streets.forEach(function(street) {
                var fullAddress = city + ' ' + street;
                if (fullAddress.toLowerCase().indexOf(queryLower) !== -1) {
                    suggestions.push(fullAddress);
                }
            });
        });
        
        // Ограничиваем количество предложений
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
                
                // Запускаем поиск пунктов
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
    
    function calculateDeliveryCost(point) {
        // Получаем общий вес корзины из DOM
        var cartWeight = 0;
        
        // Ищем все товары и их веса
        $('.wc-block-components-order-summary-item').each(function() {
            var $item = $(this);
            
            // Получаем количество товара
            var quantityElement = $item.find('.wc-block-components-order-summary-item__quantity span[aria-hidden="true"]');
            var quantity = 1;
            
            if (quantityElement.length > 0) {
                quantity = parseInt(quantityElement.text()) || 1;
            }
            
            // Получаем вес одной единицы товара
            var weightElement = $item.find('.wc-block-components-product-details__value').filter(function() {
                var siblingLabel = $(this).siblings('.wc-block-components-product-details__name');
                return siblingLabel.text().indexOf('Вес') !== -1;
            });
            
            if (weightElement.length > 0) {
                var weightText = weightElement.text().trim();
                var weightMatch = weightText.match(/(\d+(?:\.\d+)?)/);
                
                if (weightMatch) {
                    var weight = parseFloat(weightMatch[1]);
                    
                    // Конвертируем в килограммы если вес в граммах
                    if (weightText.includes('гр') || weightText.includes('г')) {
                        weight = weight / 1000;
                    }
                    
                    // Умножаем на количество и добавляем к общему весу
                    cartWeight += weight * quantity;
                }
            }
        });
        
        // Если вес не найден, используем минимальный вес
        if (cartWeight === 0) {
            cartWeight = 0.5; // Минимальный вес 500г
        }
        
        // Координаты Саратова (откуда отправляем)
        var saratovLat = 51.5924;
        var saratovLon = 46.0347;
        
        // Рассчитываем расстояние от Саратова до пункта выдачи
        var distance = 50; // Базовое расстояние если координаты не найдены
        
        if (point && point.location && point.location.latitude && point.location.longitude) {
            distance = calculateDistance(saratovLat, saratovLon, point.location.latitude, point.location.longitude);
        }
        
        // Базовая стоимость доставки от Саратова
        var baseCost = 200; // Базовая стоимость 200 руб
        
        // Дополнительная стоимость за расстояние
        if (distance > 100) {
            var extraDistance = distance - 100;
            var distanceCost = Math.ceil(extraDistance / 100) * 150; // +150 руб за каждые 100 км свыше 100 км
            baseCost += distanceCost;
        }
        
        // Дополнительная стоимость за вес свыше 1 кг
        if (cartWeight > 1) {
            var extraWeight = cartWeight - 1;
            var weightCost = Math.ceil(extraWeight) * 100; // +100 руб за каждый кг свыше 1 кг
            baseCost += weightCost;
        }
        
        return baseCost;
    }
    
    function updateOrderTotal(deliveryCost) {
        // Находим блок с общей суммой
        var totalBlock = $('.wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('Итого') !== -1 || labelText.indexOf('Total') !== -1;
        });
        
        if (totalBlock.length > 0) {
            // Получаем подытог
            var subtotalBlock = $('.wc-block-components-totals-item').filter(function() {
                var labelText = $(this).find('.wc-block-components-totals-item__label').text();
                return labelText.indexOf('Подытог') !== -1 || labelText.indexOf('Subtotal') !== -1;
            });
            
            if (subtotalBlock.length > 0) {
                var subtotalText = subtotalBlock.find('.wc-block-components-totals-item__value').text();
                var subtotal = parseInt(subtotalText.replace(/[^\d]/g, ''));
                
                // Получаем налог если есть
                var taxBlock = $('.wc-block-components-totals-taxes .wc-block-components-totals-item__value');
                var tax = 0;
                if (taxBlock.length > 0) {
                    var taxText = taxBlock.text();
                    tax = parseInt(taxText.replace(/[^\d]/g, '')) || 0;
                }
                
                // Рассчитываем новую общую сумму
                var newTotal = subtotal + deliveryCost + tax;
                
                // Обновляем общую сумму
                var totalValueElement = totalBlock.find('.wc-block-components-totals-item__value');
                totalValueElement.text(newTotal + ' руб.');
            }
        }
    }
    
    function calculateDistance(lat1, lon1, lat2, lon2) {
        // Формула гаверсинуса для расчета расстояния между двумя точками
        var R = 6371; // Радиус Земли в километрах
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        var distance = R * c;
        return distance;
    }
    
    function geocodeAddress(address, callback) {
        // Используем Yandex Maps API для геокодирования
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
    
    function hideCdekShippingBlock() {
        // Проверяем, не вызывали ли мы уже эту функцию недавно
        if (window.lastHideCall && (Date.now() - window.lastHideCall) < 1000) {
            return;
        }
        window.lastHideCall = Date.now();
        
        var cdekInputs = $('input[value*="cdek_delivery"]');
        
        cdekInputs.each(function(index, element) {
            // Скрываем родительские контейнеры
            var $this = $(this);
            var radioControl = $this.closest('.wc-block-components-radio-control');
            var package = $this.closest('.wc-block-components-shipping-rates-control__package');
            var control = $this.closest('.wc-block-components-shipping-rates-control');
            var label = $this.closest('label');
            
            if (radioControl.length) radioControl.hide();
            if (package.length) package.hide();
            if (control.length) control.hide();
            if (label.length) label.hide();
            
            // Дополнительно скрываем через CSS
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
    
    function initYandexMap() {
        if (cdekMap) {
            return;
        }
        
        // Проверяем что ymaps загружен
        if (typeof ymaps === 'undefined') {
            setTimeout(initYandexMap, 1000);
            return;
        }
        
        // Проверяем контейнер
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) {
            setTimeout(initYandexMap, 500);
            return;
        }
        
        // КРИТИЧНО: принудительно делаем контейнер видимым и с размерами
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important;';
        
        // Ждем пока контейнер получит размеры
        var checkContainer = function() {
            if (mapContainer.offsetWidth > 0 && mapContainer.offsetHeight > 0) {
                try {
                    cdekMap = new ymaps.Map(mapContainer, {
                        center: [55.753994, 37.622093],
                        zoom: 10,
                        controls: ['zoomControl', 'searchControl']
                    });
                
                // Если у нас есть точки, отображаем их сразу
                if (cdekPoints && cdekPoints.length > 0) {
                    displayCdekPoints(cdekPoints);
                }
                } catch (error) {
                    // Попробуем еще раз через секунду
                    setTimeout(function() {
                        cdekMap = null;
                        initYandexMap();
                    }, 1000);
                }
            } else {
                setTimeout(checkContainer, 300);
            }
        };
        
        // Небольшая задержка для рендеринга DOM
        setTimeout(checkContainer, 200);
    }
    
    function searchCdekPoints(address) {
        // Парсим адрес для извлечения города и улицы
        var parsedAddress = parseAddress(address);
        window.currentSearchCity = parsedAddress.city;
        window.currentSearchStreet = parsedAddress.street;
        
        // Проверяем доступность cdek_ajax
        if (typeof cdek_ajax === 'undefined') {
            return;
        }
        
        // Сначала геокодируем адрес для получения координат
        geocodeAddress(address, function(coords) {
            window.currentSearchCoordinates = coords;
            
            // Продолжаем поиск пунктов
            performCdekSearch();
        });
    }
    
    function performCdekSearch() {
        // Проверяем кэш
        var currentTime = Date.now();
        if (cdekPointsCache && (currentTime - lastSearchTime) < cacheExpiry) {
            displayCdekPoints(cdekPointsCache);
            return;
        }
        
        // Защита от множественных запросов
        if (isSearching) {
            return;
        }
        
        isSearching = true;
        
        // Всегда запрашиваем все пункты по России для умного поиска
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'get_cdek_points',
                address: 'Россия', // Запрашиваем все пункты по России
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Кэшируем данные
                    cdekPointsCache = response.data;
                    lastSearchTime = currentTime;
                    
                    displayCdekPoints(response.data);
                }
                
                // Разблокируем новые запросы
                isSearching = false;
            },
            error: function(xhr, status, error) {
                // Разблокируем новые запросы
                isSearching = false;
                
                // Если есть кэшированные данные, используем их
                if (cdekPointsCache) {
                    displayCdekPoints(cdekPointsCache);
                }
                
                // Если ошибка HTTP/2, пробуем повторить запрос через 5 секунд
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
        
        // Проверяем что карта и ymaps готовы
        if (!cdekMap || typeof ymaps === 'undefined') {
            setTimeout(function() {
                displayCdekPoints(points);
            }, 1000);
            return;
        }
        
        // Очищаем предыдущие метки
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            var cityInfo = window.currentSearchCity ? ` в городе "${window.currentSearchCity}"` : '';
            $('#cdek-points-count').text(`Пункты выдачи не найдены${cityInfo}`);
            // Показываем сообщение на карте
            var noPointsPlacemark = new ymaps.Placemark([55.753994, 37.622093], {
                balloonContent: `Пункты выдачи${cityInfo} не найдены`
            }, {
                preset: 'islands#grayIcon'
            });
            cdekMap.geoObjects.add(noPointsPlacemark);
            cdekMap.setCenter([55.753994, 37.622093], 10);
            return;
        }
        
        // Фильтруем и ограничиваем количество отображаемых пунктов
        var filteredPoints = points.filter(function(point) {
            // Показываем только пункты выдачи заказов (PVZ)
            if (point.type !== 'PVZ' && point.type) {
                return false;
            }
            
            // Умная фильтрация по всей России
            var pointCity = '';
            var pointAddress = '';
            
            // Извлекаем город из различных источников с приоритетом
            
            // 1. Приоритет: location.city
            if (point.location && point.location.city) {
                pointCity = point.location.city.trim();
            }
            
            // 2. Из названия пункта (между запятыми)
            if (!pointCity && point.name && point.name.includes(',')) {
                var nameParts = point.name.split(',');
                if (nameParts.length >= 2) {
                    pointCity = nameParts[1].trim();
                }
            }
            
            // 3. Из полного адреса (обычно город идет после индекса и страны)
            if (!pointCity && point.location && point.location.address_full) {
                var addressParts = point.location.address_full.split(',');
                // Пробуем разные позиции в адресе
                for (var i = 0; i < addressParts.length; i++) {
                    var part = addressParts[i].trim();
                    // Ищем часть, которая похожа на название города (не индекс, не улица)
                    if (part && 
                        !part.match(/^\d{6}$/) && // не индекс
                        !part.match(/^россия$/i) && // не страна
                        !part.match(/^(ул|улица|пр|проспект|пер|переулок)/i) && // не улица
                        part.length > 2) {
                        pointCity = part;
                        break;
                    }
                }
            }
            
            // 4. Дополнительная очистка названия города
            if (pointCity) {
                // Убираем лишние символы и приводим к стандартному виду
                pointCity = pointCity
                    .replace(/^(г\.?\s*|город\s+)/i, '') // убираем "г." или "город"
                    .replace(/\s*область$/i, '') // убираем "область" 
                    .replace(/\s*край$/i, '') // убираем "край"
                    .trim();
            }
            
            // Получаем полный адрес для поиска улицы
            if (point.location && point.location.address_full) {
                pointAddress = point.location.address_full.toLowerCase();
            } else if (point.address) {
                pointAddress = point.address.toLowerCase();
            }
            
            // Если указан город, проверяем соответствие
            if (window.currentSearchCity) {
                var searchCityLower = window.currentSearchCity.toLowerCase().trim();
                var pointCityLower = pointCity.toLowerCase().trim();
                
                // Проверяем точное совпадение
                var exactMatch = pointCityLower === searchCityLower;
                
                // Проверяем, что искомый город является полным словом в названии пункта
                var wordBoundaryMatch = false;
                if (!exactMatch) {
                    // Создаем регулярное выражение для поиска полного слова
                    var searchPattern = new RegExp('\\b' + searchCityLower.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
                    wordBoundaryMatch = searchPattern.test(pointCityLower);
                }
                
                // Дополнительная проверка для исключения похожих названий
                var isSimilarButDifferent = false;
                if (wordBoundaryMatch || pointCityLower.indexOf(searchCityLower) !== -1) {
                    // Список исключений для точного поиска
                    var exclusions = [
                        // Саратов vs Новосаратовка
                        { search: 'саратов', exclude: ['новосаратовка', 'саратовка'] },
                        // Уфа vs Верхний Уфалей
                        { search: 'уфа', exclude: ['уфалей', 'верхний уфалей'] },
                        // Москва vs Подмосковье
                        { search: 'москва', exclude: ['подмосковье', 'московская область'] },
                        // Санкт-Петербург vs Петербургский
                        { search: 'санкт-петербург', exclude: ['петербургский'] },
                        { search: 'петербург', exclude: ['петербургский'] },
                        // Казань vs Казанское
                        { search: 'казань', exclude: ['казанское', 'казанский'] },
                        // Пермь vs Пермское
                        { search: 'пермь', exclude: ['пермское', 'пермский'] },
                        // Тула vs Тульский
                        { search: 'тула', exclude: ['тульский', 'тульское'] }
                    ];
                    
                    exclusions.forEach(function(rule) {
                        if (searchCityLower === rule.search) {
                            rule.exclude.forEach(function(excludePattern) {
                                if (pointCityLower.indexOf(excludePattern) !== -1) {
                                    isSimilarButDifferent = true;
                                }
                            });
                        }
                    });
                }
                
                // Исключаем если это похожий, но другой город
                if (isSimilarButDifferent) {
                    return false;
                }
                
                // Принимаем только при точном совпадении или совпадении по границам слов
                if (!exactMatch && !wordBoundaryMatch) {
                    return false;
                }
            }
            
            // Если указана улица, ищем пункты с этой улицей по всей России
            if (window.currentSearchStreet && pointAddress) {
                var searchStreetLower = window.currentSearchStreet.toLowerCase();
                
                // Очищаем название улицы от лишних слов
                var cleanSearchStreet = searchStreetLower
                    .replace('улица', '')
                    .replace('ул', '')
                    .replace('проспект', '')
                    .replace('пр', '')
                    .replace('переулок', '')
                    .replace('пер', '')
                    .trim();
                
                // Проверяем, содержит ли адрес указанную улицу
                var streetFound = false;
                
                // Проверяем точное совпадение
                if (pointAddress.indexOf(searchStreetLower) !== -1) {
                    streetFound = true;
                }
                
                // Проверяем совпадение без типа улицы
                if (!streetFound && pointAddress.indexOf(cleanSearchStreet) !== -1) {
                    streetFound = true;
                }
                
                // Проверяем совпадение по ключевым словам
                if (!streetFound) {
                    var streetKeywords = cleanSearchStreet.split(' ');
                    var matchCount = 0;
                    streetKeywords.forEach(function(keyword) {
                        if (keyword.length > 2 && pointAddress.indexOf(keyword) !== -1) {
                            matchCount++;
                        }
                    });
                    if (matchCount >= streetKeywords.length * 0.7) { // 70% совпадение
                        streetFound = true;
                    }
                }
                
                if (!streetFound) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Сортируем по расстоянию, если есть координаты поиска
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
        } else if (filteredPoints.length > 0) {
            // Если координаты не найдены, сортируем по алфавиту города
            filteredPoints.sort(function(a, b) {
                var cityA = '';
                var cityB = '';
                
                // Извлекаем город из названия пункта
                if (a.name && a.name.includes(',')) {
                    cityA = a.name.split(',')[1].trim();
                }
                if (b.name && b.name.includes(',')) {
                    cityB = b.name.split(',')[1].trim();
                }
                
                return cityA.localeCompare(cityB);
            });
        }
        
        var maxPoints = 380;
        var pointsToShow = filteredPoints.slice(0, maxPoints);
        
        // Обновляем информацию о количестве пунктов
        var pointsInfo = '';
        if (filteredPoints.length > 0) {
            var locationInfo = '';
            if (window.currentSearchCity && window.currentSearchStreet) {
                locationInfo = ` с улицей "${window.currentSearchStreet}" в городе "${window.currentSearchCity}"`;
            } else if (window.currentSearchCity) {
                locationInfo = ` в городе "${window.currentSearchCity}"`;
            } else if (window.currentSearchStreet) {
                locationInfo = ` с улицей "${window.currentSearchStreet}" по всей России`;
            }
            pointsInfo = `Найдено ${filteredPoints.length} пунктов выдачи${locationInfo}`;
            if (filteredPoints.length > maxPoints) {
                pointsInfo += ` (показано ${maxPoints} ближайших)`;
            }
        } else {
            var locationInfo = '';
            if (window.currentSearchCity && window.currentSearchStreet) {
                locationInfo = ` с улицей "${window.currentSearchStreet}" в городе "${window.currentSearchCity}"`;
            } else if (window.currentSearchCity) {
                locationInfo = ` в городе "${window.currentSearchCity}"`;
            } else if (window.currentSearchStreet) {
                locationInfo = ` с улицей "${window.currentSearchStreet}" по всей России`;
            }
            pointsInfo = `Пункты выдачи не найдены${locationInfo}`;
        }
        $('#cdek-points-count').text(pointsInfo);
        
        var bounds = [];
        
        pointsToShow.forEach(function(point, index) {
            if (point.location && point.location.latitude && point.location.longitude) {
                var coords = [point.location.latitude, point.location.longitude];
                bounds.push(coords);
                
                // Создаем метку на карте
                var placemark = new ymaps.Placemark(coords, {
                    balloonContent: formatPointInfo(point),
                    hintContent: point.name
                }, {
                    preset: 'islands#redIcon'
                });
                
                // Обработчик клика по метке
                placemark.events.add('click', function() {
                    selectCdekPoint(point);
                });
                
                cdekMap.geoObjects.add(placemark);
            }
        });
        
        // Подгоняем масштаб карты под все точки
        if (bounds.length > 0) {
            if (bounds.length === 1) {
                // Если только одна точка, центрируем на ней
                cdekMap.setCenter(bounds[0], 14);
            } else {
                // Если несколько точек, рассчитываем оптимальный обзор
                var minLat = Math.min.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var maxLat = Math.max.apply(null, bounds.map(function(coord) { return coord[0]; }));
                var minLon = Math.min.apply(null, bounds.map(function(coord) { return coord[1]; }));
                var maxLon = Math.max.apply(null, bounds.map(function(coord) { return coord[1]; }));
                
                var centerLat = (minLat + maxLat) / 2;
                var centerLon = (minLon + maxLon) / 2;
                
                // Рассчитываем расстояние между крайними точками для определения зума
                var latDiff = maxLat - minLat;
                var lonDiff = maxLon - minLon;
                var maxDiff = Math.max(latDiff, lonDiff);
                
                var zoom = 12;
                if (maxDiff < 0.01) zoom = 15;      // Очень близко
                else if (maxDiff < 0.05) zoom = 13; // Близко
                else if (maxDiff < 0.1) zoom = 12;  // Средне
                else if (maxDiff < 0.5) zoom = 10;  // Далеко
                else zoom = 8;                       // Очень далеко
                
                cdekMap.setCenter([centerLat, centerLon], zoom);
            }
        } else if (window.currentSearchCoordinates) {
            // Если нет пунктов, но есть координаты поиска, центрируем на них
            cdekMap.setCenter(window.currentSearchCoordinates, 12);
        }
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        // Показываем информацию о выбранном пункте
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        // Центрируем карту на выбранном пункте
        if (cdekMap && point.location) {
            cdekMap.setCenter([point.location.latitude, point.location.longitude], 15);
        }
        
        // Сохраняем выбранный пункт в скрытое поле для отправки с формой
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
        
        // Сохраняем информацию о пункте
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
        
        // Обновляем информацию в блоке заказа
        updateOrderSummary(point);
    }
    
    function formatPointInfo(point) {
        // Форматируем название пункта
        var pointName = point.name || 'Пункт выдачи';
        if (pointName.includes(',')) {
            // Убираем код пункта из названия для лучшей читаемости
            pointName = pointName.split(',').slice(1).join(',').trim();
        }
        
        var html = `<strong>${pointName}</strong><br>`;
        
        // Адрес
        if (point.location && point.location.address_full) {
            html += `Адрес: ${point.location.address_full}<br>`;
        } else if (point.address) {
            html += `Адрес: ${point.address}<br>`;
        }
        
        // Телефон
        if (point.phones && Array.isArray(point.phones) && point.phones.length > 0) {
            var phoneNumbers = point.phones.map(function(phone) {
                return phone.number || phone;
            }).join(', ');
            html += `Телефон: ${phoneNumbers}<br>`;
        } else if (point.phone) {
            html += `Телефон: ${point.phone}<br>`;
        }
        
        // Режим работы
        html += `Режим работы: ${formatWorkTime(point.work_time, point.work_time_list)}<br>`;
        
        // Код пункта
        if (point.code) {
            html += `Код: ${point.code}<br>`;
        }
        
        // Примечание
        if (point.note) {
            html += `Примечание: ${point.note}<br>`;
        }
        
        // Тип пункта
        if (point.type) {
            html += `Тип: ${point.type}<br>`;
        }
        
        // Комментарий к адресу
        if (point.address_comment) {
            html += `Комментарий: ${point.address_comment}<br>`;
        }
        
        // Ближайшая станция
        if (point.nearest_station) {
            html += `Ближайшая станция: ${point.nearest_station}<br>`;
        }
        
        // Email
        if (point.email) {
            html += `Email: ${point.email}<br>`;
        }
        
        return html;
    }
    
    function updateOrderSummary(point) {
        // Рассчитываем стоимость доставки
        var deliveryCost = calculateDeliveryCost(point);
        
        // Ищем блок с информацией о доставке СДЭК (используем более широкий селектор)
        var cdekShippingBlock = $('.wc-block-components-totals-item, .wc-block-components-totals-shipping .wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('СДЭК') !== -1;
        });
        
        if (cdekShippingBlock.length > 0) {
            // Обновляем название пункта
            var labelElement = cdekShippingBlock.find('.wc-block-components-totals-item__label');
            var pointName = point.name || 'Пункт выдачи';
            if (pointName.includes(',')) {
                pointName = pointName.split(',').slice(1).join(',').trim();
            }
            labelElement.text(pointName);
            
            // Обновляем стоимость доставки
            var valueElement = cdekShippingBlock.find('.wc-block-components-totals-item__value');
            valueElement.text(deliveryCost + ' руб.');
            
            // Добавляем описание с адресом
            var descriptionElement = cdekShippingBlock.find('.wc-block-components-totals-item__description');
            var address = '';
            
            if (point.location && point.location.address_full) {
                address = point.location.address_full;
            } else if (point.address) {
                address = point.address;
            }
            
            if (address) {
                descriptionElement.html('<small style="color: #666;">' + address + '</small>');
            }
        } else {
            // Попробуем найти по другому селектору
            var alternativeBlock = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            if (alternativeBlock.length > 0) {
                var labelElement = alternativeBlock.find('.wc-block-components-totals-item__label');
                var pointName = point.name || 'Пункт выдачи';
                if (pointName.includes(',')) {
                    pointName = pointName.split(',').slice(1).join(',').trim();
                }
                labelElement.text(pointName);
                
                var valueElement = alternativeBlock.find('.wc-block-components-totals-item__value');
                valueElement.text(deliveryCost + ' руб.');
                
                var descriptionElement = alternativeBlock.find('.wc-block-components-totals-item__description');
                var address = '';
                
                if (point.location && point.location.address_full) {
                    address = point.location.address_full;
                } else if (point.address) {
                    address = point.address;
                }
                
                if (address) {
                    descriptionElement.html('<small style="color: #666;">' + address + '</small>');
                }
            }
        }
        
        // Обновляем общую сумму заказа
        updateOrderTotal(deliveryCost);
    }
    
    function formatWorkTime(workTime, workTimeList) {
        // Сначала проверяем work_time_list (массив с детальной информацией)
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
        
        // Если нет work_time_list, используем work_time (строка)
        if (workTime) {
            if (typeof workTime === 'string') {
                return workTime;
            }
            if (Array.isArray(workTime)) {
                var days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                var schedule = '';
                
                workTime.forEach(function(time) {
                    if (time.day !== undefined && time.time) {
                        schedule += days[time.day - 1] + ': ' + time.time + ' ';
                    }
                });
                
                return schedule || 'Не указан';
            }
            if (typeof workTime === 'object') {
                return JSON.stringify(workTime);
            }
        }
        
        return 'Не указан';
    }
    
    function resetCdekShippingToDefault() {
        // Сбрасываем текст доставки СДЭК к значению по умолчанию
        $('.wc-block-components-totals-item').each(function() {
            var $item = $(this);
            var labelElement = $item.find('.wc-block-components-totals-item__label');
            var labelText = labelElement.text();
            
            // Если это элемент СДЭК, сбрасываем к дефолтному состоянию
            if (labelText.indexOf('СДЭК') !== -1 || labelText.indexOf('Выберите пункт выдачи') !== -1 || 
                labelText.indexOf('Москва') !== -1 || labelText.indexOf('Санкт-Петербург') !== -1) {
                
                labelElement.text('Выберите пункт выдачи');
                
                var valueElement = $item.find('.wc-block-components-totals-item__value');
                valueElement.text('');
                
                var descriptionElement = $item.find('.wc-block-components-totals-item__description');
                descriptionElement.html('');
            }
        });
    }
    
    function initCdekBlock() {
        // Принудительно убираем старый текст "СДЭК — Пункт выдачи" везде
        $('.wc-block-components-totals-item__label').each(function() {
            var text = $(this).text();
            if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                $(this).text('Выберите пункт выдачи');
            }
        });
        
        // Ищем блок с информацией о доставке СДЭК (используем более широкий селектор)
        var cdekShippingBlock = $('.wc-block-components-totals-item, .wc-block-components-totals-shipping .wc-block-components-totals-item').filter(function() {
            var labelText = $(this).find('.wc-block-components-totals-item__label').text();
            return labelText.indexOf('СДЭК') !== -1 || labelText.indexOf('Выберите пункт выдачи') !== -1;
        });
        
        if (cdekShippingBlock.length > 0) {
            // Обновляем название на "Выберите пункт выдачи"
            var labelElement = cdekShippingBlock.find('.wc-block-components-totals-item__label');
            labelElement.text('Выберите пункт выдачи');
            
            // Убираем цену
            var valueElement = cdekShippingBlock.find('.wc-block-components-totals-item__value');
            valueElement.text('');
            
            // Убираем описание
            var descriptionElement = cdekShippingBlock.find('.wc-block-components-totals-item__description');
            descriptionElement.html('');
        } else {
            // Попробуем найти по другому селектору
            var alternativeBlock = $('.wc-block-components-totals-shipping .wc-block-components-totals-item');
            if (alternativeBlock.length > 0) {
                var labelElement = alternativeBlock.find('.wc-block-components-totals-item__label');
                labelElement.text('Выберите пункт выдачи');
                
                var valueElement = alternativeBlock.find('.wc-block-components-totals-item__value');
                valueElement.text('');
                
                var descriptionElement = alternativeBlock.find('.wc-block-components-totals-item__description');
                descriptionElement.html('');
            }
        }
    }
    
    function initCdekDelivery() {
        if (isInitialized) {
            return;
        }
        
        // СКРЫВАЕМ БЛОК ВЫБОРА ДОСТАВКИ СДЭК
        hideCdekShippingBlock();
        
        // Создаем контейнер для карты, если его нет
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
            
            // Ищем специальный блок для карты или места для вставки
            var mapBlock = $('.wp-block-cdek-checkout-map-block');
            
            var insertTarget = null;
            
            if (mapBlock.length > 0) {
                // Есть специальный блок для карты
                insertTarget = mapBlock;
                insertTarget.html(mapHtml);
            } else {
                // Ищем другие места для вставки
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
        
        // Принудительно инициализируем карту
        setTimeout(function() {
            initYandexMap();
        }, 500);
        
        // Инициализируем автокомплит адресов
        setTimeout(function() {
            initAddressAutocomplete();
        }, 1000);
        
        // Поиск пунктов по текущему городу
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
    }
    
    // ====== КОНЕЦ ОПРЕДЕЛЕНИЯ ФУНКЦИЙ ======
    
    // Инициализация карты при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            setTimeout(function() {
                initCdekDelivery();
            }, 100);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1) {
            hideCdekMap();
            resetCdekShippingToDefault();
        }
    });
    
    // Дополнительная инициализация для блоков WooCommerce
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        setTimeout(function() {
            initCdekDelivery();
        }, 200);
    });
    
    // Переменные для дебаунсинга и кэширования
    var searchTimeout = null;
    var isSearching = false;
    var cdekPointsCache = null;
    var lastSearchTime = 0;
    var cacheExpiry = 5 * 60 * 1000; // 5 минут
    
    // Отслеживание изменений в поле адреса
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        
        // Очищаем предыдущий таймаут
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Извлекаем город из адреса (первое слово до запятой)
        var city = address.split(',')[0].trim();
        
        if (city.length > 2) {
            // Дебаунсинг поиска - выполняем через 500мс после последнего ввода
            searchTimeout = setTimeout(function() {
                if (!isSearching) {
                    searchCdekPoints(city);
                }
            }, 500);
        }
    });
    
    // Отслеживание переключения методов доставки
    $(document).on('change', 'input[name="radio-control-3"], input[name="shipping_method[0]"], input[value*="cdek_delivery"]', function() {
        var cdekSelected = $('input[value*="cdek_delivery"]:checked');
        var pickupSelected = $('input[value*="pickup"]:checked');
        
        if (cdekSelected.length > 0) {
            hideCdekMap();
            setTimeout(function() {
                initCdekDelivery();
            }, 500);
        } else if (pickupSelected.length > 0) {
            hideCdekMap();
            resetCdekShippingToDefault();
        }
    });
    
    // Отслеживание кликов по кнопкам методов доставки
    $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
        setTimeout(function() {
            var cdekSelected = $('input[value*="cdek_delivery"]:checked');
            var pickupSelected = $('input[value*="pickup"]:checked');
            
            if (cdekSelected.length > 0) {
                hideCdekMap();
                setTimeout(function() {
                    initCdekDelivery();
                }, 500);
            } else if (pickupSelected.length > 0) {
                hideCdekMap();
                resetCdekShippingToDefault();
            }
        }, 100);
    });
    
    // Наблюдатель за изменениями DOM для автоматической инициализации
    var observer = new MutationObserver(function(mutations) {
        if (observerActive) return; // Предотвращаем рекурсию
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Принудительно убираем старый текст "СДЭК — Пункт выдачи"
                $('.wc-block-components-totals-item__label').each(function() {
                    var text = $(this).text();
                    if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                        $(this).text('Выберите пункт выдачи');
                    }
                });
                
                // Проверяем наличие метода СДЭК (выбранного или нет)
                var cdekMethod = $('input[value*="cdek_delivery"]');
                
                if (cdekMethod.length > 0 && !isInitialized) {
                    observerActive = true;
                    
                    // Безопасно скрываем блок выбора СДЭК
                    hideCdekShippingBlock();
                    
                    // Если СДЭК выбран и карты нет - инициализируем
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
    
    // Запускаем наблюдатель
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Инициализируем блок СДЭК сразу при загрузке
    initCdekBlock();
    
    // Принудительно убираем старый текст "СДЭК — Пункт выдачи"
    setTimeout(function() {
        $('.wc-block-components-totals-item__label').each(function() {
            var text = $(this).text();
            if (text.includes('СДЭК') && text.includes('Пункт выдачи')) {
                $(this).text('Выберите пункт выдачи');
            }
        });
    }, 100);
    
    // Проверяем при загрузке страницы
    setTimeout(function() {
        initAddressAutocomplete();
        
        var cdekMethod = $('input[value*="cdek_delivery"]');
        
        if (cdekMethod.length > 0) {
            hideCdekShippingBlock();
            initCdekDelivery();
        }
    }, 2000);
    
    // Дополнительная инициализация автокомплита через 5 секунд
    setTimeout(function() {
        if ($('#address-select').length === 0 && $('#address-suggestions').length === 0) {
            initAddressAutocomplete();
        }
    }, 5000);
    
    // Дополнительная проверка через больший интервал
    setTimeout(function() {
        if ($('input[value*="cdek_delivery"]').length > 0 && $('#cdek-map-container').length === 0 && !isInitialized) {
            initCdekDelivery();
        }
    }, 4000);
});