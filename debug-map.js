// Отладочный скрипт для диагностики проблем с картой СДЭК
// Вставьте этот код в консоль браузера для диагностики

function debugCdekMap() {
    console.log('🔍 === ДИАГНОСТИКА КАРТЫ СДЭК ===');
    
    // 1. Проверяем наличие блоков
    const wpBlock = document.querySelector('.wp-block-cdek-checkout-map-block');
    const mapContainer = document.getElementById('cdek-map-container');
    const mapElement = document.getElementById('cdek-map');
    
    console.log('📋 Проверка блоков:');
    console.log('- wp-block-cdek-checkout-map-block:', !!wpBlock, wpBlock);
    console.log('- cdek-map-container:', !!mapContainer, mapContainer);
    console.log('- cdek-map:', !!mapElement, mapElement);
    
    // 2. Проверяем размеры и стили
    if (wpBlock) {
        const wpStyles = getComputedStyle(wpBlock);
        console.log('🎨 Стили wp-block:', {
            display: wpStyles.display,
            visibility: wpStyles.visibility,
            width: wpStyles.width,
            height: wpStyles.height,
            innerHTML: wpBlock.innerHTML.length + ' символов'
        });
    }
    
    if (mapContainer) {
        const containerStyles = getComputedStyle(mapContainer);
        console.log('🎨 Стили map-container:', {
            display: containerStyles.display,
            visibility: containerStyles.visibility,
            width: containerStyles.width,
            height: containerStyles.height,
            offsetWidth: mapContainer.offsetWidth,
            offsetHeight: mapContainer.offsetHeight
        });
    }
    
    if (mapElement) {
        const mapStyles = getComputedStyle(mapElement);
        console.log('🎨 Стили map-element:', {
            display: mapStyles.display,
            visibility: mapStyles.visibility,
            width: mapStyles.width,
            height: mapStyles.height,
            offsetWidth: mapElement.offsetWidth,
            offsetHeight: mapElement.offsetHeight
        });
    }
    
    // 3. Проверяем состояние карты
    console.log('🗺️ Состояние карты:');
    console.log('- window.cdekMap:', typeof window.cdekMap, window.cdekMap);
    console.log('- window.ymaps:', typeof window.ymaps);
    
    // 4. Проверяем jQuery элементы
    if (typeof $ !== 'undefined') {
        console.log('📦 jQuery элементы:');
        console.log('- $(".wp-block-cdek-checkout-map-block").length:', $('.wp-block-cdek-checkout-map-block').length);
        console.log('- $("#cdek-map-container").length:', $('#cdek-map-container').length);
        console.log('- $("#cdek-map").length:', $('#cdek-map').length);
        console.log('- $("#cdek-map").is(":visible"):', $('#cdek-map').is(':visible'));
    }
    
    // 5. Принудительное исправление
    console.log('🔧 Попытка принудительного исправления...');
    
    if (wpBlock && !wpBlock.innerHTML.trim()) {
        const mapHtml = `
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
        
        wpBlock.innerHTML = mapHtml;
        console.log('✅ HTML карты вставлен в wp-block');
        
        // Принудительно показываем элементы
        wpBlock.style.cssText = 'display: block !important; visibility: visible !important;';
        
        // Переинициализируем карту
        setTimeout(() => {
            if (typeof window.initYandexMap === 'function') {
                window.cdekMap = null;
                window.initYandexMap();
                console.log('🔄 Карта переинициализирована');
            }
        }, 500);
    }
    
    console.log('🔍 === ДИАГНОСТИКА ЗАВЕРШЕНА ===');
}

// Запуск диагностики
debugCdekMap();

// Дополнительная функция для мониторинга
function watchMapChanges() {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target.classList.contains('wp-block-cdek-checkout-map-block')) {
                console.log('🔄 Изменение в wp-block-cdek-checkout-map-block:', mutation);
            }
        });
    });
    
    const wpBlock = document.querySelector('.wp-block-cdek-checkout-map-block');
    if (wpBlock) {
        observer.observe(wpBlock, {
            childList: true,
            subtree: true,
            attributes: true
        });
        console.log('👁️ Мониторинг изменений wp-block запущен');
    }
}

// Запуск мониторинга
watchMapChanges();

console.log('✅ Отладочные функции загружены. Используйте debugCdekMap() для повторной диагностики.');