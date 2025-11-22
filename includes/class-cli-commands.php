<?php
/**
 * WP-CLI Commands for SEO Page Monitor
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Manage SEO Page Monitor pages via WP-CLI
 */
class SEO_Monitor_CLI_Commands {
    
    /**
     * List all monitored pages
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor list
     *     wp seo-monitor list --format=json
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args) {
        $pages = get_option('seo_monitor_pages', array());
        
        if (empty($pages)) {
            WP_CLI::warning('No pages are currently being monitored.');
            return;
        }
        
        $items = array();
        foreach ($pages as $index => $page) {
            $items[] = array(
                'ID' => $index,
                'URL' => isset($page['url']) ? $page['url'] : '',
                'Title' => isset($page['title']) ? $page['title'] : '',
                'RankMath Score' => isset($page['rankMathScore']) ? $page['rankMathScore'] : '',
                'Focus Keyword' => isset($page['focusKeyword']) ? $page['focusKeyword'] : '',
            );
        }
        
        WP_CLI\Utils\format_items(
            WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table'),
            $items,
            array('ID', 'URL', 'Title', 'RankMath Score', 'Focus Keyword')
        );
    }
    
    /**
     * Add a new page to monitor
     *
     * ## OPTIONS
     *
     * <url>
     * : The URL of the page to monitor
     *
     * [--fetch]
     * : Automatically fetch page data after adding
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor add https://example.com/page
     *     wp seo-monitor add https://example.com/page --fetch
     *
     * @when after_wp_load
     */
    public function add($args, $assoc_args) {
        $url = $args[0];
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            WP_CLI::error('Invalid URL provided.');
            return;
        }
        
        $pages = get_option('seo_monitor_pages', array());
        
        // Check if URL already exists
        foreach ($pages as $page) {
            if (isset($page['url']) && $page['url'] === $url) {
                WP_CLI::error('This URL is already being monitored.');
                return;
            }
        }
        
        $new_page = array(
            'url' => $url,
            'title' => '',
            'description' => '',
            'focusKeyword' => '',
            'rankMathScore' => '',
            'internalLinks' => '',
            'externalLinks' => '',
            'altImages' => '',
        );
        
        $pages[] = $new_page;
        update_option('seo_monitor_pages', $pages, false);
        
        WP_CLI::success("Page added: $url");
        
