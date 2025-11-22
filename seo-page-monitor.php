<?php
/**
 * Plugin Name: SEO Page Monitor & Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/wiki-seo-page-monitor
 * Description: Track and monitor SEO rankings, PageSpeed scores, and optimization tasks for your pages
 * Version: 1.0.0
 * Author: arnelG
 * Author URI: https://github.com/wikiwyrhead
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-page-monitor
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SEO_MONITOR_VERSION', '1.0.0');
define('SEO_MONITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEO_MONITOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader if available
if (file_exists(SEO_MONITOR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SEO_MONITOR_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 */
class SEO_Page_Monitor {
        /** @var SEO_Monitor_Google_Sheets|null */
        public $sheets = null;
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Load WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            require_once SEO_MONITOR_PLUGIN_DIR . 'includes/class-cli-commands.php';
        }

        // Load Google Sheets integration scaffold
        require_once SEO_MONITOR_PLUGIN_DIR . 'includes/class-google-sheets.php';
        $this->sheets = new SEO_Monitor_Google_Sheets($this);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('SEO Monitor', 'seo-page-monitor'),
            __('SEO Monitor', 'seo-page-monitor'),
            'manage_options',
            'seo-page-monitor',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'seo-page-monitor',
            __('Settings', 'seo-page-monitor'),
            __('Settings', 'seo-page-monitor'),
            'manage_options',
            'seo-page-monitor-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <div id="seo-monitor-root"></div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'seo-page-monitor'));
        }
        
        // Handle form submission
        if (isset($_POST['seo_monitor_save_settings']) && check_admin_referer('seo_monitor_settings')) {
            $api_key = sanitize_text_field($_POST['pagespeed_api_key']);
            
            // Validate API key format (basic check)
            if (!empty($api_key) && !preg_match('/^[A-Za-z0-9_-]{30,50}$/', $api_key)) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid API key format. Please check and try again.', 'seo-page-monitor') . '</p></div>';
            } else {
                update_option('seo_monitor_pagespeed_api_key', $api_key, false);
                // Save google sheet configuration
                $sheet_id = isset($_POST['seo_monitor_google_sheet_id']) ? sanitize_text_field($_POST['seo_monitor_google_sheet_id']) : '';
                update_option('seo_monitor_google_sheet_id', $sheet_id, false);

                // Service account JSON can be pasted; store base64-encoded (optional)
                $sa_json = isset($_POST['seo_monitor_google_service_account']) ? trim($_POST['seo_monitor_google_service_account']) : '';
                if (!empty($sa_json)) {
                    // Validate JSON
                    $decoded = json_decode($sa_json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Store base64 to prevent DB issues with quotes
                        $encoded = base64_encode($sa_json);
                        update_option('seo_monitor_google_service_account', $encoded, false);
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid Google Service Account JSON. Please validate and try again.', 'seo-page-monitor') . '</p></div>';
                    }
                }
                $sheet_tab = isset($_POST['seo_monitor_google_sheet_tab']) ? sanitize_text_field($_POST['seo_monitor_google_sheet_tab']) : 'Sheet1';
                update_option('seo_monitor_google_sheet_tab', $sheet_tab, false);

                $read_headers = isset($_POST['seo_monitor_google_sheet_read_headers']) ? true : false;
                update_option('seo_monitor_google_sheet_read_headers', $read_headers, false);
                // If user clicked flush, run the queue now
                if (isset($_POST['seo_monitor_flush_sheets']) && current_user_can('manage_options')) {
                    if ($this->sheets && method_exists($this->sheets, 'flush_queue')) {
                        $this->sheets->flush_queue();
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Sheet queue flushed.', 'seo-page-monitor') . '</p></div>';
                    }
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'seo-page-monitor') . '</p></div>';
            }
        }
        
        $api_key = get_option('seo_monitor_pagespeed_api_key', '');
        $google_sheet_id = get_option('seo_monitor_google_sheet_id', '');
        $google_sa = get_option('seo_monitor_google_service_account', '');
        $google_sheet_tab = get_option('seo_monitor_google_sheet_tab', 'Sheet1');
        $google_read_headers = get_option('seo_monitor_google_sheet_read_headers', false);
        if (!empty($google_sa)) {
            // show decoded length but not raw json
            $google_sa_preview = '[stored]';
        } else {
            $google_sa_preview = '';
        }

        // Composer check for Google client
        $vendor_installed = file_exists(SEO_MONITOR_PLUGIN_DIR . 'vendor/autoload.php');
        ?>
        <div class="wrap seo-monitor-settings">
            <div class="seo-monitor-container">
                    <h1 class="seo-monitor-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <div class="seo-monitor-layout">
                        <main class="seo-monitor-main">
                            <?php
                            $sheets_ready = false;
                            if (isset($this->sheets) && method_exists($this->sheets, 'is_configured')) {
                                $sheets_ready = $this->sheets->is_configured();
                            }
                            $sa_present = !empty($google_sa)
                                || (defined('SEO_MONITOR_GOOGLE_SA_JSON') && !empty(SEO_MONITOR_GOOGLE_SA_JSON))
                                || (defined('SEO_MONITOR_GOOGLE_SA_FILE') && file_exists(SEO_MONITOR_GOOGLE_SA_FILE));

                            if (empty($google_sheet_id) || !$sa_present) {
                                echo '<div class="notice notice-warning is-dismissible" style="margin-top: 12px;"><p>'
                                    . esc_html__('Google Sheets not fully configured. Please set Spreadsheet ID and Service Account JSON (or define constants).', 'seo-page-monitor')
                                    . '</p></div>';
                            } elseif (!$sheets_ready) {
                                if (!class_exists('\\Google_Client') || !class_exists('\\Google_Service_Sheets')) {
                                    echo '<div class="notice notice-warning is-dismissible" style="margin-top: 12px;"><p>'
                                        . esc_html__('Google PHP Client is not installed. Run composer require google/apiclient in the plugin folder.', 'seo-page-monitor')
                                        . '</p></div>';
                                }
                            } else {
                                echo '<div class="notice notice-success is-dismissible" style="margin-top: 12px;"><p>'
                                    . esc_html__('Google Sheets integration is ready.', 'seo-page-monitor')
                                    . '</p></div>';
                            }
                            ?>
                            <div class="card seo-monitor-card" style="padding: 20px; margin-top: 20px;">
                                <h2>🚀 PageSpeed API Configuration</h2>

                                <form method="post" action="">
                                    <?php wp_nonce_field('seo_monitor_settings'); ?>
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="pagespeed_api_key">Google PageSpeed API Key</label>
                                            </th>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:8px;max-width:100%;">
                                                    <input type="password" id="pagespeed_api_key" name="pagespeed_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="AIzaSyC1234567890abcdefghijklmnop" style="flex:1;" />
                                                    <button type="button" id="pagespeed_api_key_toggle" class="password-toggle button">Show</button>
                                                </div>
                                                <p class="description">
                                                    Enter your Google PageSpeed Insights API key for unlimited testing (25,000 requests/day free).
                                                    <?php if (empty($api_key)): ?>
                                                        <br><strong style="color: #d63638;">⚠️ No API key configured - limited to ~100 tests per day.</strong>
                                                    <?php else: ?>
                                                        <br><strong style="color: #00a32a;">✅ API key configured</strong>
                                                    <?php endif; ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                            </div>

                            <div class="card seo-monitor-card" style="padding: 20px; margin-top: 20px;">
                                <h2>📄 Google Sheets Integration</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="seo_monitor_google_sheet_id">Google Spreadsheet ID</label>
                                        </th>
                                        <td>
                                            <input type="text" id="seo_monitor_google_sheet_id" name="seo_monitor_google_sheet_id" value="<?php echo esc_attr($google_sheet_id); ?>" class="regular-text" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" />
                                            <p class="description">Enter your Google Spreadsheet ID. You can find this ID in the sheet URL between <code>/d/</code> and <code>/edit</code>. The sheet must be shared with the Service Account email (Editor access recommended).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="seo_monitor_google_sheet_tab">Spreadsheet Tab (sheet name)</label>
                                        </th>
                                        <td>
                                            <input type="text" id="seo_monitor_google_sheet_tab" name="seo_monitor_google_sheet_tab" value="<?php echo esc_attr($google_sheet_tab); ?>" class="regular-text" />
                                            <p class="description">Tab name within the spreadsheet (default: Sheet1)</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Read headers instead of using column order</th>
                                        <td>
                                            <label for="seo_monitor_google_sheet_read_headers">
                                                <input type="checkbox" id="seo_monitor_google_sheet_read_headers" name="seo_monitor_google_sheet_read_headers" value="1" <?php checked($google_read_headers, true); ?> />
                                                Read header row to dynamically map columns
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="card seo-monitor-card" style="padding: 20px; margin-top: 20px;">
                                <h2>🔐 Service Account & Credentials</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="seo_monitor_google_service_account">Google Service Account JSON</label>
                                        </th>
                                        <td>
                                            <textarea id="seo_monitor_google_service_account" name="seo_monitor_google_service_account" rows="6" cols="60" class="regular-text code"><?php echo $google_sa_preview; ?></textarea>
                                            <p class="description">Paste your Google Service Account JSON here (or set the <code>SEO_MONITOR_GOOGLE_SA_JSON</code> constant). We recommend base64-encoding the JSON before pasting.</p>

                                            <div style="margin-top:12px;">
                                                <h4 style="margin:6px 0;">What this is for</h4>
                                                <p style="margin:6px 0 0 0;">The Service Account JSON gives this plugin programmatic access to your Google Sheets so it can read and write PageSpeed results and queued data. The plugin uses the Google Sheets API via the service account credentials to append rows, read headers, and flush queued entries.</p>

                                                <h4 style="margin:12px 0 6px 0;">How to create and connect a Service Account</h4>
                                                <ol style="margin:0 0 8px 18px; padding:0;">
                                                    <li>Create a Service Account in the Google Cloud Console: <em>IAM &amp; Admin → Service Accounts</em> → <strong>Create Service Account</strong>.</li>
                                                    <li>Grant the service account minimal roles (e.g. <code>Editor</code> on the specific spreadsheet or <code>Viewer</code>/<code>Editor</code> as needed). For Sheets-only access, the <code>roles/iam.serviceAccountUser</code> role is not required — the important part is sharing the spreadsheet below.</li>
                                                    <li>Generate a JSON key for the service account: choose <strong>Create Key → JSON</strong> and download the file.</li>
                                                    <li>Open your spreadsheet and share it with the service account's email (the one that ends with <code>@<your-project>.iam.gserviceaccount.com</code>) with Editor access.</li>
                                                    <li>Paste the JSON contents into the field above (or base64-encode it and paste the encoded string). Click <em>Save Settings</em>.</li>
                                                </ol>

                                                <h4 style="margin:12px 0 6px 0;">Where to download the JSON key (exact steps)</h4>
                                                <p style="margin:6px 0 0 0;">Open the Service Accounts page in the Google Cloud Console:</p>
                                                <p style="margin:6px 0 0 0;"><a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener">https://console.cloud.google.com/iam-admin/serviceaccounts</a></p>
                                                <ol style="margin:6px 0 8px 18px;">
                                                    <li>Find and click the service account you created.</li>
                                                    <li>Click the <strong>Keys</strong> tab.</li>
                                                    <li>Click <strong>Add Key → Create new key</strong>.</li>
                                                    <li>Select <strong>JSON</strong> and click <strong>Create</strong>. A JSON file will be downloaded to your computer.</li>
                                                </ol>
                                                <p style="margin:6px 0 0 0;">The downloaded JSON contains the credentials the plugin needs. It will include fields like <code>type</code>, <code>project_id</code>, <code>client_email</code>, and a <code>private_key</code> block. Example (truncated):</p>
                                                <pre style="background:#f6f8fa;padding:8px;border-radius:4px;overflow:auto;margin-top:8px;">
{ "type": "service_account", "project_id": "my-gcloud-project", "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEv...\n-----END PRIVATE KEY-----\n", "client_email": "my-service@my-gcloud-project.iam.gserviceaccount.com" }
                                                </pre>
                                                <p style="margin:6px 0 0 0;">Do not share the private key file. Paste its contents only into this admin field or store it securely (base64 is recommended if saving to the DB).</p>

                                                <h4 style="margin:12px 0 6px 0;">Security & best practices</h4>
                                                <ul style="margin:0 0 8px 18px; padding:0;">
                                                    <li>Store minimal, dedicated credentials in a separate Google project to isolate quota and billing.</li>
                                                    <li>Prefer base64-encoding the JSON before saving to the DB to avoid issues with quotes—this plugin already recommends that approach.</li>
                                                    <li>If you prefer not to store credentials in the database, define the JSON as a constant in <code>wp-config.php</code> (e.g. <code>SEO_MONITOR_GOOGLE_SA_JSON</code>) or use a server-side secrets manager and set the decoded JSON at runtime.</li>
                                                    <li>Rotate keys periodically and remove unused service account keys from the Cloud Console.</li>
                                                </ul>

                                                <h4 style="margin:12px 0 6px 0;">Quick test</h4>
                                                <p style="margin:6px 0 0 0;">After saving credentials and sharing the sheet, try flushing the sheet queue or run a small sync to confirm the plugin can write rows. If you see permission errors, double-check the spreadsheet share settings and ensure the service account email has Editor access.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p class="submit">
                                <input type="submit" name="seo_monitor_save_settings" class="button button-primary" value="Save Settings">
                                <input type="submit" name="seo_monitor_flush_sheets" class="button button-secondary" value="Flush Sheet Queue">
                                <?php $queue_count = count(get_option('seo_monitor_google_sheet_queue', array())); ?>
                                <?php if ($queue_count > 0): ?>
                                    <span style="margin-left: 12px; font-weight: bold;">Queued: <?php echo esc_html($queue_count); ?></span>
                                <?php endif; ?>
                            </p>

                            <script>
                            document.addEventListener('DOMContentLoaded', function(){
                                var input = document.getElementById('pagespeed_api_key');
                                var btn = document.getElementById('pagespeed_api_key_toggle');
                                if (!input || !btn) return;
                                btn.addEventListener('click', function(e){
                                    e.preventDefault();
                                    if (input.type === 'password') {
                                        input.type = 'text';
                                        btn.textContent = 'Hide';
                                    } else {
                                        input.type = 'password';
                                        btn.textContent = 'Show';
                                    }
                                });
                            });
                            </script>

                            <?php if (!file_exists(SEO_MONITOR_PLUGIN_DIR . 'vendor/autoload.php') && !empty($google_sheet_id)): ?>
                                <div class="notice notice-warning is-dismissible" style="margin-top: 20px;">
                                    <p><?php echo esc_html__('Google PHP Client is required to sync with Sheets. Run `composer require google/apiclient` from the plugin folder.', 'seo-page-monitor'); ?></p>
                                </div>
                            <?php endif; ?>

                            <div style="background: #fcf8e3; border-left: 4px solid #f0ad4e; padding: 15px; margin: 20px 0;">
                                <h4 style="margin-top: 0;">⚠️ Troubleshooting</h4>
                                <ul style="margin-bottom: 0;">
                                    <li><strong>Error: Quota exceeded</strong> - You need to add your API key or wait 24 hours</li>
                                    <li><strong>Error: API key not valid</strong> - Make sure you enabled PageSpeed Insights API for your project</li>
                                    <li><strong>Error: Permission denied</strong> - Check that API restrictions allow PageSpeed Insights API</li>
                                </ul>
                            </div>

                        </main>

                        <aside class="seo-monitor-sidebar">
                            <div class="card seo-monitor-card seo-monitor-api-instructions seo-monitor-api-instructions--compact" style="margin-top: 18px;">
                                <h3 class="seo-monitor-title">📘 Get Your Free Google PageSpeed API Key</h3>
                                <div class="seo-monitor-api-instructions-columns">
                                    <div class="seo-monitor-api-instructions-col">
                                        <ol style="margin:0 0 8px 0; padding-left:18px; line-height:1.6;">
                                            <li><strong>Create a Google Cloud project</strong> — open the Google Cloud Console and create (or select) a project to hold your API credentials.</li>
                                            <li><strong>Enable the PageSpeed Insights API</strong> — go to <code>APIs & Services → Library</code>, search for <em>PageSpeed Insights API</em> and click <strong>Enable</strong>.</li>
                                            <li><strong>Create an API key</strong> — open <code>APIs & Services → Credentials</code> and choose <strong>Create Credentials → API key</strong>. Copy the generated key.</li>
                                            <li><strong>Restrict the key</strong> — click the new key, under <em>API restrictions</em> select <strong>Restrict key</strong> and add <em>PageSpeed Insights API</em>. Optionally add Application restrictions (HTTP referrers for your admin domain or specific IPs) to reduce abuse.</li>
                                            <li><strong>Test the key</strong> — verify it works by calling the Pagespeed endpoint (example):<br><code>curl "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://example.com&strategy=mobile&key=YOUR_KEY"</code></li>
                                            <li><strong>Add it to the plugin</strong> — paste the key into the <em>PageSpeed API Key</em> field above and click <em>Save Settings</em>. You may also define a constant in <code>wp-config.php</code> named <code>GOOGLE_PAGESPEED_API_KEY</code> for site-wide configuration.</li>
                                        </ol>

                                        <div style="margin-top:8px;">
                                            <strong>Notes & Tips:</strong>
                                            <ul style="margin:6px 0 0 18px;">
                                                <li>Monitor your usage and quotas in the Google Cloud Console — high-volume testing may require enabling billing.</li>
                                                <li>Restrict the key by API and referrer to prevent unauthorized use.</li>
                                                <li>If you plan many automated tests, consider a dedicated project and separate key to isolate quota usage.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card seo-monitor-card seo-monitor-support" style="padding: 20px; margin-top: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <h2 style="color: white; margin-top: 0;">💝 Support This Plugin</h2>
                                <div style="line-height: 1.8;">
                                    <p style="font-size: 16px;">If you find this plugin helpful, please consider supporting the developer! Your contribution helps maintain and improve this plugin.</p>
                                    <div style="background: rgba(255,255,255,0.15); border-radius: 8px; padding: 20px; margin: 20px 0;">
                                        <h3 style="color: white; margin-top: 0;">☕ Buy Me a Coffee</h3>
                                        <p style="margin-bottom: 15px;">Every donation, no matter how small, helps keep this plugin free and actively maintained.</p>
                                        <a href="https://www.paypal.me/arnelborresgo" target="_blank" rel="noopener" style="display: inline-block; background: #00457C; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">💳 Donate via PayPal</a>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.15); border-radius: 8px; padding: 20px; margin: 20px 0;">
                                        <h3 style="color: white; margin-top: 0;">⭐ Star on GitHub</h3>
                                        <p style="margin-bottom: 12px;">Show your support by starring the repository! It helps others discover this plugin and motivates continued development.</p>
                                        <p style="margin:0;">
                                            <a href="https://github.com/wikiwyrhead/wiki-seo-page-monitor" target="_blank" rel="noopener noreferrer" aria-label="Star the SEO Page Monitor repo on GitHub" style="display:inline-block;background:#24292e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:700;box-shadow:0 2px 0 rgba(0,0,0,0.06);">⭐ Star on GitHub</a>
                                        </p>
                                    </div>
                                    <p style="text-align: center; margin-top: 30px; font-size: 18px; font-weight: bold;">🙏 Thank you for using SEO Page Monitor!</p>
                                </div>
                            </div>
                        </aside>
                    </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages (main app + settings)
        // Use a relaxed check to allow both top-level and submenu pages.
        if (strpos($hook, 'seo-page-monitor') === false) {
            return;
        }
        
        // Enqueue React and ReactDOM from CDN
        wp_enqueue_script(
            'react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            array(),
            '18.0.0',
            true
        );
        
        wp_enqueue_script(
            'react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            array('react'),
            '18.0.0',
            true
        );
        
        // Enqueue Tailwind CSS
        wp_enqueue_style(
            'tailwind-css',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            array(),
            '2.2.19'
        );
        
        // Enqueue our plugin script
        wp_enqueue_script(
            'seo-monitor-app',
            SEO_MONITOR_PLUGIN_URL . 'assets/js/app.js',
            array('react', 'react-dom'),
            SEO_MONITOR_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script(
            'seo-monitor-app',
            'seoMonitorData',
            array(
                'restUrl' => rest_url('seo-monitor/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'apiUrl' => rest_url(),
                'adminUrl' => admin_url(),
            )
        );
        
        // Enqueue plugin styles
        wp_enqueue_style(
            'seo-monitor-styles',
            SEO_MONITOR_PLUGIN_URL . 'assets/css/style.css',
            array(),
            SEO_MONITOR_VERSION
        );
        // Add an inline comment to help detect whether our plugin assets were loaded.
        wp_add_inline_style('seo-monitor-styles', '/* seo-monitor-styles loaded: ' . esc_js(SEO_MONITOR_VERSION) . ' */');
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('seo-monitor/v1', '/pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/pages', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_pages'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/page/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_page'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/page/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_page'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/fetch-page', array(
            'methods' => 'POST',
            'callback' => array($this, 'fetch_page_data'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/debug-meta', array(
            'methods' => 'POST',
            'callback' => array($this, 'debug_post_meta'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/pagespeed', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_pagespeed_test'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_pages'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        register_rest_route('seo-monitor/v1', '/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_pages'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    /**
     * Check REST API permissions
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get pages data
     */
    public function get_pages($request) {
        $pages = get_option('seo_monitor_pages', array());
        return rest_ensure_response($pages);
    }
    
    /**
     * Save all pages data
     */
    public function save_pages($request) {
        $pages = $request->get_json_params();
        
        // Validate and sanitize pages data
        if (!is_array($pages)) {
            return new WP_Error('invalid_data', 'Invalid pages data', array('status' => 400));
        }
        
        // Sanitize each page entry while preserving arrays for complex fields
        $sanitized_pages = array();
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $sanitized = array();
            // Scalars
            $scalars = array(
                'url', 'title', 'description', 'focusKeyword', 'rankMathScore',
                'internalLinks', 'externalLinks', 'altImages', 'priority', 'status'
            );
            foreach ($scalars as $key) {
                if (isset($page[$key])) {
                    $sanitized[$key] = sanitize_text_field($page[$key]);
                }
            }
            // Arrays we want to keep
            $array_keys = array('seoAnalysis', 'technicalSeo', 'recommendations', 'nextActions');
            foreach ($array_keys as $key) {
                if (isset($page[$key])) {
                    if (is_array($page[$key])) {
                        // Deep sanitize strings within arrays, preserve structure
                        $sanitized[$key] = wp_kses_post_deep($page[$key]);
                    } else {
                        $sanitized[$key] = sanitize_text_field($page[$key]);
                    }
                }
            }
            $sanitized_pages[] = $sanitized;
        }
        
        update_option('seo_monitor_pages', $sanitized_pages, false);
        do_action('seo_monitor_pages_saved', $sanitized_pages);
        return rest_ensure_response(array('success' => true, 'pages' => $sanitized_pages));
    }
    
    /**
     * Update a single page
     */
    public function update_page($request) {
        $pages = get_option('seo_monitor_pages', array());
        $id = $request->get_param('id');
        $page_data = $request->get_json_params();
        
        if (isset($pages[$id])) {
            $pages[$id] = array_merge($pages[$id], $page_data);
            update_option('seo_monitor_pages', $pages);
            do_action('seo_monitor_page_updated', $pages[$id], $id);
            return rest_ensure_response(array('success' => true, 'page' => $pages[$id]));
        }
        
        return new WP_Error('not_found', 'Page not found', array('status' => 404));
    }
    
    /**
     * Delete a page
     */
    public function delete_page($request) {
        $pages = get_option('seo_monitor_pages', array());
        $id = $request->get_param('id');
        
        if (isset($pages[$id])) {
            $deleted_page = $pages[$id];
            array_splice($pages, $id, 1);
            update_option('seo_monitor_pages', $pages);
            do_action('seo_monitor_page_deleted', $id, $deleted_page);
            return rest_ensure_response(array('success' => true));
        }
        
        return new WP_Error('not_found', 'Page not found', array('status' => 404));
    }
    
    /**
     * Fetch page data from URL
     */
    public function fetch_page_data($request) {
        $params = $request->get_json_params();
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
        
        if (empty($url)) {
            return new WP_Error('invalid_url', 'Invalid URL provided', array('status' => 400));
        }
        
        // Check if it's a local URL
        $is_local = (strpos($url, 'http://localhost') === 0 || 
                     strpos($url, 'https://localhost') === 0 ||
                     strpos($url, '.local') !== false ||
                     strpos($url, '127.0.0.1') !== false);
        
        // Fetch the page content
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            // Secure by default; allow dev override via constant
            'sslverify' => defined('SEO_MONITOR_DEV_MODE') ? false : true,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ),
            'reject_unsafe_urls' => defined('SEO_MONITOR_DEV_MODE') ? false : true,
        ));
        
        if (is_wp_error($response)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Could not fetch page: ' . $response->get_error_message()
            ));
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Try to get WordPress post ID from the URL
        $post_id = url_to_postid($url);
        
        // If url_to_postid fails, try alternative methods
        if ($post_id === 0) {
            // Try to extract slug from URL and query by name
            $path = parse_url($url, PHP_URL_PATH);
            $slug = trim($path, '/');
            
            // Remove trailing slashes and get the last segment
            $parts = explode('/', $slug);
            $slug = end($parts);
            
            if (!empty($slug)) {
                // Try to find post by slug
                $post = get_page_by_path($slug, OBJECT, array('post', 'page', 'product'));
                if ($post) {
                    $post_id = $post->ID;
                } else {
                    // Try custom query
                    $args = array(
                        'name' => $slug,
                        'post_type' => array('post', 'page', 'product', 'any'),
                        'post_status' => 'publish',
                        'posts_per_page' => 1
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        $post_id = $query->posts[0]->ID;
                    }
                }
            }
        }
        
        // Get RankMath data directly if post exists
        $rank_math_score = '';
        $rank_math_keyword = '';
        
        if ($post_id > 0) {
            // Get RankMath SEO score directly
            $rank_math_score = get_post_meta($post_id, 'rank_math_seo_score', true);
            
            // Get focus keyword
            $rank_math_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            
            // If RankMath plugin is active, try to get data from its functions
            if (class_exists('RankMath')) {
                try {
                    $rm_score = \RankMath\Post::get_meta('seo_score', $post_id);
                    if (!empty($rm_score) && empty($rank_math_score)) {
                        $rank_math_score = $rm_score;
                    }
                    
                    $rm_keyword = \RankMath\Post::get_meta('focus_keyword', $post_id);
                    if (empty($rank_math_keyword) && !empty($rm_keyword)) {
                        $rank_math_keyword = $rm_keyword;
                    }
                } catch (Exception $e) {
                    // Silent fail
                }
            }
        }
        
        // Parse the HTML
        $seo_analysis = $this->analyze_seo($body, $url, $post_id);
        
        // Override with direct RankMath data if available
        if (!empty($rank_math_score)) {
            $seo_analysis['rankMathScore'] = $rank_math_score;
        }
        if (!empty($rank_math_keyword)) {
            $seo_analysis['focusKeyword'] = $rank_math_keyword;
        }
        
        $data = array(
            'success' => true,
            'title' => $seo_analysis['title'],
            'description' => $seo_analysis['description'],
            'focusKeyword' => $seo_analysis['focusKeyword'],
            'rankMathScore' => $seo_analysis['rankMathScore'],
            'internalLinks' => $seo_analysis['internalLinks'],
            'externalLinks' => $seo_analysis['externalLinks'],
            'altImages' => $seo_analysis['altImages'],
            'seoAnalysis' => $seo_analysis['seoHints'],
            'technicalSeo' => $seo_analysis['technicalSeo'],
            'postId' => $post_id,
            'recommendations' => $seo_analysis['recommendations'],
        );

        do_action('seo_monitor_page_fetched', $data);
        
        return rest_ensure_response($data);
    }
    
    /**
     * Extract page title
     */
    private function extract_title($html, $post_id = 0) {
        // First, try to get the actual post title if we have a post ID
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && !empty($post->post_title)) {
                return $post->post_title;
            }
        }
        
        // Try to extract H1 as it's usually the page title
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            $h1_title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8');
            if (!empty(trim($h1_title))) {
                return trim($h1_title);
            }
        }
        
        // Try Open Graph title (often the clean page title)
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }
        
        // Fall back to <title> tag but clean it up (remove site name)
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            // Remove common separators and site name (take first part before | or -)
            $title = preg_split('/[\|\-–—]/', $title);
            return trim($title[0]);
        }
        
        return '';
    }
    
    /**
     * Extract meta description
     */
    private function extract_meta_description($html) {
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }
        return '';
    }
    
    /**
     * Extract RankMath focus keyword
     */
    private function extract_rankmath_keyword($html, $post_id = 0) {
        // Try to get from post meta if we have a post ID (handled in main function)
        if ($post_id > 0 && function_exists('get_post_meta')) {
            $keyword_keys = array(
                'rank_math_focus_keyword',
                '_rank_math_focus_keyword',
            );
            
            foreach ($keyword_keys as $key) {
                $focus_keyword = get_post_meta($post_id, $key, true);
                if (!empty($focus_keyword)) {
                    return $focus_keyword;
                }
            }
        }
        
        // RankMath stores focus keyword in meta tag
        if (preg_match('/<meta\s+name=["\']rank_math_focus_keyword["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Check RankMath JSON in page
        if (preg_match('/["\']focus_keyword["\']\s*:\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return trim($matches[1]);
        }
        
        // Check for keywords meta tag as fallback
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            $keywords = explode(',', $matches[1]);
            return trim($keywords[0]);
        }
        
        // Try to extract from page title or H1 as last resort
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            $h1_text = strip_tags($matches[1]);
            // Get first 2-3 words as potential keyword
            $words = explode(' ', $h1_text);
            if (count($words) >= 2) {
                return trim($words[0] . ' ' . $words[1]);
            }
        }
        
        return '';
    }
    
    /**
     * Extract RankMath score
     */
    private function extract_rankmath_score($html, $post_id = 0) {
        // Try to get from post meta if we have a post ID
        if ($post_id > 0 && function_exists('get_post_meta')) {
            $seo_score = get_post_meta($post_id, 'rank_math_seo_score', true);
            if (!empty($seo_score)) {
                return $seo_score;
            }
            
            // Also try internal_link_count as alternative
            $internal_score = get_post_meta($post_id, 'rank_math_internal_links_processed', true);
        }
        
        // Try multiple patterns in the HTML
        $patterns = array(
            '/rank-math-score[\'"]?\s*[:\=]\s*[\'"]?(\d+)/i',
            '/rank_math_seo_score[\'"]?\s*[:\=]\s*[\'"]?(\d+)/i',
            '/data-score[\'"]?\s*[:\=]\s*[\'"]?(\d+)/i',
            '/"seo_score":\s*(\d+)/i',
            '/seoScore[\'"]?\s*[:\=]\s*[\'"]?(\d+)/i',
            '/rankmath.*?score.*?(\d+)/i',
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $score = intval($matches[1]);
                // RankMath scores are typically 0-100
                if ($score >= 0 && $score <= 100) {
                    return (string)$score;
                }
            }
        }
        
        // Try to calculate a basic score based on SEO elements present
        $score_elements = 0;
        $max_elements = 10;
        
        // Check for various SEO elements
        if (preg_match('/<title[^>]*>.+<\/title>/is', $html)) $score_elements++;
        if (preg_match('/<meta\s+name=["\']description/is', $html)) $score_elements++;
        if (preg_match('/<link\s+rel=["\']canonical/is', $html)) $score_elements++;
        if (preg_match('/<h1[^>]*>/is', $html)) $score_elements++;
        if (preg_match('/<meta\s+property=["\']og:/is', $html)) $score_elements++;
        if (preg_match('/"@type":/i', $html)) $score_elements++;
        if (preg_match('/<img[^>]+alt=/i', $html)) $score_elements++;
        if (preg_match('/https:/', $html)) $score_elements++;
        
        $word_count = $this->count_words($html);
        if ($word_count > 300) $score_elements++;
        if ($word_count > 1000) $score_elements++;
        
        if ($score_elements > 0) {
            $calculated_score = round(($score_elements / $max_elements) * 100);
            return $calculated_score . ' (estimated)';
        }
        
        return '';
    }
    
    /**
     * Count internal links
     */
    private function count_internal_links($html, $base_url) {
        $domain = parse_url($base_url, PHP_URL_HOST);
        preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $html, $matches);
        
        $internal_count = 0;
        foreach ($matches[1] as $href) {
            $href_domain = parse_url($href, PHP_URL_HOST);
            if (empty($href_domain) || $href_domain === $domain) {
                $internal_count++;
            }
        }
        
        return (string)$internal_count;
    }
    
    /**
     * Count external links
     */
    private function count_external_links($html, $base_url) {
        $domain = parse_url($base_url, PHP_URL_HOST);
        preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $html, $matches);
        
        $external_count = 0;
        foreach ($matches[1] as $href) {
            if (strpos($href, 'http') === 0) {
                $href_domain = parse_url($href, PHP_URL_HOST);
                if (!empty($href_domain) && $href_domain !== $domain) {
                    $external_count++;
                }
            }
        }
        
        return (string)$external_count;
    }
    
    /**
     * Check alt images
     */
    private function check_alt_images($html) {
        preg_match_all('/<img\s+[^>]*>/is', $html, $img_matches);
        $total_images = count($img_matches[0]);
        
        if ($total_images === 0) {
            return 'No images';
        }
        
        $missing_alt = 0;
        foreach ($img_matches[0] as $img_tag) {
            if (!preg_match('/alt=["\'][^"\']*["\']/i', $img_tag)) {
                $missing_alt++;
            } elseif (preg_match('/alt=["\']\s*["\']/i', $img_tag)) {
                $missing_alt++;
            }
        }
        
        if ($missing_alt === 0) {
            return 'Complete';
        }
        
        return 'Missing ' . $missing_alt;
    }
    
    /**
     * Extract H1 tag
     */
    private function extract_h1($html) {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8');
        }
        return '';
    }
    
    /**
     * Count words in content
     */
    private function count_words($html) {
        // Remove scripts, styles, and HTML tags
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = strip_tags($text);
        
        // Count words
        $word_count = str_word_count($text);
        
        return (string)$word_count;
    }
    
    /**
     * Comprehensive SEO Analysis
     */
    private function analyze_seo($html, $url, $post_id = 0) {
        $analysis = array(
            'title' => $this->extract_title($html, $post_id),
            'description' => $this->extract_meta_description($html),
            'focusKeyword' => $this->extract_rankmath_keyword($html, $post_id),
            'rankMathScore' => $this->extract_rankmath_score($html, $post_id),
            'internalLinks' => $this->count_internal_links($html, $url),
            'externalLinks' => $this->count_external_links($html, $url),
            'altImages' => $this->check_alt_images($html),
            'seoHints' => array(),
            'technicalSeo' => array(),
        );
        
        // Header Tags Analysis with hierarchy
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1_matches);
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $h2_matches);
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $html, $h3_matches);
        preg_match_all('/<h4[^>]*>/i', $html, $h4_matches);
        preg_match_all('/<h5[^>]*>/i', $html, $h5_matches);
        preg_match_all('/<h6[^>]*>/i', $html, $h6_matches);
        
        $h1_count = count($h1_matches[0]);
        $h2_count = count($h2_matches[0]);
        $h3_count = count($h3_matches[0]);
        $h4_count = count($h4_matches[0]);
        $h5_count = count($h5_matches[0]);
        $h6_count = count($h6_matches[0]);
        
        // Extract actual H1 text
        $h1_text = '';
        if ($h1_count > 0) {
            $h1_text = html_entity_decode(strip_tags($h1_matches[1][0]), ENT_QUOTES, 'UTF-8');
            $h1_text = substr($h1_text, 0, 60) . (strlen($h1_text) > 60 ? '...' : '');
        }
        
        // Build header hierarchy info
        $header_counts = array();
        if ($h1_count > 0) $header_counts[] = "H1:{$h1_count}";
        if ($h2_count > 0) $header_counts[] = "H2:{$h2_count}";
        if ($h3_count > 0) $header_counts[] = "H3:{$h3_count}";
        if ($h4_count > 0) $header_counts[] = "H4:{$h4_count}";
        if ($h5_count > 0) $header_counts[] = "H5:{$h5_count}";
        if ($h6_count > 0) $header_counts[] = "H6:{$h6_count}";
        
        $analysis['technicalSeo']['headers'] = implode(' → ', $header_counts);
        $analysis['technicalSeo']['h1Text'] = $h1_text;
        
        if ($h1_count === 0) {
            $analysis['seoHints'][] = '❌ Missing H1 tag';
        } elseif ($h1_count > 1) {
            $analysis['seoHints'][] = '⚠️ Multiple H1 tags found';
        } else {
            $analysis['seoHints'][] = '✅ H1 tag structure good';
        }
        
        // Canonical URL Check
        $canonical = '';
        if (preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']/is', $html, $matches)) {
            $canonical = $matches[1];
            $analysis['technicalSeo']['canonical'] = $canonical;
            $analysis['seoHints'][] = '✅ Canonical URL set';
        } else {
            $analysis['seoHints'][] = '❌ Missing canonical URL';
        }
        
        // Meta Robots Check
        if (preg_match('/<meta\s+name=["\']robots["\']\s+content=["\'](.*?)["\']/is', $html, $matches)) {
            $robots = $matches[1];
            $analysis['technicalSeo']['robots'] = $robots;
            if (stripos($robots, 'noindex') !== false) {
                $analysis['seoHints'][] = '⚠️ Page set to NOINDEX';
            } else {
                $analysis['seoHints'][] = '✅ Page indexable';
            }
        }
        
        // Open Graph Check
        $og_count = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        if ($og_count > 0) {
            $analysis['seoHints'][] = "✅ Open Graph tags present ({$og_count})";
        } else {
            $analysis['seoHints'][] = '❌ Missing Open Graph tags';
        }
        
        // Twitter Card Check
        $twitter_count = preg_match_all('/<meta\s+name=["\']twitter:/i', $html);
        if ($twitter_count > 0) {
            $analysis['seoHints'][] = "✅ Twitter Card tags present ({$twitter_count})";
        }
        
        // Schema Markup Check
        $schema_count = preg_match_all('/"@type":/i', $html);
        if ($schema_count > 0) {
            $analysis['seoHints'][] = "✅ Schema markup found ({$schema_count} types)";
            $analysis['technicalSeo']['schema'] = "{$schema_count} types";
        } else {
            $analysis['seoHints'][] = '⚠️ No schema markup detected';
        }
        
        // Image Format Analysis
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $img_matches);
        $total_images = count($img_matches[1]);
        $webp_count = 0;
        $jpg_png_count = 0;
        
        foreach ($img_matches[1] as $img_src) {
            if (preg_match('/\.webp(\?|$)/i', $img_src)) {
                $webp_count++;
            } elseif (preg_match('/\.(jpg|jpeg|png)(\?|$)/i', $img_src)) {
                $jpg_png_count++;
            }
        }
        
        if ($total_images > 0) {
            $webp_percent = round(($webp_count / $total_images) * 100);
            $analysis['technicalSeo']['images'] = "WebP: {$webp_count}/{$total_images} ({$webp_percent}%)";
            
            if ($webp_percent === 100) {
                $analysis['seoHints'][] = '✅ All images in WebP format';
            } elseif ($webp_percent > 50) {
                $analysis['seoHints'][] = "⚠️ {$jpg_png_count} images not WebP - consider converting";
            } else {
                $analysis['seoHints'][] = "❌ Only {$webp_percent}% WebP - optimize images";
            }
        }
        
        // Word Count Analysis
        $word_count = $this->count_words($html);
        $analysis['technicalSeo']['wordCount'] = $word_count;
        
        if ($word_count < 300) {
            $analysis['seoHints'][] = "❌ Low word count ({$word_count}) - aim for 500+";
        } elseif ($word_count < 500) {
            $analysis['seoHints'][] = "⚠️ Word count ({$word_count}) could be higher";
        } else {
            $analysis['seoHints'][] = "✅ Good word count ({$word_count})";
        }
        
        // Title Length Check
        $title_length = strlen($analysis['title']);
        if ($title_length > 60) {
            $analysis['seoHints'][] = "⚠️ Title too long ({$title_length} chars)";
        } elseif ($title_length < 30) {
            $analysis['seoHints'][] = "⚠️ Title too short ({$title_length} chars)";
        }
        
        // Meta Description Length Check
        $desc_length = strlen($analysis['description']);
        if ($desc_length > 160) {
            $analysis['seoHints'][] = "⚠️ Meta description too long ({$desc_length} chars)";
        } elseif ($desc_length < 50 && $desc_length > 0) {
            $analysis['seoHints'][] = "⚠️ Meta description too short ({$desc_length} chars)";
        } elseif ($desc_length === 0) {
            $analysis['seoHints'][] = '❌ Missing meta description';
        }
        
        // SSL Check
        if (strpos($url, 'https://') === 0) {
            $analysis['seoHints'][] = '✅ HTTPS enabled';
        } else {
            $analysis['seoHints'][] = '❌ Not using HTTPS';
        }
        
        // Internal vs External Link Balance
        $internal = (int)$analysis['internalLinks'];
        $external = (int)$analysis['externalLinks'];
        
        if ($external === 0 && $internal > 0) {
            $analysis['seoHints'][] = '⚠️ No external links - add authoritative sources';
        }
        
        if ($internal < 3) {
            $analysis['seoHints'][] = '⚠️ Few internal links - improve internal linking';
        }
        
        // Generate personalized SEO recommendations
        $analysis['recommendations'] = $this->generate_seo_recommendations($analysis, $html, $url);
        
        return $analysis;
    }
    
    /**
     * Generate personalized SEO recommendations based on page analysis
     */
    private function generate_seo_recommendations($analysis, $html, $url) {
        $recommendations = array();
        $title = $analysis['title'];
        $description = $analysis['description'];
        $keyword = $analysis['focusKeyword'];
        $h1_count = substr_count($analysis['technicalSeo']['headers'], 'H1:');
        
        // Title Optimization
        $title_length = strlen($title);
        if ($title_length > 60) {
            $recommendations[] = "📝 TITLE: Shorten to 50-60 characters (currently {$title_length}). Consider: \"" . substr($title, 0, 57) . "...\"";
        } elseif ($title_length < 30) {
            $recommendations[] = "📝 TITLE: Expand to 50-60 characters (currently {$title_length}). Add target keyword or descriptive terms.";
        } elseif (!empty($keyword) && stripos($title, $keyword) === false) {
            $recommendations[] = "📝 TITLE: Include focus keyword \"{$keyword}\" near the beginning for better ranking.";
        } else {
            $recommendations[] = "✅ TITLE: Well optimized at {$title_length} characters.";
        }
        
        // Meta Description
        $desc_length = strlen($description);
        if ($desc_length === 0) {
            $recommendations[] = "📝 META: Write a compelling 150-160 character meta description with your focus keyword to improve click-through rate.";
        } elseif ($desc_length < 50) {
            $recommendations[] = "📝 META: Expand description to 150-160 characters (currently {$desc_length}). Add value proposition and call-to-action.";
        } elseif ($desc_length > 160) {
            $recommendations[] = "📝 META: Trim to 150-160 characters (currently {$desc_length}) to avoid truncation in search results.";
        } elseif (!empty($keyword) && stripos($description, $keyword) === false) {
            $recommendations[] = "📝 META: Include focus keyword \"{$keyword}\" naturally in your meta description.";
        } else {
            $recommendations[] = "✅ META: Description well optimized.";
        }
        
        // Header Structure
        if ($h1_count === 0) {
            $recommendations[] = "🏗️ HEADERS: Add ONE H1 tag with your primary keyword. This is critical for SEO.";
        } elseif ($h1_count > 1) {
            $recommendations[] = "🏗️ HEADERS: Remove duplicate H1 tags. Use only ONE H1 per page, use H2-H6 for subheadings.";
        }
        
        // H2 recommendations
        $h2_count = (int)filter_var($analysis['technicalSeo']['headers'], FILTER_SANITIZE_NUMBER_INT);
        if (stripos($analysis['technicalSeo']['headers'], 'H2:') === false) {
            $recommendations[] = "🏗️ HEADERS: Add 3-5 H2 subheadings to organize content and include related keywords.";
        }
        
        // Content Length
        if (isset($analysis['technicalSeo']['wordCount'])) {
            $word_count = (int)$analysis['technicalSeo']['wordCount'];
            if ($word_count < 300) {
                $recommendations[] = "📄 CONTENT: Expand to 800-1500 words (currently {$word_count}). Add more value, examples, and target related keywords.";
            } elseif ($word_count > 2500) {
                $recommendations[] = "📄 CONTENT: Consider breaking into multiple focused pages (currently {$word_count} words) or ensure content is scannable with subheadings.";
            } else {
                $recommendations[] = "✅ CONTENT: Good length at {$word_count} words.";
            }
        }
        
        // Internal Linking
        $internal_links = (int)$analysis['internalLinks'];
        if ($internal_links < 3) {
            $recommendations[] = "🔗 LINKS: Add 3-5 internal links to related pages/posts. This improves site structure and keeps visitors engaged.";
        } elseif ($internal_links > 20) {
            $recommendations[] = "🔗 LINKS: Reduce internal links to 10-15 (currently {$internal_links}). Too many can dilute link value and confuse users.";
        }
        
        // External Links
        $external_links = (int)$analysis['externalLinks'];
        if ($external_links === 0) {
            $recommendations[] = "🌐 EXTERNAL: Link to 2-3 authoritative sources (Wikipedia, government sites, industry leaders) to boost credibility.";
        } elseif ($external_links > 10) {
            $recommendations[] = "🌐 EXTERNAL: Reduce to 3-5 quality external links (currently {$external_links}). Focus on highly authoritative domains.";
        }
        
        // Images & Alt Text
        if ($analysis['altImages'] && stripos($analysis['altImages'], 'Missing') !== false) {
            preg_match('/Missing (\d+)/', $analysis['altImages'], $matches);
            $missing_count = isset($matches[1]) ? $matches[1] : 'some';
            $recommendations[] = "🖼️ IMAGES: Add descriptive alt text to {$missing_count} images. Include target keyword naturally where relevant.";
        }
        
        // Schema Markup
        if (stripos(implode('', $analysis['seoHints']), 'No schema markup') !== false) {
            $recommendations[] = "📊 SCHEMA: Implement schema markup (FAQ, HowTo, Product, or Article) to enhance search appearance with rich snippets.";
        }
        
        // Mobile Optimization
        $recommendations[] = "📱 MOBILE: Test on real devices. Ensure tap targets are 48px+, text is readable, and page loads under 3 seconds.";
        
        // Page Speed
        $recommendations[] = "⚡ SPEED: Compress images (use WebP), enable caching, minify CSS/JS, and use a CDN for faster loading.";
        
        // Keyword Strategy
        if (!empty($keyword)) {
            $keyword_in_title = stripos($title, $keyword) !== false;
            $keyword_in_desc = stripos($description, $keyword) !== false;
            $keyword_in_url = stripos($url, str_replace(' ', '-', strtolower($keyword))) !== false;
            
            if (!$keyword_in_title || !$keyword_in_desc || !$keyword_in_url) {
                $missing_locations = array();
                if (!$keyword_in_title) $missing_locations[] = 'title';
                if (!$keyword_in_desc) $missing_locations[] = 'description';
                if (!$keyword_in_url) $missing_locations[] = 'URL';
                $recommendations[] = "🎯 KEYWORD: Include \"{$keyword}\" in: " . implode(', ', $missing_locations) . " for better relevance.";
            }
        } else {
            $recommendations[] = "🎯 KEYWORD: Define a focus keyword to optimize this page for search rankings.";
        }
        
        // Call to Action
        $has_cta = preg_match('/(buy now|get started|learn more|sign up|contact us|download|subscribe)/i', $html);
        if (!$has_cta) {
            $recommendations[] = "💡 CTA: Add clear call-to-action buttons/links to guide visitors toward conversion.";
        }
        
        // Readability
        $recommendations[] = "📖 READABILITY: Use short paragraphs (3-4 sentences), bullet points, and bold important terms for better engagement.";
        
        return $recommendations;
    }
    
    /**
     * Debug endpoint to see all post meta
     */
    public function debug_post_meta($request) {
        $params = $request->get_json_params();
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
        
        if (empty($url)) {
            return new WP_Error('invalid_url', 'Invalid URL provided', array('status' => 400));
        }
        
        $post_id = url_to_postid($url);
        
        // If url_to_postid fails, try alternative methods
        if ($post_id === 0) {
            $path = parse_url($url, PHP_URL_PATH);
            $slug = trim($path, '/');
            $parts = explode('/', $slug);
            $slug = end($parts);
            
            if (!empty($slug)) {
                $post = get_page_by_path($slug, OBJECT, array('post', 'page', 'product'));
                if ($post) {
                    $post_id = $post->ID;
                } else {
                    $args = array(
                        'name' => $slug,
                        'post_type' => array('post', 'page', 'product', 'any'),
                        'post_status' => 'publish',
                        'posts_per_page' => 1
                    );
                    $query = new WP_Query($args);
                    if ($query->have_posts()) {
                        $post_id = $query->posts[0]->ID;
                    }
                }
            }
        }
        
        if ($post_id === 0) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'Could not find post ID for this URL',
                'url' => $url,
                'parsed_path' => parse_url($url, PHP_URL_PATH),
                'slug_attempted' => isset($slug) ? $slug : 'none',
            ));
        }
        
        // Get all meta for this post
        $all_meta = get_post_meta($post_id);
        
        // Filter to show only RankMath related meta
        $rankmath_meta = array();
        foreach ($all_meta as $key => $value) {
            if (stripos($key, 'rank') !== false || stripos($key, 'seo') !== false || stripos($key, 'focus') !== false) {
                $rankmath_meta[$key] = is_array($value) ? $value[0] : $value;
            }
        }
        
        // Get post info
        $post = get_post($post_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'post_id' => $post_id,
            'post_title' => $post ? $post->post_title : '',
            'post_type' => $post ? $post->post_type : '',
            'post_status' => $post ? $post->post_status : '',
            'url' => $url,
            'rankmath_meta' => $rankmath_meta,
            'rankmath_meta_count' => count($rankmath_meta),
            'total_meta_count' => count($all_meta),
            'sample_meta_keys' => array_slice(array_keys($all_meta), 0, 20),
        ));
    }
    
    /**
     * Check rate limiting for PageSpeed API
     */
    private function check_rate_limit() {
        $transient_key = 'seo_monitor_pagespeed_rate_limit';
        $rate_data = get_transient($transient_key);
        
        if (!$rate_data) {
            // Initialize rate limiting (10 requests per hour)
            $rate_data = array(
                'count' => 1,
                'reset_time' => time() + HOUR_IN_SECONDS
            );
            set_transient($transient_key, $rate_data, HOUR_IN_SECONDS);
            return true;
        }
        
        // Check if we've exceeded the limit
        if ($rate_data['count'] >= 10) {
            $time_remaining = $rate_data['reset_time'] - time();
            return new WP_Error('rate_limit', sprintf(
                __('Rate limit exceeded. Please wait %d minutes before testing again.', 'seo-page-monitor'),
                ceil($time_remaining / 60)
            ), array('status' => 429));
        }
        
        // Increment the counter
        $rate_data['count']++;
        set_transient($transient_key, $rate_data, $rate_data['reset_time'] - time());
        return true;
    }
    
    /**
     * Log error messages
     */
    public function log_error($context, $message, $data = array()) {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'context' => $context,
            'message' => $message,
            'data' => $data,
        );
        
        error_log('[SEO Monitor] ' . wp_json_encode($log_entry));
    }
    
    /**
     * Get cached PageSpeed results
     */
    private function get_cached_pagespeed($url) {
        $cache_key = 'seo_monitor_pagespeed_' . md5($url);
        return get_transient($cache_key);
    }
    
    /**
     * Cache PageSpeed results for 24 hours
     */
    private function cache_pagespeed($url, $results) {
        $cache_key = 'seo_monitor_pagespeed_' . md5($url);
        set_transient($cache_key, $results, DAY_IN_SECONDS);
    }
    
    /**
     * Run PageSpeed test for a URL
     */
    public function run_pagespeed_test($request) {
        $params = $request->get_json_params();
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
        $force_refresh = isset($params['force_refresh']) ? (bool)$params['force_refresh'] : false;
        
        if (empty($url)) {
            return new WP_Error('invalid_url', 'URL is required', array('status' => 400));
        }
        
        // Check cache first (unless force refresh)
        if (!$force_refresh) {
            $cached_results = $this->get_cached_pagespeed($url);
            if ($cached_results !== false) {
                $cached_results['from_cache'] = true;
                $cached_results['cache_expires'] = human_time_diff(time(), time() + DAY_IN_SECONDS);
                return rest_ensure_response($cached_results);
            }
        }
        
        // Check rate limiting
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            $this->log_error('pagespeed_rate_limit', 'Rate limit exceeded for PageSpeed API', array('url' => $url));
            return $rate_check;
        }
        
        // Get API key from plugin settings (priority) or wp-config.php
        $api_key = get_option('seo_monitor_pagespeed_api_key', '');
        if (empty($api_key) && defined('GOOGLE_PAGESPEED_API_KEY')) {
            $api_key = GOOGLE_PAGESPEED_API_KEY;
        }
        $key_param = !empty($api_key) && $api_key !== 'your-api-key-here' ? '&key=' . $api_key : '';
        
        // Google PageSpeed API endpoint
        $mobile_api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($url) . '&strategy=mobile' . $key_param;
        $desktop_api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($url) . '&strategy=desktop' . $key_param;
        
        // Fetch mobile score
        $mobile_response = wp_remote_get($mobile_api_url, array('timeout' => 90));
        
        if (is_wp_error($mobile_response)) {
            $this->log_error('pagespeed_mobile', 'Mobile test failed', array(
                'url' => $url,
                'error' => $mobile_response->get_error_message()
            ));
            return new WP_Error('api_error', 'Mobile test failed: ' . $mobile_response->get_error_message(), array('status' => 500));
        }
        
        $mobile_body = json_decode(wp_remote_retrieve_body($mobile_response), true);
        
        if (isset($mobile_body['error'])) {
            $this->log_error('pagespeed_mobile', 'PageSpeed API Error', array(
                'url' => $url,
                'error' => $mobile_body['error']['message']
            ));
            return new WP_Error('api_error', 'PageSpeed API Error: ' . $mobile_body['error']['message'], array('status' => 500));
        }
        
        // Fetch desktop score
        $desktop_response = wp_remote_get($desktop_api_url, array('timeout' => 90));
        
        if (is_wp_error($desktop_response)) {
            $this->log_error('pagespeed_desktop', 'Desktop test failed', array(
                'url' => $url,
                'error' => $desktop_response->get_error_message()
            ));
            return new WP_Error('api_error', 'Desktop test failed: ' . $desktop_response->get_error_message(), array('status' => 500));
        }
        
        $desktop_body = json_decode(wp_remote_retrieve_body($desktop_response), true);
        
        if (isset($desktop_body['error'])) {
            $this->log_error('pagespeed_desktop', 'PageSpeed API Error', array(
                'url' => $url,
                'error' => $desktop_body['error']['message']
            ));
            return new WP_Error('api_error', 'PageSpeed API Error: ' . $desktop_body['error']['message'], array('status' => 500));
        }
        
        // Extract scores
        $mobile_score = isset($mobile_body['lighthouseResult']['categories']['performance']['score']) 
            ? round($mobile_body['lighthouseResult']['categories']['performance']['score'] * 100) 
            : null;
            
        $desktop_score = isset($desktop_body['lighthouseResult']['categories']['performance']['score']) 
            ? round($desktop_body['lighthouseResult']['categories']['performance']['score'] * 100) 
            : null;
        
        // Get the actual PageSpeed ID from the response
        $mobile_id = isset($mobile_body['id']) ? $mobile_body['id'] : null;
        $desktop_id = isset($desktop_body['id']) ? $desktop_body['id'] : null;
        
        // Generate PageSpeed Insights URLs with actual IDs if available
        $url_path = str_replace(array('http://', 'https://'), '', $url);
        $url_path = str_replace('/', '-', rtrim($url_path, '/'));
        
        $mobile_url = $mobile_id ? 
            'https://pagespeed.web.dev/analysis/' . $url_path . '/' . $mobile_id . '?form_factor=mobile' :
            'https://pagespeed.web.dev/analysis?url=' . urlencode($url) . '&form_factor=mobile';
            
        $desktop_url = $desktop_id ?
            'https://pagespeed.web.dev/analysis/' . $url_path . '/' . $desktop_id . '?form_factor=desktop' :
            'https://pagespeed.web.dev/analysis?url=' . urlencode($url) . '&form_factor=desktop';
        
        $results = array(
            'mobile_score' => $mobile_score,
            'desktop_score' => $desktop_score,
            'mobile_url' => $mobile_url,
            'desktop_url' => $desktop_url,
            'tested_at' => current_time('mysql'),
            'from_cache' => false,
        );
        
        // Cache the results for 24 hours
        $this->cache_pagespeed($url, $results);
        
        $this->log_error('pagespeed_success', 'PageSpeed test completed successfully', array(
            'url' => $url,
            'mobile_score' => $mobile_score,
            'desktop_score' => $desktop_score
        ));

        // Trigger sync/update callback for PageSpeed
        do_action('seo_monitor_pagespeed_result', $url, $results);
        
        return rest_ensure_response($results);
    }
    
    /**
     * Export pages data as JSON
     */
    public function export_pages($request) {
        $pages = get_option('seo_monitor_pages', array());
        
        $export_data = array(
            'version' => SEO_MONITOR_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'pages' => $pages,
        );
        
        return rest_ensure_response($export_data);
    }
    
    /**
     * Import pages data from JSON
     */
    public function import_pages($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['pages']) || !is_array($params['pages'])) {
            return new WP_Error('invalid_data', 'Invalid import data', array('status' => 400));
        }
        
        // Validate import data
        $imported_pages = array();
        foreach ($params['pages'] as $page) {
            if (is_array($page) && isset($page['url']) && isset($page['title'])) {
                $imported_pages[] = array_map('sanitize_text_field', $page);
            }
        }
        
        if (empty($imported_pages)) {
            return new WP_Error('no_valid_data', 'No valid pages to import', array('status' => 400));
        }
        
        // Merge with existing pages or replace
        $merge = isset($params['merge']) ? (bool)$params['merge'] : false;
        
        if ($merge) {
            $existing_pages = get_option('seo_monitor_pages', array());
            $all_pages = array_merge($existing_pages, $imported_pages);
            update_option('seo_monitor_pages', $all_pages, false);
        } else {
            update_option('seo_monitor_pages', $imported_pages, false);
        }
        
        $this->log_error('import_success', 'Pages imported successfully', array(
            'count' => count($imported_pages),
            'merge' => $merge
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'imported_count' => count($imported_pages),
            'merge' => $merge
        ));
    }
}

/**
 * Initialize plugin
 */
function seo_page_monitor_init() {
    SEO_Page_Monitor::get_instance();
}
add_action('plugins_loaded', 'seo_page_monitor_init');

/**
 * Activation hook
 */
function seo_page_monitor_activate() {
    // Check if first activation
    if (!get_option('seo_monitor_pages')) {
        update_option('seo_monitor_pages', array(), false);
    }
    
    // Set default options with autoload false
    add_option('seo_monitor_pagespeed_api_key', '', '', false);
    
    // Set plugin version
    update_option('seo_monitor_version', SEO_MONITOR_VERSION, false);
}
register_activation_hook(__FILE__, 'seo_page_monitor_activate');

/**
 * Deactivation hook
 */
function seo_page_monitor_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'seo_page_monitor_deactivate');
