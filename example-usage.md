# Примеры использования плагина СДЭК Доставка

## Добавление габаритов к существующим товарам

```php
// functions.php вашей темы

// Автоматическое добавление габаритов к товарам при импорте
add_action('woocommerce_product_import_inserted_product_object', 'auto_add_product_dimensions', 10, 2);

function auto_add_product_dimensions($product, $data) {
    // Пример: устанавливаем стандартные габариты для товаров без размеров
    if (!$product->get_length() || !$product->get_width() || !$product->get_height()) {
        $product->set_length(20);  // 20 см
        $product->set_width(15);   // 15 см  
        $product->set_height(10);  // 10 см
        $product->save();
    }
    
    // Устанавливаем минимальный вес если не указан
    if (!$product->get_weight()) {
        $product->set_weight(500); // 500 грамм
        $product->save();
    }
}

// Массовое обновление габаритов для существующих товаров
function bulk_update_product_dimensions() {
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish'
    ));
    
    foreach ($products as $product) {
        // Проверяем есть ли габариты
        if (!$product->get_length() || !$product->get_width() || !$product->get_height()) {
            // Устанавливаем габариты в зависимости от категории
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            
            foreach ($categories as $category) {
                switch ($category->slug) {
                    case 'books':
                        $product->set_length(24);
                        $product->set_width(17);
                        $product->set_height(2);
                        break;
                    case 'electronics':
                        $product->set_length(30);
                        $product->set_width(20);
                        $product->set_height(15);
                        break;
                    default:
                        $product->set_length(25);
                        $product->set_width(20);
                        $product->set_height(10);
                }
            }
            
            $product->save();
        }
    }
}

// Запуск обновления (выполнить один раз)
// bulk_update_product_dimensions();
```

## Кастомизация отображения габаритов

```php
// Изменение отображения габаритов на странице оформления заказа
add_filter('woocommerce_checkout_after_order_review', 'custom_product_dimensions_display', 15);

function custom_product_dimensions_display() {
    $cart_items = WC()->cart->get_cart();
    
    if (empty($cart_items)) {
        return;
    }
    
    echo '<div class="custom-dimensions-info">';
    echo '<h4>Информация о посылке:</h4>';
    
    $total_weight = 0;
    $total_volume = 0;
    
    foreach ($cart_items as $cart_item) {
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        
        if ($product->get_weight()) {
            $total_weight += $product->get_weight() * $quantity;
        }
        
        if ($product->get_length() && $product->get_width() && $product->get_height()) {
            $volume = $product->get_length() * $product->get_width() * $product->get_height();
            $total_volume += $volume * $quantity;
        }
    }
    
    echo '<p><strong>Общий вес:</strong> ' . $total_weight . ' г</p>';
    echo '<p><strong>Общий объем:</strong> ' . number_format($total_volume / 1000, 2) . ' л</p>';
    echo '</div>';
}
```

## Настройка расчета стоимости

```php
// Кастомная логика расчета fallback стоимости
add_filter('cdek_delivery_fallback_cost', 'custom_fallback_cost_calculation', 10, 4);

function custom_fallback_cost_calculation($cost, $weight, $value, $dimensions) {
    // Увеличиваем стоимость для дорогих товаров
    if ($value > 10000) {
        $cost += 200; // +200 руб за ценные товары
    }
    
    // Скидка для легких товаров
    if ($weight < 200) {
        $cost -= 50; // -50 руб для товаров менее 200г
    }
    
    // Доплата за крупногабаритные товары
    if ($dimensions && $dimensions['length'] > 50) {
        $cost += 150; // +150 руб за длину более 50см
    }
    
    return max($cost, 150); // Минимум 150 руб
}

// Изменение базовой стоимости доставки
add_filter('woocommerce_shipping_cdek_delivery_cost', 'modify_cdek_base_cost', 10, 2);

function modify_cdek_base_cost($cost, $package) {
    // Бесплатная доставка при заказе свыше 5000 руб
    $cart_total = WC()->cart->get_subtotal();
    
    if ($cart_total >= 5000) {
        return 0;
    }
    
    return $cost;
}
```

## Интеграция с другими плагинами

