<?php
/**
 * Просмотр логов формы оформления заказа
 * Разместите этот файл в корне WordPress для просмотра логов
 */

// Проверяем, что файл запущен в WordPress
if (!defined('ABSPATH')) {
    // Если файл запущен напрямую, подключаем WordPress
    $wp_load = dirname(__FILE__) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    } else {
        die('WordPress не найден');
    }
}

// Проверяем права администратора
if (!current_user_can('manage_options')) {
    wp_die('Недостаточно прав для просмотра логов');
}

$log_file = WP_CONTENT_DIR . '/checkout-form-debug.log';
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// Обработка действий
if ($action === 'clear' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_logs')) {
    if (file_exists($log_file)) {
        unlink($log_file);
        $message = 'Логи очищены';
    }
} elseif ($action === 'download' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'download_logs')) {
    if (file_exists($log_file)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="checkout-form-debug-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    }
}

// Получаем содержимое лога
$log_content = '';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $log_lines = array_reverse($log_lines); // Показываем последние записи первыми
    $log_content = implode("\n", $log_lines);
} else {
    $log_content = 'Файл логов не найден';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Логи формы оформления заказа</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .actions {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 10px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-danger {
            background: #dc3232;
        }
        .btn-danger:hover {
            background: #a00;
        }
        .log-content {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            font-size: 12px;
            line-height: 1.4;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        .log-entry.error {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
        }
        .log-entry.warning {
            color: #ffd93d;
            background: rgba(255, 217, 61, 0.1);
        }
        .log-entry.info {
            color: #6bcf7f;
        }
        .stats {
            margin-bottom: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 5px;
        }
        .stats h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .filter {
            margin-bottom: 15px;
        }
        .filter input {
            padding: 5px;
            width: 200px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .highlight {
            background: yellow;
            color: black;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Логи формы оформления заказа</h1>
        
        <div class="actions">
            <a href="?action=view" class="btn">Обновить</a>
            <a href="?action=download&_wpnonce=<?php echo wp_create_nonce('download_logs'); ?>" class="btn">Скачать логи</a>
            <a href="?action=clear&_wpnonce=<?php echo wp_create_nonce('clear_logs'); ?>" class="btn btn-danger" onclick="return confirm('Очистить все логи?')">Очистить логи</a>
        </div>
        
        <?php if (file_exists($log_file)): ?>
        <div class="stats">
            <h3>📊 Статистика</h3>
            <?php
            $lines = explode("\n", $log_content);
            $total_lines = count($lines);
            $error_count = 0;
            $warning_count = 0;
            $info_count = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, '[ERROR]') !== false) $error_count++;
                elseif (strpos($line, '[WARNING]') !== false) $warning_count++;
                elseif (strpos($line, '[INFO]') !== false) $info_count++;
            }
            ?>
            <p><strong>Всего записей:</strong> <?php echo $total_lines; ?></p>
            <p><strong>Ошибки:</strong> <span style="color: #ff6b6b;"><?php echo $error_count; ?></span></p>
            <p><strong>Предупреждения:</strong> <span style="color: #ffd93d;"><?php echo $warning_count; ?></span></p>
            <p><strong>Информация:</strong> <span style="color: #6bcf7f;"><?php echo $info_count; ?></span></p>
            <p><strong>Размер файла:</strong> <?php echo number_format(filesize($log_file) / 1024, 2); ?> KB</p>
        </div>
        
        <div class="filter">
            <input type="text" id="filterInput" placeholder="Фильтр по тексту..." onkeyup="filterLogs()">
        </div>
        <?php endif; ?>
        
        <div class="log-content" id="logContent">
            <?php 
            if (file_exists($log_file)) {
                $lines = explode("\n", $log_content);
                foreach ($lines as $line) {
                    $class = '';
                    if (strpos($line, '[ERROR]') !== false) $class = 'error';
                    elseif (strpos($line, '[WARNING]') !== false) $class = 'warning';
                    elseif (strpos($line, '[INFO]') !== false) $class = 'info';
                    
                    if (!empty(trim($line))) {
                        echo '<div class="log-entry ' . $class . '">' . htmlspecialchars($line) . '</div>';
                    }
                }
            } else {
                echo '<div class="log-entry">Файл логов не найден. Логирование может быть неактивно.</div>';
            }
            ?>
        </div>
    </div>
    
    <script>
    function filterLogs() {
        var filter = document.getElementById('filterInput').value.toLowerCase();
        var logEntries = document.querySelectorAll('.log-entry');
        
        logEntries.forEach(function(entry) {
            var text = entry.textContent.toLowerCase();
            if (text.includes(filter)) {
                entry.style.display = 'block';
                if (filter) {
                    highlightText(entry, filter);
                } else {
                    entry.innerHTML = entry.textContent;
                }
            } else {
                entry.style.display = 'none';
            }
        });
    }
    
    function highlightText(element, searchText) {
        var text = element.textContent;
        var regex = new RegExp('(' + searchText + ')', 'gi');
        element.innerHTML = text.replace(regex, '<span class="highlight">$1</span>');
    }
    
    // Автообновление каждые 30 секунд
    setInterval(function() {
        location.reload();
    }, 30000);
    </script>
</body>
</html>