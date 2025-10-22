/**
 * Подключение скриптов СДЭК с проверкой путей
 */
function cdek_enqueue_scripts() {
    // Подключаем только на страницах корзины и оформления заказа
    if (is_cart() || is_checkout() || is_wc_endpoint_url()) {
        
        // Попробуем разные варианты путей к файлам
        $possible_paths = array(
            get_template_directory_uri() . '/js/',           // папка js в теме
            get_template_directory_uri() . '/',              // корень темы
            get_stylesheet_directory_uri() . '/js/',         // папка js в дочерней теме
            get_stylesheet_directory_uri() . '/',            // корень дочерней темы
            home_url('/wp-content/themes/' . get_template() . '/js/'),  // прямой путь
        );
        
        $delivery_loaded = false;
        $cart_loaded = false;
        
        // Проверяем каждый путь
        foreach ($possible_paths as $path) {
            // Проверяем cdek-delivery.js
            if (!$delivery_loaded) {
                $delivery_url = $path . 'cdek-delivery.js';
                $delivery_file = str_replace(home_url(), ABSPATH, $delivery_url);
                
                if (file_exists($delivery_file)) {
                    wp_enqueue_script('cdek-delivery', $delivery_url, array('jquery'), '1.0.0', true);
                    
                    wp_localize_script('cdek-delivery', 'cdek_ajax', array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('cdek_ajax_nonce')
                    ));
                    
                    $delivery_loaded = true;
                }
            }
            
            // Проверяем cdek-cart.js
            if (!$cart_loaded) {
                $cart_url = $path . 'cdek-cart.js';
                $cart_file = str_replace(home_url(), ABSPATH, $cart_url);
                
                if (file_exists($cart_file)) {
                    wp_enqueue_script('cdek-cart', $cart_url, array('jquery'), '1.0.0', true);
                    $cart_loaded = true;
                }
            }
            
            // Если оба файла найдены, прекращаем поиск
            if ($delivery_loaded && $cart_loaded) {
                break;
            }
        }
        
        // Если файлы не найдены, выводим сообщение в консоль (только для администраторов)
        if (!$delivery_loaded && current_user_can('administrator')) {
            wp_add_inline_script('jquery', 'console.log("CDEK: файл cdek-delivery.js не найден. Проверьте пути к файлам.");');
        }
        if (!$cart_loaded && current_user_can('administrator')) {
            wp_add_inline_script('jquery', 'console.log("CDEK: файл cdek-cart.js не найден. Проверьте пути к файлам.");');
        }
    }
}
add_action('wp_enqueue_scripts', 'cdek_enqueue_scripts');

// АЛЬТЕРНАТИВНЫЙ СПОСОБ: если файлы находятся в определенном месте
// Раскомментируйте и укажите точные пути к вашим файлам
/*
function cdek_enqueue_scripts_manual() {
    if (is_cart() || is_checkout() || is_wc_endpoint_url()) {
        
        // ИЗМЕНИТЕ НА ВАШИ РЕАЛЬНЫЕ ПУТИ:
        $delivery_js = 'https://вашсайт.ru/путь/к/cdek-delivery.js';
        $cart_js = 'https://вашсайт.ru/путь/к/cdek-cart.js';
        
        wp_enqueue_script('cdek-delivery', $delivery_js, array('jquery'), '1.0.0', true);
        wp_enqueue_script('cdek-cart', $cart_js, array('jquery'), '1.0.0', true);
        
        wp_localize_script('cdek-delivery', 'cdek_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cdek_ajax_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'cdek_enqueue_scripts_manual');
*/