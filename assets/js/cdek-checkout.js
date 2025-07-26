jQuery(document).ready(function($) {
    let cdekMap = null;
    let cdekPickupPoints = [];
    let selectedPickupPoint = null;

    // Инициализация карты после загрузки Яндекс.Карт
    function initCdekMap() {
        if (typeof ymaps === 'undefined') {
            setTimeout(initCdekMap, 100);
            return;
        }

        ymaps.ready(function() {
            const mapContainer = document.getElementById('cdek-map');
            if (mapContainer) {
                cdekMap = new ymaps.Map('cdek-map', {
                    center: [55.76, 37.64], // Москва по умолчанию
                    zoom: 10,
                    controls: ['zoomControl', 'searchControl']
                });
            }
        });
    }

    // Функция для очистки названия города
    function cleanCityName(city) {
        if (!city) return '';
        
        // Убираем лишние слова и символы
        city = city.trim();
        city = city.replace(/^(г\.?|город)\s+/i, ''); // Убираем "г." или "город"
        city = city.replace(/\s+/g, ' '); // Убираем лишние пробелы
        
        return city;
    }

    // Загрузка пунктов выдачи
    function loadPickupPoints(city) {
        if (!city) return;

        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_get_pickup_points',
                city: city,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    cdekPickupPoints = response.data;
                    displayPickupPoints();
                    updateMapWithPoints();
                } else {
                    showError('Пункты выдачи в данном городе не найдены');
                }
            },
            error: function() {
                showError('Ошибка при загрузке пунктов выдачи');
            }
        });
    }

    // Отображение пунктов выдачи в списке
    function displayPickupPoints() {
        const container = $('#cdek-pickup-points-list');
        container.empty();

        if (cdekPickupPoints.length === 0) {
            container.html('<p>Пункты выдачи не найдены</p>');
            return;
        }

        let html = '<h4>Выберите пункт выдачи:</h4><div class="cdek-points-list">';
        
        cdekPickupPoints.forEach(function(point, index) {
            const address = point.location ? point.location.address_full : point.address_comment;
            const workTime = point.work_time ? formatWorkTime(point.work_time) : 'Время работы не указано';
            
            html += `
                <div class="cdek-pickup-point" data-index="${index}">
                    <div class="point-info">
                        <strong>${point.name}</strong><br>
                        <span class="address">${address}</span><br>
                        <span class="work-time">${workTime}</span>
                    </div>
                    <button type="button" class="select-point-btn" data-index="${index}">Выбрать</button>
                </div>
            `;
        });
        
        html += '</div>';
        container.html(html);

        // Обработчики для выбора пункта
        $('.select-point-btn').on('click', function() {
            const index = $(this).data('index');
            selectPickupPoint(index);
        });

        $('.cdek-pickup-point').on('click', function() {
            const index = $(this).data('index');
            highlightPointOnMap(index);
        });
    }

    // Обновление карты с пунктами выдачи
    function updateMapWithPoints() {
        if (!cdekMap || cdekPickupPoints.length === 0) return;

        cdekMap.geoObjects.removeAll();
        
        const coordinates = [];
        
        cdekPickupPoints.forEach(function(point, index) {
            if (point.location && point.location.latitude && point.location.longitude) {
                const coords = [parseFloat(point.location.latitude), parseFloat(point.location.longitude)];
                coordinates.push(coords);
                
                const placemark = new ymaps.Placemark(coords, {
                    balloonContent: `
                        <strong>${point.name}</strong><br>
                        ${point.location.address_full}<br>
                        <button onclick="selectPickupPointFromMap(${index})">Выбрать этот пункт</button>
                    `,
                    hintContent: point.name
                }, {
                    preset: 'islands#redDotIcon'
                });
                
                placemark.events.add('click', function() {
                    highlightPickupPoint(index);
                });
                
                cdekMap.geoObjects.add(placemark);
            }
        });

        // Подгоняем масштаб карты под все точки
        if (coordinates.length > 0) {
            cdekMap.setBounds(cdekMap.geoObjects.getBounds(), {
                checkZoomRange: true,
                zoomMargin: 30
            });
        }
    }

    // Выбор пункта выдачи
    function selectPickupPoint(index) {
        if (index >= 0 && index < cdekPickupPoints.length) {
            selectedPickupPoint = cdekPickupPoints[index];
            
            // Обновляем UI
            $('.cdek-pickup-point').removeClass('selected');
            $(`.cdek-pickup-point[data-index="${index}"]`).addClass('selected');
            
            // Сохраняем выбранный пункт
            $('#cdek-selected-pickup-point').val(JSON.stringify(selectedPickupPoint));
            
            // Показываем информацию о выбранном пункте
            showSelectedPoint();
            
            // Обновляем способы доставки
            $('body').trigger('update_checkout');
        }
    }

    // Глобальная функция для выбора пункта с карты
    window.selectPickupPointFromMap = function(index) {
        selectPickupPoint(index);
    };

    // Подсветка пункта на карте
    function highlightPointOnMap(index) {
        if (!cdekMap || !cdekPickupPoints[index]) return;
        
        const point = cdekPickupPoints[index];
        if (point.location && point.location.latitude && point.location.longitude) {
            const coords = [parseFloat(point.location.latitude), parseFloat(point.location.longitude)];
            cdekMap.setCenter(coords, 15);
        }
    }

    // Подсветка пункта в списке
    function highlightPickupPoint(index) {
        $('.cdek-pickup-point').removeClass('highlighted');
        $(`.cdek-pickup-point[data-index="${index}"]`).addClass('highlighted');
    }

    // Показать информацию о выбранном пункте
    function showSelectedPoint() {
        if (!selectedPickupPoint) return;
        
        const address = selectedPickupPoint.location ? 
            selectedPickupPoint.location.address_full : 
            selectedPickupPoint.address_comment;
        
        const html = `
            <div class="selected-pickup-info">
                <h4>Выбранный пункт выдачи:</h4>
                <strong>${selectedPickupPoint.name}</strong><br>
                ${address}
            </div>
        `;
        
        $('#cdek-selected-point-info').html(html);
    }

    // Форматирование времени работы
    function formatWorkTime(workTime) {
        if (!workTime || !Array.isArray(workTime)) return '';
        
        const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        let formatted = [];
        
        workTime.forEach(function(schedule) {
            if (schedule.day && schedule.time) {
                const dayName = days[schedule.day - 1] || schedule.day;
                formatted.push(`${dayName}: ${schedule.time}`);
            }
        });
        
        return formatted.join(', ');
    }

    // Показать ошибку
    function showError(message) {
        $('#cdek-pickup-points-list').html(`<p class="error">${message}</p>`);
    }

    // Отслеживание изменений в поле города
    let cityTimeout;
    $(document).on('input change', '#shipping-city, #shipping_city, input[name="shipping_city"]', function() {
        const city = cleanCityName($(this).val());
        
        clearTimeout(cityTimeout);
        cityTimeout = setTimeout(function() {
            if (city && city.length > 2) {
                loadPickupPoints(city);
                showCdekMapContainer();
            } else {
                hideCdekMapContainer();
            }
        }, 1000);
    });

    // Показать контейнер с картой
    function showCdekMapContainer() {
        if ($('#cdek-pickup-container').length === 0) {
            const container = `
                <div id="cdek-pickup-container" class="cdek-pickup-container">
                    <h3>Выбор пункта выдачи СДЭК</h3>
                    <div class="cdek-content">
                        <div id="cdek-map" class="cdek-map"></div>
                        <div id="cdek-pickup-points-list" class="cdek-pickup-points-list"></div>
                    </div>
                    <div id="cdek-selected-point-info"></div>
                    <input type="hidden" id="cdek-selected-pickup-point" name="cdek_selected_pickup_point" value="">
                </div>
            `;
            
            // Ищем подходящее место для вставки
            const targetElement = $('#shipping .wc-block-components-address-form').length ? 
                $('#shipping .wc-block-components-address-form') : 
                $('#shipping');
            
            targetElement.after(container);
            
            // Инициализируем карту с задержкой
            setTimeout(initCdekMap, 100);
        } else {
            $('#cdek-pickup-container').show();
        }
    }

    // Скрыть контейнер с картой
    function hideCdekMapContainer() {
        $('#cdek-pickup-container').hide();
        selectedPickupPoint = null;
        $('#cdek-selected-pickup-point').val('');
        $('#cdek-selected-point-info').empty();
    }

    // Скрыть контейнер при смене способа доставки
    $(document).on('change', 'input[name^="radio_control_"]', function() {
        const selectedMethod = $(this).val();
        if (selectedMethod && !selectedMethod.includes('cdek_shipping')) {
            $('#cdek-pickup-container').hide();
        }
    });

    // Инициализация при загрузке страницы
    function initOnPageLoad() {
        const cityField = $('#shipping-city, #shipping_city, input[name="shipping_city"]').first();
        if (cityField.length > 0) {
            const city = cleanCityName(cityField.val());
            if (city && city.length > 2) {
                loadPickupPoints(city);
                showCdekMapContainer();
            }
        }
    }
    
    // Запускаем инициализацию
    $(document).ready(initOnPageLoad);
    
    // Также при обновлении checkout
    $(document.body).on('updated_checkout', initOnPageLoad);
});