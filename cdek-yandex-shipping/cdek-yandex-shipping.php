<?php
/**
 * Plugin Name: WooCommerce CDEK Yandex Shipping
 * Plugin URI:  https://example.com/
 * Description: Интеграция пунктов выдачи СДЭК на карте Яндекс + упрощённая форма доставки WooCommerce.
 * Version:     1.0.0
 * Author:      ChatGPT
 * License:     GPLv3 or later
 * Text Domain: wc-cdek-yandex
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_CDEK_Yandex_Shipping {

    /** CDEK API учётные данные */
    const CDEK_ACCOUNT  = 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR';
    const CDEK_PASSWORD = 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM';

    /** Ключ Яндекс.Карт */
    const YMAPS_KEY = '4020b4d5-1d96-476c-a10e-8ab18f0f3702';

    public function __construct() {
        // Регистрируем метод доставки.
        add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_method' ] );

        // Сокращаем форму доставки.
        add_filter( 'woocommerce_checkout_fields', [ $this, 'filter_checkout_fields' ] );

        // Выводим карту после формы.
        add_action( 'woocommerce_after_checkout_form', [ $this, 'render_pickup_map' ] );

        // Скрипты/стили.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX — точки СДЭК.
        add_action( 'wp_ajax_wc_cdek_get_points', [ $this, 'ajax_get_points' ] );
        add_action( 'wp_ajax_nopriv_wc_cdek_get_points', [ $this, 'ajax_get_points' ] );

        // Сохраняем выбранный ПВЗ.
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_order_meta' ], 20, 2 );
    }

    /**
     * Регистрируем класс метода доставки.
     */
    public function register_shipping_method( $methods ) {
        $methods['cdek_yandex'] = 'WC_Shipping_CDEK_Yandex';
        return $methods;
    }

    /**
     * Убираем лишние поля из блока «Адрес доставки».
     */
    public function filter_checkout_fields( $fields ) {
        unset( $fields['shipping']['shipping_city'] );
        unset( $fields['shipping']['shipping_state'] );
        unset( $fields['shipping']['shipping_postcode'] );
        return $fields;
    }

    /**
     * Подключаем скрипты и стили на странице оформления заказа.
     */
    public function enqueue_assets() {
        if ( ! is_checkout() ) {
            return;
        }

        // Яндекс.Карты.
        wp_enqueue_script( 'yandex-maps', 'https://api-maps.yandex.ru/2.1/?apikey=' . self::YMAPS_KEY . '&lang=ru_RU', [], null, true );

        // Наш скрипт.
        wp_enqueue_script( 'wc-cdek-yandex', plugins_url( 'assets/js/cdek-yandex.js', __FILE__ ), [ 'jquery', 'yandex-maps' ], '1.0.0', true );

        wp_localize_script( 'wc-cdek-yandex', 'wc_cdek_yandex', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cdek_yandex_nonce' ),
        ] );
    }

    /**
     * Вывод карты и скрытых полей.
     */
    public function render_pickup_map() {
        wc_get_template( 'cdek-pickup-map.php', [], '', plugin_dir_path( __FILE__ ) . 'templates/' );
    }

    /**
     * AJAX: Точки выдачи СДЭК по координатам.
     */
    public function ajax_get_points() {
        check_ajax_referer( 'cdek_yandex_nonce', 'nonce' );

        $lat = isset( $_POST['lat'] ) ? floatval( $_POST['lat'] ) : 0;
        $lng = isset( $_POST['lng'] ) ? floatval( $_POST['lng'] ) : 0;

        if ( ! $lat || ! $lng ) {
            wp_send_json_error( 'no_coords' );
        }

        // Параметры квадрата поиска (±0.5 градуса ~ 55км).
        $url = sprintf( 'https://api.cdek.ru/v2/deliverypoints?type=PVZ&latitudeFrom=%s&latitudeTo=%s&longitudeFrom=%s&longitudeTo=%s',
            $lat - 0.5,
            $lat + 0.5,
            $lng - 0.5,
            $lng + 0.5
        );

        $token = $this->get_cdek_token();

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( $data );
    }

    /**
     * Получаем и кешируем OAuth-токен CDEK.
     */
    private function get_cdek_token() {
        $token = get_transient( 'wc_cdek_token' );
        if ( $token ) {
            return $token;
        }

        $response = wp_remote_post( 'https://api.cdek.ru/v2/oauth/token?parameters', [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => self::CDEK_ACCOUNT,
                'client_secret' => self::CDEK_PASSWORD,
            ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['access_token'] ) ) {
            set_transient( 'wc_cdek_token', $body['access_token'], intval( $body['expires_in'] ) - 60 );
            return $body['access_token'];
        }

        return '';
    }

    /**
     * Сохраняем выбранный ПВЗ в мета заказа.
     */
    public function save_order_meta( $order, $data ) {
        if ( isset( $_POST['cdek_point_code'] ) && ! empty( $_POST['cdek_point_code'] ) ) {
            $order->update_meta_data( '_cdek_point_code', sanitize_text_field( wp_unslash( $_POST['cdek_point_code'] ) ) );
            $order->update_meta_data( '_cdek_point_address', sanitize_text_field( wp_unslash( $_POST['cdek_point_address'] ) ) );
        }
    }
}

new WC_CDEK_Yandex_Shipping();

// Убираю прямое определение класса и переношу его в хук.
add_action( 'woocommerce_shipping_init', 'wc_cdek_yandex_register_shipping_class', 0 );
function wc_cdek_yandex_register_shipping_class() {
    if ( ! class_exists( 'WC_Shipping_Method' ) || class_exists( 'WC_Shipping_CDEK_Yandex' ) ) {
        return;
    }

    class WC_Shipping_CDEK_Yandex extends WC_Shipping_Method {

        public function __construct() {
            $this->id                 = 'cdek_yandex';
            $this->method_title       = __( 'CDEK доставка', 'wc-cdek-yandex' );
            $this->method_description = __( 'Доставка через пункты выдачи СДЭК (PVZ).', 'wc-cdek-yandex' );
            $this->enabled            = 'yes';
            $this->title              = __( 'СДЭК (ПВЗ)', 'wc-cdek-yandex' );

            $this->init();
        }

        /**
         * Настройки отсутствуют, поэтому просто объявляем пустую форму.
         */
        public function init() {
            $this->init_form_fields();
        }

        /**
         * Настройки отсутствуют, форма пуста.
         */
        public function init_form_fields() {
            $this->form_fields = [];
        }

        /**
         * Вычисляем стоимость доставки (упрощённо — 0 ₽).
         */
        public function calculate_shipping( $package = [] ) {
            $rate = [
                'id'       => $this->id,
                'label'    => $this->title,
                'cost'     => 0,
                'calc_tax' => 'per_order',
            ];

            $this->add_rate( $rate );
        }
    }
}