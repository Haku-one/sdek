# Руководство по отладке СДЭК

## ✅ Что было исправлено:

### 1. JavaScript ошибки:
- ✅ `ReferenceError: getCartDataForCdek is not defined` → заменено на `getCartDataForCalculation`
- ✅ Исправлены поля `cartData.totalWeight` → `cartData.weight` и `cartData.totalPrice` → `cartData.value`
- ✅ Исправлена переменная `selectedCdekPoint` → `selectedPoint`

### 2. PHP улучшения:
- ✅ Добавлена функция отображения данных СДЭК на странице заказа
- ✅ Добавлена функция обновления стоимости доставки в заказе
- ✅ Добавлено отладочное логирование

### 3. Добавленные функции:
- ✅ `display_cdek_info_on_order_page()` - отображение на странице благодарности
- ✅ `update_order_shipping_cost()` - обновление стоимости доставки в заказе
- ✅ Отладочные console.log в JavaScript
- ✅ Отладочные error_log в PHP

## 🔍 Как проверить работу:

### 1. Проверка JavaScript:
1. Откройте консоль браузера (F12)
2. Оформите заказ с доставкой СДЭК
3. Выберите пункт выдачи
4. Проверьте логи в консоли:
   ```
   CDEK Point selected: {объект с данными пункта}
   Created cdek_selected_point_code field with value: код_пункта
   Created cdek-delivery-cost field with value: стоимость
   ```

### 2. Проверка PHP логов:
1. Включите отладку WordPress: `define('WP_DEBUG_LOG', true);` в wp-config.php
2. Оформите заказ
3. Проверьте файл `/wp-content/debug.log`:
   ```
   CDEK DEBUG: Saving order data for order ID: 123
   CDEK DEBUG: Saved point code: код_пункта
   CDEK DEBUG: Saved delivery cost: стоимость
   ```

### 3. Проверка в админке:
1. Зайдите в админку WordPress
2. Откройте раздел WooCommerce → Заказы
3. Откройте любой заказ с доставкой СДЭК
4. Должен отображаться блок "📦 Информация о доставке СДЭК"

### 4. Проверка на странице заказа:
1. После оформления заказа
2. На странице благодарности должен появиться блок "📦 Информация о доставке СДЭК"
3. Стоимость доставки должна быть добавлена к общей сумме

## 🐛 Если проблемы остаются:

### Проблема: Данные не сохраняются
**Решение:**
1. Проверьте логи JavaScript - отправляются ли данные
2. Проверьте логи PHP - получаются ли данные
3. Убедитесь что форма содержит правильные скрытые поля

### Проблема: Стоимость не обновляется
**Решение:**
1. Проверьте, что функция `update_order_shipping_cost` вызывается
2. Убедитесь что метод доставки в заказе имеет ID содержащий "cdek_delivery"
3. Проверьте права на изменение заказов

### Проблема: Не отображается на странице заказа
**Решение:**
1. Убедитесь что хук `woocommerce_order_details_after_order_table` поддерживается темой
2. Попробуйте другой хук: `woocommerce_thankyou` или `woocommerce_view_order`

## 📧 Проверка в email:
Информация о СДЭК должна автоматически добавляться в письма:
- Новый заказ (администратору)
- Заказ в обработке (клиенту)
- Заказ выполнен (клиенту)

## 🔧 Дополнительная отладка:
Для более детальной отладки добавьте в functions.php:
```php
add_action('wp_footer', function() {
    if (is_checkout() || is_order_received_page()) {
        echo '<script>console.log("CDEK DEBUG: All form data:", new FormData(document.querySelector("form.checkout, form.woocommerce-checkout")));</script>';
    }
});
```