<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );
define( 'EHP_THEME_SLUG', 'hello-elementor' );

define( 'HELLO_THEME_PATH', get_template_directory() );
define( 'HELLO_THEME_URL', get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL', HELLO_THEME_URL . '/assets/' );
define( 'HELLO_THEME_SCRIPTS_PATH', HELLO_THEME_ASSETS_PATH . 'js/' );
define( 'HELLO_THEME_SCRIPTS_URL', HELLO_THEME_ASSETS_URL . 'js/' );
define( 'HELLO_THEME_STYLE_PATH', HELLO_THEME_ASSETS_PATH . 'css/' );
define( 'HELLO_THEME_STYLE_URL', HELLO_THEME_ASSETS_URL . 'css/' );
define( 'HELLO_THEME_IMAGES_PATH', HELLO_THEME_ASSETS_PATH . 'images/' );
define( 'HELLO_THEME_IMAGES_URL', HELLO_THEME_ASSETS_URL . 'images/' );

if ( ! isset( $content_width ) ) {
	$content_width = 800; // Pixels.
}

if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/**
	 * Set up theme support.
	 *
	 * @return void
	 */
	function hello_elementor_setup() {
		if ( is_admin() ) {
			hello_maybe_update_theme_version_in_db();
		}

		if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
			register_nav_menus( [ 'menu-1' => esc_html__( 'Header', 'hello-elementor' ) ] );
			register_nav_menus( [ 'menu-2' => esc_html__( 'Footer', 'hello-elementor' ) ] );
		}

		if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}

		if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support(
				'html5',
				[
					'search-form',
					'comment-form',
					'comment-list',
					'gallery',
					'caption',
					'script',
					'style',
					'navigation-widgets',
				]
			);
			add_theme_support(
				'custom-logo',
				[
					'height'      => 100,
					'width'       => 350,
					'flex-height' => true,
					'flex-width'  => true,
				]
			);
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );

			/*
			 * Editor Styles
			 */
			add_theme_support( 'editor-styles' );
			add_editor_style( 'editor-styles.css' );

			/*
			 * WooCommerce.
			 */
			if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
				// WooCommerce in general.
				add_theme_support( 'woocommerce' );
				// Enabling WooCommerce product gallery features (are off by default since WC 3.0.0).
				// zoom.
				add_theme_support( 'wc-product-gallery-zoom' );
				// lightbox.
				add_theme_support( 'wc-product-gallery-lightbox' );
				// swipe.
				add_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