        // Fetch data if requested
        if (WP_CLI\Utils\get_flag_value($assoc_args, 'fetch', false)) {
            WP_CLI::line('Fetching page data...');
            $this->fetch(array(count($pages) - 1), array());
        }
    }
    
    /**
     * Fetch data for a monitored page
     *
     * ## OPTIONS
     *
     * <id>
     * : The ID of the page to fetch (from list command)
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor fetch 0
     *
     * @when after_wp_load
     */
    public function fetch($args, $assoc_args) {
        $id = (int)$args[0];
        $pages = get_option('seo_monitor_pages', array());
        
        if (!isset($pages[$id])) {
            WP_CLI::error('Page ID not found.');
            return;
        }
        
        $url = $pages[$id]['url'];
        
        WP_CLI::line("Fetching data for: $url");
        
        // Fetch the page content
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => false,
        ));
        
        if (is_wp_error($response)) {
            WP_CLI::error('Could not fetch page: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $post_id = url_to_postid($url);
        
        // Use the plugin's analyze_seo method
        $monitor = SEO_Page_Monitor::get_instance();
        $reflection = new ReflectionClass($monitor);
        $method = $reflection->getMethod('analyze_seo');
        $method->setAccessible(true);
        $seo_analysis = $method->invoke($monitor, $body, $url, $post_id);
        
        // Update the page data
        $pages[$id] = array_merge($pages[$id], array(
            'title' => $seo_analysis['title'],
            'description' => $seo_analysis['description'],
            'focusKeyword' => $seo_analysis['focusKeyword'],
            'rankMathScore' => $seo_analysis['rankMathScore'],
            'internalLinks' => $seo_analysis['internalLinks'],
            'externalLinks' => $seo_analysis['externalLinks'],
            'altImages' => $seo_analysis['altImages'],
            'seoAnalysis' => $seo_analysis['seoHints'],
            'technicalSeo' => $seo_analysis['technicalSeo'],
        ));
        
        update_option('seo_monitor_pages', $pages, false);
        
        WP_CLI::success('Page data fetched and updated successfully!');
        WP_CLI::line('RankMath Score: ' . $seo_analysis['rankMathScore']);
        WP_CLI::line('Focus Keyword: ' . $seo_analysis['focusKeyword']);
    }
    
    /**
     * Remove a page from monitoring
     *
     * ## OPTIONS
     *
     * <id>
     * : The ID of the page to remove (from list command)
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor remove 0
     *
     * @when after_wp_load
     */
    public function remove($args, $assoc_args) {
        $id = (int)$args[0];
        $pages = get_option('seo_monitor_pages', array());
        
        if (!isset($pages[$id])) {
            WP_CLI::error('Page ID not found.');
            return;
        }
        
        $url = $pages[$id]['url'];
        array_splice($pages, $id, 1);
        update_option('seo_monitor_pages', $pages, false);
        
        WP_CLI::success("Page removed: $url");
    }
    
    /**
     * Run PageSpeed test for a monitored page
     *
     * ## OPTIONS
     *
     * <id>
     * : The ID of the page to test (from list command)
     *
     * [--force]
     * : Force refresh, ignore cache
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor pagespeed 0
     *     wp seo-monitor pagespeed 0 --force
     *
     * @when after_wp_load
     */
    public function pagespeed($args, $assoc_args) {
        $id = (int)$args[0];
        $pages = get_option('seo_monitor_pages', array());
        
        if (!isset($pages[$id])) {
            WP_CLI::error('Page ID not found.');
            return;
        }
        
        $url = $pages[$id]['url'];
        $force = WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
        
        WP_CLI::line("Running PageSpeed test for: $url");
        WP_CLI::line('This may take 30-60 seconds...');
        
        // Check cache first
        if (!$force) {
            $cache_key = 'seo_monitor_pagespeed_' . md5($url);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                WP_CLI::warning('Using cached results (add --force to refresh)');
                WP_CLI::line('Mobile Score: ' . $cached['mobile_score']);
                WP_CLI::line('Desktop Score: ' . $cached['desktop_score']);
                WP_CLI::line('Mobile Report: ' . $cached['mobile_url']);
                WP_CLI::line('Desktop Report: ' . $cached['desktop_url']);
                return;
            }
        }
        
        // Make API request
        $request = new WP_REST_Request('POST', '/seo-monitor/v1/pagespeed');
        $request->set_body_params(array(
            'url' => $url,
            'force_refresh' => $force
        ));
        
        $monitor = SEO_Page_Monitor::get_instance();
        $response = $monitor->run_pagespeed_test($request);
        
        if (is_wp_error($response)) {
            WP_CLI::error($response->get_error_message());
            return;
        }
        
        $data = $response->get_data();
        
        WP_CLI::success('PageSpeed test completed!');
        WP_CLI::line('Mobile Score: ' . $data['mobile_score'] . '/100');
        WP_CLI::line('Desktop Score: ' . $data['desktop_score'] . '/100');
        WP_CLI::line('Mobile Report: ' . $data['mobile_url']);
        WP_CLI::line('Desktop Report: ' . $data['desktop_url']);
        
        if (isset($data['from_cache']) && $data['from_cache']) {
            WP_CLI::line('(Results from cache - expires in ' . $data['cache_expires'] . ')');
        }
    }

    /**
     * Sync pages to Google Sheets
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor sheets sync
     *
     * @when after_wp_load
     */
    public function sheets($args, $assoc_args) {
        $action = isset($args[0]) ? $args[0] : 'sync';

        if ($action === 'sync') {
            $pages = get_option('seo_monitor_pages', array());
            if (empty($pages)) {
                WP_CLI::warning('No pages to sync');
                return;
            }

            $monitor = SEO_Page_Monitor::get_instance();
            if (!isset($monitor->sheets) || !method_exists($monitor->sheets, 'sync_pages')) {
                WP_CLI::error('Google Sheets integration not available or not configured.');
                return;
            }

            WP_CLI::line('Queueing pages and flushing to Google Sheets...');
            $monitor->sheets->sync_pages($pages);
            // flush queue immediately
            $monitor->sheets->flush_queue();
            WP_CLI::success('Sync complete');
        }
        if ($action === 'flush') {
            $monitor = SEO_Page_Monitor::get_instance();
            if (!isset($monitor->sheets) || !method_exists($monitor->sheets, 'flush_queue')) {
                WP_CLI::error('Google Sheets integration not available or not configured.');
                return;
            }

            WP_CLI::line('Flushing queued Google Sheet operations...');
            $monitor->sheets->flush_queue();
            WP_CLI::success('Flush complete');
        }
    }
    
    /**
     * Export monitored pages to JSON file
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Path to export file
     * ---
     * default: seo-monitor-export.json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor export
     *     wp seo-monitor export /path/to/backup.json
     *
     * @when after_wp_load
     */
    public function export($args, $assoc_args) {
        $file = isset($args[0]) ? $args[0] : 'seo-monitor-export.json';
        
        $pages = get_option('seo_monitor_pages', array());
        
        $export_data = array(
            'version' => SEO_MONITOR_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'pages' => $pages,
        );
        
        $json = wp_json_encode($export_data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($file, $json) === false) {
            WP_CLI::error("Could not write to file: $file");
            return;
        }
        
        WP_CLI::success("Exported " . count($pages) . " pages to: $file");
    }
    
    /**
     * Import monitored pages from JSON file
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to import file
     *
     * [--merge]
     * : Merge with existing pages instead of replacing
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor import backup.json
     *     wp seo-monitor import backup.json --merge
     *
     * @when after_wp_load
     */
    public function import($args, $assoc_args) {
        $file = $args[0];
        
        if (!file_exists($file)) {
            WP_CLI::error("File not found: $file");
            return;
        }
        
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error('Invalid JSON file: ' . json_last_error_msg());
            return;
        }
        
        if (!isset($data['pages']) || !is_array($data['pages'])) {
            WP_CLI::error('Invalid export file format.');
            return;
        }
        
        $merge = WP_CLI\Utils\get_flag_value($assoc_args, 'merge', false);
        
        if ($merge) {
            $existing_pages = get_option('seo_monitor_pages', array());
            $all_pages = array_merge($existing_pages, $data['pages']);
            update_option('seo_monitor_pages', $all_pages, false);
            WP_CLI::success('Merged ' . count($data['pages']) . ' pages with existing data.');
        } else {
            update_option('seo_monitor_pages', $data['pages'], false);
            WP_CLI::success('Imported ' . count($data['pages']) . ' pages (replaced existing data).');
        }
    }
    
    /**
     * Clear PageSpeed cache
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor clear-cache
     *
     * @when after_wp_load
     */
    public function clear_cache($args, $assoc_args) {
        global $wpdb;
        
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_seo_monitor_pagespeed_%'"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_seo_monitor_pagespeed_%'"
        );
        
        wp_cache_flush();
        
        WP_CLI::success("Cleared PageSpeed cache (deleted $deleted cached results).");
    }
}

// Register commands
WP_CLI::add_command('seo-monitor', 'SEO_Monitor_CLI_Commands');
