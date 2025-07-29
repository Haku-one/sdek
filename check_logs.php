<?php
/**
 * Скрипт для быстрой проверки логов СДЭК
 * Запустите этот файл в браузере для просмотра последних логов
 */

// Путь к файлу логов WordPress (может отличаться в зависимости от настроек)
$log_files = [
    '/var/log/nginx/error.log',
    '/var/log/apache2/error.log', 
    './wp-content/debug.log',
    '../wp-content/debug.log',
    '../../wp-content/debug.log',
    '/tmp/wordpress-debug.log'
];

echo '<h1>🔍 Проверка логов СДЭК</h1>';
echo '<style>body{font-family:monospace;} .log{background:#f0f0f0;padding:10px;margin:10px 0;border-left:3px solid #333;}</style>';

foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "<h2>📄 Найден лог: $log_file</h2>";
        
        // Читаем последние 50 строк
        $lines = array_slice(file($log_file), -50);
        
        // Фильтруем строки связанные с СДЭК
        $cdek_lines = array_filter($lines, function($line) {
            return stripos($line, 'СДЭК') !== false || 
                   stripos($line, 'CDEK') !== false ||
                   stripos($line, 'cdek') !== false;
        });
        
        if (!empty($cdek_lines)) {
            echo '<div class="log">';
            foreach ($cdek_lines as $line) {
                $line = htmlspecialchars($line);
                
                // Цветовая разметка логов
                if (strpos($line, '❌') !== false) {
                    $line = '<span style="color:red;">' . $line . '</span>';
                } elseif (strpos($line, '✅') !== false || strpos($line, '🎉') !== false) {
                    $line = '<span style="color:green;">' . $line . '</span>';
                } elseif (strpos($line, '⚠️') !== false) {
                    $line = '<span style="color:orange;">' . $line . '</span>';
                } elseif (strpos($line, '🔑') !== false) {
                    $line = '<span style="color:blue;">' . $line . '</span>';
                }
                
                echo $line . '<br>';
            }
            echo '</div>';
        } else {
            echo '<p>Логи СДЭК не найдены в последних 50 строках</p>';
        }
        break; // Используем первый найденный файл
    }
}

if (!file_exists($log_file)) {
    echo '<p style="color:red;">❌ Файлы логов не найдены. Проверьте настройки WP_DEBUG_LOG в wp-config.php</p>';
    echo '<p>Добавьте в wp-config.php:</p>';
    echo '<code>define("WP_DEBUG", true);<br>define("WP_DEBUG_LOG", true);</code>';
}

echo '<hr>';
echo '<h2>🧪 Инструкция по проверке:</h2>';
echo '<ol>';
echo '<li>Выберите пункт выдачи СДЭК на сайте</li>';
echo '<li>Обновите эту страницу</li>';
echo '<li>Ищите логи с эмодзи: 🔑 (авторизация), 🏙️ (город), 🚀 (запрос), 📥 (ответ)</li>';
echo '<li>Если видите ❌ - это ошибка, ✅ - успех</li>';
echo '</ol>';

echo '<p><strong>Последнее обновление:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><a href="javascript:location.reload()">🔄 Обновить логи</a></p>';
?>