```php
// Интеграция с плагином скидок
add_action('cdek_point_selected', 'apply_delivery_discount', 10, 1);

function apply_delivery_discount($point_data) {
    // Скидка 10% на доставку в определенные города
    $discount_cities = array('Москва', 'Санкт-Петербург');
    
    if ($point_data && isset($point_data['location']['city'])) {
        $city = $point_data['location']['city'];
        
        if (in_array($city, $discount_cities)) {
            // Добавляем купон на скидку доставки
            if (!WC()->cart->has_discount('delivery_discount_10')) {
                WC()->cart->add_discount('delivery_discount_10');
            }
        }
    }
}

// Логирование выбора пунктов выдачи для аналитики
add_action('cdek_point_selected', 'log_point_selection', 10, 1);

function log_point_selection($point_data) {
    if ($point_data) {
        error_log('СДЭК: Выбран пункт ' . $point_data['code'] . ' в городе ' . $point_data['location']['city']);
        
        // Отправка данных в Google Analytics (если настроено)
        if (function_exists('gtag')) {
            echo "<script>
                gtag('event', 'cdek_point_selected', {
                    'point_code': '{$point_data['code']}',
                    'city': '{$point_data['location']['city']}'
                });
            </script>";
        }
    }
}
```

## Кастомизация интерфейса

```css
/* Кастомные стили для карты СДЭК */

/* Изменение цвета акцентов */
#cdek-points-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

#cdek-points-info strong {
    color: #fff;
}

/* Стилизация выбранного пункта */
#cdek-selected-point {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
}

/* Анимация появления карты */
#cdek-map-container {
    animation: slideInUp 0.5s ease-out;
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Кастомные иконки для пунктов на карте */
.ymaps-2-1-79-placemark-overlay {
    filter: hue-rotate(120deg); /* Зеленые маркеры вместо красных */
}
```

## Расширение функциональности

```php
// Добавление SMS уведомлений при выборе пункта
add_action('woocommerce_checkout_update_order_meta', 'send_sms_notification', 20, 1);

function send_sms_notification($order_id) {
    $point_code = get_post_meta($order_id, '_cdek_point_code', true);
    
    if ($point_code) {
        $order = wc_get_order($order_id);
        $phone = $order->get_billing_phone();
        
        if ($phone) {
            // Интеграция с SMS сервисом
            $message = "Ваш заказ №{$order_id} будет доставлен в пункт выдачи СДЭК {$point_code}";
            // send_sms($phone, $message);
        }
    }
}

// Создание отчета по популярным пунктам выдачи
function generate_cdek_points_report() {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT meta_value as point_code, COUNT(*) as orders_count
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_cdek_point_code'
        GROUP BY meta_value
        ORDER BY orders_count DESC
        LIMIT 10
    ");
    
    echo '<h3>Топ-10 популярных пунктов выдачи СДЭК</h3>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<tr><th>Код пункта</th><th>Количество заказов</th></tr>';
    
    foreach ($results as $row) {
        echo "<tr><td>{$row->point_code}</td><td>{$row->orders_count}</td></tr>";
    }
    
    echo '</table>';
}

// Добавление отчета в админку
add_action('admin_menu', 'add_cdek_reports_page');

function add_cdek_reports_page() {
    add_submenu_page(
        'woocommerce',
        'Отчеты СДЭК',
        'Отчеты СДЭК',
        'manage_woocommerce',
        'cdek-reports',
        'generate_cdek_points_report'
    );
}
```

## Миграция с других плагинов доставки

```php
// Скрипт для миграции заказов с другого плагина СДЭК
function migrate_from_old_cdek_plugin() {
    $orders = wc_get_orders(array(
        'limit' => -1,
        'meta_key' => '_old_cdek_point',
        'meta_compare' => 'EXISTS'
    ));
    
    foreach ($orders as $order) {
        $old_point = get_post_meta($order->get_id(), '_old_cdek_point', true);
        
        if ($old_point) {
            // Конвертируем в новый формат
            update_post_meta($order->get_id(), '_cdek_point_code', $old_point);
            
            // Удаляем старые мета-поля
            delete_post_meta($order->get_id(), '_old_cdek_point');
        }
    }
    
    echo 'Миграция завершена!';
}

// Запуск миграции через WP-CLI
// wp eval 'migrate_from_old_cdek_plugin();'
```