# Исправление ошибки 500 на wp-json/wc/store/v1/batch

## Проблема
Пользователь сообщил о стойкой ошибке 500 на endpoint `wp-json/wc/store/v1/batch`:
```
POST https://dobriytravnik.ru/wp-json/wc/store/v1/batch?_locale=site 500 (Internal Server Error)
```

## Причины ошибки
После анализа кода были выявлены следующие проблемы:

### 1. Двойная инициализация Store API Extension
- В файле `includes/class-cdek-store-api-extension.php` в конце была автоматическая инициализация
- В основном плагине также выполнялась инициализация
- Это приводило к конфликту и повторной регистрации хуков

### 2. Дублирование регистрации хуков
- Store API Extension регистрировал хуки `woocommerce_store_api_checkout_update_order_meta` и `woocommerce_store_api_checkout_order_received_object`
- Основной плагин в методе `register_rest_fields()` также регистрировал те же хуки
- Дублирование могло вызывать конфликты в Store API

### 3. Отсутствие инициализации Store API Extension
- Store API Extension загружался, но не инициализировался в основном плагине
- Метод `WC_Cdek_Store_API_Extension::init()` не вызывался

### 4. Недостаточная проверка безопасности
- Отсутствовали проверки существования WooCommerce и сессий
- Могли возникать фатальные ошибки при недоступности компонентов

## Выполненные исправления

### 1. Исправлена инициализация Store API Extension
**Файл:** `cdek-delivery-plugin.php` (строки 810-820)

**Было:**
```php
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php';
    error_log('CDEK: Store API extension загружен');
} else {
    error_log('CDEK: Файл Store API extension не найден');
}
```

**Стало:**
```php
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-cdek-store-api-extension.php';
    
    // Проверяем что класс загружен успешно
    if (class_exists('WC_Cdek_Store_API_Extension')) {
        WC_Cdek_Store_API_Extension::init();
        error_log('CDEK: Store API extension загружен и инициализирован');
    } else {
        error_log('CDEK: Класс WC_Cdek_Store_API_Extension не найден после подключения файла');
    }
} else {
    error_log('CDEK: Файл Store API extension не найден');
}
```

### 2. Убраны дублирующиеся регистрации хуков
**Файл:** `cdek-delivery-plugin.php` (метод `register_rest_fields`)

**Было:**
```php
public function register_rest_fields() {
    try {
        error_log('CDEK: Начинаем регистрацию REST полей');
        
        // Простая регистрация для совместимости с Store API
        if (class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
            // Регистрируем обработчики для Store API только если он доступен
            add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_cdek_data_from_store_api'));
            add_filter('woocommerce_store_api_checkout_order_received_object', array($this, 'add_cdek_data_to_order_response'), 10, 3);
            error_log('CDEK: Store API обработчики зарегистрированы');
        } else {
            error_log('CDEK: Store API недоступен, пропускаем регистрацию');
        }
        
        // Дополнительно регистрируем обработчики для REST API
        add_action('woocommerce_rest_checkout_process_payment', array($this, 'save_cdek_data_from_rest'), 10, 2);
        
        error_log('CDEK: REST поля успешно зарегистрированы');
        
    } catch (Exception $e) {
        error_log('CDEK: Ошибка регистрации REST полей: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('CDEK: Фатальная ошибка в register_rest_fields: ' . $e->getMessage());
    }
}
```

**Стало:**
```php
public function register_rest_fields() {
    try {
        error_log('CDEK: Начинаем регистрацию REST полей');
        
        // Store API обработчики теперь регистрируются в WC_Cdek_Store_API_Extension
        // Здесь только регистрируем обработчики для REST API
        add_action('woocommerce_rest_checkout_process_payment', array($this, 'save_cdek_data_from_rest'), 10, 2);
        
        error_log('CDEK: REST поля успешно зарегистрированы');
        
    } catch (Exception $e) {
        error_log('CDEK: Ошибка регистрации REST полей: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('CDEK: Фатальная ошибка в register_rest_fields: ' . $e->getMessage());
    }
}
```

