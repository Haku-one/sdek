# Устранение проблем СДЭК плагина

## Ошибка "Class CDEK_API not found"

### Причины:
1. Проблемы с автозагрузчиком классов
2. Неправильная структура файлов
3. Ошибки прав доступа к файлам

### Решения:

#### 1. Проверьте структуру файлов
Убедитесь, что структура плагина выглядит так:
```
sdek-woocommerce/
├── cdek-shipping-plugin.php
├── includes/
│   ├── class-cdek-api.php
│   ├── class-cdek-shipping-method.php
│   ├── class-cdek-activator.php
│   ├── class-cdek-checkout-block.php
│   └── admin-page.php
└── assets/
    ├── js/
    └── css/
```

#### 2. Проверьте права доступа
```bash
chmod 644 includes/class-cdek-api.php
chmod 644 includes/class-cdek-shipping-method.php
```

#### 3. Включите отладку WordPress
В `wp-config.php` добавьте:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

#### 4. Проверьте логи
Логи будут в `/wp-content/debug.log`. Ищите сообщения:
- "CDEK Plugin: Loaded class..."
- "CDEK Plugin: Could not load class..."

#### 5. Ручная проверка загрузки класса
Добавьте в начало `calculate_shipping()` метода:
```php
if (!class_exists('CDEK_API')) {
    require_once plugin_dir_path(__DIR__) . 'includes/class-cdek-api.php';
}
```

## Ошибки REST API (500 Internal Server Error)

### Причины:
1. Ошибки в PHP коде
2. Конфликты с другими плагинами
3. Проблемы с API СДЭК

### Решения:

#### 1. Проверьте PHP логи
В cPanel или через SSH проверьте error_log

#### 2. Тестирование API отдельно
```bash
curl -X GET "https://yoursite.com/wp-json/cdek/v1/pickup-points?city=Москва"
```

#### 3. Деактивируйте другие плагины
Временно отключите все плагины кроме WooCommerce и СДЭК

## Проблемы с Яндекс.Картами

### Симптомы:
- Карта не загружается
- Ошибки в консоли браузера

### Решения:

#### 1. Проверьте API ключ
В админке WooCommerce → СДЭК доставка → Яндекс.Карты API ключ

#### 2. Проверьте домен в настройках Яндекс
Убедитесь, что ваш домен добавлен в настройки API ключа

#### 3. Проверьте HTTPS
Яндекс.Карты требуют HTTPS для корректной работы

## Блочный checkout не работает

### Решения:

#### 1. Очистите кэш
- Кэш плагинов
- Кэш браузера
- CDN кэш

#### 2. Проверьте тему
Убедитесь, что тема поддерживает WooCommerce Blocks

#### 3. Обновите WooCommerce
Используйте последнюю версию WooCommerce

## Не сохраняется выбранный пункт выдачи

### Причины:
1. JavaScript ошибки
2. Конфликты с темой
3. Проблемы с AJAX

### Решения:

#### 1. Проверьте консоль браузера
F12 → Console → ищите ошибки JavaScript

#### 2. Проверьте скрытое поле
В инспекторе элементов найдите:
```html
<input type="hidden" id="cdek-selected-pickup-point" name="cdek_selected_pickup_point" value="">
```

#### 3. Тестирование AJAX
В консоли браузера:
```javascript
console.log(cdek_checkout_params);
```

## Диагностические команды

### 1. Проверка активных плагинов
```php
print_r(get_option('active_plugins'));
```

### 2. Проверка загруженных классов
```php
if (class_exists('CDEK_API')) {
    echo "CDEK_API загружен";
} else {
    echo "CDEK_API НЕ загружен";
}
```

### 3. Проверка автозагрузчика
```php
$autoloaders = spl_autoload_functions();
var_dump($autoloaders);
```

## Контакты для поддержки

1. Проверьте логи WordPress
2. Включите WP_DEBUG
3. Проверьте права доступа к файлам
4. Убедитесь в корректности API ключей

## Частые ошибки и их исправление

### "Fatal error: Cannot redeclare function"
- Проблема: Функция уже объявлена
- Решение: Проверить на дублирование кода, использовать `function_exists()`

### "Call to undefined method"
- Проблема: Метод не существует в классе
- Решение: Проверить версию WooCommerce, обновить плагин

### "Permission denied"
- Проблема: Нет прав на чтение файла
- Решение: `chmod 644` для файлов, `chmod 755` для папок