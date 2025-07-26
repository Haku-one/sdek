jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    
    // Инициализация карты при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"], input[name*="radio-control"], input[value*="cdek_delivery"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            console.log('СДЭК: выбран метод доставки СДЭК');
            setTimeout(function() {
                initCdekDelivery();
            }, 100);
        } else if ($(this).attr('name') && $(this).attr('name').indexOf('shipping_method') !== -1) {
            hideCdekMap();
        }
    });
    
    // Дополнительная инициализация для блоков WooCommerce
    $(document).on('click', 'input[value*="cdek_delivery"]', function() {
        console.log('СДЭК: клик по методу доставки СДЭК');
        setTimeout(function() {
            initCdekDelivery();
        }, 200);
    });
    
    // Отслеживание изменений в поле адреса
    $(document).on('input', '#shipping-address_1', function() {
        var address = $(this).val();
        // Извлекаем город из адреса (первое слово до запятой)
        var city = address.split(',')[0].trim();
        if (city.length > 2) {
            searchCdekPoints(city);
        }
    });
    
    function initCdekDelivery() {
        console.log('СДЭК: инициализация доставки');
        
        // СКРЫВАЕМ БЛОК ВЫБОРА ДОСТАВКИ СДЭК
        hideCdekShippingBlock();
        
        // Создаем контейнер для карты, если его нет
        if ($('#cdek-map-container').length === 0) {
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
            
            // Ищем разные возможные места для вставки
            var insertAfter = $('.wc-block-components-address-form').length ? 
                $('.wc-block-components-address-form') : 
                $('.wp-block-woocommerce-checkout-shipping-address-block').length ?
                $('.wp-block-woocommerce-checkout-shipping-address-block') :
                $('.wc-block-components-shipping-rates-control').first();
                
            insertAfter.after(mapHtml);
        }
        
        $('#cdek-map-container').show();
        
        // Принудительно инициализируем карту
        setTimeout(function() {
            initYandexMap();
        }, 500);
        
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
    }
    
    function hideCdekMap() {
        $('#cdek-map-container').hide();
    }
    
    function hideCdekShippingBlock() {
        console.log('СДЭК: скрываем блок выбора доставки');
        
        // Скрываем все возможные блоки с СДЭК доставкой
        $('input[value*="cdek_delivery"]').each(function() {
            // Скрываем родительские контейнеры
            $(this).closest('.wc-block-components-radio-control').hide();
            $(this).closest('.wc-block-components-shipping-rates-control__package').hide();
            $(this).closest('.wc-block-components-shipping-rates-control').hide();
            $(this).closest('label').hide();
            
            // Дополнительно скрываем через CSS
            $(this).css({
                'display': 'none !important',
                'visibility': 'hidden !important',
                'position': 'absolute',
                'left': '-9999px'
            });
            
            console.log('СДЭК: скрыт элемент', this);
        });
        
        // Скрываем по классам
        $('.wc-block-components-shipping-rates-control__package').each(function() {
            if ($(this).find('input[value*="cdek_delivery"]').length > 0) {
                $(this).hide();
            }
        });
    }
    
    function initYandexMap() {
        if (cdekMap) {
            console.log('СДЭК: карта уже инициализирована');
            return;
        }
        
        console.log('СДЭК: начинаем инициализацию карты');
        
        // Проверяем что ymaps загружен
        if (typeof ymaps === 'undefined' || !ymaps.ready) {
            console.log('СДЭК: Яндекс.Карты еще загружаются...');
            setTimeout(initYandexMap, 1000);
            return;
        }
        
        // Проверяем контейнер
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer) {
            console.log('СДЭК: контейнер карты не найден, ждем...');
            setTimeout(initYandexMap, 500);
            return;
        }
        
        // КРИТИЧНО: принудительно делаем контейнер видимым и с размерами
        mapContainer.style.cssText = 'display: block !important; width: 100% !important; height: 450px !important; visibility: visible !important; position: relative !important;';
        
        // Ждем пока контейнер получит размеры
        var checkContainer = function() {
            if (mapContainer.offsetWidth > 0 && mapContainer.offsetHeight > 0) {
                console.log('СДЭК: контейнер готов, размеры:', mapContainer.offsetWidth, 'x', mapContainer.offsetHeight);
                
                ymaps.ready(function() {
                    try {
                        cdekMap = new ymaps.Map(mapContainer, {
                            center: [55.753994, 37.622093],
                            zoom: 10,
                            controls: ['zoomControl', 'searchControl']
                        });
                        console.log('СДЭК: ✅ карта успешно создана!');
                    } catch (error) {
                        console.error('СДЭК: ❌ ошибка создания карты:', error);
                        // Попробуем еще раз через секунду
                        setTimeout(function() {
                            cdekMap = null;
                            initYandexMap();
                        }, 1000);
                    }
                });
            } else {
                console.log('СДЭК: контейнер еще без размеров, ждем...');
                setTimeout(checkContainer, 300);
            }
        };
        
        // Небольшая задержка для рендеринга DOM
        setTimeout(checkContainer, 200);
    }
    
    function searchCdekPoints(address) {
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cdek_points',
                address: address,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayCdekPoints(response.data);
                }
            },
            error: function() {
                console.log('Ошибка при получении пунктов СДЭК');
            }
        });
    }
    
    function displayCdekPoints(points) {
        cdekPoints = points;
        
        // Проверяем что карта и ymaps готовы
        if (!cdekMap || typeof ymaps === 'undefined') {
            console.log('СДЭК: карта не готова, отложим отображение точек');
            setTimeout(function() {
                displayCdekPoints(points);
            }, 1000);
            return;
        }
        
        // Очищаем предыдущие метки
        cdekMap.geoObjects.removeAll();
        
        if (!points || points.length === 0) {
            console.log('СДЭК: пункты выдачи не найдены');
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
        
        console.log('СДЭК: отображаем ' + points.length + ' пунктов выдачи');
        
        var bounds = [];
        
        points.forEach(function(point, index) {
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
            cdekMap.setBounds(bounds, {
                checkZoomRange: true,
                zoomMargin: 30
            });
        }
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        console.log('СДЭК: выбран пункт выдачи', point.name);
        
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
});

// CSS стили для выбранного пункта
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .cdek-point-item.selected {
                background-color: #e7f3ff !important;
                border-color: #007cba !important;
            }
            .cdek-point-item:hover {
                background-color: #f5f5f5;
            }
            #cdek-map-container h4, #cdek-map-container h5 {
                margin-bottom: 10px;
                color: #333;
            }
        `)
        .appendTo('head');
        
    // Наблюдатель за изменениями DOM для автоматической инициализации
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Проверяем наличие метода СДЭК (выбранного или нет)
                var cdekMethod = $('input[value*="cdek_delivery"]');
                if (cdekMethod.length > 0) {
                    // Всегда скрываем блок выбора СДЭК
                    hideCdekShippingBlock();
                    
                    // Если СДЭК выбран и карты нет - инициализируем
                    var cdekSelected = $('input[value*="cdek_delivery"]:checked');
                    if (cdekSelected.length > 0 && $('#cdek-map-container').length === 0) {
                        console.log('СДЭК: обнаружен выбранный метод СДЭК, инициализируем');
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
    
    // Проверяем при загрузке страницы
    setTimeout(function() {
        // Всегда скрываем блок СДЭК при загрузке
        var cdekMethod = $('input[value*="cdek_delivery"]');
        if (cdekMethod.length > 0) {
            hideCdekShippingBlock();
        }
        
        // Если СДЭК выбран - инициализируем карту
        var cdekSelected = $('input[value*="cdek_delivery"]:checked');
        if (cdekSelected.length > 0) {
            console.log('СДЭК: метод СДЭК уже выбран при загрузке');
            initCdekDelivery();
        }
    }, 2000);
});