jQuery(document).ready(function($) {
    
    function fixCdekCartDisplay() {
        // Исправляем отображение "СДЭК — Пункт выдачи" в корзине
        $('.wc-block-components-totals-item__label').each(function() {
            var $label = $(this);
            var text = $label.text();
            
            // Исправляем HTML-сущности и заменяем на правильный текст
            if (text.includes('СДЭК') && (text.includes('&#8212;') || text.includes('—') || text.includes('Пункт выдачи'))) {
                $label.text('Выберите пункт выдачи');
            }
        });
        
        // Убираем описание если не выбран пункт выдачи
        $('.wc-block-components-totals-item').each(function() {
            var $item = $(this);
            var labelText = $item.find('.wc-block-components-totals-item__label').text();
            
            if (labelText === 'Выберите пункт выдачи') {
                var $description = $item.find('.wc-block-components-totals-item__description');
                $description.html('');
                
                var $value = $item.find('.wc-block-components-totals-item__value');
                $value.text('');
            }
        });
    }
    
    function resetShippingDescriptionOnMethodChange() {
        // Сбрасываем описание доставки при смене метода доставки
        $('.wc-block-components-totals-item').each(function() {
            var $item = $(this);
            var labelText = $item.find('.wc-block-components-totals-item__label').text();
            
            // Проверяем все возможные варианты названий
            if (labelText.includes('СДЭК') || 
                labelText.includes('Выберите пункт выдачи') ||
                labelText.includes('Москва') ||
                labelText.includes('Санкт-Петербург') ||
                labelText.includes('Самовывоз') ||
                labelText.includes('pickup')) {
                
                var $description = $item.find('.wc-block-components-totals-item__description');
                $description.html('');
            }
        });
    }
    
    // Запускаем исправления при загрузке
    fixCdekCartDisplay();
    
    // Повторяем через интервалы для динамически загружаемого контента
    setInterval(fixCdekCartDisplay, 2000);
    
    // Отслеживаем изменения в методах доставки
    $(document).on('change', 'input[name*="shipping"], input[name*="radio-control"]', function() {
        setTimeout(function() {
            resetShippingDescriptionOnMethodChange();
            fixCdekCartDisplay();
        }, 500);
    });
    
    // Наблюдатель за изменениями DOM в корзине
    var cartObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                var hasShippingChanges = false;
                
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        var $node = $(node);
                        if ($node.find('.wc-block-components-totals-item__label').length > 0 ||
                            $node.hasClass('wc-block-components-totals-item') ||
                            $node.find('.wc-block-components-totals-shipping').length > 0) {
                            hasShippingChanges = true;
                        }
                    }
                });
                
                if (hasShippingChanges) {
                    setTimeout(function() {
                        fixCdekCartDisplay();
                        resetShippingDescriptionOnMethodChange();
                    }, 100);
                }
            }
        });
    });
    
    // Запускаем наблюдатель для всей страницы
    cartObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Дополнительная проверка через большие интервалы
    setTimeout(function() {
        fixCdekCartDisplay();
        resetShippingDescriptionOnMethodChange();
    }, 5000);
    
    setTimeout(function() {
        fixCdekCartDisplay();
        resetShippingDescriptionOnMethodChange();
    }, 10000);
});