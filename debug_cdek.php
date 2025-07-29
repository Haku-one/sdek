<?php
/**
 * Простой скрипт для просмотра логов СДЭК
 * Откройте этот файл в браузере для диагностики
 */

// Включаем отображение ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<h1>🔍 Диагностика СДЭК API</h1>';
echo '<style>
body { font-family: monospace; background: #f5f5f5; }
.log { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #333; }
.error { border-left-color: #e74c3c; }
.success { border-left-color: #27ae60; }
.warning { border-left-color: #f39c12; }
.info { border-left-color: #3498db; }
pre { white-space: pre-wrap; word-wrap: break-word; }
</style>';

// Поиск файлов логов
$log_files = [
    '/var/log/php/error.log',
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    ini_get('error_log'),
    './wp-content/debug.log',
    '../wp-content/debug.log',
    '../../wp-content/debug.log',
    '/tmp/php_errors.log'
];

echo '<h2>📄 Поиск файлов логов...</h2>';

$found_logs = [];
foreach ($log_files as $log_file) {
    if (!empty($log_file) && file_exists($log_file) && is_readable($log_file)) {
        $found_logs[] = $log_file;
        echo "<p>✅ Найден: <strong>$log_file</strong></p>";
    }
}

if (empty($found_logs)) {
    echo '<p style="color: red;">❌ Файлы логов не найдены!</p>';
    echo '<p>Проверьте настройки PHP:</p>';
    echo '<pre>';
    echo 'log_errors = ' . ini_get('log_errors') . "\n";
    echo 'error_log = ' . ini_get('error_log') . "\n";
    echo 'display_errors = ' . ini_get('display_errors') . "\n";
    echo '</pre>';
    exit;
}

// Читаем логи
foreach ($found_logs as $log_file) {
    echo "<h2>📋 Логи из: $log_file</h2>";
    
    if (!is_readable($log_file)) {
        echo '<p style="color: red;">❌ Нет доступа для чтения</p>';
        continue;
    }
    
    // Читаем последние 100 строк
    $lines = [];
    $handle = fopen($log_file, 'r');
    if ($handle) {
        // Читаем файл с конца
        fseek($handle, -1, SEEK_END);
        $lines_count = 0;
        $pos = ftell($handle);
        $line = '';
        
        while ($pos >= 0 && $lines_count < 100) {
            fseek($handle, $pos, SEEK_SET);
            $char = fgetc($handle);
            
            if ($char === "\n" || $pos === 0) {
                if (!empty(trim($line))) {
                    $lines[] = $line;
                    $lines_count++;
                }
                $line = '';
            } else {
                $line = $char . $line;
            }
            $pos--;
        }
        fclose($handle);
    }
    
    // Фильтруем строки связанные с СДЭК
    $cdek_lines = array_filter($lines, function($line) {
        return stripos($line, 'СДЭК') !== false || 
               stripos($line, 'CDEK') !== false ||
               stripos($line, 'cdek') !== false ||
               stripos($line, 'calculate_cdek_delivery_cost') !== false;
    });
    
    if (!empty($cdek_lines)) {
        echo '<div class="log">';
        foreach (array_reverse($cdek_lines) as $line) {
            $line = htmlspecialchars($line);
            
            // Определяем тип сообщения
            $class = '';
            if (strpos($line, '❌') !== false || strpos($line, 'ERROR') !== false) {
                $class = 'error';
            } elseif (strpos($line, '✅') !== false || strpos($line, '🎉') !== false) {
                $class = 'success';
            } elseif (strpos($line, '⚠️') !== false || strpos($line, 'WARNING') !== false) {
                $class = 'warning';
            } elseif (strpos($line, '🔑') !== false || strpos($line, '🔍') !== false) {
                $class = 'info';
            }
            
            echo '<div class="' . $class . '"><pre>' . $line . '</pre></div>';
        }
        echo '</div>';
    } else {
        echo '<p>Логи СДЭК не найдены в последних 100 строках</p>';
    }
}

echo '<hr>';
echo '<h2>🧪 Тест подключения к СДЭК API</h2>';

// Простой тест API
try {
    // Проверяем, можем ли мы подключиться к API СДЭК
    $test_url = 'https://api.cdek.ru/v2/oauth/token';
    
    echo '<p>Тестируем подключение к: <code>' . $test_url . '</code></p>';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CDEK-Debug-Script/1.0');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&client_id=test&client_secret=test');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo '<p style="color: red;">❌ Ошибка cURL: ' . htmlspecialchars($error) . '</p>';
    } else {
        echo '<p style="color: green;">✅ Подключение к API успешно (HTTP: ' . $http_code . ')</p>';
        if ($http_code === 400) {
            echo '<p style="color: blue;">ℹ️ HTTP 400 ожидаем (неверные тестовые данные авторизации)</p>';
        }
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Исключение: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<p><strong>Время генерации:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><a href="javascript:location.reload()">🔄 Обновить</a></p>';
?>