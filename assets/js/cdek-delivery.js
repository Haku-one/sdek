jQuery(document).ready(function($) {
    var cdekMap = null;
    var cdekPoints = [];
    var selectedPoint = null;
    
    // Инициализация карты при выборе доставки СДЭК
    $(document).on('change', 'input[name="shipping_method[0]"]', function() {
        if ($(this).val().indexOf('cdek_delivery') !== -1) {
            initCdekDelivery();
        } else {
            hideCdekMap();
        }
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
        // Создаем контейнер для карты, если его нет
        if ($('#cdek-map-container').length === 0) {
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
                    <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
                        <strong>Выбранный пункт:</strong>
                        <div id="cdek-point-info"></div>
                    </div>
                    <div id="cdek-map" style="width: 100%; height: 450px; border: 1px solid #ddd; border-radius: 6px;"></div>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        💡 Введите город в поле адреса выше, затем выберите пункт выдачи на карте
                    </p>
                </div>
            `;
            $('.wc-block-components-address-form').after(mapHtml);
        }
        
        $('#cdek-map-container').show();
        
        // Инициализируем карту
        if (typeof ymaps !== 'undefined') {
            ymaps.ready(function() {
                initYandexMap();
            });
        }
        
        // Поиск пунктов по текущему городу
        var currentAddress = $('#shipping-address_1').val();
        if (currentAddress) {
            var city = currentAddress.split(',')[0].trim();
            if (city.length > 2) {
                searchCdekPoints(city);
            }
        }
    }
    
    function hideCdekMap() {
        $('#cdek-map-container').hide();
    }
    
    function initYandexMap() {
        if (cdekMap) {
            return; // Карта уже инициализирована
        }
        
        // Проверяем что контейнер существует и видим
        var mapContainer = document.getElementById('cdek-map');
        if (!mapContainer || mapContainer.offsetWidth === 0) {
            console.log('СДЭК: контейнер карты не готов, повторяем через 500мс');
            setTimeout(initYandexMap, 500);
            return;
        }
        
        try {
            cdekMap = new ymaps.Map('cdek-map', {
                center: [55.753994, 37.622093], // Москва по умолчанию
                zoom: 10,
                controls: ['zoomControl', 'searchControl']
            });
            console.log('СДЭК: карта успешно инициализирована');
        } catch (error) {
            console.error('СДЭК: ошибка инициализации карты', error);
        }
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
        
        if (!cdekMap) {
            console.log('СДЭК: карта не инициализирована, инициализируем');
            initYandexMap();
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
});