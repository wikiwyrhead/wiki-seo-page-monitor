<?php
/**
 * Google Sheets integration for SEO Page Monitor
 *
 * This class provides a lightweight wrapper around Google Sheets API.
 * It is intentionally scaffolded to be safe when google/apiclient is not installed.
 *
 * @package SEO_Page_Monitor
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Monitor_Google_Sheets {

    /** @var SEO_Page_Monitor */
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;

        // Register hooks for automatic sync events - queueing for batch processing
        add_action('seo_monitor_pages_saved', array($this, 'queue_pages'), 10, 1);
        add_action('seo_monitor_page_updated', array($this, 'queue_page'), 10, 2);
        add_action('seo_monitor_page_deleted', array($this, 'queue_delete'), 10, 2);
        add_action('seo_monitor_page_fetched', array($this, 'queue_page'), 10, 1);
        add_action('seo_monitor_pagespeed_result', array($this, 'queue_pagespeed_result'), 10, 2);

        add_action('seo_monitor_flush_sheets_queue', array($this, 'flush_queue'));
    }

    /**
     * Quick configured check
     */
    public function is_configured() {
        $sheet_id = get_option('seo_monitor_google_sheet_id', '');
        $sa = get_option('seo_monitor_google_service_account', '');
        $has_sa = !empty($sa)
            || (defined('SEO_MONITOR_GOOGLE_SA_JSON') && !empty(SEO_MONITOR_GOOGLE_SA_JSON))
            || (defined('SEO_MONITOR_GOOGLE_SA_FILE') && file_exists(SEO_MONITOR_GOOGLE_SA_FILE));

        // Also require Google API client classes to be available
        $have_client = class_exists('\\Google_Client') && class_exists('\\Google_Service_Sheets');

        return !empty($sheet_id) && $has_sa && $have_client;
    }

    /**
     * Placeholder for the get_client method — will load google client later.
     */
    private function get_client() {
        // If google client is registered via composer, instantiate it here.
        if (!class_exists('\Google_Client')) {
            $this->plugin->log_error('google_sheets', 'Google Client not installed');
            return null;
        }

        $sa_json = get_option('seo_monitor_google_service_account', '');
        if (defined('SEO_MONITOR_GOOGLE_SA_JSON') && empty($sa_json)) {
            $sa_json = SEO_MONITOR_GOOGLE_SA_JSON;
        }
        // Support an optional file path constant
        if (empty($sa_json) && defined('SEO_MONITOR_GOOGLE_SA_FILE')) {
            $file = SEO_MONITOR_GOOGLE_SA_FILE;
            if (file_exists($file)) {
                $sa_json = file_get_contents($file);
            }
        }

        if (empty($sa_json)) {
            $this->plugin->log_error('google_sheets', 'Service account JSON not configured');
            return null;
        }

        // Accept base64 encoded JSON
        $decoded = base64_decode($sa_json, true);
        if ($decoded !== false) {
            $json = $decoded;
        } else {
            $json = $sa_json;
        }

        $client = new \Google_Client();
        $client->setAuthConfig(json_decode($json, true));
        $client->addScope('https://www.googleapis.com/auth/spreadsheets');
        $client->setApplicationName('SEO Page Monitor');

        return $client;
    }

    /**
     * Safe get service placeholder
     */
    private function get_service() {
        if (!class_exists('\Google_Service_Sheets')) {
            $this->plugin->log_error('google_sheets', 'Google Service Sheets not available');
            return null;
        }

        $client = $this->get_client();
        if (!$client) {
            return null;
        }

        try {
            return new \Google_Service_Sheets($client);
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to create Google_Service_Sheets', array('message' => $e->getMessage()));
            return null;
        }
    }

    /**
     * Called when pages are saved (bulk)
     */
    public function sync_pages($pages) {
        if (!$this->is_configured()) {
            return;
        }
        // For now: loop and call sync_page
        // Use batch to reduce API calls; fall back to individual sync
        $sheet_id = get_option('seo_monitor_google_sheet_id', '');
        if (!$sheet_id) {
            return;
        }

        // ensure a header row exists when using header mapping
        if (get_option('seo_monitor_google_sheet_read_headers', false)) {
            $this->ensure_headers($sheet_id);
        }

        foreach ($pages as $page) {
            $this->add_to_queue(array(
                'op' => 'upsert',
                'page' => $page,
                'attempts' => 0,
                'time' => time()
            ));
        }
    }

    public function queue_pages($pages) {
        foreach ($pages as $page) {
            $this->add_to_queue(array('op' => 'upsert', 'page' => $page, 'attempts' => 0, 'time' => time()));
        }
    }

    /**
     * Queue a single page for upsert
     */
    public function queue_page($page, $id = null) {
        $this->add_to_queue(array('op' => 'upsert', 'page' => $page, 'attempts' => 0, 'time' => time()));
    }

    /**
     * Queue a delete operation by page URL
     */
    public function queue_delete($id, $page = null) {
        $url = '';
        if (is_array($page)) {
            $url = isset($page['url']) ? $page['url'] : '';
        }
        $this->add_to_queue(array('op' => 'delete', 'url' => $url, 'attempts' => 0, 'time' => time()));
    }

    public function queue_pagespeed_result($url, $results) {
        $page = array('url' => $url, 'pageSpeedMobile' => $results['mobile_score'], 'pageSpeedDesktop' => $results['desktop_score']);
        $this->add_to_queue(array('op' => 'upsert', 'page' => $page, 'attempts' => 0, 'time' => time()));
    }

    /**
     * Ensure a default header row exists
     */
    public function ensure_headers($sheet_id) {
        $headers = $this->get_headers($sheet_id);
        if (!empty($headers)) {
            return;
        }

        $default = array('Timestamp', 'URL', 'SEO Score', 'Status', 'Notes');
        $service = $this->get_service();
        if (!$service) {
            return;
        }

        $range = $this->get_sheet_tab() . '!A1:E1';
        $body = new \Google_Service_Sheets_ValueRange(array('values' => array($default)));
        $params = array('valueInputOption' => 'RAW');
        try {
            $service->spreadsheets_values->update($sheet_id, $range, $body, $params);
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to write default headers', array('message' => $e->getMessage()));
        }
    }

    /**
     * Sync a single page to sheet (append or update by URL)
     */
    public function sync_page($page, $id = null) {
        if (!$this->is_configured()) {
            return;
        }

        // This is a scaffold. Real implementation will use Google Service to find row.
        $sheet_id = get_option('seo_monitor_google_sheet_id', '');
        // Convert to upsert request and add to queue for batching
        $this->add_to_queue(array('op' => 'upsert', 'page' => $page, 'attempts' => 0, 'time' => time()));
        return;

        // Log for now
        $this->plugin->log_error('google_sheets', 'Sync page placeholder', array('url' => isset($page['url']) ? $page['url'] : '', 'row' => $row));
    }

    /**
     * Placeholder to add/delete rows by url
     */
    public function delete_page_by_url($id, $page = null) {
        if (!$this->is_configured()) {
            return;
        }

        $url = '';
        if (is_array($page)) {
            $url = isset($page['url']) ? $page['url'] : '';
        }

        $service = $this->get_service();
        if (!$service) {
            return;
        }

        $sheet_id = get_option('seo_monitor_google_sheet_id', '');
        $headers = array();
        $read_headers = get_option('seo_monitor_google_sheet_read_headers', false);
        if ($read_headers) {
            $headers = $this->get_headers($sheet_id);
        }

        // queue a delete request
        $this->add_to_queue(array('op' => 'delete', 'url' => $url, 'attempts' => 0, 'time' => time()));
    }

    /**
     * Called after a PageSpeed test completes
     */
    public function on_pagespeed_result($url, $results) {
        if (!$this->is_configured()) {
            return;
        }

        $page = array('url' => $url, 'pageSpeedMobile' => $results['mobile_score'], 'pageSpeedDesktop' => $results['desktop_score']);
        $this->sync_page($page);
    }

    /**
     * Build row array from page data. Order: Timestamp, URL, SEO Score, Status, Notes
     */
    public function build_row_from_page($page) {
        $timestamp = current_time('mysql');
        $url = isset($page['url']) ? $page['url'] : '';
        $seo_score = isset($page['rankMathScore']) ? $page['rankMathScore'] : (isset($page['rank_math_score']) ? $page['rank_math_score'] : '');
        $status = isset($page['priority']) ? $page['priority'] : '';
        $notes = '';
        if (isset($page['nextActions'])) {
            $notes = is_array($page['nextActions']) ? implode("\n", $page['nextActions']) : $page['nextActions'];
        } elseif (isset($page['recommendations'])) {
            $notes = is_array($page['recommendations']) ? implode("\n", $page['recommendations']) : $page['recommendations'];
        }

        return array($timestamp, $url, $seo_score, $status, $notes);
    }

    /**
     * Build a row array aligned to provided headers. Unknown headers left blank.
     * Known logical fields we map:
     *  - Timestamp, URL, SEO Score, Status, Notes
     */
    private function build_row_for_headers($page, array $headers) {
        $base = $this->build_row_from_page($page);
        $map = array(
            'timestamp' => $base[0],
            'url' => $base[1],
            'seo score' => $base[2],
            'status' => $base[3],
            'notes' => $base[4],
        );
        $row = array();
        foreach ($headers as $label) {
            $key = strtolower(trim((string)$label));
            $row[] = array_key_exists($key, $map) ? $map[$key] : '';
        }
        return $row;
    }

    /**
     * Add an operation to persistent queue
     */
    public function add_to_queue($operation) {
        $queue = get_option('seo_monitor_google_sheet_queue', array());
        $queue[] = $operation;
        // Keep queue size reasonable
        if (count($queue) > 1000) {
            $queue = array_slice($queue, -1000);
        }
        update_option('seo_monitor_google_sheet_queue', $queue, false);

        // Schedule queue flush if not already scheduled
        if (!wp_next_scheduled('seo_monitor_flush_sheets_queue')) {
            wp_schedule_single_event(time() + 30, 'seo_monitor_flush_sheets_queue');
        }
    }

    /**
     * Flush queued sheet operations (batch append, batch update, batch clear)
     */
    public function flush_queue($max_items = 200) {
        if (!$this->is_configured()) {
            return;
        }

        $sheet_id = get_option('seo_monitor_google_sheet_id', '');
        if (empty($sheet_id)) {
            return;
        }

        $queue = get_option('seo_monitor_google_sheet_queue', array());
        if (empty($queue)) {
            return;
        }

        $service = $this->get_service();
        if (!$service) {
            return;
        }

        $read_headers = get_option('seo_monitor_google_sheet_read_headers', false);
        $headers = $read_headers ? $this->get_headers($sheet_id) : array();

        // Determine URL column letter
        $url_col = 'B';
        if (!empty($headers)) {
            foreach ($headers as $idx => $label) {
                if (strcasecmp(trim($label), 'URL') === 0) {
                    $url_col = $this->column_index_to_letter($idx + 1);
                    break;
                }
            }
        }

        // Read all URL column values
        $col_range = $this->get_sheet_tab() . "!{$url_col}:{$url_col}";
        $values = array();
        try {
            $resp = $this->backoff_retry(function() use ($service, $sheet_id, $col_range) {
                return $service->spreadsheets_values->get($sheet_id, $col_range);
            });
            $values = $resp->getValues();
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to read URL column', array('message' => $e->getMessage()));
        }

        $url_map = array();
        if (is_array($values)) {
            foreach ($values as $index => $row) {
                $val = isset($row[0]) ? trim($row[0]) : '';
                if (!empty($val)) {
                    // row index is 1-based
                    $url_map[$val] = $index + 1;
                }
            }
        }

        // Process up to $max_items from queue
        $to_process = array_slice($queue, 0, $max_items);

        $append_rows = array();
        $update_data = array();
        $clear_ranges = array();
        $delete_rows = array(); // collect 1-based row indices to delete
        $processed_count = 0;
        $new_queue = $queue;

        foreach ($to_process as $i => $item) {
            $processed = false;
            try {
                if ($item['op'] === 'upsert') {
                    $page = $item['page'];
                    $url = isset($page['url']) ? $page['url'] : '';
                    // Build row using header mapping when available
                    if (!empty($headers)) {
                        $row = $this->build_row_for_headers($page, $headers);
                    } else {
                        $row = $this->build_row_from_page($page);
                    }

                    // If exists, update; otherwise append
                    $row_index = isset($url_map[$url]) ? $url_map[$url] : null;
                    if ($row_index) {
                        if (!empty($headers)) {
                            $lastCol = $this->column_index_to_letter(count($headers));
                            $range = $this->get_sheet_tab() . '!A' . $row_index . ':' . $lastCol . $row_index;
                        } else {
                            $range = $this->get_sheet_tab() . '!A' . $row_index . ':E' . $row_index;
                        }
                        $update_data[] = array('range' => $range, 'values' => array($row));
                    } else {
                        $append_rows[] = $row;
                    }
                    $processed = true;
                } elseif ($item['op'] === 'delete') {
                    $url = isset($item['url']) ? $item['url'] : '';
                    $row_index = isset($url_map[$url]) ? $url_map[$url] : null;
                    if ($row_index) {
                        // Prefer hard delete (remove row); fallback to clear later if needed
                        $delete_rows[] = $row_index;
                    }
                    $processed = true;
                }
            } catch (\Exception $e) {
                $this->plugin->log_error('google_sheets', 'Queue item processing error', array('error' => $e->getMessage(), 'item' => $item));
                $processed = false;
            }

            if ($processed) {
                // remove this item from queue
                array_shift($new_queue);
                $processed_count++;
            } else {
                // increase attempts and keep in queue
                if (isset($new_queue[0]['attempts'])) {
                    $new_queue[0]['attempts']++;
                    if ($new_queue[0]['attempts'] > 3) {
                        // give up and drop
                        array_shift($new_queue);
                        $processed_count++;
                    }
                }
            }
        }

        // Execute batch operations
        try {
            if (!empty($update_data)) {
                $batch = new \Google_Service_Sheets_BatchUpdateValuesRequest(array('valueInputOption' => 'RAW', 'data' => $update_data));
                $this->backoff_retry(function() use ($service, $sheet_id, $batch) {
                    return $service->spreadsheets_values->batchUpdate($sheet_id, $batch);
                });
            }

            if (!empty($append_rows)) {
                if (!empty($headers)) {
                    $lastCol = $this->column_index_to_letter(count($headers));
                    $range = $this->get_sheet_tab() . '!A:' . $lastCol;
                } else {
                    $range = $this->get_sheet_tab() . '!A:E';
                }
                $body = new \Google_Service_Sheets_ValueRange(array('values' => $append_rows));
                $params = array('valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS');
                $this->backoff_retry(function() use ($service, $sheet_id, $range, $body, $params) {
                    return $service->spreadsheets_values->append($sheet_id, $range, $body, $params);
                });
            }

            // Perform hard deletes if possible (requires sheetId); sort desc to avoid index shifting
            if (!empty($delete_rows)) {
                $sheetId = $this->get_sheet_id($service, $sheet_id);
                if ($sheetId !== null) {
                    rsort($delete_rows, SORT_NUMERIC);
                    $requests = array();
                    foreach ($delete_rows as $r) {
                        // 1-based row to 0-based indices, end exclusive
                        $requests[] = new \Google_Service_Sheets_Request(array(
                            'deleteDimension' => array(
                                'range' => array(
                                    'sheetId' => $sheetId,
                                    'dimension' => 'ROWS',
                                    'startIndex' => max(0, $r - 1),
                                    'endIndex' => max(0, $r)
                                )
                            )
                        ));
                    }
                    if (!empty($requests)) {
                        $batchReq = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array('requests' => $requests));
                        $this->backoff_retry(function() use ($service, $sheet_id, $batchReq) {
                            return $service->spreadsheets->batchUpdate($sheet_id, $batchReq);
                        });
                    }
                } else {
                    // Fallback to clearing values when sheetId cannot be resolved
                    foreach ($delete_rows as $r) {
                        $clear_ranges[] = $this->get_sheet_tab() . '!A' . $r . ':E' . $r;
                    }
                }
            }

            // If any clear ranges accumulated (fallback), clear them
            if (!empty($clear_ranges)) {
                $clearReq = new \Google_Service_Sheets_BatchClearValuesRequest(array('ranges' => $clear_ranges));
                $this->backoff_retry(function() use ($service, $sheet_id, $clearReq) {
                    return $service->spreadsheets_values->batchClear($sheet_id, $clearReq);
                });
            }
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to flush queue', array('message' => $e->getMessage()));
        }

        update_option('seo_monitor_google_sheet_queue', $new_queue, false);
    }

    /**
     * Read headers (first row) from the specified spreadsheet tab
     */
    public function get_headers($sheet_id) {
        $service = $this->get_service();
        if (!$service) {
            return array();
        }

        $range = $this->get_sheet_tab() . "!1:1";
        try {
            $response = $this->backoff_retry(function() use ($service, $sheet_id, $range) {
                return $service->spreadsheets_values->get($sheet_id, $range);
            });
            $values = $response->getValues();
            if (is_array($values) && isset($values[0])) {
                return $values[0];
            }
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to fetch headers', array('message' => $e->getMessage()));
        }

        return array();
    }
    public function find_row_index_by_url($sheet_id, $url, $headers = array()) {
        if (empty($url)) {
            return null;
        }

        $service = $this->get_service();
        if (!$service) {
            return null;
        }

        // Default: URL is second column (B)
        $col = 'B';
        if (!empty($headers)) {
            // Find which header equals 'URL' (case-insensitive)
            foreach ($headers as $idx => $label) {
                if (strcasecmp(trim($label), 'URL') === 0) {
                    $i = $idx + 1; // 1-based
                    $col = $this->column_index_to_letter($i);
                    break;
                }
            }
        }

        $range = $this->get_sheet_tab() . "!{$col}:{$col}";
        try {
            $response = $this->backoff_retry(function() use ($service, $sheet_id, $range) {
                return $service->spreadsheets_values->get($sheet_id, $range);
            });
            $values = $response->getValues();
            if (is_array($values)) {
                foreach ($values as $index => $row) {
                    // Skip header row if present
                    if ($index === 0 && !empty($headers)) {
                        continue;
                    }
                    if (isset($row[0]) && trim($row[0]) === $url) {
                        // 1-based row index
                        return $index + 1;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to find url', array('message' => $e->getMessage()));
        }

        return null;
    }

    private function column_index_to_letter($index) {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intval(($index - $mod) / 26);
        }
        return $letters;
    }

    /**
     * Simple append
     */
    public function append_row($sheet_id, array $row) {
        $service = $this->get_service();
        if (!$service) {
            return;
        }

        $range = $this->get_sheet_tab() . '!A:E';
        $body = new \Google_Service_Sheets_ValueRange(array(
            'values' => array($row)
        ));
        $params = array('valueInputOption' => 'RAW');
        try {
            $this->backoff_retry(function() use ($service, $sheet_id, $range, $body, $params) {
                return $service->spreadsheets_values->append($sheet_id, $range, $body, $params);
            });
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Append failed', array('message' => $e->getMessage()));
        }
    }

    /**
     * Update row at 1-based index
     */
    public function update_row($sheet_id, $row_index, array $row) {
        $service = $this->get_service();
        if (!$service) {
            return;
        }

        $range = $this->get_sheet_tab() . '!A' . $row_index . ':E' . $row_index;
        $body = new \Google_Service_Sheets_ValueRange(array('values' => array($row)));
        $params = array('valueInputOption' => 'RAW');
        try {
            $this->backoff_retry(function() use ($service, $sheet_id, $range, $body, $params) {
                return $service->spreadsheets_values->update($sheet_id, $range, $body, $params);
            });
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Update row failed', array('message' => $e->getMessage()));
        }
    }

    private function get_sheet_tab() {
        $tab = get_option('seo_monitor_google_sheet_tab', 'Sheet1');
        if (empty($tab)) $tab = 'Sheet1';
        return $tab;
    }

    /**
     * Resolve numeric sheetId for the active tab. Returns null if not found.
     */
    private function get_sheet_id($service, $spreadsheetId) {
        try {
            $sheet = $service->spreadsheets->get($spreadsheetId);
            $sheets = $sheet->getSheets();
            $title = $this->get_sheet_tab();
            foreach ($sheets as $s) {
                $props = $s->getProperties();
                if ($props && $props->getTitle() === $title) {
                    return $props->getSheetId();
                }
            }
        } catch (\Exception $e) {
            $this->plugin->log_error('google_sheets', 'Failed to resolve sheetId', array('message' => $e->getMessage()));
        }
        return null;
    }

    /**
     * Generic retry wrapper for transient Google API errors
     */
    private function backoff_retry($callable, $max_retries = 3) {
        $attempt = 0;
        $wait = 500; // milliseconds
        while (true) {
            try {
                return $callable();
            } catch (\Google_Service_Exception $e) {
                $code = $e->getCode();
                if ($code === 429 || ($code >= 500 && $code < 600)) {
                    $attempt++;
                    if ($attempt > $max_retries) {
                        throw $e;
                    }
                    usleep($wait * 1000);
                    $wait *= 2;
                    continue;
                }
                throw $e;
            } catch (\Exception $e) {
                // network or auth errors - don't retry
                throw $e;
            }
        }
    }
}
