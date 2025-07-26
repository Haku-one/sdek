<?php
/**
 * Admin page for CDEK Shipping settings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['submit']) && wp_verify_nonce($_POST['cdek_settings_nonce'], 'cdek_settings')) {
    update_option('cdek_account_id', sanitize_text_field($_POST['cdek_account_id']));
    update_option('cdek_secure_password', sanitize_text_field($_POST['cdek_secure_password']));
    update_option('cdek_yandex_api_key', sanitize_text_field($_POST['cdek_yandex_api_key']));
    update_option('cdek_default_sender_city', sanitize_text_field($_POST['cdek_default_sender_city']));
    update_option('cdek_test_mode', isset($_POST['cdek_test_mode']) ? '1' : '0');
    
    echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
}

// Get current settings
$account_id = get_option('cdek_account_id', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
$secure_password = get_option('cdek_secure_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
$yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
$default_sender_city = get_option('cdek_default_sender_city', '44');
$test_mode = get_option('cdek_test_mode', '0');
?>

<div class="wrap">
    <h1>Настройки СДЭК доставки</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cdek_settings', 'cdek_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cdek_account_id">Account ID (Идентификатор)</label>
                </th>
                <td>
                    <input type="text" id="cdek_account_id" name="cdek_account_id" value="<?php echo esc_attr($account_id); ?>" class="regular-text" />
                    <p class="description">Идентификатор аккаунта СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_secure_password">Secure Password (Пароль)</label>
                </th>
                <td>
                    <input type="password" id="cdek_secure_password" name="cdek_secure_password" value="<?php echo esc_attr($secure_password); ?>" class="regular-text" />
                    <p class="description">Секретный пароль для API СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_yandex_api_key">Яндекс.Карты API ключ</label>
                </th>
                <td>
                    <input type="text" id="cdek_yandex_api_key" name="cdek_yandex_api_key" value="<?php echo esc_attr($yandex_api_key); ?>" class="regular-text" />
                    <p class="description">API ключ для Яндекс.Карт</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_default_sender_city">Код города отправления</label>
                </th>
                <td>
                    <input type="text" id="cdek_default_sender_city" name="cdek_default_sender_city" value="<?php echo esc_attr($default_sender_city); ?>" class="regular-text" />
                    <p class="description">Код города отправления в системе СДЭК (по умолчанию 44 - Москва)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_test_mode">Тестовый режим</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="cdek_test_mode" name="cdek_test_mode" value="1" <?php checked($test_mode, '1'); ?> />
                        Включить тестовый режим
                    </label>
                    <p class="description">В тестовом режиме используется тестовое API СДЭК</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Сохранить настройки'); ?>
    </form>
    
    <hr>
    
    <h2>Тестирование подключения</h2>
    <div id="cdek-test-results"></div>
    <button type="button" id="test-cdek-connection" class="button">Проверить подключение к СДЭК</button>
    
    <hr>
    
    <h2>Инструкции по настройке</h2>
    <div class="cdek-instructions">
        <h3>1. Настройка учетных данных СДЭК</h3>
        <p>Получите Account ID и Secure Password в личном кабинете СДЭК в разделе "Интеграция" → "API".</p>
        
        <h3>2. Настройка Яндекс.Карт</h3>
        <p>Получите API ключ для Яндекс.Карт в <a href="https://developer.tech.yandex.ru/" target="_blank">консоли разработчика Яндекс</a>.</p>
        
        <h3>3. Настройка способов доставки</h3>
        <p>Перейдите в WooCommerce → Настройки → Доставка → Зоны доставки и добавьте способ "СДЭК доставка" в нужные зоны.</p>
        
        <h3>4. Скрытие полей</h3>
        <p>Плагин автоматически скрывает поля "Населенный пункт", "Область" и "Почтовый индекс" при выборе доставки СДЭК.</p>
        <p>Город определяется автоматически из поля "Адрес" - убедитесь, что клиенты указывают город в начале адреса.</p>
        
        <h3>5. Настройка веса и размеров товаров</h3>
        <p>Для корректного расчета стоимости доставки обязательно указывайте вес товаров в настройках каждого товара.</p>
        <p>Размеры товаров также влияют на стоимость, поэтому рекомендуется их указывать.</p>
    </div>
</div>

<style>
.cdek-instructions {
    background: #f1f1f1;
    padding: 20px;
    border-radius: 5px;
    margin-top: 20px;
}

.cdek-instructions h3 {
    color: #23282d;
    margin-top: 15px;
    margin-bottom: 8px;
}

.cdek-instructions p {
    margin-bottom: 10px;
    line-height: 1.5;
}

#cdek-test-results {
    margin: 10px 0;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    display: none;
}

#cdek-test-results.success {
    border-color: #46b450;
    background: #f0fff0;
    color: #155724;
}

#cdek-test-results.error {
    border-color: #dc3232;
    background: #fff0f0;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#test-cdek-connection').on('click', function() {
        const button = $(this);
        const results = $('#cdek-test-results');
        
        button.prop('disabled', true).text('Проверка...');
        results.removeClass('success error').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_cdek_connection',
                account_id: $('#cdek_account_id').val(),
                secure_password: $('#cdek_secure_password').val(),
                _wpnonce: '<?php echo wp_create_nonce('test_cdek_connection'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    results.addClass('success').html('<strong>Успешно!</strong> ' + response.data.message).show();
                } else {
                    results.addClass('error').html('<strong>Ошибка:</strong> ' + response.data.message).show();
                }
            },
            error: function() {
                results.addClass('error').html('<strong>Ошибка:</strong> Не удалось выполнить запрос').show();
            },
            complete: function() {
                button.prop('disabled', false).text('Проверить подключение к СДЭК');
            }
        });
    });
});
</script>