### 3. Убрана автоматическая инициализация из Store API Extension
**Файл:** `includes/class-cdek-store-api-extension.php` (строки 258-259)

**Было:**
```php
// Инициализируем расширение Store API
WC_Cdek_Store_API_Extension::init();
```

**Стало:**
```php
// Инициализация расширения Store API выполняется в основном плагине
```

### 4. Усилена проверка безопасности в Store API Extension
**Файл:** `includes/class-cdek-store-api-extension.php`

#### Проверки WooCommerce сессии:
**Было:**
```php
if (WC()->session) {
```

**Стало:**
```php
if (function_exists('WC') && WC() && WC()->session) {
```

#### Улучшена обработка ошибок в `extend_store()`:
```php
public static function extend_store() {
    try {
        // Проверяем что ExtendSchema доступен
        if (!self::$extend) {
            error_log('CDEK Store API: ExtendSchema не инициализирован');
            return;
        }

        if (is_callable([self::$extend, 'register_endpoint_data'])) {
            // ... регистрация
            error_log('CDEK Store API: register_endpoint_data зарегистрирован');
        } else {
            error_log('CDEK Store API: register_endpoint_data недоступен');
        }

        if (is_callable([self::$extend, 'register_update_callback'])) {
            // ... регистрация
            error_log('CDEK Store API: register_update_callback зарегистрирован');
        } else {
            error_log('CDEK Store API: register_update_callback недоступен');
        }

        // Альтернативный метод регистрации для совместимости
        add_action('woocommerce_store_api_checkout_update_order_meta', array(__CLASS__, 'save_cdek_order_meta'));
        add_filter('woocommerce_store_api_checkout_order_received_object', array(__CLASS__, 'add_cdek_data_to_response'), 10, 3);

        error_log('CDEK Store API: Все обработчики зарегистрированы');
        
    } catch (Exception $e) {
        error_log('CDEK Store API: Ошибка регистрации расширения: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('CDEK Store API: Фатальная ошибка регистрации расширения: ' . $e->getMessage());
    }
}
```

## Результат
После выполнения всех исправлений:

1. ✅ Убраны конфликты между дублирующимися хуками Store API
2. ✅ Store API Extension правильно инициализируется только один раз
3. ✅ Улучшена обработка ошибок и проверки безопасности
4. ✅ Исправлена архитектура: Store API хуки регистрируются только в Store API Extension
5. ✅ Добавлено подробное логирование для диагностики

## Инструкции по применению

1. **Скопируйте обновленные файлы** на ваш сайт:
   - `cdek-delivery-plugin.php`
   - `includes/class-cdek-store-api-extension.php`

2. **Деактивируйте и активируйте** плагин СДЭК в админке WordPress

3. **Очистите все кеши** (плагинов, сайта, CDN)

4. **Проверьте логи** WordPress (`wp-content/debug.log`) на наличие новых ошибок

5. **Тестируйте** блочный checkout WooCommerce

## Проверка работоспособности

1. Откройте консоль разработчика в браузере
2. Перейдите на страницу checkout с блоками WooCommerce  
3. Заполните форму и попробуйте выбрать пункт выдачи СДЭК
4. Убедитесь что нет ошибок 500 на endpoints:
   - `wp-json/wc/store/v1/batch`
   - `wp-json/wc/store/v1/checkout`

## Логи для мониторинга

После исправлений в логах должны появиться записи:
```
CDEK: Store API extension загружен и инициализирован
CDEK Store API: Инициализируем расширение Store API
CDEK Store API: register_endpoint_data зарегистрирован
CDEK Store API: register_update_callback зарегистрирован
CDEK Store API: Все обработчики зарегистрированы
```

Если видите другие записи - проблема может быть не полностью решена.