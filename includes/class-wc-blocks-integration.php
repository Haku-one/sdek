<?php
/**
 * WooCommerce Blocks Integration для СДЭК Доставки
 *
 * @package CdekDelivery
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Класс интеграции с WooCommerce Blocks
 */
class WC_Cdek_Blocks_Integration implements IntegrationInterface {

    /**
     * Название интеграции
     *
     * @return string
     */
    public function get_name() {
        return 'cdek-delivery';
    }

    /**
     * Инициализация интеграции
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    /**
     * Возвращает массив зависимостей скриптов
     *
     * @return array
     */
    public function get_script_handles() {
        return array('cdek-delivery-blocks-frontend', 'cdek-delivery-blocks-editor');
    }

    /**
     * Возвращает массив данных для скриптов
     *
     * @return array
     */
    public function get_script_data() {
        return array(
            'cdek_ajax_url' => admin_url('admin-ajax.php'),
            'cdek_nonce' => wp_create_nonce('cdek_nonce'),
            'yandex_api_key' => get_option('cdek_yandex_api_key', '4020b4d5-1d96-476c-a10e-8ab18f0f3702'),
            'plugin_url' => CDEK_DELIVERY_PLUGIN_URL
        );
    }

    /**
     * Регистрация скриптов для фронтенда
     */
    private function register_block_frontend_scripts() {
        $script_path = 'assets/js/blocks/cdek-blocks-frontend.js';
        $script_asset_path = CDEK_DELIVERY_PLUGIN_PATH . 'assets/js/blocks/cdek-blocks-frontend.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array('dependencies' => array(), 'version' => CDEK_DELIVERY_VERSION);

        wp_register_script(
            'cdek-delivery-blocks-frontend',
            CDEK_DELIVERY_PLUGIN_URL . $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'cdek-delivery-blocks-frontend',
            'cdek-delivery'
        );
    }

    /**
     * Регистрация скриптов для редактора
     */
    private function register_block_editor_scripts() {
        $script_path = 'assets/js/blocks/cdek-blocks-editor.js';
        $script_asset_path = CDEK_DELIVERY_PLUGIN_PATH . 'assets/js/blocks/cdek-blocks-editor.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array('dependencies' => array(), 'version' => CDEK_DELIVERY_VERSION);

        wp_register_script(
            'cdek-delivery-blocks-editor',
            CDEK_DELIVERY_PLUGIN_URL . $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'cdek-delivery-blocks-editor',
            'cdek-delivery'
        );
    }
}