<?php
/**
 * Tests for Google Sheets integration
 */

use PHPUnit\Framework\TestCase;

class SEO_Monitor_Google_Sheets_Test extends TestCase {

    public function test_build_row_from_page() {
        if (!class_exists('\\Google_Client')) {
            $this->markTestSkipped('Google API client not installed. Run composer require google/apiclient');
        }
        $page = array(
            'url' => 'https://example.com/page',
            'rankMathScore' => '85',
            'priority' => 'High',
            'nextActions' => "Add alt text\nOptimize images"
        );

        $monitor = SEO_Page_Monitor::get_instance();
        $sheets = new SEO_Monitor_Google_Sheets($monitor);
        $row = $sheets->build_row_from_page($page);

        $this->assertIsArray($row);
        $this->assertEquals(5, count($row));
        $this->assertEquals('https://example.com/page', $row[1]);
        $this->assertEquals('85', $row[2]);
        $this->assertEquals('High', $row[3]);
        $this->assertStringContainsString('Add alt text', $row[4]);
    }

    public function test_column_index_to_letter() {
        if (!class_exists('\\Google_Client')) {
            $this->markTestSkipped('Google API client not installed. Run composer require google/apiclient');
        }
        $monitor = SEO_Page_Monitor::get_instance();
        $sheets = new SEO_Monitor_Google_Sheets($monitor);

        $this->assertEquals('A', $this->invokeMethod($sheets, 'column_index_to_letter', array(1)));
        $this->assertEquals('Z', $this->invokeMethod($sheets, 'column_index_to_letter', array(26)));
        $this->assertEquals('AA', $this->invokeMethod($sheets, 'column_index_to_letter', array(27)));
        $this->assertEquals('AZ', $this->invokeMethod($sheets, 'column_index_to_letter', array(52)));
    }

    public function test_queue_addition() {
        $monitor = SEO_Page_Monitor::get_instance();
        $sheets = new SEO_Monitor_Google_Sheets($monitor);

        // Clear queue
        update_option('seo_monitor_google_sheet_queue', array(), false);

        $sheets->add_to_queue(array('op' => 'upsert', 'page' => array('url' => 'https://example.com/test'), 'attempts' => 0));

        $queue = get_option('seo_monitor_google_sheet_queue', array());
        $this->assertNotEmpty($queue);
        $this->assertEquals('upsert', $queue[0]['op']);
    }

    // Helper to call protected/private methods
    protected function invokeMethod(&$object, $methodName, array $parameters = array()) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
