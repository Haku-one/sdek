jQuery(document).ready(function($) {
    console.log('🚀 СДЭК: Начинаем загрузку скрипта...');
    console.log('🚀 СДЭК: jQuery версия:', $.fn.jquery);
    console.log('🚀 СДЭК: ymaps доступен:', typeof ymaps !== 'undefined');
    console.log('🚀 СДЭК: Текущий URL:', window.location.href);
    
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    
    console.log('🚀 СДЭК: Переменные инициализированы');
    
    // ====== ОПРЕДЕЛЕНИЕ ВСЕХ ФУНКЦИЙ В НАЧАЛЕ ======
    
    function hideCdekShippingBlock() {
        console.log('🚫 СДЭК: === СКРЫВАЕМ БЛОК ВЫБОРА ДОСТАВКИ ===');
        
        var cdekInputs = $('input[value*="cdek_delivery"]');
        console.log('🔍 СДЭК: Найдено input элементов СДЭК:', cdekInputs.length);
        
        cdekInputs.each(function(index, element) {
            console.log('🎯 СДЭК: Обрабатываем элемент #' + index + ':', element.value);
            
            // Скрываем родительские контейнеры
            var $this = $(this);
            var radioControl = $this.closest('.wc-block-components-radio-control');
            var package = $this.closest('.wc-block-components-shipping-rates-control__package');
            var control = $this.closest('.wc-block-components-shipping-rates-control');
            var label = $this.closest('label');
            
            console.log('📦 СДЭК: radio-control:', radioControl.length);
            console.log('📦 СДЭК: package:', package.length);
            console.log('📦 СДЭК: control:', control.length);
            console.log('📦 СДЭК: label:', label.length);
            
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
            
            console.log('✅ СДЭК: Элемент #' + index + ' скрыт');
        });
        
        console.log('🚫 СДЭК: === СКРЫТИЕ БЛОКА ЗАВЕРШЕНО ===');
    }
    
    function hideCdekMap() {
        console.log('🗺️ СДЭК: Скрываем карту');
        $('#cdek-map-container').hide();
    }
    
    function initYandexMap() {
        console.log('🗺️ СДЭК: === ИНИЦИАЛИЗАЦИЯ ЯНДЕКС КАРТЫ ===');
        
        if (cdekMap) {
            console.log('✅ СДЭК: карта уже инициализирована');
            return;
        }
        
        // Проверяем что ymaps загружен
        if (typeof ymaps === 'undefined' || !ymaps.ready) {
            console.log('⏳ СДЭК: Яндекс.Карты еще загружаются... повтор через 1 сек');
            setTimeout(initYandexMap, 1000);
            return;
        }
        
        // Проверяем контейнер
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) {
            console.log('❌ СДЭК: контейнер карты не найден, повтор через 0.5 сек');
            setTimeout(initYandexMap, 500);
            return;
        }
        
        console.log('📏 СДЭК: Размеры контейнера до установки стилей:', 
                   mapContainer.offsetWidth + 'x' + mapContainer.offsetHeight);
        
        // КРИТИЧНО: принудительно делаем контейнер видимым и с размерами
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important;';
        
        console.log('📏 СДЭК: Размеры контейнера после установки стилей:', 
                   mapContainer.offsetWidth + 'x' + mapContainer.offsetHeight);
        
        // Ждем пока контейнер получит размеры
        var checkContainer = function() {
            console.log('🔍 СДЭК: Проверяем размеры контейнера...', 
                       mapContainer.offsetWidth + 'x' + mapContainer.offsetHeight);
            
            if (mapContainer.offsetWidth > 0 && mapContainer.offsetHeight > 0) {
                console.log('✅ СДЭК: контейнер готов! Создаем карту...');
                
                ymaps.ready(function() {
                    try {
                        console.log('🗺️ СДЭК: Вызываем new ymaps.Map...');
                        cdekMap = new ymaps.Map(mapContainer, {
                            center: [55.753994, 37.622093],
                            zoom: 10,
                            controls: ['zoomControl', 'searchControl']
                        });
                        console.log('🎉 СДЭК: ✅ КАРТА УСПЕШНО СОЗДАНА!');
                    } catch (error) {
                        console.error('💥 СДЭК: ❌ ОШИБКА создания карты:', error);
                        // Попробуем еще раз через секунду
                        setTimeout(function() {
                            cdekMap = null;
                            initYandexMap();
                        }, 1000);
                    }
                });
            } else {
                console.log('⏳ СДЭК: контейнер еще без размеров, ждем...');
                setTimeout(checkContainer, 300);
            }
        };
        
        // Небольшая задержка для рендеринга DOM
        setTimeout(checkContainer, 200);
    }
    
    function searchCdekPoints(address) {
        console.log('🔍 СДЭК: === ПОИСК ПУНКТОВ ВЫДАЧИ ===');
        console.log('🔍 СДЭК: Ищем пункты для адреса:', address);
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cdek_points',
                address: address,
                nonce: cdek_ajax.nonce
            },
            beforeSend: function() {
                console.log('📡 СДЭК: Отправляем AJAX запрос...');
            },
            success: function(response) {
                console.log('📡 СДЭК: AJAX ответ получен:', response);
                if (response.success && response.data) {
                    displayCdekPoints(response.data);
                } else {
                    console.log('❌ СДЭК: Ошибка в ответе сервера:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('💥 СДЭК: AJAX ошибка:', status, error);
            }
        });
    }
    
    function displayCdekPoints(points) {
        console.log('📍 СДЭК: === ОТОБРАЖЕНИЕ ПУНКТОВ НА КАРТЕ ===');
        console.log('📍 СДЭК: Получено пунктов:', points ? points.length : 0);
        
        cdekPoints = points;
        
        // Проверяем что карта и ymaps готовы
        if (!cdekMap || typeof ymaps === 'undefined') {
            console.log('⏳ СДЭК: карта не готова, отложим отображение точек');
            setTimeout(function() {
                displayCdekPoints(points);
            }, 1000);
            return;
        }
        
        // Очищаем предыдущие метки
        console.log('🧹 СДЭК: Очищаем предыдущие метки');
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            console.log('❌ СДЭК: пункты выдачи не найдены');
            // Показываем сообщение на карте
            var noPointsPlacemark = new ymaps.Placemark([55.753994, 37.622093], {
                balloonContent: 'Пункты выдачи в указанном городе не найдены'
            }, {
                preset: 'islands#grayIcon'
            });
            cdekMap.geoObjects.add(noPointsPlacemark);
            cdekMap.setCenter([55.753994, 37.622093], 10);
            return;
        }
        
        console.log('✅ СДЭК: отображаем ' + points.length + ' пунктов выдачи');
        
        var bounds = [];
        
        points.forEach(function(point, index) {
            console.log('📍 СДЭК: Обрабатываем пункт #' + index + ':', point.name);
            
            if (point.location && point.location.latitude && point.location.longitude) {
                var coords = [point.location.latitude, point.location.longitude];
                bounds.push(coords);
                
                console.log('📍 СДЭК: Координаты пункта #' + index + ':', coords);
                
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
                console.log('✅ СДЭК: Пункт #' + index + ' добавлен на карту');
            } else {
                console.log('❌ СДЭК: У пункта #' + index + ' нет координат');
            }
        });
        
        // Подгоняем масштаб карты под все точки
        if (bounds.length > 0) {
            console.log('🎯 СДЭК: Подгоняем масштаб карты под ' + bounds.length + ' точек');
            cdekMap.setBounds(bounds, {
                checkZoomRange: true,
                zoomMargin: 30
            });
        }
        
        console.log('📍 СДЭК: === ОТОБРАЖЕНИЕ ПУНКТОВ ЗАВЕРШЕНО ===');
    }
    
    function selectCdekPoint(point) {
        console.log('🎯 СДЭК: === ВЫБОР ПУНКТА ВЫДАЧИ ===');
        console.log('🎯 СДЭК: Выбран пункт:', point.name);
        
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
        
        console.log('✅ СДЭК: Пункт выбран и сохранен');
    }
    
    function formatPointInfo(point) {
        var html = `
            <strong>${point.name}</strong><br>
            Адрес: ${point.location.address_full}<br>
            Телефон: ${point.phone || 'Не указан'}<br>
            Режим работы: ${formatWorkTime(point.work_time)}<br>
        `;
        
        if (point.note) {
            html += `Примечание: ${point.note}<br>`;
        }
        
        return html;
    }
    
    function formatWorkTime(workTime) {
        if (!workTime || workTime.length === 0) {
            return 'Не указан';
        }
        
        var days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        var schedule = '';
        
        workTime.forEach(function(time) {
            if (time.day !== undefined && time.time) {
                schedule += days[time.day - 1] + ': ' + time.time + ' ';
            }
        });
        
        return schedule || 'Не указан';
    }
    
    function initCdekDelivery() {
        console.log('🚀 СДЭК: === НАЧАЛО ИНИЦИАЛИЗАЦИИ ДОСТАВКИ ===');
        console.log('🔍 СДЭК: Проверяем наличие контейнера карты:', $('#cdek-map-container').length);
        console.log('🔍 СДЭК: Проверяем специальный блок:', $('.wp-block-cdek-checkout-map-block').length);
        
        // СКРЫВАЕМ БЛОК ВЫБОРА ДОСТАВКИ СДЭК
        console.log('🚫 СДЭК: Вызываем hideCdekShippingBlock...');
        hideCdekShippingBlock();
        
        // Создаем контейнер для карты, если его нет
        if ($('#cdek-map-container').length === 0) {
            console.log('🏗️ СДЭК: Создаем контейнер карты');
            
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px; display: block !important;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
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
            console.log('🎯 СДЭК: Найден специальный блок карты:', mapBlock.length);
            
            var insertTarget = null;
            
            if (mapBlock.length > 0) {
                // Есть специальный блок для карты
                insertTarget = mapBlock;
                console.log('📍 СДЭК: Вставляем карту в специальный блок');
                insertTarget.html(mapHtml);
                console.log('✅ СДЭК: карта вставлена в специальный блок');
            } else {
                console.log('🔍 СДЭК: Ищем альтернативные места для вставки');
                
                // Ищем другие места для вставки
                var addressForm = $('.wc-block-components-address-form');
                var shippingBlock = $('.wp-block-woocommerce-checkout-shipping-address-block');
                var shippingControl = $('.wc-block-components-shipping-rates-control');
                
                console.log('🔍 СДЭК: address-form:', addressForm.length);
                console.log('🔍 СДЭК: shipping-address-block:', shippingBlock.length);
                console.log('🔍 СДЭК: shipping-rates-control:', shippingControl.length);
                
                insertTarget = addressForm.length ? addressForm : 
                    shippingBlock.length ? shippingBlock :
                    shippingControl.first();
                    
                if (insertTarget.length > 0) {
                    console.log('📍 СДЭК: Вставляем карту после элемента:', insertTarget[0].className);
                    insertTarget.after(mapHtml);
                    console.log('✅ СДЭК: карта вставлена после элемента');
                } else {
                    console.log('❌ СДЭК: НЕ НАЙДЕНО место для вставки карты!');
                }
            }
        } else {
            console.log('ℹ️ СДЭК: Контейнер карты уже существует');
        }
        
        $('#cdek-map-container').show();
        console.log('👁️ СДЭК: Контейнер карты отображен');
        
        // Принудительно инициализируем карту
        console.log('🗺️ СДЭК: Запускаем инициализацию карты через 0.5 сек');
        setTimeout(function() {
            initYandexMap();
        }, 500);
        
        // Поиск пунктов по текущему городу
        var currentAddress = $('#shipping-address_1').val();
        console.log('🔍 СДЭК: Текущий адрес в поле:', currentAddress);
        
        if (currentAddress) {
            var city = currentAddress.split(',')[0].trim();
            console.log('🏙️ СДЭК: Извлеченный город:', city);
            
            if (city.length > 2) {
                console.log('🔍 СДЭК: Запускаем поиск пунктов для города');
                setTimeout(function() {
                    searchCdekPoints(city);
                }, 1000);
            }
        }
        
        console.log('🚀 СДЭК: === ИНИЦИАЛИЗАЦИЯ ДОСТАВКИ ЗАВЕРШЕНА ===');
    }
    
    // ====== КОНЕЦ ОПРЕДЕЛЕНИЯ ФУНКЦИЙ ======
    
    console.log('✅ СДЭК: Все функции определены');
    
    // Инициализация карты при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        console.log('🔄 СДЭК: Изменен метод доставки:', $(this).val());
        
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            console.log('✅ СДЭК: выбран метод доставки СДЭК');
            setTimeout(function() {
                initCdekDelivery();
            }, 100);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1) {
            hideCdekMap();
        }
    });
    
    // Дополнительная инициализация для блоков WooCommerce
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        console.log('👆 СДЭК: клик по методу доставки СДЭК');
        setTimeout(function() {
            initCdekDelivery();
        }, 200);
    });
    
    // Отслеживание изменений в поле адреса
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        console.log('⌨️ СДЭК: Изменен адрес:', address);
        
        // Извлекаем город из адреса (первое слово до запятой)
        var city = address.split(',')[0].trim();
        console.log('🏙️ СДЭК: Извлеченный город:', city);
        
        if (city.length > 2) {
            console.log('🔍 СДЭК: Запускаем поиск пунктов для города');
            searchCdekPoints(city);
        }
    });
    
    // Наблюдатель за изменениями DOM для автоматической инициализации
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Проверяем наличие метода СДЭК (выбранного или нет)
                var cdekMethod = $('input[value*="cdek_delivery"]');
                console.log('👁️ СДЭК: DOM изменился, найдено методов СДЭК:', cdekMethod.length);
                
                if (cdekMethod.length > 0) {
                    // Безопасно скрываем блок выбора СДЭК
                    console.log('🚫 СДЭК: Скрываем блок выбора СДЭК');
                    hideCdekShippingBlock();
                    
                    // Если СДЭК выбран и карты нет - инициализируем
                    var cdekSelected = $('input[value*="cdek_delivery"]:checked');
                    console.log('🎯 СДЭК: выбранных методов СДЭК:', cdekSelected.length);
                    
                    if (cdekSelected.length > 0 && $('#cdek-map-container').length === 0) {
                        console.log('✅ СДЭК: обнаружен выбранный метод СДЭК, инициализируем');
                        setTimeout(function() {
                            initCdekDelivery();
                        }, 500);
                    } else if ($('#cdek-map-container').length === 0) {
                        console.log('💡 СДЭК: метод есть но не выбран, инициализируем карту принудительно');
                        setTimeout(function() {
                            initCdekDelivery();
                        }, 500);
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
    
    console.log('👁️ СДЭК: DOM наблюдатель запущен');
    
    // Проверяем при загрузке страницы
    setTimeout(function() {
        console.log('⏰ СДЭК: Проверка через 2 секунды после загрузки');
        
        // Всегда скрываем блок СДЭК при загрузке
        var cdekMethod = $('input[value*="cdek_delivery"]');
        console.log('🔍 СДЭК: При загрузке найдено методов СДЭК:', cdekMethod.length);
        
        if (cdekMethod.length > 0) {
            console.log('📋 СДЭК: Детали найденных методов:', cdekMethod.map(function() { return this.value; }).get());
            
            console.log('🚫 СДЭК: Скрываем блок выбора СДЭК');
            hideCdekShippingBlock();
            
            // ПРИНУДИТЕЛЬНО инициализируем карту если есть метод СДЭК
            console.log('🚀 СДЭК: найден метод СДЭК, принудительно инициализируем карту');
            initCdekDelivery();
        } else {
            console.log('❌ СДЭК: методы доставки СДЭК не найдены');
        }
    }, 2000);
    
    // Дополнительная проверка через больший интервал
    setTimeout(function() {
        console.log('⏰ СДЭК: Финальная проверка через 4 секунды');
        
        if ($('input[value*="cdek_delivery"]').length > 0 && $('#cdek-map-container').length === 0) {
            console.log('🔄 СДЭК: повторная принудительная инициализация');
            initCdekDelivery();
        } else {
            console.log('ℹ️ СДЭК: Финальная проверка - все в порядке');
        }
    }, 4000);
    
    console.log('🎉 СДЭК: === СКРИПТ ПОЛНОСТЬЮ ЗАГРУЖЕН ===');
});