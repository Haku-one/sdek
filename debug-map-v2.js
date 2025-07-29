// Обновленный отладочный скрипт для диагностики проблем с картой СДЭК v2
// Исправляет проблемы с отображением карты и добавляет поддержку постоматов

function debugCdekMapV2() {
    console.log('🔍 === ДИАГНОСТИКА КАРТЫ СДЭК V2 ===');
    
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
    
    // 3. ПРИНУДИТЕЛЬНОЕ ИСПРАВЛЕНИЕ ОТОБРАЖЕНИЯ
    console.log('🔧 === ПРИНУДИТЕЛЬНОЕ ИСПРАВЛЕНИЕ ===');
    
    if (wpBlock) {
        // Если блок пустой или карта не отображается
        if (!wpBlock.innerHTML.trim() || !mapElement || mapElement.offsetWidth === 0) {
            const mapHtml = `
                <div id="cdek-map-container" style="margin-top: 20px; display: block !important; visibility: visible !important;">
                    <h4>Выберите пункт выдачи СДЭК на карте:</h4>
                    <div id="cdek-points-info" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;">
                        <strong>Информация:</strong>
                        <div id="cdek-points-count">🟦 ПВЗ (синие метки) и 🟧 Постоматы (оранжевые метки)</div>
                    </div>
                    <div id="cdek-selected-point" style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; display: none;">
                        <strong>Выбранный пункт:</strong>
                        <div id="cdek-point-info"></div>
                    </div>
                    <div id="cdek-map" style="width: 100% !important; height: 450px !important; border: 1px solid #ddd; border-radius: 6px; display: block !important; visibility: visible !important; position: relative !important;"></div>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        💡 Введите город в поле адреса выше, затем выберите пункт выдачи на карте<br>
                        🟦 Синие метки - пункты выдачи заказов (ПВЗ)<br>
                        🟧 Оранжевые метки - постоматы
                    </p>
                </div>
            `;
            
            wpBlock.innerHTML = mapHtml;
            console.log('✅ HTML карты вставлен в wp-block');
        }
        
        // Принудительно показываем все уровни
        wpBlock.style.cssText = 'display: block !important; visibility: visible !important; height: auto !important; min-height: 500px !important; position: relative !important;';
        
        const newMapContainer = document.getElementById('cdek-map-container');
        const newMapElement = document.getElementById('cdek-map');
        
        if (newMapContainer) {
            newMapContainer.style.cssText = 'display: block !important; visibility: visible !important; position: relative !important; margin-top: 20px;';
        }
        
        if (newMapElement) {
            newMapElement.style.cssText = 'display: block !important; visibility: visible !important; width: 100% !important; height: 450px !important; position: relative !important;';
        }
        
        console.log('🔧 Принудительно показаны все контейнеры');
        
        // 4. Переинициализация карты
        setTimeout(() => {
            if (typeof window.ymaps !== 'undefined') {
                console.log('🗺️ Переинициализация карты...');
                
                // Сбрасываем карту
                window.cdekMap = null;
                
                // Запускаем инициализацию
                if (typeof window.initYandexMap === 'function') {
                    window.initYandexMap();
                    console.log('🔄 Карта переинициализирована');
                    
                    // Проверяем через 2 секунды
                    setTimeout(() => {
                        const finalMapElement = document.getElementById('cdek-map');
                        if (finalMapElement && finalMapElement.offsetWidth > 0) {
                            console.log('✅ Карта успешно отображается!');
                            console.log('📏 Финальные размеры:', {
                                width: finalMapElement.offsetWidth,
                                height: finalMapElement.offsetHeight
                            });
                        } else {
                            console.warn('⚠️ Карта все еще не отображается');
                        }
                    }, 2000);
                } else {
                    console.error('❌ Функция initYandexMap не найдена');
                }
            } else {
                console.error('❌ Яндекс.Карты не загружены');
            }
        }, 500);
    } else {
        console.error('❌ Блок wp-block-cdek-checkout-map-block не найден');
    }
    
    console.log('🔍 === ДИАГНОСТИКА V2 ЗАВЕРШЕНА ===');
}

// Функция для принудительного показа карты
function forceShowMap() {
    console.log('🚀 Принудительный показ карты...');
    
    const wpBlock = document.querySelector('.wp-block-cdek-checkout-map-block');
    if (wpBlock) {
        wpBlock.style.cssText = 'display: block !important; visibility: visible !important; height: auto !important;';
        
        const mapContainer = document.getElementById('cdek-map-container');
        const mapElement = document.getElementById('cdek-map');
        
        if (mapContainer) {
            mapContainer.style.cssText = 'display: block !important; visibility: visible !important;';
        }
        
        if (mapElement) {
            mapElement.style.cssText = 'display: block !important; visibility: visible !important; width: 100% !important; height: 450px !important;';
            
            // Принудительная перерисовка карты
            if (window.cdekMap && window.cdekMap.container) {
                setTimeout(() => {
                    window.cdekMap.container.fitToViewport();
                    console.log('🔄 Карта принудительно перерисована');
                }, 100);
            }
        }
        
        console.log('✅ Принудительный показ завершен');
    }
}

// Автозапуск диагностики
debugCdekMapV2();

// Дополнительные функции доступны в консоли
console.log('✅ Диагностические функции V2 загружены:');
console.log('- debugCdekMapV2() - полная диагностика и исправление');
console.log('- forceShowMap() - принудительный показ карты');

// Мониторинг изменений
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.target.classList && mutation.target.classList.contains('wp-block-cdek-checkout-map-block')) {
            console.log('🔄 Изменение в блоке карты обнаружено');
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
}