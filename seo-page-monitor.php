<?php
/**
 * Plugin Name: SEO Page Monitor & Optimizer
 * Plugin URI: https://github.com/wikiwyrhead/wiki-seo-page-monitor
 * Description: Track and monitor SEO rankings, PageSpeed scores, and optimization tasks for your pages
 * Version: 1.3.0
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
define('SEO_MONITOR_VERSION', '1.3.0');
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
     * Handle CSV export for current pages list
     */
    public function handle_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export.', 'seo-page-monitor'));
        }
        check_admin_referer('seo_monitor_export_csv');

        $pages = get_option('seo_monitor_pages', array());
        $filename = 'seo-page-monitor-' . date('Ymd-His') . '.csv';

        // Send headers
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);

        // UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        // If a single URL is requested, filter down
        $single_url = isset($_REQUEST['url']) ? esc_url_raw($_REQUEST['url']) : '';
        if (!empty($single_url)) {
            $pages = array_values(array_filter($pages, function($row) use ($single_url) {
                return isset($row['url']) && $row['url'] === $single_url;
            }));
        }

        // Header row (include PageSpeed + Technical/Notes)
        fputcsv($out, array('URL','Title','Description','Focus Keyword','RankMath Score','Internal Links','External Links','Alt Images','Status','PageSpeed Mobile','PageSpeed Desktop','Technical','Notes'));

        foreach ($pages as $p) {
            $url = isset($p['url']) ? $this->sanitize_csv($p['url']) : '';
            $title = isset($p['title']) ? $this->sanitize_csv($p['title']) : '';
            $desc = isset($p['description']) ? $this->sanitize_csv($p['description']) : '';
            $fk = isset($p['focusKeyword']) ? $this->sanitize_csv($p['focusKeyword']) : '';
            $score = isset($p['rankMathScore']) ? $this->sanitize_csv($p['rankMathScore']) : '';
            $il = isset($p['internalLinks']) ? $this->sanitize_csv($p['internalLinks']) : '';
            $el = isset($p['externalLinks']) ? $this->sanitize_csv($p['externalLinks']) : '';
            $ai = isset($p['altImages']) ? $this->sanitize_csv($p['altImages']) : '';
            $status = isset($p['priority']) ? $this->sanitize_csv($p['priority']) : (isset($p['status']) ? $this->sanitize_csv($p['status']) : '');
            $psm = isset($p['pageSpeedMobile']) ? $this->sanitize_csv($p['pageSpeedMobile']) : '';
            $psd = isset($p['pageSpeedDesktop']) ? $this->sanitize_csv($p['pageSpeedDesktop']) : '';
            $tech = '';
            if (isset($p['onPageActions'])) {
                $tech = is_array($p['onPageActions']) ? implode("; ", array_map(array($this,'sanitize_csv'), $p['onPageActions'])) : $this->sanitize_csv($p['onPageActions']);
            }
            $notes = '';
            if (isset($p['nextActions']) && is_array($p['nextActions'])) {
                $notes = implode("; ", array_map(array($this,'sanitize_csv'), $p['nextActions']));
            } elseif (isset($p['recommendations']) && is_array($p['recommendations'])) {
                $notes = implode("; ", array_map(array($this,'sanitize_csv'), $p['recommendations']));
            }
            fputcsv($out, array($url,$title,$desc,$fk,$score,$il,$el,$ai,$status,$psm,$psd,$tech,$notes));
        }

        fclose($out);
        exit;
    }

    /**
     * Handle XLSX export with multiple sheets (Overview, Technical, PageSpeed)
     */
    public function handle_export_xlsx() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        // Reuse the same nonce as CSV export exposed to JS
        check_admin_referer('seo_monitor_export_csv');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_die('Excel export unavailable: PhpSpreadsheet is not installed. Please run composer require phpoffice/phpspreadsheet in the plugin directory.');
        }

        $pages = get_option('seo_monitor_pages', array());
        if (!is_array($pages)) $pages = array();
        // Optional single URL filter
        $single_url = isset($_REQUEST['url']) ? esc_url_raw($_REQUEST['url']) : '';
        if (!empty($single_url)) {
            $pages = array_values(array_filter($pages, function($row) use ($single_url) {
                return isset($row['url']) && $row['url'] === $single_url;
            }));
        }

        // Build spreadsheet with shared formatter
        $spreadsheet = $this->build_xlsx_workbook($pages);
        // Ensure Overview is the active sheet
        if (method_exists($spreadsheet, 'setActiveSheetIndex')) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        // Output
        $filename = 'seo-page-monitor-' . date('Ymd-His') . '.xlsx';
        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Build a consistent XLSX workbook for both global and per-page export.
     * Sheets: Overview, Technical, PageSpeed.
     * Overview includes Actions Completed, SEO Recommendations, Next Actions.
     *
     * @param array $pages
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    private function build_xlsx_workbook($pages) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()->setCreator('SEO Page Monitor')->setTitle('SEO Monitor Export');

        // Overview sheet (visual order similar to UI)
        $overview = $spreadsheet->getActiveSheet();
        $overview->setTitle('Overview');
        $overview->fromArray(array(array(
            'URL',
            'Title',
            'Description',
            'Focus Keyword',
            'Search Volume',
            'Ranking',
            'RankMath Score',
            'Priority',
            'Actions Completed',
            'SEO Recommendations',
            'Next Actions'
        )));
        $row = 2;
        foreach ($pages as $p) {
            // Actions Completed -> bullet list with icons
            $actionsCompleted = '';
            if (isset($p['onPageActions'])) {
                if (is_array($p['onPageActions'])) {
                    $actsArr = array();
                    foreach ($p['onPageActions'] as $it) {
                        foreach ($this->split_items((string)$it) as $sp) { $actsArr[] = $sp; }
                    }
                    $actsArr = array_filter(array_map('strval', $actsArr));
                } else {
                    $actsArr = $this->split_items((string)$p['onPageActions']);
                }
                $actsArr = array_map(function($s){ return $this->iconize_item($s); }, $actsArr);
                $actionsCompleted = !empty($actsArr) ? implode("\n", $actsArr) : '';
            }
            $recommendations = '';
            if (isset($p['recommendations'])) {
                // Convert to bullet list using robust splitter
                if (is_array($p['recommendations'])) {
                    $recsArr = array();
                    foreach ($p['recommendations'] as $it) {
                        foreach ($this->split_items((string)$it) as $sp) { $recsArr[] = $sp; }
                    }
                    $recsArr = array_filter(array_map('strval', $recsArr));
                } else {
                    $recsArr = $this->split_items((string)$p['recommendations']);
                }
                $recsArr = array_map(function($s){ return $this->iconize_item($s); }, $recsArr);
                $recommendations = !empty($recsArr) ? implode("\n", $recsArr) : '';
            }
            $nextActions = '';
            if (isset($p['nextActions'])) {
                if (is_array($p['nextActions'])) {
                    $nxtArr = array();
                    foreach ($p['nextActions'] as $it) {
                        foreach ($this->split_items((string)$it) as $sp) { $nxtArr[] = $sp; }
                    }
                    $nxtArr = array_filter(array_map('strval', $nxtArr));
                } else {
                    $nxtArr = $this->split_items((string)$p['nextActions']);
                }
                $nxtArr = array_map(function($s){ return $this->iconize_item($s); }, $nxtArr);
                $nextActions = !empty($nxtArr) ? implode("\n", $nxtArr) : '';
            }

            // Write scalar cells via fromArray for A–H
            $overview->fromArray(array(array(
                isset($p['url']) ? (string)$p['url'] : '',
                isset($p['title']) ? (string)$p['title'] : '',
                isset($p['description']) ? (string)$p['description'] : '',
                isset($p['focusKeyword']) ? (string)$p['focusKeyword'] : '',
                isset($p['searchVolume']) ? (string)$p['searchVolume'] : '',
                isset($p['ranking']) ? (string)$p['ranking'] : '',
                isset($p['rankMathScore']) ? (string)$p['rankMathScore'] : '',
                isset($p['priority']) ? (string)$p['priority'] : (isset($p['status']) ? (string)$p['status'] : ''),
            )), null, 'A' . $row);
            // Normalize to LF newlines (Excel recognizes "\n") and set as plain strings
            $acVal = str_replace(["\r\n","\r"], "\n", $actionsCompleted);
            $reVal = str_replace(["\r\n","\r"], "\n", $recommendations);
            $nxVal = str_replace(["\r\n","\r"], "\n", $nextActions);
            $overview->setCellValue('I' . $row, $acVal);
            $overview->setCellValue('J' . $row, $reVal);
            $overview->setCellValue('K' . $row, $nxVal);
            $overview->getStyle('I' . $row . ':K' . $row)->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setIndent(1);
            $row++;
        }

        // Styling for Overview
        $lastRow = $row - 1;
        if ($lastRow >= 1) {
            // Header style: green fill, bold white text
            $overview->getStyle('A1:K1')->applyFromArray(array(
                'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
                'fill' => array('fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => array('rgb' => '33CC66')),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ));
            // Freeze header & enable autofilter
            $overview->freezePane('A2');
            $overview->setAutoFilter('A1:K1');
            // Column widths
            foreach (range('A','H') as $col) { $overview->getColumnDimension($col)->setAutoSize(true); }
            $overview->getColumnDimension('I')->setWidth(70);
            $overview->getColumnDimension('J')->setWidth(90);
            $overview->getColumnDimension('K')->setWidth(90);
            // Wrap long text and top align (redundant per-row and per-column to ensure Excel honors it)
            $overview->getStyle('A2:K' . $lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
            $overview->getStyle('I2:I' . $lastRow)->getAlignment()->setWrapText(true);
            $overview->getStyle('J2:J' . $lastRow)->getAlignment()->setWrapText(true);
            $overview->getStyle('K2:K' . $lastRow)->getAlignment()->setWrapText(true);
            // Ensure auto row height so line breaks display
            for ($r = 2; $r <= $lastRow; $r++) {
                $overview->getRowDimension($r)->setRowHeight(-1);
            }
            // Thin borders around cells
            $overview->getStyle('A1:K' . $lastRow)->applyFromArray(array(
                'borders' => array('allBorders' => array('borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => array('rgb' => 'CCCCCC')))
            ));
            // Hyperlink style for URL column
            for ($r = 2; $r <= $lastRow; $r++) {
                $cell = $overview->getCell('A' . $r);
                $urlVal = $cell->getValue();
                if (!empty($urlVal)) {
                    $cell->getHyperlink()->setUrl($urlVal);
                    $overview->getStyle('A' . $r)->applyFromArray(array('font' => array('color' => array('rgb' => '0563C1'), 'underline' => true)));
                }
            }

            // Conditional formatting: Ranking (F)
            $condGreen = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $condGreen->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_CONTAINSTEXT)
                ->setText('#')
                ->getStyle()->getFont()->getColor()->setRGB('006100');
            $condYellow = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $condYellow->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_CONTAINSTEXT)
                ->setText('#1') // generic; detailed numeric rules are complex with text; keep subtle styling
                ->getStyle()->getFont()->getColor()->setRGB('9C5700');
            $condRed = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $condRed->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_CONTAINSTEXT)
                ->setText('Not ranking')
                ->getStyle()->getFont()->getColor()->setRGB('9C0006');
            $overview->getStyle('F2:F' . $lastRow)->setConditionalStyles(array($condRed, $condGreen));

            // Conditional: RankMath Score (G)
            $c1 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $c1->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHANOREQUAL)
                ->addCondition('80')
                ->getStyle()->getFont()->getColor()->setRGB('006100');
            $c2 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $c2->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_BETWEEN)
                ->addCondition('50')->addCondition('79')
                ->getStyle()->getFont()->getColor()->setRGB('9C5700');
            $c3 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $c3->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN)
                ->addCondition('50')
                ->getStyle()->getFont()->getColor()->setRGB('9C0006');
            $overview->getStyle('G2:G' . $lastRow)->setConditionalStyles(array($c1, $c2, $c3));

            // Conditional: Priority (H)
            $pCrit = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $pCrit->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setText('Critical');
            $pCrit->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
            $pCrit->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            $pHigh = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $pHigh->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setText('High');
            $pHigh->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFA500');
            $pMed = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $pMed->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setText('Medium');
            $pMed->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFD966');
            $pLow = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $pLow->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
                ->setText('Low');
            $pLow->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('70AD47');
            $pLow->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            $overview->getStyle('H2:H' . $lastRow)->setConditionalStyles(array($pCrit, $pHigh, $pMed, $pLow));
        }

        // Technical sheet
        $technical = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Technical');
        $spreadsheet->addSheet($technical, 1);
        $technical->fromArray(array(array('URL','Internal Links','External Links','Alt Images','Actions Completed')));
        $row = 2;
        foreach ($pages as $p) {
            $actions = '';
            if (isset($p['onPageActions'])) {
                $actions = is_array($p['onPageActions']) ? implode('; ', array_filter(array_map('strval', $p['onPageActions']))) : (string)$p['onPageActions'];
            }
            $technical->fromArray(array(array(
                isset($p['url']) ? (string)$p['url'] : '',
                isset($p['internalLinks']) ? (string)$p['internalLinks'] : '',
                isset($p['externalLinks']) ? (string)$p['externalLinks'] : '',
                isset($p['altImages']) ? (string)$p['altImages'] : '',
                $actions,
            )), null, 'A' . $row);
            $row++;
        }
        // Styling for Technical
        $lastTech = $row - 1;
        if ($lastTech >= 1) {
            $technical->getStyle('A1:E1')->applyFromArray(array(
                'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
                'fill' => array('fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => array('rgb' => '33CC66')),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ));
            $technical->freezePane('A2');
            $technical->setAutoFilter('A1:E1');
            $technical->getStyle('A2:E' . $lastTech)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
            foreach (['A'=>'auto','B'=>16,'C'=>16,'D'=>18,'E'=>60] as $col=>$w) {
                if ($w === 'auto') { $technical->getColumnDimension($col)->setAutoSize(true); } else { $technical->getColumnDimension($col)->setWidth($w); }
            }
            $technical->getStyle('A1:E' . $lastTech)->applyFromArray(array(
                'borders' => array('allBorders' => array('borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => array('rgb' => 'CCCCCC')))
            ));
            for ($r = 2; $r <= $lastTech; $r++) {
                $cell = $technical->getCell('A' . $r);
                $urlVal = $cell->getValue();
                if (!empty($urlVal)) {
                    $cell->getHyperlink()->setUrl($urlVal);
                    $technical->getStyle('A' . $r)->applyFromArray(array('font' => array('color' => array('rgb' => '0563C1'), 'underline' => true)));
                }
            }
        }

        // PageSpeed sheet
        $perf = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'PageSpeed');
        $spreadsheet->addSheet($perf, 2);
        $perf->fromArray(array(array('URL','Mobile Score','Desktop Score')));
        $row = 2;
        foreach ($pages as $p) {
            $perf->fromArray(array(array(
                isset($p['url']) ? (string)$p['url'] : '',
                isset($p['pageSpeedMobile']) ? (string)$p['pageSpeedMobile'] : '',
                isset($p['pageSpeedDesktop']) ? (string)$p['pageSpeedDesktop'] : '',
            )), null, 'A' . $row);
            $row++;
        }
        // Styling for PageSpeed
        $lastPerf = $row - 1;
        if ($lastPerf >= 1) {
            $perf->getStyle('A1:C1')->applyFromArray(array(
                'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
                'fill' => array('fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => array('rgb' => '33CC66')),
                'alignment' => array('horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ));
            $perf->freezePane('A2');
            $perf->setAutoFilter('A1:C1');
            foreach (['A'=>'auto','B'=>14,'C'=>14] as $col=>$w) {
                if ($w === 'auto') { $perf->getColumnDimension($col)->setAutoSize(true); } else { $perf->getColumnDimension($col)->setWidth($w); }
            }
            $perf->getStyle('A2:C' . $lastPerf)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            $perf->getStyle('A1:C' . $lastPerf)->applyFromArray(array(
                'borders' => array('allBorders' => array('borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => array('rgb' => 'CCCCCC')))
            ));
            for ($r = 2; $r <= $lastPerf; $r++) {
                $cell = $perf->getCell('A' . $r);
                $urlVal = $cell->getValue();
                if (!empty($urlVal)) {
                    $cell->getHyperlink()->setUrl($urlVal);
                    $perf->getStyle('A' . $r)->applyFromArray(array('font' => array('color' => array('rgb' => '0563C1'), 'underline' => true)));
                }
            }

            // Conditional for scores (B and C)
            foreach (['B','C'] as $col) {
                $ok = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $ok->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                    ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHANOREQUAL)
                    ->addCondition('90')
                    ->getStyle()->getFont()->getColor()->setRGB('006100');
                $mid = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $mid->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                    ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_BETWEEN)
                    ->addCondition('50')->addCondition('89')
                    ->getStyle()->getFont()->getColor()->setRGB('9C5700');
                $bad = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $bad->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                    ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN)
                    ->addCondition('50')
                    ->getStyle()->getFont()->getColor()->setRGB('9C0006');
                $perf->getStyle($col . '2:' . $col . $lastPerf)->setConditionalStyles(array($ok, $mid, $bad));
            }
        }

        return $spreadsheet;
    }

    /**
     * Prevent CSV/Excel formula injection and ensure scalar string
     */
    private function sanitize_csv($value) {
        $s = is_scalar($value) ? (string)$value : '';
        // If starts with =, +, -, @ then prefix with apostrophe
        if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
            $s = "'" . $s;
        }
        return $s;
    }

    /**
     * Split long text into items using newlines, pipes, emoji markers, or labeled sections.
     * Mirrors client-side parseRecommendations behavior.
     * @param string $text
     * @return array
     */
    private function split_items($text) {
        $text = trim((string)$text);
        if ($text === '') return array();

        // Helper to split a single chunk further by emoji and labels
        $split_chunk = function($chunk) {
            $chunk = trim((string)$chunk);
            if ($chunk === '') return array();

            // Normalize whitespace for specific pattern checks
            $norm = trim(preg_replace('/\s+/', ' ', $chunk));
            // Specific: Header Structure combined line => two items
            if (preg_match('/^Header Structure:\s*H1\s*:?\s*(\d+)\s*H1\s*Content\s*:?\s*(.+)$/i', $norm, $m)) {
                return array(
                    trim('Header Structure: H1:' . $m[1]),
                    trim('H1 Content: ' . $m[2])
                );
            }
            if (preg_match('/^H1\s*:?\s*(\d+)\s*H1\s*Content\s*:?\s*(.+)$/i', $norm, $m)) {
                return array(
                    trim('H1:' . $m[1]),
                    trim('H1 Content: ' . $m[2])
                );
            }

            // Emoji markers (include common ones + 🤖 + ✖/❌/✔/✅) and dashboard-set icons like 🏗️ 📄 🌐 📊
            $parts = preg_split('/(?=(?:📝|✅|✔|❌|✖|🔗|🖼|📱|⚡|📖|🎯|🔍|🔔|💡|⚠️|🔧|🟢|🟡|🔴|🤖|📑|🏗️|📄|🌐|📊))/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts && count($parts) > 1) return array_map('trim', $parts);

            // Generic emoji boundary fallback
            $parts = preg_split('/(?=\p{Extended_Pictographic})/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts && count($parts) > 1) return array_map('trim', $parts);

            // Labeled blocks (includes technical labels)
            $parts = preg_split('/(?=(?:TITLE:|META:|CONTENT:|LINKS:|IMAGES:|MOBILE:|SPEED:|READABILITY:|KEYWORD:|CTA:|SCHEMA:|Header Structure:|H1\s*:|H1\s*Content:|Robots:))/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts && count($parts) > 1) return array_map('trim', $parts);

            // Bullet chars and pipes
            $parts = preg_split('/\s*[•\|]\s*/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts && count($parts) > 1) return array_map('trim', $parts);

            return array($chunk);
        };

        // First split by newlines, then further split each line and flatten
        $lines = preg_split("/\r?\n/", $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$lines) $lines = array($text);
        $out = array();
        foreach ($lines as $ln) {
            $pieces = $split_chunk($ln);
            foreach ($pieces as $p) {
                $p = trim($p);
                if ($p !== '') $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Add an icon prefix based on simple heuristics. Mirrors dashboard iconize logic.
     */
    private function iconize_item($text) {
        $t = trim((string)$text);
        if ($t === '') return '';
        if (preg_match('/^[\p{Extended_Pictographic}]/u', $t)) return $t; // already iconized with an emoji
        $l = mb_strtolower($t, 'UTF-8');
        if (preg_match('/missing|\bno\b|not found|set to noindex|error/u', $l)) return '❌ ' . $t;
        if (preg_match('/low\b|warn|slow/u', $l)) return '⚠️ ' . $t;
        if (preg_match('/good|ok\b|enabled|passed|success/u', $l)) return '✅ ' . $t;
        if (preg_match('/link|external|internal/u', $l)) return '🔗 ' . $t;
        if (preg_match('/image|alt/u', $l)) return '🖼 ' . $t;
        if (preg_match('/mobile/u', $l)) return '📱 ' . $t;
        if (preg_match('/speed|load|performance/u', $l)) return '⚡ ' . $t;
        if (preg_match('/schema|faq|howto/u', $l)) return '📖 ' . $t;
        if (preg_match('/keyword/u', $l)) return '🎯 ' . $t;
        if (preg_match('/cta/u', $l)) return '🔔 ' . $t;
        if (preg_match('/robot|robots|noindex/u', $l)) return '🤖 ' . $t;
        if (preg_match('/h1|title|header/u', $l)) return '📑 ' . $t;
        return '• ' . $t;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        // Ensure our REST responses are never cached by LSCache/proxies/browsers
        add_filter('rest_post_dispatch', array($this, 'rest_no_cache_headers'), 10, 3);
        add_action('admin_post_seo_monitor_export_csv', array($this, 'handle_export_csv'));
        add_action('admin_post_seo_monitor_export_xlsx', array($this, 'handle_export_xlsx'));
        
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
                            </form>

                            <!-- CSV export moved to main dashboard UI -->

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
                'exportNonce' => wp_create_nonce('seo_monitor_export_csv'),
                'xlsxAvailable' => class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? true : false,
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
     * Add no-cache headers to REST responses under our namespace to avoid
     * LiteSpeed/proxy/browser caching that can hide newly-saved data.
     *
     * @param WP_HTTP_Response|mixed $result
     * @param WP_REST_Server         $server
     * @param WP_REST_Request        $request
     * @return WP_HTTP_Response|mixed
     */
    public function rest_no_cache_headers($result, $server, $request) {
        try {
            $route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
            if ($route !== '' && strpos($route, '/seo-monitor/v1') !== false) {
                if (!headers_sent()) {
                    // WordPress helper + explicit directives
                    nocache_headers();
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    header('Cache-Control: post-check=0, pre-check=0', false);
                    header('Pragma: no-cache');
                    header('Expires: 0');
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore header issues
        }
        return $result;
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
            // Whitelisted scalar fields to persist
            $scalars = array(
                'url', 'title', 'description', 'focusKeyword', 'rankMathScore',
                'internalLinks', 'externalLinks', 'altImages', 'priority', 'status',
                // persist ranking and search volume
                'ranking', 'searchVolume',
                // persist page speed metrics and references
                'pageSpeedMobile', 'pageSpeedDesktop', 'pageSpeedMobileUrl', 'pageSpeedDesktopUrl', 'pageSpeedTestedAt',
                // persist WP linkage
                'postId'
            );
            foreach ($scalars as $key) {
                if (isset($page[$key])) {
                    $sanitized[$key] = sanitize_text_field($page[$key]);
                }
            }
            // Preserve newlines in actions text for clean bullet splitting
            if (isset($page['onPageActions'])) {
                $sanitized['onPageActions'] = sanitize_textarea_field($page['onPageActions']);
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
            // ========================================
            // ENHANCED: New forensic audit data
            // ========================================
            'keywordAnalysis' => $seo_analysis['keywordAnalysis'],
            'openingParagraph' => $seo_analysis['openingParagraph'],
            'imageAnalysis' => $seo_analysis['imageAnalysis'],
            'headingAnalysis' => $seo_analysis['headingAnalysis'],
            'contentLinks' => $seo_analysis['contentLinks'],
            'faqAnalysis' => $seo_analysis['faqAnalysis'],
            'howtoAnalysis' => $seo_analysis['howtoAnalysis'],
            'seoScore' => $seo_analysis['seoScore'],
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
     * ========================================
     * ENHANCED SEO AUDIT FUNCTIONS
     * Based on forensic audit methodology
     * ========================================
     */
    
    /**
     * Extract main content area from HTML
     * Uses H1 as anchor and traverses up to find content container
     * Excludes header, footer, sidebar, navigation
     */
    private function extract_content_area($html) {
        // Try to use DOMDocument for proper parsing
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Find the main H1
        $h1_nodes = $xpath->query('//h1');
        if ($h1_nodes->length === 0) {
            // Fallback: try common content selectors
            $content_selectors = array(
                '//article',
                '//main',
                '//*[contains(@class, "entry-content")]',
                '//*[contains(@class, "post-content")]',
                '//*[contains(@class, "content-area")]',
                '//*[contains(@class, "col-lg-8")]',
                '//*[@id="content"]'
            );
            
            foreach ($content_selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    return $dom->saveHTML($nodes->item(0));
                }
            }
            
            return $html; // Fallback to full HTML
        }
        
        $h1 = $h1_nodes->item(0);
        $container = $h1->parentNode;
        
        // Traverse up to find a container with substantial content
        $max_iterations = 10;
        $iteration = 0;
        
        while ($container && $container->nodeName !== 'body' && $iteration < $max_iterations) {
            $paragraphs = $xpath->query('.//p', $container)->length;
            $images = $xpath->query('.//img', $container)->length;
            $headings = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $container)->length;
            
            // Check if this container has substantial content
            if ($paragraphs >= 3 && $headings >= 2) {
                // Exclude whole-page containers
                $class = $container->getAttribute('class');
                $id = $container->getAttribute('id');
                
                // Skip if it's a site-wide container
                if (stripos($class, 'site') === false && 
                    stripos($class, 'wrapper') === false &&
                    stripos($id, 'page') === false &&
                    $container->nodeName !== 'body') {
                    break;
                }
            }
            $container = $container->parentNode;
            $iteration++;
        }
        
        if ($container && $container->nodeName !== 'body') {
            return $dom->saveHTML($container);
        }
        
        return $html;
    }
    
    /**
     * Calculate keyword density in content
     */
    private function calculate_keyword_density($content, $keyword) {
        if (empty($keyword) || empty($content)) {
            return array(
                'count' => 0,
                'density' => '0%',
                'status' => 'missing',
                'words' => 0
            );
        }
        
        // Clean content - remove scripts, styles, HTML tags
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = strtolower(strip_tags($text));
        
        // Count words
        $words = str_word_count($text);
        if ($words === 0) {
            return array('count' => 0, 'density' => '0%', 'status' => 'error', 'words' => 0);
        }
        
        // Count keyword occurrences (as phrase)
        $keyword_lower = strtolower(trim($keyword));
        $keyword_count = substr_count($text, $keyword_lower);
        
        // Calculate density
        $density = ($keyword_count / $words) * 100;
        
        // Determine status
        $status = 'good';
        if ($keyword_count === 0) {
            $status = 'missing';
        } elseif ($density < 0.5) {
            $status = 'low';
        } elseif ($density > 3) {
            $status = 'high';
        }
        
        return array(
            'count' => $keyword_count,
            'density' => round($density, 1) . '%',
            'densityValue' => round($density, 2),
            'status' => $status,
            'words' => $words
        );
    }
    
    /**
     * Check if keyword appears in first 100 words
     */
    private function check_opening_paragraph($content, $keyword) {
        if (empty($keyword)) {
            return array(
                'found' => false, 
                'status' => 'No keyword set',
                'hint' => '⚠️ Set a focus keyword to check opening paragraph'
            );
        }
        
        // Clean content
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = strtolower(strip_tags($text));
        
        // Get first 100 words
        $words = preg_split('/\s+/', trim($text));
        $first_100 = implode(' ', array_slice($words, 0, 100));
        
        $found = stripos($first_100, strtolower($keyword)) !== false;
        
        return array(
            'found' => $found,
            'status' => $found ? 'present' : 'missing',
            'hint' => $found ? '✅ Keyword in opening paragraph' : '⚠️ Add keyword to first 100 words',
            'firstWords' => substr($first_100, 0, 200) . '...'
        );
    }
    
    /**
     * Comprehensive image SEO analysis
     */
    private function analyze_images_seo($content, $keyword) {
        preg_match_all('/<img\s+[^>]*>/is', $content, $img_matches);
        $total_images = count($img_matches[0]);
        
        if ($total_images === 0) {
            return array(
                'total' => 0,
                'missingAlt' => 0,
                'withKeyword' => 0,
                'details' => array(),
                'status' => 'No images',
                'hint' => '⚠️ Consider adding relevant images'
            );
        }
        
        $missing_alt = 0;
        $with_keyword = 0;
        $details = array();
        $keyword_lower = strtolower(trim($keyword));
        
        foreach ($img_matches[0] as $index => $img_tag) {
            // Extract src
            preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match);
            $src = isset($src_match[1]) ? basename($src_match[1]) : 'unknown';
            
            // Skip base64 placeholder images for keyword check but count for alt
            $is_placeholder = strpos($src_match[1] ?? '', 'data:image') !== false;
            
            // Extract alt
            preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
            $alt = isset($alt_match[1]) ? trim($alt_match[1]) : '';
            
            // Check alt status
            $has_alt = !empty($alt);
            $has_keyword = !empty($keyword) && !$is_placeholder && stripos($alt, $keyword_lower) !== false;
            
            if (!$has_alt) {
                $missing_alt++;
            }
            if ($has_keyword) {
                $with_keyword++;
            }
            
            $details[] = array(
                'id' => $index + 1,
                'src' => $src,
                'alt' => $alt ?: '❌ MISSING',
                'hasAlt' => $has_alt,
                'hasKeyword' => $has_keyword,
                'isPlaceholder' => $is_placeholder
            );
        }
        
        // Generate status and hint
        $status = 'Complete';
        $hint = '✅ All images have alt text';
        
        if ($missing_alt > 0) {
            $status = "Missing {$missing_alt}";
            $hint = "❌ Add alt text to {$missing_alt} images";
        } elseif ($with_keyword === 0 && !empty($keyword)) {
            $hint = "⚠️ Add keyword to at least 1 image alt text";
        } elseif ($with_keyword > 0) {
            $hint = "✅ {$with_keyword} images have keyword in alt text";
        }
        
        return array(
            'total' => $total_images,
            'missingAlt' => $missing_alt,
            'withKeyword' => $with_keyword,
            'details' => $details,
            'status' => $status,
            'hint' => $hint
        );
    }
    
    /**
     * Analyze headings with keyword presence
     */
    private function analyze_headings_seo($content, $keyword) {
        $headings = array();
        $keyword_lower = strtolower(trim($keyword));
        
        for ($i = 1; $i <= 6; $i++) {
            preg_match_all("/<h{$i}[^>]*>(.*?)<\/h{$i}>/is", $content, $matches);
            foreach ($matches[1] as $text) {
                $clean_text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
                $clean_text = trim($clean_text);
                if (empty($clean_text)) continue;
                
                $has_keyword = !empty($keyword) && stripos($clean_text, $keyword_lower) !== false;
                $headings[] = array(
                    'tag' => "H{$i}",
                    'text' => substr($clean_text, 0, 80) . (strlen($clean_text) > 80 ? '...' : ''),
                    'hasKeyword' => $has_keyword
                );
            }
        }
        
        $h1_count = count(array_filter($headings, function($h) { return $h['tag'] === 'H1'; }));
        $h2_count = count(array_filter($headings, function($h) { return $h['tag'] === 'H2'; }));
        $with_keyword = count(array_filter($headings, function($h) { return $h['hasKeyword']; }));
        
        // Generate hints
        $hints = array();
        if ($h1_count === 0) {
            $hints[] = '❌ Missing H1 tag';
        } elseif ($h1_count > 1) {
            $hints[] = '⚠️ Multiple H1 tags found';
        } else {
            $hints[] = '✅ Single H1 tag';
        }
        
        if ($with_keyword === 0 && !empty($keyword)) {
            $hints[] = '⚠️ Add keyword to headings';
        } elseif ($with_keyword > 0) {
            $hints[] = "✅ Keyword in {$with_keyword} headings";
        }
        
        return array(
            'headings' => $headings,
            'total' => count($headings),
            'h1Count' => $h1_count,
            'h2Count' => $h2_count,
            'withKeyword' => $with_keyword,
            'hints' => $hints
        );
    }
    
    /**
     * Detect FAQ section presence
     */
    private function detect_faq_section($html) {
        // Check for FAQ heading
        $has_faq_heading = preg_match('/<h[2-4][^>]*>.*?(FAQ|Frequently Asked|Common Questions).*?<\/h[2-4]>/is', $html);
        
        // Check for FAQ schema
        $has_faq_schema = stripos($html, '"@type":"FAQPage"') !== false || 
                          stripos($html, '"@type": "FAQPage"') !== false ||
                          stripos($html, '"@type":"Question"') !== false;
        
        // Check for question-answer pattern (questions ending with ?)
        preg_match_all('/<(h[3-5]|dt|strong)[^>]*>[^<]*\?[^<]*<\/(h[3-5]|dt|strong)>/is', $html, $qa_matches);
        $question_count = count($qa_matches[0]);
        
        // Generate status and hint
        if ($has_faq_schema) {
            $status = 'schema_present';
            $hint = '✅ FAQ Schema markup present';
        } elseif ($has_faq_heading || $question_count >= 3) {
            $status = 'content_present';
            $hint = '⚠️ FAQ content found - add FAQ Schema for rich snippets';
        } else {
            $status = 'not_found';
            $hint = '💡 Consider adding FAQ section for rich snippets';
        }
        
        return array(
            'hasFaqHeading' => $has_faq_heading > 0,
            'hasFaqSchema' => $has_faq_schema,
            'questionCount' => $question_count,
            'status' => $status,
            'hint' => $hint
        );
    }
    
    /**
     * Detect HowTo content for schema opportunity
     */
    private function detect_howto_content($html) {
        $has_howto_schema = stripos($html, '"@type":"HowTo"') !== false ||
                           stripos($html, '"@type": "HowTo"') !== false;
        
        // Check for step-by-step content
        $has_steps = preg_match('/step\s*[1-9]|step\s*one|first\s*step/i', $html);
        $has_numbered_list = preg_match('/<ol[^>]*>.*?<li/is', $html);
        $has_howto_heading = preg_match('/<h[1-4][^>]*>.*?(how\s*to|guide|tutorial|steps)/is', $html);
        
        if ($has_howto_schema) {
            $hint = '✅ HowTo Schema present';
        } elseif ($has_howto_heading && ($has_steps || $has_numbered_list)) {
            $hint = '⚠️ HowTo content detected - add HowTo Schema';
        } else {
            $hint = null;
        }
        
        return array(
            'hasSchema' => $has_howto_schema,
            'hasSteps' => $has_steps > 0,
            'hasNumberedList' => $has_numbered_list > 0,
            'hasHowtoHeading' => $has_howto_heading > 0,
            'hint' => $hint
        );
    }
    
    /**
     * Count links in content area only
     */
    private function count_content_links($content, $base_url) {
        $domain = parse_url($base_url, PHP_URL_HOST);
        
        preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\']/is', $content, $matches);
        
        $internal = 0;
        $external = 0;
        $internal_urls = array();
        $external_urls = array();
        
        foreach ($matches[1] as $href) {
            // Skip anchors, javascript, mailto, tel
            if (strpos($href, '#') === 0 || 
                strpos($href, 'javascript:') === 0 ||
                strpos($href, 'mailto:') === 0 ||
                strpos($href, 'tel:') === 0) {
                continue;
            }
            
            if (strpos($href, 'http') === 0) {
                $href_domain = parse_url($href, PHP_URL_HOST);
                if ($href_domain === $domain) {
                    $internal++;
                    $internal_urls[] = $href;
                } else {
                    $external++;
                    $external_urls[] = $href;
                }
            } elseif (strpos($href, '/') === 0) {
                // Relative URL starting with /
                $internal++;
                $internal_urls[] = $href;
            }
        }
        
        return array(
            'internal' => $internal,
            'external' => $external,
            'total' => $internal + $external,
            'internalUrls' => array_slice($internal_urls, 0, 10), // First 10
            'externalUrls' => array_slice($external_urls, 0, 10)
        );
    }
    
    /**
     * Calculate comprehensive SEO score
     */
    private function calculate_seo_score($analysis) {
        $score = 0;
        $breakdown = array();
        
        // Title (15 points)
        $title_len = strlen($analysis['title'] ?? '');
        if ($title_len >= 50 && $title_len <= 60) {
            $score += 15;
            $breakdown['title'] = array('score' => 15, 'max' => 15, 'status' => 'optimal');
        } elseif ($title_len >= 30 && $title_len <= 70) {
            $score += 10;
            $breakdown['title'] = array('score' => 10, 'max' => 15, 'status' => 'good');
        } elseif ($title_len > 0) {
            $score += 5;
            $breakdown['title'] = array('score' => 5, 'max' => 15, 'status' => 'needs_work');
        } else {
            $breakdown['title'] = array('score' => 0, 'max' => 15, 'status' => 'missing');
        }
        
        // Meta Description (10 points)
        $desc_len = strlen($analysis['description'] ?? '');
        if ($desc_len >= 150 && $desc_len <= 160) {
            $score += 10;
            $breakdown['description'] = array('score' => 10, 'max' => 10, 'status' => 'optimal');
        } elseif ($desc_len >= 100 && $desc_len <= 180) {
            $score += 7;
            $breakdown['description'] = array('score' => 7, 'max' => 10, 'status' => 'good');
        } elseif ($desc_len > 0) {
            $score += 3;
            $breakdown['description'] = array('score' => 3, 'max' => 10, 'status' => 'needs_work');
        } else {
            $breakdown['description'] = array('score' => 0, 'max' => 10, 'status' => 'missing');
        }
        
        // H1 Tag (15 points)
        $h1_count = $analysis['headingAnalysis']['h1Count'] ?? 0;
        $headings_with_keyword = $analysis['headingAnalysis']['withKeyword'] ?? 0;
        if ($h1_count === 1) {
            $score += 10;
            if ($headings_with_keyword > 0) {
                $score += 5;
                $breakdown['h1'] = array('score' => 15, 'max' => 15, 'status' => 'optimal');
            } else {
                $breakdown['h1'] = array('score' => 10, 'max' => 15, 'status' => 'good');
            }
        } elseif ($h1_count > 1) {
            $score += 5;
            $breakdown['h1'] = array('score' => 5, 'max' => 15, 'status' => 'multiple');
        } else {
            $breakdown['h1'] = array('score' => 0, 'max' => 15, 'status' => 'missing');
        }
        
        // Keyword Density (15 points)
        $density = $analysis['keywordAnalysis']['densityValue'] ?? 0;
        if ($density >= 0.8 && $density <= 2.5) {
            $score += 15;
            $breakdown['keywordDensity'] = array('score' => 15, 'max' => 15, 'status' => 'optimal');
        } elseif ($density >= 0.5 && $density <= 3) {
            $score += 10;
            $breakdown['keywordDensity'] = array('score' => 10, 'max' => 15, 'status' => 'good');
        } elseif ($density > 0) {
            $score += 5;
            $breakdown['keywordDensity'] = array('score' => 5, 'max' => 15, 'status' => 'needs_work');
        } else {
            $breakdown['keywordDensity'] = array('score' => 0, 'max' => 15, 'status' => 'missing');
        }
        
        // Opening Paragraph (10 points)
        $keyword_in_opening = $analysis['openingParagraph']['found'] ?? false;
        if ($keyword_in_opening) {
            $score += 10;
            $breakdown['openingParagraph'] = array('score' => 10, 'max' => 10, 'status' => 'present');
        } else {
            $breakdown['openingParagraph'] = array('score' => 0, 'max' => 10, 'status' => 'missing');
        }
        
        // Image Alt Text (10 points)
        $missing_alt = $analysis['imageAnalysis']['missingAlt'] ?? 0;
        $images_with_keyword = $analysis['imageAnalysis']['withKeyword'] ?? 0;
        $total_images = $analysis['imageAnalysis']['total'] ?? 0;
        
        if ($total_images === 0) {
            $score += 3; // Some points for no images (not penalized heavily)
            $breakdown['images'] = array('score' => 3, 'max' => 10, 'status' => 'no_images');
        } elseif ($missing_alt === 0 && $images_with_keyword > 0) {
            $score += 10;
            $breakdown['images'] = array('score' => 10, 'max' => 10, 'status' => 'optimal');
        } elseif ($missing_alt === 0) {
            $score += 7;
            $breakdown['images'] = array('score' => 7, 'max' => 10, 'status' => 'good');
        } elseif ($missing_alt < $total_images) {
            $score += 3;
            $breakdown['images'] = array('score' => 3, 'max' => 10, 'status' => 'needs_work');
        } else {
            $breakdown['images'] = array('score' => 0, 'max' => 10, 'status' => 'missing');
        }
        
        // Internal Links (5 points)
        $internal = $analysis['contentLinks']['internal'] ?? 0;
        if ($internal >= 3 && $internal <= 15) {
            $score += 5;
            $breakdown['internalLinks'] = array('score' => 5, 'max' => 5, 'status' => 'optimal');
        } elseif ($internal > 0) {
            $score += 3;
            $breakdown['internalLinks'] = array('score' => 3, 'max' => 5, 'status' => 'good');
        } else {
            $breakdown['internalLinks'] = array('score' => 0, 'max' => 5, 'status' => 'missing');
        }
        
        // External Links (5 points)
        $external = $analysis['contentLinks']['external'] ?? 0;
        if ($external >= 1 && $external <= 5) {
            $score += 5;
            $breakdown['externalLinks'] = array('score' => 5, 'max' => 5, 'status' => 'optimal');
        } elseif ($external > 0) {
            $score += 3;
            $breakdown['externalLinks'] = array('score' => 3, 'max' => 5, 'status' => 'good');
        } else {
            $breakdown['externalLinks'] = array('score' => 0, 'max' => 5, 'status' => 'missing');
        }
        
        // Content Length (5 points)
        $words = $analysis['keywordAnalysis']['words'] ?? 0;
        if ($words >= 800) {
            $score += 5;
            $breakdown['contentLength'] = array('score' => 5, 'max' => 5, 'status' => 'optimal');
        } elseif ($words >= 500) {
            $score += 3;
            $breakdown['contentLength'] = array('score' => 3, 'max' => 5, 'status' => 'good');
        } elseif ($words >= 300) {
            $score += 1;
            $breakdown['contentLength'] = array('score' => 1, 'max' => 5, 'status' => 'needs_work');
        } else {
            $breakdown['contentLength'] = array('score' => 0, 'max' => 5, 'status' => 'short');
        }
        
        // Schema/FAQ (5 points)
        $has_faq = $analysis['faqAnalysis']['hasFaqSchema'] ?? false;
        $has_schema = isset($analysis['technicalSeo']['schema']);
        if ($has_faq) {
            $score += 5;
            $breakdown['schema'] = array('score' => 5, 'max' => 5, 'status' => 'faq_present');
        } elseif ($has_schema) {
            $score += 3;
            $breakdown['schema'] = array('score' => 3, 'max' => 5, 'status' => 'schema_present');
        } else {
            $breakdown['schema'] = array('score' => 0, 'max' => 5, 'status' => 'missing');
        }
        
        // Canonical (5 points)
        $has_canonical = !empty($analysis['technicalSeo']['canonical'] ?? '');
        if ($has_canonical) {
            $score += 5;
            $breakdown['canonical'] = array('score' => 5, 'max' => 5, 'status' => 'present');
        } else {
            $breakdown['canonical'] = array('score' => 0, 'max' => 5, 'status' => 'missing');
        }
        
        return array(
            'score' => $score,
            'maxScore' => 100,
            'percentage' => $score,
            'grade' => $this->get_seo_grade($score),
            'breakdown' => $breakdown
        );
    }
    
    /**
     * Get SEO grade based on score
     */
    private function get_seo_grade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    /**
     * ========================================
     * END ENHANCED SEO AUDIT FUNCTIONS
     * ========================================
     */
    
    /**
     * Comprehensive SEO Analysis - Enhanced with forensic audit capabilities
     */
    private function analyze_seo($html, $url, $post_id = 0) {
        // Extract basic info first
        $title = $this->extract_title($html, $post_id);
        $description = $this->extract_meta_description($html);
        $keyword = $this->extract_rankmath_keyword($html, $post_id);
        
        // ========================================
        // ENHANCED: Extract content area for accurate analysis
        // ========================================
        $content_area = $this->extract_content_area($html);
        
        // ========================================
        // ENHANCED: Keyword density analysis
        // ========================================
        $keyword_analysis = $this->calculate_keyword_density($content_area, $keyword);
        
        // ========================================
        // ENHANCED: Opening paragraph keyword check
        // ========================================
        $opening_paragraph = $this->check_opening_paragraph($content_area, $keyword);
        
        // ========================================
        // ENHANCED: Image SEO analysis with keyword checking
        // ========================================
        $image_analysis = $this->analyze_images_seo($content_area, $keyword);
        
        // ========================================
        // ENHANCED: Heading analysis with keyword presence
        // ========================================
        $heading_analysis = $this->analyze_headings_seo($content_area, $keyword);
        
        // ========================================
        // ENHANCED: Content-only link counting
        // ========================================
        $content_links = $this->count_content_links($content_area, $url);
        
        // ========================================
        // ENHANCED: FAQ section detection
        // ========================================
        $faq_analysis = $this->detect_faq_section($html);
        
        // ========================================
        // ENHANCED: HowTo content detection
        // ========================================
        $howto_analysis = $this->detect_howto_content($html);
        
        // Build initial analysis array
        $analysis = array(
            'title' => $title,
            'description' => $description,
            'focusKeyword' => $keyword,
            'rankMathScore' => $this->extract_rankmath_score($html, $post_id),
            // Use content-only link counts (more accurate)
            'internalLinks' => (string)$content_links['internal'],
            'externalLinks' => (string)$content_links['external'],
            'altImages' => $image_analysis['status'],
            'seoHints' => array(),
            'technicalSeo' => array(),
            // NEW: Enhanced analysis data
            'keywordAnalysis' => $keyword_analysis,
            'openingParagraph' => $opening_paragraph,
            'imageAnalysis' => $image_analysis,
            'headingAnalysis' => $heading_analysis,
            'contentLinks' => $content_links,
            'faqAnalysis' => $faq_analysis,
            'howtoAnalysis' => $howto_analysis,
        );
        
        // Build header hierarchy info from enhanced analysis
        $header_counts = array();
        $h1_count = $heading_analysis['h1Count'];
        $h2_count = $heading_analysis['h2Count'];
        
        if ($h1_count > 0) $header_counts[] = "H1:{$h1_count}";
        if ($h2_count > 0) $header_counts[] = "H2:{$h2_count}";
        
        // Count other headings
        $h3_count = count(array_filter($heading_analysis['headings'], function($h) { return $h['tag'] === 'H3'; }));
        $h4_count = count(array_filter($heading_analysis['headings'], function($h) { return $h['tag'] === 'H4'; }));
        $h5_count = count(array_filter($heading_analysis['headings'], function($h) { return $h['tag'] === 'H5'; }));
        $h6_count = count(array_filter($heading_analysis['headings'], function($h) { return $h['tag'] === 'H6'; }));
        
        if ($h3_count > 0) $header_counts[] = "H3:{$h3_count}";
        if ($h4_count > 0) $header_counts[] = "H4:{$h4_count}";
        if ($h5_count > 0) $header_counts[] = "H5:{$h5_count}";
        if ($h6_count > 0) $header_counts[] = "H6:{$h6_count}";
        
        // Extract H1 text
        $h1_text = '';
        foreach ($heading_analysis['headings'] as $h) {
            if ($h['tag'] === 'H1') {
                $h1_text = $h['text'];
                break;
            }
        }
        
        $analysis['technicalSeo']['headers'] = implode(' → ', $header_counts);
        $analysis['technicalSeo']['h1Text'] = $h1_text;
        $analysis['technicalSeo']['wordCount'] = $keyword_analysis['words'];
        
        // ========================================
        // SEO HINTS - Enhanced with new analysis
        // ========================================
        
        // Heading hints
        foreach ($heading_analysis['hints'] as $hint) {
            $analysis['seoHints'][] = $hint;
        }
        
        // Keyword density hints
        if ($keyword_analysis['status'] === 'missing' && !empty($keyword)) {
            $analysis['seoHints'][] = "❌ Keyword \"{$keyword}\" not found in content";
        } elseif ($keyword_analysis['status'] === 'low') {
            $analysis['seoHints'][] = "⚠️ Keyword density low ({$keyword_analysis['density']}) - aim for 0.8-2.5%";
        } elseif ($keyword_analysis['status'] === 'high') {
            $analysis['seoHints'][] = "⚠️ Keyword density high ({$keyword_analysis['density']}) - reduce to avoid stuffing";
        } elseif ($keyword_analysis['status'] === 'good') {
            $analysis['seoHints'][] = "✅ Keyword density optimal ({$keyword_analysis['density']})";
        }
        
        // Opening paragraph hint
        $analysis['seoHints'][] = $opening_paragraph['hint'];
        
        // Image hints
        $analysis['seoHints'][] = $image_analysis['hint'];
        
        // FAQ hint
        $analysis['seoHints'][] = $faq_analysis['hint'];
        
        // HowTo hint (if applicable)
        if (!empty($howto_analysis['hint'])) {
            $analysis['seoHints'][] = $howto_analysis['hint'];
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
        
        // ========================================
        // ENHANCED: Calculate comprehensive SEO score
        // ========================================
        $analysis['seoScore'] = $this->calculate_seo_score($analysis);
        
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