function hello_maybe_update_theme_version_in_db() {
	$theme_version_option_name = 'hello_theme_version';
	// The theme version saved in the database.
	$hello_theme_db_version = get_option( $theme_version_option_name );

	// If the 'hello_theme_version' option does not exist in the DB, or the version needs to be updated, do the update.
	if ( ! $hello_theme_db_version || version_compare( $hello_theme_db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $theme_version_option_name, HELLO_ELEMENTOR_VERSION );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/**
	 * Check whether to display header footer.
	 *
	 * @return bool
	 */
	function hello_elementor_display_header_footer() {
		$hello_elementor_header_footer = true;

		return apply_filters( 'hello_elementor_header_footer', $hello_elementor_header_footer );
	}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/**
	 * Theme Scripts & Styles.
	 *
	 * @return void
	 */
	function hello_elementor_scripts_styles() {
		if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor',
				HELLO_THEME_STYLE_URL . 'reset.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor-theme-style',
				HELLO_THEME_STYLE_URL . 'theme.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( hello_elementor_display_header_footer() ) {
			wp_enqueue_style(
				'hello-elementor-header-footer',
				HELLO_THEME_STYLE_URL . 'header-footer.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/**
	 * Register Elementor Locations.
	 *
	 * @param ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $elementor_theme_manager theme manager.
	 *
	 * @return void
	 */
	function hello_elementor_register_elementor_locations( $elementor_theme_manager ) {
		if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
			$elementor_theme_manager->register_all_core_location();
		}
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/**
	 * Set default content width.
	 *
	 * @return void
	 */
	function hello_elementor_content_width() {
		$GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/**
	 * Add description meta tag with excerpt text.
	 *
	 * @return void
	 */
	function hello_elementor_add_description_meta_tag() {
		if ( ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( empty( $post->post_excerpt ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

// Settings page
require get_template_directory() . '/includes/settings-functions.php';

// Header & footer styling option, inside Elementor
require get_template_directory() . '/includes/elementor-functions.php';

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	// Customizer controls
	function hello_elementor_customizer() {
		if ( ! is_customize_preview() ) {
			return;
		}

		if ( ! hello_elementor_display_header_footer() ) {
			return;
		}

		require get_template_directory() . '/includes/customizer-functions.php';
	}
}
add_action( 'init', 'hello_elementor_customizer' );

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/**
	 * Check whether to display the page title.
	 *
	 * @param bool $val default value.
	 *
	 * @return bool
	 */
	function hello_elementor_check_hide_title( $val ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$current_doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
			if ( $current_doc && 'yes' === $current_doc->get_settings( 'hide_title' ) ) {
				$val = false;
			}
		}
		return $val;
	}
}
add_filter( 'hello_elementor_page_title', 'hello_elementor_check_hide_title' );

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

require HELLO_THEME_PATH . '/theme.php';

HelloTheme\Theme::instance();

// Перевод строки "No products in the cart." в мини-корзине WooCommerce
add_filter('gettext', 'custom_woocommerce_cart_translations', 100, 3);
function custom_woocommerce_cart_translations($translation, $text, $domain) {
    if ($domain === 'woocommerce') {
        if ($text === 'No products in the cart.') {
            return 'В корзине нет товаров.';
        }
    }
    return $translation;
}

// Альтернативный вариант с более точным таргетингом
add_filter('woocommerce_empty_mini_cart_message', 'custom_empty_mini_cart_message');
function custom_empty_mini_cart_message($message) {
    return 'В корзине нет товаров.';
}

// Для случаев, когда текст генерируется через JS
add_action('wp_footer', 'custom_cart_translation_js');
function custom_cart_translation_js() {
    ?>
    <script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            // Заменяем текст при загрузке страницы
            $('.woocommerce-mini-cart__empty-message').text('В корзине нет товаров.');
            
            // Заменяем текст при обновлении фрагментов корзины
            $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function() {
                $('.woocommerce-mini-cart__empty-message').text('В корзине нет товаров.');
            });
            
            // Для Elementor
            $(document).on('elementor/popup/show', function() {
                setTimeout(function() {
                    $('.woocommerce-mini-cart__empty-message').text('В корзине нет товаров.');
                }, 100);
            });
        });
    })(jQuery);
    </script>
    <?php
}

// Дополнительно для Elementor виджета корзины
add_filter('elementor/widget/render_content', 'custom_elementor_cart_content', 10, 2);
function custom_elementor_cart_content($content, $widget) {
    if ($widget->get_name() === 'woocommerce-menu-cart') {
        $content = str_replace('No products in the cart.', 'В корзине нет товаров.', $content);
    }
    return $content;
}

/**
 * Система скидок по объему для WooCommerce
 * Версия с улучшенным UX/UI - зачеркнутые цены и плиточный выбор вариаций
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Проверяем активацию WooCommerce
if (!class_exists('WooCommerce')) {
    return;
}

/**
 * Основной класс системы скидок с улучшенным UI
 */
class WC_Volume_Discounts_Enhanced_UI {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Админка - только для родительских товаров
        add_action('woocommerce_product_options_pricing', array($this, 'add_volume_discount_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_volume_discount_fields'));
        add_action('admin_head', array($this, 'admin_styles'));
        
        // Фронтенд
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_volume_discounts'), 10, 1);
        add_filter('woocommerce_get_item_data', array($this, 'display_discount_in_cart'), 10, 2);
        add_filter('woocommerce_cart_item_name', array($this, 'display_discount_in_mini_cart'), 10, 3);
        
        // Форматирование цен - ИСПРАВЛЕННАЯ ВЕРСИЯ
        add_filter('wc_price', array($this, 'format_price'), 10, 1);
        add_filter('woocommerce_get_price_html', array($this, 'format_price'), 100, 1);
        add_filter('woocommerce_cart_item_price', array($this, 'format_price'), 100, 1);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'format_price'), 100, 1);
        
        // AJAX
        add_action('wp_ajax_get_discounted_price', array($this, 'ajax_get_discounted_price'));
        add_action('wp_ajax_nopriv_get_discounted_price', array($this, 'ajax_get_discounted_price'));
        add_action('wp_ajax_add_to_cart_volume_discount', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_add_to_cart_volume_discount', array($this, 'ajax_add_to_cart'));
        
        // Шорткод
        add_shortcode('volume_discount_controls', array($this, 'volume_discount_shortcode'));
        
        // Скрипты
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Обновление мини-корзины
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'update_mini_cart_fragments'));
    }
    
    /**
     * Получение настроек скидок для товара (всегда для родительского)
     */
    public function get_discount_settings($product_id) {
        $discount_settings = array();
        
        // Для вариаций ВСЕГДА используем настройки родительского товара
        $parent_id = wp_get_post_parent_id($product_id);
        if ($parent_id) {
            $product_id = $parent_id;
        }
        
        // Получаем настройки из метаполей родительского товара
        for ($i = 1; $i <= 7; $i++) {
            $min = get_post_meta($product_id, '_volume_discount_min_' . $i, true);
            $max = ($i == 7) ? 999999 : get_post_meta($product_id, '_volume_discount_max_' . $i, true);
            $percentage = get_post_meta($product_id, '_volume_discount_percentage_' . $i, true);
            
            if (!empty($min) && !empty($percentage)) {
                $discount_settings[] = array(
                    'min' => intval($min),
                    'max' => intval($max),
                    'percentage' => floatval(str_replace(',', '.', $percentage))
                );
            }
        }
        
        // Значения по умолчанию
        if (empty($discount_settings)) {
            $discount_settings = array(
                array('min' => 5, 'max' => 10, 'percentage' => 3),
                array('min' => 11, 'max' => 20, 'percentage' => 6),
                array('min' => 21, 'max' => 59, 'percentage' => 12.5),
                array('min' => 60, 'max' => 119, 'percentage' => 45),
                array('min' => 120, 'max' => 239, 'percentage' => 46.5),
                array('min' => 240, 'max' => 799, 'percentage' => 48),
                array('min' => 800, 'max' => 999999, 'percentage' => 50)
            );
        }
        
        return $discount_settings;
    }
    
    /**
     * Расчет процента скидки
     */
    public function get_discount_percentage($quantity, $product_id) {
        $discount_settings = $this->get_discount_settings($product_id);
        
        foreach ($discount_settings as $setting) {
            if ($quantity >= $setting['min'] && $quantity <= $setting['max']) {
                return floatval($setting['percentage']);
            }
        }
        
        return 0;
    }
    
    /**
     * Применение скидок в корзине
     */
    public function apply_volume_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['quantity'])) continue;
            
            $quantity = $cart_item['quantity'];
            $product_id = $cart_item['product_id'];
            $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
            
            // Используем родительский ID для получения настроек скидок
            $discount_product_id = $variation_id ? $product_id : $product_id;
            $discount_percentage = $this->get_discount_percentage($quantity, $discount_product_id);
            
            if ($discount_percentage > 0) {
                $original_price = $cart_item['data']->get_regular_price();
                if (empty($original_price)) {
                    $original_price = $cart_item['data']->get_price();
                }
                
                if ($original_price) {
                    $discounted_price = $original_price * (1 - ($discount_percentage / 100));
                    $cart_item['data']->set_price($discounted_price);
                    
                    // Сохраняем информацию о скидке
                    $cart->cart_contents[$cart_item_key]['volume_discount'] = $discount_percentage;
                    $cart->cart_contents[$cart_item_key]['original_price'] = $original_price;
                }
            }
        }
    }
    
    /**
     * Форматирование цены - ИСПРАВЛЕННАЯ ВЕРСИЯ
     * Убирает .00 только в конце строки и заменяет символ валюты
     */
    public function format_price($price_html) {
        // Удаляем .00 только если оно стоит в конце числа перед валютой или пробелом
        $price_html = preg_replace('/(\d)\.00(\s*(?:руб\.|₽|<span[^>]*>(?:руб\.|₽)<\/span>))/', '$1$2', $price_html);
        
        // Заменяем символ валюты
        $price_html = str_replace('₽', ' руб.', $price_html);
        
        return $price_html;
    }
    
    /**
     * Отображение скидки в корзине
     */
    public function display_discount_in_cart($cart_item_data, $cart_item) {
        if (isset($cart_item['volume_discount']) && $cart_item['volume_discount'] > 0) {
            $cart_item_data[] = array(
                'name' => 'Скидка',
                'value' => $cart_item['volume_discount'] . '%',
                'display' => '',
            );
        }
        return $cart_item_data;
    }
    
    /**
     * Отображение скидки в мини-корзине
     */
    public function display_discount_in_mini_cart($product_name, $cart_item, $cart_item_key) {
        if (isset($cart_item['volume_discount']) && $cart_item['volume_discount'] > 0) {
            if (strpos($product_name, 'volume-discount-mini') === false) {
                $product_name .= '<div class="volume-discount-mini" style="font-size: 12px; color: #01923F;">Скидка: ' . $cart_item['volume_discount'] . '%</div>';
            }
        }
        return $product_name;
    }
    
    /**
     * Обновление фрагментов мини-корзины
     */
    public function update_mini_cart_fragments($fragments) {
        // Обновляем общее количество
        $fragments['.cart-contents-count'] = WC()->cart->get_cart_contents_count();
        
        return $fragments;
    }
    
    /**
     * AJAX получение цены со скидкой с улучшенным отображением
     */
    public function ajax_get_discounted_price() {
        check_ajax_referer('volume_discount_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($product_id <= 0 || $quantity <= 0) {
            wp_send_json_error('Неверные параметры');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Товар не найден');
        }
        
        // Для вариаций используем родительский ID для настроек скидок
        $parent_id = $product->get_parent_id();
        $discount_product_id = $parent_id ? $parent_id : $product_id;
        
        $discount_percentage = $this->get_discount_percentage($quantity, $discount_product_id);
        
        $regular_price = $product->get_regular_price();
        if (empty($regular_price)) {
            $regular_price = $product->get_price();
        }
        
        $final_price = $regular_price;
        $price_html = '';
        
        if ($discount_percentage > 0) {
            $final_price = $regular_price * (1 - ($discount_percentage / 100));
            
            // Форматируем цену с зачеркнутой оригинальной ценой
            $original_price_formatted = number_format($regular_price, 0, '.', ' ') . ' руб.';
            $discounted_price_formatted = number_format($final_price, 0, '.', ' ') . ' руб.';
            
            $price_html = '
                <div class="volume-discount-price-wrapper">
                    <span class="original-price" style="text-decoration: line-through; color: #999; font-size: 0.9em;">' . $original_price_formatted . '</span>
                    <span class="discounted-price" style="color: #01923F; font-weight: bold; font-size: 1.1em;">' . $discounted_price_formatted . '</span>
                    <span class="discount-badge" style="background: #01923F; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-left: 8px;">-' . $discount_percentage . '%</span>
                </div>';
        } else {
            // Обычная цена без скидки
            $price_html = '<span class="woocommerce-Price-amount amount"><bdi>' . number_format($final_price, 0, '.', ' ') . ' руб.</bdi></span>';
        }
        
        wp_send_json_success(array(
            'price_html' => $price_html,
            'discount_percentage' => $discount_percentage,
            'original_price' => $regular_price,
            'discounted_price' => $final_price,
            'has_discount' => $discount_percentage > 0
        ));
    }
    
    /**
     * AJAX добавление в корзину
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('volume_discount_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        
        if ($product_id <= 0 || $quantity <= 0) {
            wp_send_json_error('Неверные параметры');
        }
        
        // Собираем атрибуты вариации
        $variation_data = array();
        if ($variation_id > 0) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'attribute_') === 0) {
                    $variation_data[$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Добавляем в корзину
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data);
        
        if ($cart_item_key) {
            // Получаем обновленные фрагменты
            $fragments = apply_filters('woocommerce_add_to_cart_fragments', array(
                '.widget_shopping_cart_content' => $this->get_mini_cart_content(),
                '.cart-contents-count' => WC()->cart->get_cart_contents_count()
            ));
            
            wp_send_json_success(array(
                'message' => 'Товар добавлен в корзину',
                'cart_hash' => WC()->cart->get_cart_hash(),
                'fragments' => $fragments
            ));
        } else {
            wp_send_json_error('Не удалось добавить товар в корзину');
        }
    }
    
    /**
     * Получение содержимого мини-корзины
     */
    private function get_mini_cart_content() {
        ob_start();
        woocommerce_mini_cart();
        return '<div class="widget_shopping_cart_content">' . ob_get_clean() . '</div>';
    }
    
    /**
     * Подключение скриптов
     */
    public function enqueue_scripts() {
        if (is_product() || is_shop() || is_product_category() || is_cart()) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'volume_discount_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('volume_discount_nonce'),
                'cart_url' => wc_get_cart_url()
            ));
        }
    }
    
    /**
     * Шорткод элементов управления с улучшенным UI
     */
    public function volume_discount_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => 0,
            'button_text' => 'Добавить в корзину',
            'button_class' => 'button',
            'show_price' => 'yes',
            'show_discounts' => 'yes',
        ), $atts);
        
        if (empty($atts['product_id'])) {
            global $product;
            if (!$product) return '<p>Товар не найден.</p>';
            $product_id = $product->get_id();
            $product_obj = $product;
        } else {
            $product_id = intval($atts['product_id']);
            $product_obj = wc_get_product($product_id);
            if (!$product_obj) return '<p>Товар не найден.</p>';
        }
        
        $is_variable = $product_obj->is_type('variable');
        $unique_id = 'volume-controls-' . $product_id . '-' . rand(1000, 9999);
        
        // Для вариативных товаров берем настройки скидок с родительского товара
        $discount_product_id = $is_variable ? $product_id : $product_id;
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($unique_id); ?>" class="volume-discount-controls enhanced-ui" data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php if ($atts['show_price'] === 'yes' && !$is_variable): ?>
                <div class="product-price">
                    <span class="price"><?php echo wc_price($product_obj->get_price()); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($is_variable): ?>
                <?php $this->render_variable_product_form($product_obj, $atts, $unique_id); ?>
            <?php else: ?>
                <?php $this->render_simple_product_form($product_obj, $atts, $unique_id); ?>
            <?php endif; ?>
            
            <?php if ($atts['show_discounts'] === 'yes'): ?>
                <div class="volume-discount-table">
                    <h4 style="color: #121212; margin-bottom: 15px;">Скидки по количеству:</h4>
                    <div class="discount-grid">
                        <?php foreach ($this->get_discount_settings($discount_product_id) as $index => $setting): ?>
                            <div class="discount-item" style="border-left: 3px solid #00A0E3;">
                                <div class="quantity-range">
                                    <?php if ($setting['max'] < 999999): ?>
                                        <?php echo $setting['min']; ?>-<?php echo $setting['max']; ?> шт
                                    <?php else: ?>
                                        от <?php echo $setting['min']; ?> шт
                                    <?php endif; ?>
                                </div>
                                <div class="discount-percent">-<?php echo $setting['percentage']; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="cart-notification"></div>
        </div>
        
        <style>
        #<?php echo esc_attr($unique_id); ?> {
            margin: 20px 0;
            font-family: 'Montserrat';
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
			flex-wrap: wrap;
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 8px;
            border: 2px solid #00A0E3;
            overflow: hidden;
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity button {
            width: 40px;
            height: 40px;
            border: none;
            background: #00A0E3;
            color: white;
            cursor: pointer;
            font-weight: bold;
            font-size: 18px;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity button:hover {
            background: #0087c7;
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity input {
            width: 70px;
            height: 40px;
            text-align: center;
            border: none;
            background: white;
            font-weight: bold;
            font-size: 16px;
            color: #121212;
        }
        
        #<?php echo esc_attr($unique_id); ?> .add-to-cart-btn {
            background: #01923F;
            color: white;
            border: none;
            padding: 12px 24px !important;
            border-radius: 10px !important; 
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .add-to-cart-btn:hover {
            background: #017a35;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(1, 146, 63, 0.3);
        }
        
        #<?php echo esc_attr($unique_id); ?> .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variations {
            margin: 20px 0;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-row {
            margin: 15px 0;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-row label {
            display: block;
            font-weight: bold;
            color: #121212;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-tiles {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-tile {
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #121212;
            text-align: center;
            min-width: 80px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-tile:hover {
            border-color: #00A0E3;
            background: #f0f8ff;
        }
        
        #<?php echo esc_attr($unique_id); ?> .variation-tile.selected {
            border-color: #01923F;
            background: #01923F;
            color: white;
        }
        
        #<?php echo esc_attr($unique_id); ?> .volume-discount-table {
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }
        
        #<?php echo esc_attr($unique_id); ?> .discount-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .discount-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
			DISPLAY: flex;
            gap: 10px;
            align-items: center;
        }
        
        #<?php echo esc_attr($unique_id); ?> .discount-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        #<?php echo esc_attr($unique_id); ?> .quantity-range {
            font-weight: bold;
            color: #121212;
            font-size: 14px;
            
        }
        
        #<?php echo esc_attr($unique_id); ?> .discount-percent {
            color: #01923F;
            font-weight: bold;
            font-size: 16px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .cart-notification {
            margin: 15px 0;
        }
        
        #<?php echo esc_attr($unique_id); ?> .success {
            color: #01923F;
            background: #f0fff4;
            padding: 15px;
            border: 1px solid #01923F;
            border-radius: 8px;
            font-weight: 500;
        }
        
        #<?php echo esc_attr($unique_id); ?> .error {
            color: #dc3545;
            background: #fff5f5;
            padding: 15px;
            border: 1px solid #dc3545;
            border-radius: 8px;
            font-weight: 500;
        }
        
        #<?php echo esc_attr($unique_id); ?> .single_variation_wrap {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        #<?php echo esc_attr($unique_id); ?> .woocommerce-variation-price {
            margin: 15px 0;
            font-weight: bold;
            font-size: 18px;
        }
        
        #<?php echo esc_attr($unique_id); ?> .volume-discount-price-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            #<?php echo esc_attr($unique_id); ?> .quantity-controls {
                
                gap: 15px;
                align-items: stretch;
				    border: 0px solid #e9ecef;
				padding: 0px;
            }
            
            #<?php echo esc_attr($unique_id); ?> .quantity {
                justify-content: center;
				width: fit-content;
            }
			
			#<?php echo esc_attr($unique_id); ?> .add-to-cart-btn{
				letter-spacing: 0px;
				font-size: 12px;
				text-transform: none;
				padding: 8px 13px !important;
			}
			
			
            
            #<?php echo esc_attr($unique_id); ?> .variation-tile {
                    padding: 0.4em;
				    min-width: 60px;
            }
            
            #<?php echo esc_attr($unique_id); ?> .discount-grid {
                grid-template-columns: 1fr;
            }
            
            
			#<?php echo esc_attr($unique_id); ?> .single_variation_wrap{
				padding: 10px;
			}
			
			span.original-price {
    font-size: 16px !important;
}

