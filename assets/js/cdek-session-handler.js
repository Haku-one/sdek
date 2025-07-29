/**
 * СДЭК - Обработчик сохранения данных в сессии
 * Этот скрипт следит за выбором ПВЗ и автоматически сохраняет данные в WooCommerce сессии
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('CDEK Session Handler: Инициализирован');
    
    // Следим за изменениями в скрытых полях СДЭК
    function saveCdekDataToSession() {
        const pointCode = $('#cdek-selected-point-code').val();
        const pointData = $('#cdek-selected-point-data').val();
        const deliveryCost = $('#cdek-delivery-cost').val();
        
        console.log('CDEK Session: Проверяем данные для сохранения');
        console.log('Point Code:', pointCode);
        console.log('Delivery Cost:', deliveryCost);
        
        if (pointCode || deliveryCost) {
            console.log('CDEK Session: Отправляем данные в сессию');
            
            $.ajax({
                url: cdek_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_cdek_data_to_session',
                    nonce: cdek_ajax.nonce,
                    point_code: pointCode,
                    point_data: pointData,
                    delivery_cost: deliveryCost
                },
                success: function(response) {
                    if (response.success) {
                        console.log('CDEK Session: Данные успешно сохранены в сессии');
                    } else {
                        console.error('CDEK Session: Ошибка сохранения данных');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CDEK Session: AJAX ошибка:', error);
                }
            });
        }
    }
    
    // Следим за созданием и изменением скрытых полей
    const targetNode = document.querySelector('body');
    const config = { childList: true, subtree: true, attributes: true };
    
    const observer = new MutationObserver(function(mutationsList) {
        for (let mutation of mutationsList) {
            // Проверяем добавление новых элементов
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.id === 'cdek-selected-point-code' || 
                            node.id === 'cdek-delivery-cost' ||
                            node.querySelector('#cdek-selected-point-code') ||
                            node.querySelector('#cdek-delivery-cost')) {
                            
                            console.log('CDEK Session: Обнаружено создание СДЭК поля');
                            setTimeout(saveCdekDataToSession, 500);
                        }
                    }
                });
            }
            
            // Проверяем изменение атрибутов (в том числе value)
            if (mutation.type === 'attributes' && 
                (mutation.target.id === 'cdek-selected-point-code' || 
                 mutation.target.id === 'cdek-delivery-cost')) {
                
                console.log('CDEK Session: Обнаружено изменение СДЭК поля');
                setTimeout(saveCdekDataToSession, 500);
            }
        }
    });
    
    if (targetNode) {
        observer.observe(targetNode, config);
    }
    
    // Дополнительные события для отслеживания изменений
    $(document).on('change', '#cdek-selected-point-code, #cdek-delivery-cost', function() {
        console.log('CDEK Session: Change event на СДЭК поле');
        setTimeout(saveCdekDataToSession, 500);
    });
    
    // Сохраняем данные при обновлении checkout
    $(document.body).on('updated_checkout', function() {
        console.log('CDEK Session: Checkout обновлен, проверяем данные');
        setTimeout(saveCdekDataToSession, 1000);
    });
    
    // Сохраняем данные перед отправкой формы
    $('form.checkout').on('submit', function() {
        console.log('CDEK Session: Форма отправляется, сохраняем данные');
        saveCdekDataToSession();
    });
    
    // Периодическая проверка (каждые 5 секунд)
    setInterval(function() {
        const pointCode = $('#cdek-selected-point-code').val();
        const deliveryCost = $('#cdek-delivery-cost').val();
        
        if ((pointCode || deliveryCost) && !window.cdekDataSaved) {
            console.log('CDEK Session: Периодическая проверка - сохраняем данные');
            saveCdekDataToSession();
            window.cdekDataSaved = true;
        }
    }, 5000);
    
    console.log('CDEK Session Handler: Все обработчики установлены');
});