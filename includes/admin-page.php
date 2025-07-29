<?php

if (!defined('ABSPATH')) {
    exit;
}

// Обработка сохранения настроек
if (isset($_POST['submit'])) {
    if (wp_verify_nonce($_POST['cdek_settings_nonce'], 'cdek_settings')) {
        update_option('cdek_account', sanitize_text_field($_POST['cdek_account']));
        update_option('cdek_password', sanitize_text_field($_POST['cdek_password']));
        update_option('cdek_test_mode', isset($_POST['cdek_test_mode']) ? 1 : 0);
        update_option('cdek_sender_city', sanitize_text_field($_POST['cdek_sender_city']));
        update_option('cdek_yandex_api_key', sanitize_text_field($_POST['cdek_yandex_api_key']));
        
        echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
    }
}

// Получение текущих настроек
$cdek_account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
$cdek_password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
$cdek_test_mode = get_option('cdek_test_mode', 0);
$cdek_sender_city = get_option('cdek_sender_city', '354');
$cdek_yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');

?>

<div class="wrap">
    <h1>Настройки СДЭК Доставка</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cdek_settings', 'cdek_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">Account (Client ID)</th>
                <td>
                    <input type="text" name="cdek_account" value="<?php echo esc_attr($cdek_account); ?>" class="regular-text" />
                    <p class="description">Идентификатор клиента для API СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Secure Password (Client Secret)</th>
                <td>
                    <input type="password" name="cdek_password" value="<?php echo esc_attr($cdek_password); ?>" class="regular-text" />
                    <p class="description">Секретный ключ для API СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Тестовый режим</th>
                <td>
                    <label>
                        <input type="checkbox" name="cdek_test_mode" value="1" <?php checked($cdek_test_mode, 1); ?> />
                        Использовать тестовую среду СДЭК
                    </label>
                    <p class="description">В тестовом режиме будет использоваться api.edu.cdek.ru</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Город отправителя</th>
                <td>
                    <input type="text" name="cdek_sender_city" value="<?php echo esc_attr($cdek_sender_city); ?>" class="regular-text" />
                    <p class="description">Код города отправителя (по умолчанию 51 - Саратов)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">API ключ Яндекс.Карт</th>
                <td>
                    <input type="text" name="cdek_yandex_api_key" value="<?php echo esc_attr($cdek_yandex_api_key); ?>" class="regular-text" />
                    <p class="description">API ключ для работы с Яндекс.Картами</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Сохранить настройки'); ?>
    </form>
    
    <hr>
    
    <h2>Проверка подключения и расчета стоимости</h2>
    <p>Нажмите кнопки ниже для тестирования API СДЭК:</p>
    <p>
        <button type="button" id="test-cdek-connection" class="button button-secondary">Проверить подключение</button>
        <button type="button" id="test-cdek-calculation" class="button button-primary" style="margin-left: 10px;">Тестировать расчет стоимости</button>
        <button type="button" id="test-cdek-api-detailed" class="button button-secondary" style="margin-left: 10px;">Детальное тестирование API</button>
        <button type="button" id="test-saratov-kursk" class="button button-secondary" style="margin-left: 10px;">🎯 Тест Саратов-Курск</button>
    </p>
    <div id="connection-result" style="margin-top: 10px;"></div>
    <div id="calculation-result" style="margin-top: 10px;"></div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-cdek-connection').on('click', function() {
            var button = $(this);
            var result = $('#connection-result');
            
            button.prop('disabled', true).text('Проверка...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_connection',
                nonce: '<?php echo wp_create_nonce('test_cdek_connection'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text('Проверить подключение');
            });
        });
        
        $('#test-cdek-calculation').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('Тестирование...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_calculation',
                nonce: '<?php echo wp_create_nonce('test_cdek_calculation'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
                
                button.prop('disabled', false).text('Тестировать расчет стоимости');
            });
        });
        
        $('#test-cdek-api-detailed').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('Детальное тестирование...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_cdek_api_detailed',
                nonce: '<?php echo wp_create_nonce('test_cdek_api_detailed'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                }
                
                button.prop('disabled', false).text('Детальное тестирование API');
            });
        });
        
        $('#test-saratov-kursk').on('click', function() {
            var button = $(this);
            var result = $('#calculation-result');
            
            button.prop('disabled', true).text('🎯 Тестируем Саратов-Курск...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_saratov_kursk',
                nonce: '<?php echo wp_create_nonce('test_saratov_kursk'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>🎉 ' + response.data + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                }
                
                button.prop('disabled', false).text('🎯 Тест Саратов-Курск');
            });
        });
    });
    </script>
    
    <hr>
    
    <h2>Инструкции по настройке</h2>
    <div class="card">
        <h3>Получение API ключей СДЭК</h3>
        <ol>
            <li>Зарегистрируйтесь в <a href="https://lk.cdek.ru/" target="_blank">личном кабинете СДЭК</a></li>
            <li>Перейдите в раздел "Интеграция" → "API"</li>
            <li>Создайте новое приложение и получите Account и Secure password</li>
            <li>Введите полученные данные в форму выше</li>
        </ol>
        
        <h3>Настройка зон доставки</h3>
        <ol>
            <li>Перейдите в <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>">WooCommerce → Настройки → Доставка</a></li>
            <li>Выберите зону доставки или создайте новую</li>
            <li>Добавьте метод доставки "СДЭК — Пункт выдачи"</li>
            <li>Настройте параметры метода доставки</li>
        </ol>
        
        <h3>Коды городов СДЭК</h3>
        <p>Популярные коды городов:</p>
        <ul>
            <li>Москва: 44</li>
            <li>Санкт-Петербург: 137</li>
            <li>Новосибирск: 114</li>
            <li>Екатеринбург: 49</li>
            <li>Саратов: 51 (по умолчанию)</li>
        </ul>
    </div>
</div>