span.discounted-price {
    font-size: 18px !important;
}

span.discount-badge {
    font-size: 16px !important;
}
			
			span.price{
				font-size: 18px !important;
			}
			
			.quantity{
				margin-right :10px;
			}
			
			.quantity .qty{
				width: 40px !important;
			}
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var container = $('#<?php echo esc_js($unique_id); ?>');
            var productId = <?php echo esc_js($product_id); ?>;
            var isVariable = <?php echo esc_js($is_variable ? 'true' : 'false'); ?>;
            
            // Обновление цены с учетом скидки
            function updatePriceWithDiscount() {
                var quantity = parseInt(container.find('.qty').val()) || 1;
                var currentProductId = productId;
                
                if (isVariable) {
                    var variationId = container.find('input[name="variation_id"]').val();
                    if (!variationId || variationId == 0) {
                        return;
                    }
                    currentProductId = variationId;
                }
                
                $.post(volume_discount_ajax.ajax_url, {
                    action: 'get_discounted_price',
                    nonce: volume_discount_ajax.nonce,
                    product_id: currentProductId,
                    quantity: quantity
                }, function(response) {
                    if (response.success) {
                        var priceContainer = isVariable ? 
                            container.find('.single_variation .woocommerce-variation-price .price') : 
                            container.find('.product-price .price');
                        
                        if (priceContainer.length > 0) {
                            priceContainer.html(response.data.price_html);
                        }
                    }
                });
            }
            
            // Кнопки количества
            container.on('click', '.qty-plus', function() {
                var input = container.find('.qty');
                var val = parseInt(input.val()) + 1;
                input.val(val).trigger('change');
            });
            
            container.on('click', '.qty-minus', function() {
                var input = container.find('.qty');
                var val = parseInt(input.val()) - 1;
                if (val < 1) val = 1;
                input.val(val).trigger('change');
            });
            
            // Изменение количества
            container.on('change', '.qty', function() {
                updatePriceWithDiscount();
            });
            
            // Плиточный выбор вариаций
            container.on('click', '.variation-tile', function() {
                var $tile = $(this);
                var value = $tile.data('value');
                var attributeName = $tile.data('attribute');
                
                // Убираем выделение с других плиток этого атрибута
                container.find('.variation-tile[data-attribute="' + attributeName + '"]').removeClass('selected');
                
                // Выделяем выбранную плитку
                $tile.addClass('selected');
                
                // Обновляем скрытое поле
                container.find('input[name="' + attributeName + '"]').val(value);
                
                // Проверяем и обновляем вариацию
                setTimeout(function() {
                    findVariation();
                    updatePriceWithDiscount();
                }, 100);
            });
            
            // Добавление в корзину
            container.on('click', '.add-to-cart-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var quantity = parseInt(container.find('.qty').val()) || 1;
                var data = {
                    action: 'add_to_cart_volume_discount',
                    nonce: volume_discount_ajax.nonce,
                    product_id: productId,
                    quantity: quantity
                };
                
                if (isVariable) {
                    var variationId = container.find('input[name="variation_id"]').val();
                    if (!variationId || variationId == 0) {
                        container.find('.cart-notification').html('<div class="error">Выберите все опции товара</div>');
                        return;
                    }
                    data.variation_id = variationId;
                    
                    // Добавляем атрибуты
                    container.find('input[name^="attribute_"]').each(function() {
                        data[$(this).attr('name')] = $(this).val();
                    });
                }
                
                button.prop('disabled', true).text('Добавление...');
                
                $.post(volume_discount_ajax.ajax_url, data, function(response) {
                    button.prop('disabled', false).text('<?php echo esc_js($atts['button_text']); ?>');
                    
                    if (response.success) {
                        container.find('.cart-notification').html('<div class="success">' + response.data.message + '</div>');
                        
                        // Обновляем фрагменты корзины
                        if (response.data.fragments) {
                            $.each(response.data.fragments, function(key, value) {
                                $(key).replaceWith(value);
                            });
                        }
                        
                        // Триггерим обновление корзины
                        $(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash, button]);
                        $(document.body).trigger('wc_fragments_refreshed');
                    } else {
                        container.find('.cart-notification').html('<div class="error">' + response.data + '</div>');
                    }
                    
                    setTimeout(function() {
                        container.find('.cart-notification').html('');
                    }, 5000);
                });
            });
            
            // Инициализация
            if (!isVariable) {
                updatePriceWithDiscount();
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Рендер формы для простого товара
     */
    private function render_simple_product_form($product, $atts, $unique_id) {
        ?>
        <div class="quantity-controls">
            <div class="quantity">
                <button type="button" class="qty-minus">−</button>
                <input type="number" class="qty" value="1" min="1" step="1">
                <button type="button" class="qty-plus">+</button>
            </div>
            <button type="button" class="add-to-cart-btn <?php echo esc_attr($atts['button_class']); ?>">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Рендер формы для вариативного товара с плиточным выбором
     */
    private function render_variable_product_form($product, $atts, $unique_id) {
        $available_variations = $product->get_available_variations();
        $attributes = $product->get_variation_attributes();
        ?>
        <div class="variations">
            <?php foreach ($attributes as $attribute_name => $options): ?>
                <div class="variation-row">
                    <label for="<?php echo esc_attr(sanitize_title($attribute_name)); ?>">
                        <?php echo wc_attribute_label($attribute_name); ?>:
                    </label>
                    <div class="variation-tiles">
                        <?php foreach ($options as $option): ?>
                            <div class="variation-tile" 
                                 data-value="<?php echo esc_attr($option); ?>" 
                                 data-attribute="attribute_<?php echo esc_attr(sanitize_title($attribute_name)); ?>">
                                <?php echo esc_html($option); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="attribute_<?php echo esc_attr(sanitize_title($attribute_name)); ?>" value="">
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="single_variation_wrap" style="display: none;">
            <div class="single_variation"></div>
            <div class="quantity-controls">
                <div class="quantity">
                    <button type="button" class="qty-minus">−</button>
                    <input type="number" class="qty" value="1" min="1" step="1">
                    <button type="button" class="qty-plus">+</button>
                </div>
                <button type="button" class="add-to-cart-btn <?php echo esc_attr($atts['button_class']); ?>">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </div>
        </div>
        
        <input type="hidden" name="variation_id" value="0">
        
        <script>
        jQuery(document).ready(function($) {
            var variations = <?php echo wp_json_encode($available_variations); ?>;
            var container = $('#<?php echo esc_js($unique_id); ?>');
            
            function findVariation() {
                var attributes = {};
                var allSelected = true;
                
                container.find('input[name^="attribute_"]').each(function() {
                    var name = $(this).attr('name').replace('attribute_', '');
                    var value = $(this).val();
                    
                    if (value === '') {
                        allSelected = false;
                    }
                    attributes[name] = value;
                });
                
                if (!allSelected) {
                    container.find('.single_variation_wrap').hide();
                    container.find('input[name="variation_id"]').val(0);
                    return;
                }
                
                var matchedVariation = null;
                $.each(variations, function(index, variation) {
                    var match = true;
                    
                    $.each(attributes, function(attr_name, attr_value) {
                        var variation_attr_name = 'attribute_' + attr_name;
                        if (variation.attributes[variation_attr_name] !== undefined && variation.attributes[variation_attr_name] !== attr_value) {
                            match = false;
                            return false;
                        }
                    });
                    
                    if (match) {
                        matchedVariation = variation;
                        return false;
                    }
                });
                
                if (matchedVariation) {
                    container.find('input[name="variation_id"]').val(matchedVariation.variation_id);
                    
                    // Отображаем базовую цену вариации
                    var priceDisplay = '';
                    
                    if (matchedVariation.price_html) {
                        priceDisplay = matchedVariation.price_html;
                    } else {
                        var price = matchedVariation.display_price || matchedVariation.display_regular_price;
                        if (price) {
                            priceDisplay = '<span class="woocommerce-Price-amount amount"><bdi>' + Math.round(price) + ' руб.</bdi></span>';
                        }
                    }
                    
                    if (priceDisplay) {
                        container.find('.single_variation').html('<div class="woocommerce-variation-price"><span class="price">' + priceDisplay + '</span></div>');
                    }
                    
                    container.find('.single_variation_wrap').show();
                    
                    // Обновляем цену с учетом скидки
                    setTimeout(function() {
                        container.find('.qty').trigger('change');
                    }, 100);
                } else {
                    container.find('.single_variation_wrap').hide();
                    container.find('input[name="variation_id"]').val(0);
                }
            }
            
            // Экспортируем функцию для использования в основном скрипте
            window.findVariation = findVariation;
        });
        </script>
        <?php
    }
    
    /**
     * Добавление полей в админке товара (ТОЛЬКО для родительских товаров)
     */
    public function add_volume_discount_fields() {
        global $post;
        
        if (!isset($post->ID) || get_post_type($post->ID) !== 'product') return;
        
        $product = wc_get_product($post->ID);
        if (!$product) return;
        
        // Показываем поля только для простых товаров и вариативных (родительских)
        if (!$product->is_type('simple') && !$product->is_type('variable')) return;
        
        echo '<div class="options_group">';
        echo '<h3>Настройки скидок по объему</h3>';
        if ($product->is_type('variable')) {
            echo '<p><strong>Эти настройки применяются ко всем вариациям данного товара</strong></p>';
        }
        echo '<p>Укажите диапазоны количества товаров и соответствующие проценты скидок (до 7 правил)</p>';
        
        for ($i = 1; $i <= 7; $i++) {
            echo '<div class="volume-discount-field-group">';
            
            woocommerce_wp_text_input(array(
                'id' => '_volume_discount_min_' . $i,
                'label' => 'От (шт)',
                'type' => 'number',
                'custom_attributes' => array('step' => '1', 'min' => '1'),
                'wrapper_class' => 'form-field'
            ));
            
            if ($i < 7) {
                woocommerce_wp_text_input(array(
                    'id' => '_volume_discount_max_' . $i,
                    'label' => 'До (шт)',
                    'type' => 'number',
                    'custom_attributes' => array('step' => '1', 'min' => '1'),
                    'wrapper_class' => 'form-field'
                ));
            }
            
            woocommerce_wp_text_input(array(
                'id' => '_volume_discount_percentage_' . $i,
                'label' => 'Скидка (%)',
                'type' => 'text',
                'wrapper_class' => 'form-field'
            ));
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Сохранение полей товара
     */
    public function save_volume_discount_fields($post_id) {
        if (get_post_type($post_id) !== 'product') return;
        
        for ($i = 1; $i <= 7; $i++) {
            $min_field = '_volume_discount_min_' . $i;
            $min_value = isset($_POST[$min_field]) ? wc_clean($_POST[$min_field]) : '';
            update_post_meta($post_id, $min_field, $min_value);
            
            if ($i < 7) {
                $max_field = '_volume_discount_max_' . $i;
                $max_value = isset($_POST[$max_field]) ? wc_clean($_POST[$max_field]) : '';
                update_post_meta($post_id, $max_field, $max_value);
            }
            
            $percentage_field = '_volume_discount_percentage_' . $i;
            $percentage_value = isset($_POST[$percentage_field]) ? wc_clean($_POST[$percentage_field]) : '';
            $percentage_value = str_replace(',', '.', $percentage_value);
            update_post_meta($post_id, $percentage_field, $percentage_value);
        }
    }
    
    /**
     * Стили для админки
     */
    public function admin_styles() {
        ?>
        <style>
        .volume-discount-field-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .volume-discount-field-group .form-field {
            margin: 0 10px 0 0 !important;
            width: 120px;
        }
			
			
        </style>
        <?php
    }
}

// Инициализация
new WC_Volume_Discounts_Enhanced_UI();

add_filter('woocommerce_currency_symbol', 'misha_symbol_to_bukvi', 9999, 2);
 
function misha_symbol_to_bukvi( $valyuta_symbol, $valyuta_code ) {
	if( $valyuta_code === 'UAH' ) {
		return 'грн.';
	}
	if( $valyuta_code === 'RUB' ) {
		return 'руб.';
	}
	return $valyuta_symbol;
}

function custom_excerpt_length( $length ) {
    return 1000;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );


/**
 * Переопределение переводов WooCommerce
 * Замена "Сэкономьте" на "Скидка"
 */

// Запретить прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Переопределение переводов WooCommerce
 */
function override_woocommerce_translations($translation, $text, $domain) {
    // Если это WooCommerce или WooCommerce блоки
    if (in_array($domain, ['woocommerce', 'woo-gutenberg-products-block', 'woocommerce-blocks'])) {
        // Переводы для замены
        $translations = array(
            'Save' => 'Скидка',
            'Сэкономьте' => 'Скидка',
            'You save' => 'Скидка',
            'Savings' => 'Скидка',
        );
        
        // Проверяем есть ли замена для данного текста
        if (isset($translations[$text])) {
            return $translations[$text];
        }
    }
    
    return $translation;
}

// Добавляем фильтр для переопределения переводов
add_filter('gettext', 'override_woocommerce_translations', 20, 3);
add_filter('gettext_with_context', 'override_woocommerce_translations', 20, 3);

/**
 * Альтернативный способ через JavaScript для блоков
 */
function custom_woocommerce_blocks_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Заменяем текст "Сэкономьте" на "Скидка" в блоках WooCommerce
        function replaceText() {
            const elements = document.querySelectorAll('.wc-block-components-sale-badge, .wc-block-components-product-badge');
            elements.forEach(function(element) {
                if (element.textContent.includes('Сэкономьте')) {
                    element.innerHTML = element.innerHTML.replace(/Сэкономьте/g, 'Скидка');
                }
            });
        }
        
        // Выполняем сразу
        replaceText();
        
        // Наблюдаем за изменениями DOM для динамически загружаемых блоков
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    replaceText();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
}

// Добавляем скрипт в футер
add_action('wp_footer', 'custom_woocommerce_blocks_script');

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

// ПРАВИЛЬНАЯ ВЕРСИЯ - вкладка как у WooCommerce Blocks

// 3. ДОБАВЛЯЕМ ПРАВИЛЬНУЮ ВКЛАДКУ
add_action('wp_footer', 'add_proper_tab');
function add_proper_tab() {
    if (is_checkout()) {
        ?>
        <style>
        
        </style>
        
        <script>
		
        function fillAllFields() {
            const fieldMappings = [
                
            ];
            
            fieldMappings.forEach(mapping => {
                mapping.ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = mapping.value;
                        el.setAttribute('value', mapping.value);
                        el.defaultValue = mapping.value;
                        el.removeAttribute('required');
                        el.setAttribute('aria-invalid', 'false');
                        el.classList.remove('wc-invalid', 'has-error');
                        
                        const parent = el.closest('.wc-block-components-text-input');
                        if (parent) {
                            parent.classList.remove('has-error');
                        }
                        
                        ['input', 'change', 'blur'].forEach(eventType => {
                            el.dispatchEvent(new Event(eventType, {bubbles: true}));
                        });
                    }
                });
            });
            
            document.querySelectorAll('.wc-block-components-validation-error').forEach(error => {
                
            });
            
            document.querySelectorAll('.has-error').forEach(el => {
                el.classList.remove('has-error');
            });
        }
        
        function addDiscussTab() {
    const container = document.querySelector('.wc-block-checkout__shipping-method-container');
    if (container && !document.getElementById('discuss-tab')) {
        // Создаем вкладку
        const tab = document.createElement('div');
        tab.id = 'discuss-tab';
        tab.setAttribute('role', 'radio');
        tab.setAttribute('aria-checked', 'false');
        tab.setAttribute('tabindex', '0');
        tab.className = 'wc-block-checkout__shipping-method-option';
        
        tab.innerHTML = `
            <span class="wc-block-checkout__shipping-method-option-title-wrapper">
                <span class="wc-block-checkout__shipping-method-option-title">Обсудить доставку с менеджером</span>
            </span>
        `;
        
        // Обработчик для всех вкладок доставки
        const handleShippingMethodClick = function(clickedTab) {
            // Убираем выделение со всех вкладок
            container.querySelectorAll('.wc-block-checkout__shipping-method-option').forEach(option => {
                option.setAttribute('aria-checked', 'false');
                option.classList.remove('wc-block-checkout__shipping-method-option--selected');
            });
            
            // Выделяем кликнутую вкладку
            clickedTab.setAttribute('aria-checked', 'true');
            clickedTab.classList.add('wc-block-checkout__shipping-method-option--selected');
            
            // Управляем видимостью карты СДЭК
            const cdekElements = document.querySelectorAll('.wp-block-cdek-checkout-map-block, #cdek-map-container');
            const isDiscussTab = clickedTab.id === 'discuss-tab';
            
            cdekElements.forEach(el => {
                el.style.display = isDiscussTab ? 'none' : 'block';
            });
            
            // Управляем скрытым полем для обсуждения доставки
            let hiddenField = document.getElementById('discuss_selected');
            if (isDiscussTab) {
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.id = 'discuss_selected';
                    hiddenField.name = 'discuss_delivery_selected';
                    hiddenField.value = '1';
                    document.body.appendChild(hiddenField);
                }
                fillAllFields();
            } else if (hiddenField) {
                hiddenField.remove();
            }
			
			
        };
        
        // Добавляем обработчики для всех вкладок
        container.querySelectorAll('.wc-block-checkout__shipping-method-option').forEach(option => {
            option.addEventListener('click', function() {
                handleShippingMethodClick(this);
            });
        });
        
        // Добавляем обработчик для новой вкладки
        tab.addEventListener('click', function() {
            handleShippingMethodClick(this);
        });
        
        // Добавляем вкладку в контейнер
        container.appendChild(tab);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    fillAllFields();
    setTimeout(addDiscussTab, 500);
    
    // Наблюдатель за изменениями DOM
    new MutationObserver(function() {
        addDiscussTab();
    }).observe(document.body, {
        childList: true, 
        subtree: true
    });
});
        
        document.addEventListener('DOMContentLoaded', function() {
            fillAllFields();
            setTimeout(addDiscussTab, 500);
            setInterval(fillAllFields, 1000);
            
            new MutationObserver(function() {
                addDiscussTab();
                setTimeout(fillAllFields, 100);
            }).observe(document.body, {
                childList: true, 
                subtree: true
            });
        });
        </script>
        <?php
    }
}

