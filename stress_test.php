<?php

// Find wp-load.php inside the docker container
$wp_load = dirname(__FILE__, 3) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("wp-load.php not found at $wp_load\n");
}
require_once $wp_load;

echo "Starting Stress Test for Formular Logging plugin...\n\n";

use Signalfeuer\FormularLogs\Core\Plugin;
use Signalfeuer\FormularLogs\Core\Crypto;
use Signalfeuer\FormularLogs\Storage\LogStorage;

$plugin = Plugin::instance();

// 1. Test AJAX Endpoint Directly (simulating massive and malformed data)
echo "--- Testing AJAX Endpoint ---\n";
$nonce = wp_create_nonce(Plugin::NONCE_ACTION);

// Helper to mock a request
function mock_ajax_request($post_data)
{
    $post_data['action'] = Plugin::AJAX_ACTION;
    $url = admin_url('admin-ajax.php');

    $response = wp_remote_post($url, [
        'timeout' => 15,
        'body' => $post_data
    ]);

    if (is_wp_error($response)) {
        echo "HTTP Error: " . $response->get_error_message() . "\n";
    }
    else {
        echo "Response code: " . wp_remote_retrieve_response_code($response) . "\n";
        echo "Response body: " . wp_remote_retrieve_body($response) . "\n";
    }
}

// 1.1 Miss Required Fields
echo "1.1 Missing Required Fields:\n";
mock_ajax_request(['nonce' => $nonce]);

// 1.2 Invalid Nonce
echo "1.2 Invalid Nonce:\n";
mock_ajax_request(['nonce' => 'invalid_nonce', 'event_type' => 'test', 'event_stage' => 'test', 'status' => 'info']);

// 1.3 Huge Payload JSON
echo "1.3 Huge Payload JSON (Stress on Crypto and Storage):\n";
$huge_array = [];
for ($i = 0; $i < 50000; $i++) {
    $huge_array["key_$i"] = "value_" . md5($i);
}
mock_ajax_request([
    'nonce' => $nonce,
    'event_type' => 'stress_test',
    'event_stage' => 'huge_payload',
    'status' => 'info',
    'payload_json' => wp_json_encode($huge_array),
]);

// 1.4 Malformed Payload (not JSON but passed as JSON)
echo "1.4 Malformed Payload:\n";
mock_ajax_request([
    'nonce' => $nonce,
    'event_type' => 'stress_test',
    'event_stage' => 'malformed_payload',
    'status' => 'info',
    'payload_json' => '{"broken_json": "value"',
]);

// 1.5 Rate Limiter Test
echo "1.5 Rate Limiter Test (IP Spoofing & Blocking):\n";
update_option('fl_rate_limit_enabled', true);
update_option('fl_rate_limit_threshold', 3);
update_option('fl_rate_limit_duration', 1);

$test_ip = '203.0.113.42'; // Fake IP
$_SERVER['REMOTE_ADDR'] = $test_ip;

for ($i = 0; $i < 5; $i++) {
    echo "  Request $i (should block after 3 errors):\n";

    // Wir rufen die Action direkt auf um wp_remote_post() Bypass durch den lokalen Server zu vermeiden
    // da wp_remote_post evtl keine REMOTE_ADDR durchreicht
    $_POST = [
        'nonce' => $nonce,
        'request_id' => 'test-rl-' . $i,
        'event_type' => 'test',
        'event_stage' => 'test',
        'status' => 'error'
    ];

    // Check block before handling, wie in init hook
    $block_key = 'fl_rl_block_' . md5($test_ip);
    if (get_transient($block_key)) {
        echo "  [Blocked!] Transient fl_rl_block_ is active.\n";
    }
    else {
        echo "  [Allowed] Processing...\n";
        // Handle in AjaxLogger simulate
        try {
            $logger = new \Signalfeuer\FormularLogs\Loggers\AjaxLogger(
                new LogStorage(new Crypto()),
                new \Signalfeuer\FormularLogs\Core\RequestContext(),
                Plugin::NONCE_ACTION
                );
            // $logger->handle_frontend_event() makes wp_send_json_success() which calls wp_die(). We just call track_ip_error manually to test the logic.
            Plugin::instance()->track_ip_error();
        }
        catch (\Exception $e) {
        }
    }
}

// Reset options
update_option('fl_rate_limit_enabled', false);
delete_transient('fl_rl_block_' . md5($test_ip));
delete_transient('fl_rl_err_' . md5($test_ip));

// 2. Test Mail Logger Hooks (using wp_mail)
echo "--- Testing Mail Logger Hooks ---\n";
// 2.1 Huge Attachment
echo "2.1 Huge Attachment (Stress on JSON encoding attachments):\n";
$attachments = [];
for ($i = 0; $i < 5000; $i++) {
    $attachments[] = "/tmp/fake_file_$i.txt";
}
// wp_mail('test@example.com', 'Stress Test Mail', 'This is a test', '', $attachments);

// 2.2 Invalid Headers Array
echo "2.2 Invalid Headers:\n";
// wp_mail('test@example.com', 'Stress Test Mail 2', 'This is a test', ['Invalid Header Array Structure', ['key', 'value'], 'X-Custom-Header: value']);

// 3. Test LogStorage
echo "--- Testing Log Storage ---\n";
$crypto = new Crypto();
$storage = new LogStorage($crypto);

// Simulate Log File Corruption (remove headers, add garbage)
$log_dir = $storage->get_log_dir();
$log_path = $storage->get_daily_log_path();

echo "Log path: $log_path\n";

if (file_exists($log_path)) {
    // Add garbage data
    $handle = fopen($log_path, 'a');
    fwrite($handle, "\nGarbage data\nMore garbage without quotes or commas\n\"Unclosed string,something,else\n");
    fclose($handle);
}

// Now try to read rows
echo "Reading from corrupted CSV:\n";
try {
    $rows = $storage->read_filtered_rows($log_path, [], 100);
    echo "Successfully read " . count($rows) . " rows after corruption.\n";
}
catch (\Throwable $e) {
    echo "Caught Exception reading CSV: " . $e->getMessage() . "\n";
}

echo "\nStress tests completed.\n";