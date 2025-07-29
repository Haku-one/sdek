# CDEK Plugin Fixes for WooCommerce Store API 500 Errors

## Problem
The user reported persistent 500 errors on the WooCommerce Store API endpoint:
- `POST https://dobriytravnik.ru/wp-json/wc/store/v1/batch?_locale=site 500 (Internal Server Error)`

## Root Causes Identified and Fixed

### 1. Missing Method: `init_store_api_support`
**Issue**: The constructor called `add_action('init', array($this, 'init_store_api_support'))` but the method didn't exist.
**Fix**: Added the missing method with proper error handling:
```php
public function init_store_api_support() {
    try {
        error_log('CDEK: Инициализация поддержки Store API');
        
        // Проверяем что WooCommerce загружен
        if (!class_exists('WooCommerce')) {
            error_log('CDEK: WooCommerce не найден при инициализации Store API');
            return;
        }
        
        // Проверяем доступность Store API
        if (class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
            error_log('CDEK: Store API доступен');
        } else {
            error_log('CDEK: Store API недоступен');
        }
        
    } catch (Exception $e) {
        error_log('CDEK: Ошибка инициализации Store API: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('CDEK: Фатальная ошибка при инициализации Store API: ' . $e->getMessage());
    }
}
```

### 2. Incorrect Hydration Filter Usage
**Issue**: The plugin was trying to use `woocommerce_hydration_request_after_callbacks` filter incorrectly, which could cause conflicts with WooCommerce 8.9+ batch requests.
**Fix**: Removed the problematic hydration filter:
```php
// REMOVED: Problematic hydration filter
// add_filter('woocommerce_hydration_request_after_callbacks', array($this, 'add_cdek_data_to_order_response'), 10, 3);
```

### 3. Improved Hook Timing
**Issue**: REST API hooks were being registered on `rest_api_init`, which is too early for Store API.
**Fix**: Changed to use `woocommerce_loaded` hook:
```php
// OLD: add_action('rest_api_init', array($this, 'register_rest_fields'));
// NEW: 
add_action('woocommerce_loaded', array($this, 'register_rest_fields'));
```

### 4. Enhanced Error Handling in Blocks Integration
**Issue**: Blocks integration could fail without proper error handling.
**Fix**: Added comprehensive try-catch blocks and class existence checks:
```php
public function register_blocks_integration() {
    try {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry') && 
            class_exists('WC_Cdek_Blocks_Integration')) {
            $container = \Automattic\WooCommerce\Blocks\Package::container();
            $container->get(\Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry::class)
                ->register(new WC_Cdek_Blocks_Integration());
            error_log('CDEK: Blocks integration зарегистрирована');
        } else {
            error_log('CDEK: Не удалось зарегистрировать blocks integration - отсутствуют классы');
        }
    } catch (Exception $e) {
        error_log('CDEK: Ошибка регистрации blocks integration: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('CDEK: Фатальная ошибка при регистрации blocks integration: ' . $e->getMessage());
    }
}
```

## Expected Result
These fixes should resolve the 500 errors on the `wp-json/wc/store/v1/batch` endpoint by:
1. Eliminating fatal errors from missing methods
2. Removing incompatible filter usage that could conflict with WooCommerce's hydration system
3. Ensuring proper timing of hook registration
4. Adding robust error handling to prevent crashes

## Files Modified
- `cdek-delivery-plugin.php` - Main plugin file with all the fixes
- No new files created, existing `includes/class-wc-blocks-integration.php` remains unchanged

## Next Steps
1. Test the checkout process with WooCommerce Blocks
2. Monitor WordPress error logs for any remaining issues
3. Verify that CDEK delivery functionality works correctly with the Store API