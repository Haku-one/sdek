<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Класс интеграции с блоками WooCommerce
 */
final class CdekDeliveryBlocksIntegration implements IntegrationInterface {

    /**
     * Имя интеграции
     */
    public function get_name() {
        return 'cdek-delivery';
    }

    /**
     * Инициализация интеграции
     */
    public function initialize() {
        $this->register_scripts();
        $this->register_editor_blocks();
    }

    /**
     * Регистрация скриптов
     */
    private function register_scripts() {
        $script_path = CDEK_DELIVERY_PLUGIN_PATH . 'assets/js/blocks/cdek-checkout-block.js';
        $script_url = CDEK_DELIVERY_PLUGIN_URL . 'assets/js/blocks/cdek-checkout-block.js';

        wp_register_script(
            'cdek-checkout-block',
            $script_url,
            array('wp-element', 'wp-hooks'),
            filemtime($script_path),
            true
        );

        wp_localize_script('cdek-checkout-block', 'cdekBlockData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cdek_nonce'),
            'yandex_api_key' => get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702')
        ));
    }

    /**
     * Получение данных скрипта
     */
    public function get_script_handles() {
        return array('cdek-checkout-block');
    }

    /**
     * Получение данных для редактора
     */
    public function get_editor_script_handles() {
        return array('cdek-checkout-block');
    }

    /**
     * Данные для передачи в скрипт
     */
    public function get_script_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cdek_nonce'),
            'yandex_api_key' => get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702')
        );
    }

    /**
     * Регистрация блоков в редакторе
     */
    private function register_editor_blocks() {
        // Дополнительная логика для редактора блоков
    }
}

// Регистрация интеграции с блоками
add_action('woocommerce_blocks_loaded', function() {
    if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
        $integration_registry = Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry::get_instance();
        $integration_registry->register(new CdekDeliveryBlocksIntegration());
    }
});