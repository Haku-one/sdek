// CDEK checkout integration for WooCommerce Blocks - simplified version

// CDEK checkout integration for WooCommerce Blocks
(function() {
    let cdekMap = null;
    let cdekPickupPoints = [];
    let selectedPickupPoint = null;
    let mapInitialized = false;

    // Check if we're on checkout page
    if (!document.querySelector('.wp-block-woocommerce-checkout')) {
        return;
    }

    // Initialize CDEK functionality
    function initCdekCheckout() {
        observeAddressChanges();
        initYandexMaps();
        addCdekStyles();
    }

    // Initialize Yandex Maps
    function initYandexMaps() {
        if (typeof ymaps === 'undefined') {
            setTimeout(initYandexMaps, 500);
            return;
        }

        if (mapInitialized) return;

        ymaps.ready(function() {
            mapInitialized = true;
        });
    }

    // Observe changes in address field (which is now city field)
    function observeAddressChanges() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Look for address_1 inputs (which are now city inputs)
                    const cityInputs = document.querySelectorAll('input[id*="address"], input[name*="address"], input[id*="shipping-address_1"]');
                    cityInputs.forEach(function(input) {
                        if (!input.hasAttribute('data-cdek-listener')) {
                            input.setAttribute('data-cdek-listener', 'true');
                            input.addEventListener('input', debounce(handleCityChange, 1000));
                            input.addEventListener('change', debounce(handleCityChange, 1000));
                        }
                    });
                    
                    // Also check for CDEK shipping method selection
                    const shippingRadios = document.querySelectorAll('input[value*="cdek_shipping"]');
                    shippingRadios.forEach(function(radio) {
                        if (!radio.hasAttribute('data-cdek-shipping-listener')) {
                            radio.setAttribute('data-cdek-shipping-listener', 'true');
                            radio.addEventListener('change', handleShippingMethodChange);
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Handle city field changes
    function handleCityChange(event) {
        const city = cleanCityName(event.target.value);
        
        // Only show CDEK interface if CDEK shipping is selected
        if (isCdekShippingSelected() && city && city.length > 2) {
            loadPickupPoints(city);
        } else {
            hideCdekContainer();
        }
    }

    // Handle shipping method change
    function handleShippingMethodChange(event) {
        if (event.target.value.includes('cdek_shipping')) {
            // CDEK shipping selected, check if we have a city
            const cityInput = document.querySelector('input[id*="shipping-address_1"]');
            if (cityInput && cityInput.value.trim().length > 2) {
                const city = cleanCityName(cityInput.value);
                loadPickupPoints(city);
            }
        } else {
            // Other shipping method selected, hide CDEK interface
            hideCdekContainer();
        }
    }

    // Check if CDEK shipping method is selected
    function isCdekShippingSelected() {
        const selectedRadio = document.querySelector('input[value*="cdek_shipping"]:checked');
        return selectedRadio !== null;
    }

    // Clean city name
    function cleanCityName(city) {
        if (!city) return '';
        
        city = city.trim();
        city = city.replace(/^(г\.?|город)\s+/i, '');
        city = city.replace(/\s+/g, ' ');
        
        return city;
    }

    // Load pickup points from API
    function loadPickupPoints(city) {
        const apiUrl = cdek_checkout_params.rest_url + 'pickup-points?city=' + encodeURIComponent(city);
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    cdekPickupPoints = data;
                    showCdekContainer();
                    displayPickupPoints();
                    updateMapWithPoints();
                } else {
                    showError('Пункты выдачи в данном городе не найдены');
                }
            })
            .catch(error => {
                console.error('CDEK API Error:', error);
                showError('Ошибка при загрузке пунктов выдачи');
            });
    }

    // Show CDEK container
    function showCdekContainer() {
        let container = document.getElementById('cdek-pickup-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'cdek-pickup-container';
            container.className = 'cdek-pickup-container';
            container.innerHTML = `
                <h3>Выбор пункта выдачи СДЭК</h3>
                <div class="cdek-content">
                    <div id="cdek-map" class="cdek-map"></div>
                    <div id="cdek-pickup-points-list" class="cdek-pickup-points-list"></div>
                </div>
                <div id="cdek-selected-point-info"></div>
                <input type="hidden" id="cdek-selected-pickup-point" name="cdek_selected_pickup_point" value="">
            `;
            
            // Find the CDEK shipping option and insert container right after it
            const cdekRadio = document.querySelector('input[value*="cdek_shipping"]:checked');
            if (cdekRadio) {
                const radioContainer = cdekRadio.closest('.wc-block-components-radio-control');
                if (radioContainer) {
                    radioContainer.parentNode.insertBefore(container, radioContainer.nextSibling);
                } else {
                    insertInDefaultLocation(container);
                }
            } else {
                insertInDefaultLocation(container);
            }
            
            // Initialize map
            setTimeout(initCdekMap, 100);
        } else {
            container.style.display = 'block';
        }
    }

    // Insert container in default location
    function insertInDefaultLocation(container) {
        const shippingSection = document.querySelector('.wc-block-checkout__shipping-option');
        if (shippingSection) {
            shippingSection.appendChild(container);
        } else {
            const checkoutForm = document.querySelector('.wp-block-woocommerce-checkout');
            if (checkoutForm) {
                checkoutForm.appendChild(container);
            }
        }
    }

    // Hide CDEK container
    function hideCdekContainer() {
        const container = document.getElementById('cdek-pickup-container');
        if (container) {
            container.style.display = 'none';
        }
        selectedPickupPoint = null;
        const hiddenInput = document.getElementById('cdek-selected-pickup-point');
        if (hiddenInput) {
            hiddenInput.value = '';
        }
    }

    // Initialize CDEK map
    function initCdekMap() {
        if (!mapInitialized || typeof ymaps === 'undefined') {
            setTimeout(initCdekMap, 500);
            return;
        }

        const mapContainer = document.getElementById('cdek-map');
        if (mapContainer && !cdekMap) {
            ymaps.ready(function() {
                cdekMap = new ymaps.Map('cdek-map', {
                    center: [55.76, 37.64],
                    zoom: 10,
                    controls: ['zoomControl', 'searchControl']
                });
            });
        }
    }

    // Display pickup points list
    function displayPickupPoints() {
        const container = document.getElementById('cdek-pickup-points-list');
        if (!container) return;
        
        container.innerHTML = '';

        if (cdekPickupPoints.length === 0) {
            container.innerHTML = '<p>Пункты выдачи не найдены</p>';
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
        container.innerHTML = html;

        // Add event listeners
        container.querySelectorAll('.select-point-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                selectPickupPoint(index);
            });
        });

        container.querySelectorAll('.cdek-pickup-point').forEach(function(item) {
            item.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                highlightPointOnMap(index);
            });
        });
    }

    // Update map with pickup points
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
                        <button onclick="window.selectPickupPointFromMap(${index})" style="margin-top:8px;padding:4px 8px;background:#007cba;color:white;border:none;border-radius:4px;cursor:pointer;">Выбрать этот пункт</button>
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

        // Fit map bounds
        if (coordinates.length > 0) {
            cdekMap.setBounds(cdekMap.geoObjects.getBounds(), {
                checkZoomRange: true,
                zoomMargin: 30
            });
        }
    }

    // Select pickup point
    function selectPickupPoint(index) {
        if (index >= 0 && index < cdekPickupPoints.length) {
            selectedPickupPoint = cdekPickupPoints[index];
            
            // Update UI
            document.querySelectorAll('.cdek-pickup-point').forEach(function(item) {
                item.classList.remove('selected');
            });
            const selectedItem = document.querySelector(`.cdek-pickup-point[data-index="${index}"]`);
            if (selectedItem) {
                selectedItem.classList.add('selected');
            }
            
            // Save selected point
            const hiddenInput = document.getElementById('cdek-selected-pickup-point');
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(selectedPickupPoint);
            }
            
            // Show selected point info
            showSelectedPoint();
        }
    }

    // Global function for map selection
    window.selectPickupPointFromMap = function(index) {
        selectPickupPoint(index);
    };

    // Highlight point on map
    function highlightPointOnMap(index) {
        if (!cdekMap || !cdekPickupPoints[index]) return;
        
        const point = cdekPickupPoints[index];
        if (point.location && point.location.latitude && point.location.longitude) {
            const coords = [parseFloat(point.location.latitude), parseFloat(point.location.longitude)];
            cdekMap.setCenter(coords, 15);
        }
    }

    // Highlight pickup point in list
    function highlightPickupPoint(index) {
        document.querySelectorAll('.cdek-pickup-point').forEach(function(item) {
            item.classList.remove('highlighted');
        });
        const item = document.querySelector(`.cdek-pickup-point[data-index="${index}"]`);
        if (item) {
            item.classList.add('highlighted');
        }
    }

    // Show selected point info
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
        
        const infoContainer = document.getElementById('cdek-selected-point-info');
        if (infoContainer) {
            infoContainer.innerHTML = html;
        }
    }

    // Format work time
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

    // Show error message
    function showError(message) {
        const container = document.getElementById('cdek-pickup-points-list');
        if (container) {
            container.innerHTML = `<p class="error">${message}</p>`;
        }
    }

    // Add CDEK styles
    function addCdekStyles() {
        if (document.getElementById('cdek-checkout-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'cdek-checkout-styles';
        style.textContent = `
            .cdek-pickup-container { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
            .cdek-pickup-container h3 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
            .cdek-content { display: flex; gap: 20px; margin-bottom: 15px; }
            .cdek-map { width: 60%; height: 400px; border: 1px solid #ccc; border-radius: 4px; }
            .cdek-pickup-points-list { width: 40%; max-height: 400px; overflow-y: auto; }
            .cdek-pickup-point { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; cursor: pointer; transition: all 0.3s ease; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
            .cdek-pickup-point:hover { border-color: #007cba; }
            .cdek-pickup-point.selected { border-color: #46b450; background: #f0fff0; }
            .select-point-btn { background: #007cba; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
            .select-point-btn:hover { background: #005a87; }
            .selected-pickup-info { padding: 15px; background: #e8f5e8; border: 1px solid #46b450; border-radius: 6px; margin-top: 15px; }
            @media (max-width: 768px) { .cdek-content { flex-direction: column; } .cdek-map, .cdek-pickup-points-list { width: 100%; } }
        `;
        document.head.appendChild(style);
    }

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCdekCheckout);
    } else {
        initCdekCheckout();
    }

})();