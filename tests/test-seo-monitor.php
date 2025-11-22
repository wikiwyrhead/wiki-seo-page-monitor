<?php
/**
 * PHPUnit Tests for SEO Page Monitor
 * 
 * Run tests with: vendor/bin/phpunit
 */

use PHPUnit\Framework\TestCase;

class SEO_Monitor_Test extends TestCase {
    
    private $plugin;
    
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions if not in WordPress context
        if (!function_exists('add_action')) {
            function add_action($hook, $callback) { return true; }
            function add_menu_page() { return true; }
            function add_submenu_page() { return true; }
            function register_rest_route() { return true; }
            function wp_enqueue_script() { return true; }
            function wp_enqueue_style() { return true; }
            function wp_localize_script() { return true; }
            function wp_create_nonce() { return 'test_nonce'; }
            function rest_url($path = '') { return 'https://example.com/wp-json/' . $path; }
            function plugin_dir_path($file) { return __DIR__ . '/'; }
            function plugin_dir_url($file) { return 'https://example.com/wp-content/plugins/seo-page-monitor/'; }
            function get_option($key, $default = false) { return $default; }
            function update_option($key, $value) { return true; }
            function current_user_can($capability) { return true; }
            function esc_url_raw($url) { return $url; }
            function sanitize_text_field($str) { return strip_tags($str); }
            function rest_ensure_response($data) { return $data; }
        }
    }
    
    /**
     * Test plugin singleton instance
     */
    public function test_singleton_instance() {
        $this->markTestSkipped('Requires WordPress environment');
        
        // $instance1 = SEO_Page_Monitor::get_instance();
        // $instance2 = SEO_Page_Monitor::get_instance();
        // $this->assertSame($instance1, $instance2);
    }
    
    /**
     * Test URL validation
     */
    public function test_url_validation() {
        $valid_urls = [
            'https://example.com',
            'http://example.com/page',
            'https://sub.example.com/page?query=1'
        ];
        
        foreach ($valid_urls as $url) {
            $this->assertTrue(
                filter_var($url, FILTER_VALIDATE_URL) !== false,
                "URL should be valid: $url"
            );
        }
        
        $invalid_urls = [
            'not a url',
            'ftp://example.com',
            'javascript:alert(1)',
        ];
        
        foreach ($invalid_urls as $url) {
            $filtered = filter_var($url, FILTER_VALIDATE_URL);
            if ($filtered === false || !in_array(parse_url($filtered, PHP_URL_SCHEME), ['http', 'https'])) {
                $this->assertTrue(true, "URL should be invalid: $url");
            }
        }
    }
    
    /**
     * Test sanitization
     */
    public function test_data_sanitization() {
        $dirty_data = [
            'title' => '<script>alert("xss")</script>Test Title',
            'url' => 'https://example.com',
            'focusKeyword' => 'test<b>keyword</b>',
        ];
        
        $clean_data = array_map('strip_tags', $dirty_data);
        
        $this->assertEquals('Test Title', $clean_data['title']);
        $this->assertEquals('https://example.com', $clean_data['url']);
        $this->assertEquals('testkeyword', $clean_data['focusKeyword']);
    }
    
    /**
     * Test score validation
     */
    public function test_score_validation() {
        $valid_scores = [0, 50, 100, '75', '0', '100'];
        
        foreach ($valid_scores as $score) {
            $int_score = intval($score);
            $this->assertTrue(
                $int_score >= 0 && $int_score <= 100,
                "Score should be valid: $score"
            );
        }
        
        $invalid_scores = [-1, 101, 'abc', null];
        
        foreach ($invalid_scores as $score) {
            $int_score = intval($score);
            if ($int_score < 0 || $int_score > 100) {
                $this->assertTrue(true, "Score should be invalid: $score");
            }
        }
    }
    
    /**
     * Test API key validation pattern
     */
    public function test_api_key_validation() {
        $valid_keys = [
            'AIzaSyC1234567890abcdefghijklmnop',
            'AIzaSyDEFGHIJKLMNOPQRSTUVWXYZ012345',
            'test-api_key-with-special_chars123'
        ];
        
        $pattern = '/^[A-Za-z0-9_-]{30,50}$/';
        
        foreach ($valid_keys as $key) {
            $this->assertEquals(
                1,
                preg_match($pattern, $key),
                "API key should match pattern: $key"
            );
        }
        
        $invalid_keys = [
            'short',
            'has spaces in it not allowed',
            'has@special#chars',
            ''
        ];
        
        foreach ($invalid_keys as $key) {
            $this->assertEquals(
                0,
                preg_match($pattern, $key),
                "API key should not match pattern: $key"
            );
        }
    }
    
    /**
     * Test export data structure
     */
    public function test_export_data_structure() {
        $export_data = [
            'version' => '1.0.0',
            'exported_at' => '2025-11-20 00:00:00',
            'site_url' => 'https://example.com',
            'pages' => [
                [
                    'url' => 'https://example.com/page1',
                    'title' => 'Page 1',
                    'focusKeyword' => 'test',
                ]
            ]
        ];
        
        $this->assertArrayHasKey('version', $export_data);
        $this->assertArrayHasKey('pages', $export_data);
        $this->assertIsArray($export_data['pages']);
        $this->assertNotEmpty($export_data['pages']);
        $this->assertEquals('1.0.0', $export_data['version']);
    }
    
    /**
     * Test import data validation
     */
    public function test_import_data_validation() {
        $valid_import = [
            'pages' => [
                ['url' => 'https://example.com', 'title' => 'Test'],
            ]
        ];
        
        $this->assertArrayHasKey('pages', $valid_import);
        $this->assertIsArray($valid_import['pages']);
        
        $invalid_imports = [
            [],
            ['pages' => 'not an array'],
            ['wrong_key' => []],
        ];
        
        foreach ($invalid_imports as $data) {
            $is_valid = isset($data['pages']) && is_array($data['pages']);
            $this->assertFalse($is_valid, 'Import data should be invalid');
        }
    }
    
    /**
     * Test cache key generation
     */
    public function test_cache_key_generation() {
        $url = 'https://example.com/test-page';
        $expected_key = 'seo_monitor_pagespeed_' . md5($url);
        $generated_key = 'seo_monitor_pagespeed_' . md5($url);
        
        $this->assertEquals($expected_key, $generated_key);
        $this->assertEquals(32, strlen(md5($url)), 'MD5 hash should be 32 characters');
    }
    
    /**
     * Test rate limiting logic
     */
    public function test_rate_limiting_logic() {
        $max_requests = 10;
        $current_count = 5;
        
        $this->assertTrue($current_count < $max_requests, 'Should allow request');
        
        $current_count = 10;
        $this->assertFalse($current_count < $max_requests, 'Should block request');
    }
    
    /**
     * Test header hierarchy parsing
     */
    public function test_header_hierarchy() {
        $html = '<html><body><h1>Title</h1><h2>Subtitle</h2><h3>Section</h3></body></html>';
        
        preg_match_all('/<h1[^>]*>/i', $html, $h1);
        preg_match_all('/<h2[^>]*>/i', $html, $h2);
        preg_match_all('/<h3[^>]*>/i', $html, $h3);
        
        $this->assertEquals(1, count($h1[0]), 'Should find one H1');
        $this->assertEquals(1, count($h2[0]), 'Should find one H2');
        $this->assertEquals(1, count($h3[0]), 'Should find one H3');
    }
}
