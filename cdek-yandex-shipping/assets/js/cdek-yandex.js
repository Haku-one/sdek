jQuery(function ($) {
  let map;
  const $address = $('#shipping-address_1');

  // При потере фокуса из поля адреса — ищем ПВЗ.
  $address.on('blur', function () {
    const addr = $(this).val();
    if (!addr) return;

    // Ждём готовности API Яндекс.Карт.
    if (typeof ymaps === 'undefined') return;

    ymaps.ready(function () {
      ymaps.geocode(addr, { results: 1 }).then(function (res) {
        if (!res || !res.geoObjects || !res.geoObjects.get(0)) return;
        const coords = res.geoObjects.get(0).geometry.getCoordinates();
        loadCDEKPoints(coords, addr);
      });
    });
  });

  /**
   * Запрашиваем точки СДЭК через AJAX.
   */
  function loadCDEKPoints(coords, addr) {
    $.post(
      wc_cdek_yandex.ajax_url,
      {
        action: 'wc_cdek_get_points',
        nonce: wc_cdek_yandex.nonce,
        lat: coords[0],
        lng: coords[1],
        address: addr,
      },
      function (resp) {
        if (resp.success) {
          renderMap(coords, resp.data);
        }
      }
    );
  }

  /**
   * Рисуем карту и маркеры.
   */
  function renderMap(center, points) {
    if ($('#cdek-map').length === 0) {
      $('<div id="cdek-map" style="width:100%;height:400px;margin:20px 0;"></div>')
        .insertBefore('.woocommerce-checkout-review-order');
    }

    if (!map) {
      map = new ymaps.Map('cdek-map', {
        center: center,
        zoom: 12,
      });
    } else {
      map.geoObjects.removeAll();
      map.setCenter(center);
    }

    points.forEach(function (p) {
      if (!p.location) return;
      const placemark = new ymaps.Placemark(
        [p.location.latitude, p.location.longitude],
        {
          balloonContent:
            '<strong>' +
            p.name +
            '</strong><br>' +
            p.location.address_full +
            '<br><button class="select-cdek-point" data-code="' +
            p.code +
            '" data-address="' +
            p.location.address_full.replace(/"/g, '&quot;') +
            '">Выбрать</button>',
        },
        { preset: 'islands#blueIcon' }
      );
      map.geoObjects.add(placemark);
    });
  }

  // Сохраняем выбор ПВЗ.
  $(document).on('click', '.select-cdek-point', function () {
    const code = $(this).data('code');
    const address = $(this).data('address');
    $('#cdek_point_code').val(code);
    $('#cdek_point_address').val(address);
    alert('Выбрано: ' + address);
    $.fancybox && $.fancybox.close();
  });
});