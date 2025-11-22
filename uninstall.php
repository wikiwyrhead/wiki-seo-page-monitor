<?php
/**
 * Uninstall script for SEO Page Monitor & Optimizer
 * 
 * This file is executed when the plugin is uninstalled via WordPress admin
 * 
 * @package SEO_Page_Monitor
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('seo_monitor_pages');
delete_option('seo_monitor_pagespeed_api_key');
delete_option('seo_monitor_version');
// Google Sheets config
delete_option('seo_monitor_google_sheet_id');
delete_option('seo_monitor_google_service_account');
delete_option('seo_monitor_google_sheet_tab');
delete_option('seo_monitor_google_sheet_read_headers');
delete_option('seo_monitor_google_sheet_queue');

// For multisite installations
if (is_multisite()) {
    global $wpdb;
    
    // Get all blog IDs
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Delete options for each site
        delete_option('seo_monitor_pages');
        delete_option('seo_monitor_pagespeed_api_key');
        delete_option('seo_monitor_version');
        
        restore_current_blog();
    }
}

// Clear any cached data
wp_cache_flush();
