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
        if (address.length > 5) {
            searchCdekPoints(address);
        }
    });
    
    function initCdekDelivery() {
        // Создаем контейнер для карты, если его нет
        if ($('#cdek-map-container').length === 0) {
            var mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px;">
                    <h4>Выберите пункт выдачи СДЭК:</h4>
                    <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
                        <strong>Выбранный пункт:</strong>
                        <div id="cdek-point-info"></div>
                    </div>
                    <div id="cdek-map" style="width: 100%; height: 400px; border: 1px solid #ddd;"></div>
                    <div id="cdek-points-list" style="margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
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
        
        // Поиск пунктов по текущему адресу
        var currentAddress = $('#shipping-address_1').val();
        if (currentAddress) {
            searchCdekPoints(currentAddress);
        }
    }
    
    function hideCdekMap() {
        $('#cdek-map-container').hide();
    }
    
    function initYandexMap() {
        if (cdekMap) {
            return; // Карта уже инициализирована
        }
        
        cdekMap = new ymaps.Map('cdek-map', {
            center: [55.753994, 37.622093], // Москва по умолчанию
            zoom: 10,
            controls: ['zoomControl', 'searchControl']
        });
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
            initYandexMap();
            setTimeout(function() {
                displayCdekPoints(points);
            }, 1000);
            return;
        }
        
        // Очищаем предыдущие метки
        cdekMap.geoObjects.removeAll();
        
        // Очищаем список пунктов
        $('#cdek-points-list').empty();
        
        if (!points || points.length === 0) {
            $('#cdek-points-list').html('<p>Пункты выдачи не найдены</p>');
            return;
        }
        
        var bounds = [];
        var pointsListHtml = '<h5>Список пунктов выдачи:</h5>';
        
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
                
                // Добавляем в список
                pointsListHtml += `
                    <div class="cdek-point-item" data-point-code="${point.code}" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 5px; cursor: pointer; border-radius: 4px;">
                        <strong>${point.name}</strong><br>
                        <small>${point.location.address_full}</small><br>
                        <small>Режим работы: ${formatWorkTime(point.work_time)}</small>
                    </div>
                `;
            }
        });
        
        $('#cdek-points-list').html(pointsListHtml);
        
        // Обработчики клика по пунктам в списке
        $('.cdek-point-item').click(function() {
            var pointCode = $(this).data('point-code');
            var point = cdekPoints.find(p => p.code === pointCode);
            if (point) {
                selectCdekPoint(point);
            }
        });
        
        // Подгоняем масштаб карты под все точки
        if (bounds.length > 0) {
            cdekMap.setBounds(bounds, {
                checkZoomRange: true,
                zoomMargin: 20
            });
        }
    }
    
    function selectCdekPoint(point) {
        selectedPoint = point;
        
        // Показываем информацию о выбранном пункте
        $('#cdek-point-info').html(formatPointInfo(point));
        $('#cdek-selected-point').show();
        
        // Выделяем выбранный пункт в списке
        $('.cdek-point-item').removeClass('selected');
        $(`.cdek-point-item[data-point-code="${point.code}"]`).addClass('selected');
        
        // Сохраняем выбранный пункт в скрытое поле для отправки с формой
        if ($('#cdek-selected-point-code').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                id: 'cdek-selected-point-code',
                name: 'cdek_selected_point_code',
                value: point.code
            }).appendTo('form.checkout');
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
            }).appendTo('form.checkout');
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