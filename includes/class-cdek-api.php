<?php
/**
 * CDEK API Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDEK_API {
    
    private $account_id;
    private $secure_password;
    private $api_url = 'https://api.cdek.ru/v2/';
    private $access_token = null;
    
    public function __construct() {
        $this->account_id = get_option('cdek_account_id', 'Lr7x5fauu0eOXDA4hlK04HiMUpqHgzzR');
        $this->secure_password = get_option('cdek_secure_password', 'fzwKqoaKaTrwRjxVhf6csNzTefyHRHYM');
        
        // Use test API if test mode is enabled
        if (get_option('cdek_test_mode', '0') === '1') {
            $this->api_url = 'https://api.edu.cdek.ru/v2/';
        }
        
        $this->get_access_token();
    }
    
    /**
     * Get access token for CDEK API
     */
    private function get_access_token() {
        $token_cache_key = 'cdek_access_token';
        $cached_token = get_transient($token_cache_key);
        
        if ($cached_token) {
            $this->access_token = $cached_token;
            return;
        }
        
        $response = wp_remote_post($this->api_url . 'oauth/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account_id,
                'client_secret' => $this->secure_password
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('CDEK API: Failed to get access token - ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            // Cache token for 1 hour (expires in 3600 seconds)
            set_transient($token_cache_key, $this->access_token, 3500);
        }
    }
    
    /**
     * Get pickup points by city
     */
    public function get_pickup_points($city) {
        if (!$this->access_token) {
            return array();
        }
        
        // First, get city code
        $city_code = $this->get_city_code($city);
        if (!$city_code) {
            return array();
        }
        
        $response = wp_remote_get($this->api_url . 'deliverypoints?' . http_build_query(array(
            'city_code' => $city_code,
            'type' => 'PVZ', // Пункты выдачи
            'take_only' => 1 // Только с возможностью получения
        )), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('CDEK API: Failed to get pickup points - ' . $response->get_error_message());
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data) && is_array($data) ? $data : array();
    }
    
    /**
     * Get city code by city name
     */
    public function get_city_code($city_name) {
        $response = wp_remote_get($this->api_url . 'location/cities?' . http_build_query(array(
            'city' => $city_name,
            'size' => 1
        )), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data[0]['code'])) {
            return $data[0]['code'];
        }
        
        return false;
    }
    
    /**
     * Calculate shipping cost
     */
    public function calculate_shipping($from_city_code, $to_city_code, $packages) {
        if (!$this->access_token) {
            return false;
        }
        
        $request_data = array(
            'type' => 1, // Тип заказа (интернет-магазин)
            'from_location' => array(
                'code' => $from_city_code
            ),
            'to_location' => array(
                'code' => $to_city_code
            ),
            'packages' => $packages
        );
        
        $response = wp_remote_post($this->api_url . 'calculator/tarifflist', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Extract city from address
     */
    public static function extract_city_from_address($address) {
        // Simple regex to extract city (можно улучшить)
        $patterns = array(
            '/^([А-Яа-яёЁ\s-]+),/', // Город в начале адреса перед запятой
            '/г\.?\s*([А-Яа-яёЁ\s-]+),/', // г. Название города
            '/город\s+([А-Яа-яёЁ\s-]+),/i', // город Название
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $address, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Fallback - берем первое слово до запятой
        $parts = explode(',', $address);
        if (count($parts) > 1) {
            return trim($parts[0]);
        }
        
        return '';
    }
}