// 4. СОХРАНЯЕМ ВЫБОР
add_action('woocommerce_checkout_update_order_meta', 'save_discuss_choice');
function save_discuss_choice($order_id) {
    if (isset($_POST['discuss_delivery_selected'])) {
        update_post_meta($order_id, '_discuss_delivery_selected', 'Да');
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('Клиент выбрал "Обсудить доставку с менеджером"');
        }
    }
}

// 5. ПОКАЗЫВАЕМ В АДМИНКЕ
add_action('woocommerce_admin_order_data_after_shipping_address', 'show_discuss_admin');
function show_discuss_admin($order) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        ?>
        <div style="background: #ffeb3b; padding: 15px; margin: 10px 0; border-radius: 5px;">
            <h4 style="color: #e65100; margin: 0;">ОБСУДИТЬ ДОСТАВКУ С МЕНЕДЖЕРОМ</h4>
            <p style="color: #e65100; font-weight: bold; margin: 5px 0 0 0;">
                Необходимо связаться с клиентом для обсуждения доставки!
            </p>
        </div>
        <?php
    }
}

// 6. EMAIL УВЕДОМЛЕНИЯ
add_action('woocommerce_email_order_details', 'email_discuss_info', 10, 4);
function email_discuss_info($order, $sent_to_admin, $plain_text, $email) {
    if (get_post_meta($order->get_id(), '_discuss_delivery_selected', true) == 'Да') {
        if ($plain_text) {
            echo "\nДОСТАВКА: Обсуждается с менеджером\n\n";
        } else {
            ?>
            <div style="background: #ffeb3b; padding: 15px; margin: 15px 0; border-radius: 5px;">
                <h3 style="color: #e65100; margin: 0;">ДОСТАВКА: Обсуждается с менеджером</h3>
            </div>
            <?php
        }
    }
}