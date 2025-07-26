<?php
if (!defined('ABSPATH')) {
    exit;
}

// Обработка сохранения настроек
if (isset($_POST['submit']) && wp_verify_nonce($_POST['cdek_settings_nonce'], 'cdek_settings')) {
    update_option('cdek_account', sanitize_text_field($_POST['cdek_account']));
    update_option('cdek_password', sanitize_text_field($_POST['cdek_password']));
    update_option('cdek_test_mode', isset($_POST['cdek_test_mode']) ? 1 : 0);
    update_option('cdek_yandex_api_key', sanitize_text_field($_POST['cdek_yandex_api_key']));
    update_option('cdek_sender_city', sanitize_text_field($_POST['cdek_sender_city']));
    
    echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
}

// Получение текущих настроек
$cdek_account = get_option('cdek_account', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
$cdek_password = get_option('cdek_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
$cdek_test_mode = get_option('cdek_test_mode', 0);
$cdek_yandex_api_key = get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702');
$cdek_sender_city = get_option('cdek_sender_city', '44');
?>

<div class="wrap">
    <h1>Настройки СДЭК Доставки</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cdek_settings', 'cdek_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cdek_account">Идентификатор аккаунта СДЭК</label>
                </th>
                <td>
                    <input type="text" id="cdek_account" name="cdek_account" value="<?php echo esc_attr($cdek_account); ?>" class="regular-text" />
                    <p class="description">Получите в личном кабинете СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_password">Пароль СДЭК</label>
                </th>
                <td>
                    <input type="password" id="cdek_password" name="cdek_password" value="<?php echo esc_attr($cdek_password); ?>" class="regular-text" />
                    <p class="description">Secure password из личного кабинета СДЭК</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_test_mode">Тестовый режим</label>
                </th>
                <td>
                    <input type="checkbox" id="cdek_test_mode" name="cdek_test_mode" value="1" <?php checked($cdek_test_mode, 1); ?> />
                    <label for="cdek_test_mode">Включить тестовый режим (используется тестовое API)</label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_yandex_api_key">API ключ Яндекс.Карт</label>
                </th>
                <td>
                    <input type="text" id="cdek_yandex_api_key" name="cdek_yandex_api_key" value="<?php echo esc_attr($cdek_yandex_api_key); ?>" class="regular-text" />
                    <p class="description">Получите на <a href="https://developer.tech.yandex.ru/" target="_blank">developer.tech.yandex.ru</a></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cdek_sender_city">Код города отправления</label>
                </th>
                <td>
                    <input type="text" id="cdek_sender_city" name="cdek_sender_city" value="<?php echo esc_attr($cdek_sender_city); ?>" class="regular-text" />
                    <p class="description">Код города СДЭК откуда отправляются заказы (44 - Москва, 137 - СПб)</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Сохранить настройки'); ?>
    </form>
    
    <div class="card" style="margin-top: 20px;">
        <h2>Инструкция по настройке</h2>
        <ol>
            <li><strong>Получите учетные данные СДЭК:</strong>
                <ul>
                    <li>Зарегистрируйтесь на <a href="https://www.cdek.ru/" target="_blank">сайте СДЭК</a></li>
                    <li>В личном кабинете получите Идентификатор и Пароль для API</li>
                </ul>
            </li>
            <li><strong>Настройте Яндекс.Карты:</strong>
                <ul>
                    <li>Получите API ключ на <a href="https://developer.tech.yandex.ru/" target="_blank">developer.tech.yandex.ru</a></li>
                    <li>Включите JavaScript API и Геокодер в настройках ключа</li>
                </ul>
            </li>
            <li><strong>Настройте зоны доставки в WooCommerce:</strong>
                <ul>
                    <li>Перейдите в WooCommerce → Настройки → Доставка</li>
                    <li>Создайте зону доставки и добавьте метод "СДЭК Доставка"</li>
                </ul>
            </li>
            <li><strong>Настройте веса товаров:</strong>
                <ul>
                    <li>Убедитесь, что у всех товаров указан вес в граммах</li>
                    <li>Это необходимо для корректного расчета стоимости доставки</li>
                </ul>
            </li>
        </ol>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h2>Проверка подключения</h2>
        <p>
            <button type="button" class="button button-secondary" onclick="testCdekConnection()">
                Проверить подключение к СДЭК API
            </button>
            <span id="cdek-test-result"></span>
        </p>
    </div>
</div>

<script>
function testCdekConnection() {
    var button = document.querySelector('button');
    var result = document.getElementById('cdek-test-result');
    
    button.disabled = true;
    button.textContent = 'Проверка...';
    result.textContent = '';
    
    // AJAX запрос для проверки подключения
    jQuery.post(ajaxurl, {
        action: 'test_cdek_connection',
        nonce: '<?php echo wp_create_nonce('test_cdek_connection'); ?>'
    }, function(response) {
        button.disabled = false;
        button.textContent = 'Проверить подключение к СДЭК API';
        
        if (response.success) {
            result.innerHTML = '<span style="color: green;">✓ Подключение успешно</span>';
        } else {
            result.innerHTML = '<span style="color: red;">✗ Ошибка: ' + response.data + '</span>';
        }
    });
}
</script>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
    color: #23282d;
}

.card ul {
    margin-left: 20px;
}

.card li {
    margin-bottom: 5px;
}
</style>