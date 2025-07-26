( function() {
    'use strict';

    // Проверяем наличие wp.hooks для добавления фильтров
    if ( typeof wp !== 'undefined' && wp.hooks ) {
        
        // Добавляем фильтр для модификации полей адреса в блоках
        wp.hooks.addFilter(
            'woocommerce_blocks_checkout_address_fields',
            'cdek-delivery/hide-address-fields',
            function( fields ) {
                // Убираем ненужные поля из формы адреса доставки
                if ( fields && fields.shipping ) {
                    delete fields.shipping.city;
                    delete fields.shipping.state;
                    delete fields.shipping.postcode;
                    
                    // Модифицируем поле адреса
                    if ( fields.shipping.address_1 ) {
                        fields.shipping.address_1.label = 'Адрес (город, улица, дом)';
                        fields.shipping.address_1.placeholder = 'Например: Москва, ул. Ленина, д. 1';
                    }
                }
                
                return fields;
            }
        );

        // Добавляем функциональность СДЭК после инициализации блоков
        wp.hooks.addAction(
            'woocommerce_blocks_checkout_block_rendered',
            'cdek-delivery/init-checkout',
            function() {
                initCdekForBlocks();
            }
        );
    }

    // Функция инициализации СДЭК для блоков
    function initCdekForBlocks() {
        // Добавляем обработчики для новых блоков WooCommerce
        const checkoutContainer = document.querySelector('.wp-block-woocommerce-checkout');
        
        if ( checkoutContainer ) {
            // Инициализируем наблюдатель за изменениями в DOM
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Ищем метод доставки СДЭК
                        const cdekShippingMethod = document.querySelector('input[value*="cdek_delivery"]');
                        if (cdekShippingMethod) {
                            initCdekIntegration();
                        }
                    }
                });
            });

            observer.observe(checkoutContainer, {
                childList: true,
                subtree: true
            });
        }
    }

    // Функция инициализации интеграции СДЭК
    function initCdekIntegration() {
        // Проверяем, не инициализировано ли уже
        if (document.getElementById('cdek-map-container')) {
            return;
        }

        // Добавляем стили для скрытия полей
        addCdekStyles();

        // Добавляем обработчик изменения метода доставки
        document.addEventListener('change', function(e) {
            if (e.target.name && e.target.name.includes('shipping_method') && e.target.value.includes('cdek_delivery')) {
                initCdekDelivery();
            } else if (e.target.name && e.target.name.includes('shipping_method')) {
                hideCdekMap();
            }
        });

        // Добавляем обработчик изменения адреса
        document.addEventListener('input', function(e) {
            if (e.target.id === 'shipping-address_1' || e.target.name === 'shipping_address_1') {
                const address = e.target.value;
                if (address.length > 5) {
                    searchCdekPoints(address);
                }
            }
        });
    }

    // Добавление стилей для скрытия полей
    function addCdekStyles() {
        if (document.getElementById('cdek-block-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'cdek-block-styles';
        style.textContent = `
            .wp-block-woocommerce-checkout .wc-block-components-address-form__city,
            .wp-block-woocommerce-checkout .wc-block-components-address-form__state,
            .wp-block-woocommerce-checkout .wc-block-components-address-form__postcode {
                display: none !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Функции для работы с картой и пунктами (переиспользуем из основного скрипта)
    window.initCdekDelivery = window.initCdekDelivery || function() {
        // Реализация аналогична основному скрипту
        console.log('СДЭК: Инициализация для блоков');
    };

    window.hideCdekMap = window.hideCdekMap || function() {
        const container = document.getElementById('cdek-map-container');
        if (container) {
            container.style.display = 'none';
        }
    };

    window.searchCdekPoints = window.searchCdekPoints || function(address) {
        if (typeof jQuery !== 'undefined') {
            jQuery.ajax({
                url: cdekBlockData.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_cdek_points',
                    address: address,
                    nonce: cdekBlockData.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Обработка ответа
                        console.log('СДЭК: Найдены пункты выдачи', response.data);
                    }
                }
            });
        }
    };

    // Инициализация после загрузки DOM
    document.addEventListener('DOMContentLoaded', function() {
        // Небольшая задержка для полной загрузки блоков
        setTimeout(initCdekForBlocks, 1000);
    });

})();