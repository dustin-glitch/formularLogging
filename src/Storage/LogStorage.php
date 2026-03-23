<?php
namespace Signalfeuer\FormularLogs\Storage;

use Signalfeuer\FormularLogs\Core\RequestContext;
use SplFileObject;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Storage\LogStorage')) {
    class LogStorage
    {
        /** @var array<string> */
        private $columns = array(
            'timestamp_utc',
            'request_id',
            'event_type',
            'event_stage',
            'status',
            'source',
            'form_identifier',
            'page_url',
            'http_method',
            'client_ip',
            'user_agent',
            'browser',
            'os',
            'recipient',
            'subject',
            'mailer',
            'smtp_host',
            'smtp_port',
            'error_code',
            'error_message',
            'payload_json',
            'attachments_json',
            'extra_json',
        );

        public function get_columns()
        {
            return $this->columns;
        }

        /** @var \Signalfeuer\FormularLogs\Core\Crypto */
        private $crypto;

        public function __construct(\Signalfeuer\FormularLogs\Core\Crypto $crypto)
        {
            $this->crypto = $crypto;
        }

        public function get_log_dir()
        {
            $custom_dir = trim((string)get_option('fl_custom_log_dir', ''));
            if (!empty($custom_dir)) {
                return trailingslashit($custom_dir);
            }

            $uploads = wp_get_upload_dir();
            $base = isset($uploads['basedir']) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
            return trailingslashit($base) . 'form-logs';
        }

        public function get_daily_log_path($date = null)
        {
            if ($date === null) {
                $date = wp_date('Y-m-d');
            }

            $log_dir = trailingslashit($this->get_log_dir());
            $pattern = $log_dir . 'form-log-' . $date . '*.csv';
            $existing = glob($pattern);

            if (!empty($existing) && is_array($existing)) {
                return $existing[0];
            }

            $hash = wp_generate_password(16, false, false);
            return $log_dir . 'form-log-' . $date . '-' . $hash . '.csv';
        }

        public function write_log(array $row, RequestContext $context)
        {
            $log_dir = $this->get_log_dir();
            if (!$this->ensure_log_dir($log_dir)) {
                return false;
            }

            $path = $this->get_daily_log_path();
            $handle = @fopen($path, 'ab');
            if (!$handle) {
                return false;
            }

            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                return false;
            }

            clearstatcache(true, $path);
            $is_empty = filesize($path) === 0;
            if ($is_empty) {
                fputcsv($handle, $this->columns);
            }

            fputcsv($handle, $this->build_ordered_row($row, $context));

            flock($handle, LOCK_UN);
            fclose($handle);

            do_action('fl_log_written', $row);

            return true;
        }

        public function cleanup_old_logs($retention_days = 30)
        {
            $log_dir = $this->get_log_dir();
            if (!is_dir($log_dir)) {
                return;
            }

            $retention_days = floatval($retention_days);
            if ($retention_days <= 0) {
                $retention_days = 30;
            }

            $threshold = time() - ($retention_days * DAY_IN_SECONDS);
            $files = glob(trailingslashit($log_dir) . 'form-log-*.csv');
            if (!is_array($files)) {
                return;
            }

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $modified = filemtime($file);
                if ($modified !== false && $modified < $threshold) {
                    wp_delete_file($file);
                }
            }
        }

        public function get_aggregated_stats_for_last_days($days = 7)
        {
            $cache_key = 'fl_stats_' . (int)$days . '_' . wp_date('Y-m-d-H');
            $cached = get_transient($cache_key);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }

            $stats = array(
                'labels' => array(),
                'success' => array(),
                'errors' => array()
            );

            for ($i = $days - 1; $i >= 0; $i--) {
                $time = time() - ($i * DAY_IN_SECONDS);
                $date = wp_date('Y-m-d', $time);
                $path = $this->get_daily_log_path($date);

                $success = 0;
                $errors = 0;

                if (file_exists($path)) {
                    $rows = $this->read_filtered_rows($path, array(), 5000);
                    $grouped = array();

                    foreach ($rows as $row) {
                        $req_id = isset($row['request_id']) && $row['request_id'] !== '' ? $row['request_id'] : 'unknown_' . md5(wp_json_encode($row));
                        if (!isset($grouped[$req_id])) {
                            $grouped[$req_id] = array('has_error' => false);
                        }

                        $status = isset($row['status']) ? (string)$row['status'] : '';
                        $type = isset($row['event_type']) ? (string)$row['event_type'] : '';
                        $stage = isset($row['event_stage']) ? (string)$row['event_stage'] : '';

                        if ($status === 'error' || $status === 'failed' || $type === 'frontend_js_error' || $stage === 'form_validation_failed' || $stage === 'form_field_invalid' || $stage === 'wp_mail_failed' || $stage === 'form_submission_error' || $stage === 'mail_send_failed') {
                            $grouped[$req_id]['has_error'] = true;
                        }

                        $extra = isset($row['extra_json']) ? (string)$row['extra_json'] : '';
                        if ($extra !== '') {
                            $extraDecoded = @json_decode($extra, true);
                            if (is_array($extraDecoded) && (!empty($extraDecoded['error']) || isset($extraDecoded['field']) || !empty($extraDecoded['validation']))) {
                                $grouped[$req_id]['has_error'] = true;
                            }
                        }
                    }

                    foreach ($grouped as $g) {
                        if ($g['has_error']) {
                            $errors++;
                        }
                        else {
                            $success++;
                        }
                    }
                }

                $stats['labels'][] = wp_date('d.m.', $time);
                $stats['success'][] = $success;
                $stats['errors'][] = $errors;
            }

            // Cache for 1 hour (keyed by date+hour, so it auto-expires each hour)
            set_transient($cache_key, $stats, HOUR_IN_SECONDS);

            return $stats;
        }

        public function read_filtered_rows($path, array $filters, $limit)
        {
            if (!file_exists($path)) {
                return array();
            }

            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

            $rows = array();
            $header_checked = false;

            foreach ($file as $line) {
                if (!is_array($line) || $line === array(null)) {
                    continue;
                }

                if (!$header_checked) {
                    $header_checked = true;
                    continue;
                }

                $assoc = array();
                foreach ($this->columns as $index => $column) {
                    $assoc[$column] = isset($line[$index]) ? (string)$line[$index] : '';
                }

                if (!empty($filters['request_id']) && $assoc['request_id'] !== $filters['request_id']) {
                    continue;
                }
                if (!empty($filters['status']) && $assoc['status'] !== $filters['status']) {
                    continue;
                }
                if (!empty($filters['event_type']) && $assoc['event_type'] !== $filters['event_type']) {
                    continue;
                }

                $rows[] = $assoc;
                // When no filters are active, stop after $limit rows to avoid loading the full file
                if (empty($filters['request_id']) && empty($filters['status']) && empty($filters['event_type'])) {
                    if (count($rows) >= $limit) {
                        break;
                    }
                }
                elseif (count($rows) > $limit) {
                    // With filters: keep a sliding window of the last $limit matching rows
                    array_shift($rows);
                }
            }

            return $rows;
        }

        private function ensure_log_dir($log_dir)
        {
            if (!is_dir($log_dir)) {
                wp_mkdir_p($log_dir);
            }

            if (!is_dir($log_dir) || !is_writable($log_dir)) {
                return false;
            }

            // Create blank index.php
            $indexFile = trailingslashit($log_dir) . 'index.php';
            if (!file_exists($indexFile)) {
                @file_put_contents($indexFile, '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL);
            }

            // Create restrictive .htaccess (Apache only — Nginx ignores this file)
            // On Nginx servers, configure the web server to deny access to this directory directly.
            $htaccessFile = trailingslashit($log_dir) . '.htaccess';
            if (!file_exists($htaccessFile)) {
                $htaccessRules = "Deny from all\n<Files \"*\">\n\tOrder allow,deny\n\tDeny from all\n</Files>\n";
                @file_put_contents($htaccessFile, $htaccessRules);
            }

            return true;
        }

        private function build_ordered_row(array $row, RequestContext $context)
        {
            $defaults = array(
                'timestamp_utc' => gmdate('c'),
                'request_id' => $context->resolve_request_id(),
                'event_type' => '',
                'event_stage' => '',
                'status' => '',
                'source' => '',
                'form_identifier' => '',
                'page_url' => $context->get_page_url(),
                'http_method' => $context->get_request_method(),
                'client_ip' => $context->get_client_ip(),
                'user_agent' => $context->get_user_agent(),
                'browser' => '',
                'os' => '',
                'recipient' => '',
                'subject' => '',
                'mailer' => '',
                'smtp_host' => '',
                'smtp_port' => '',
                'error_code' => '',
                'error_message' => '',
                'payload_json' => '',
                'attachments_json' => '',
                'extra_json' => '',
            );

            $merged = array_merge($defaults, $row);
            $ordered = array();

            foreach ($this->columns as $column) {
                $value = isset($merged[$column]) ? $merged[$column] : '';
                if (is_array($value) || is_object($value)) {
                    $value = $context->json_encode_safe($value);
                }

                $scalar_val = is_scalar($value) ? (string)$value : '';

                if ($column === 'payload_json' && $scalar_val !== '') {
                    $ordered[] = $this->crypto->encrypt($scalar_val);
                }
                else {
                    // Prevent CSV/formula injection when opened in Excel or LibreOffice
                    if ($scalar_val !== '' && preg_match('/^[=+\-@\t\r]/', $scalar_val)) {
                        $scalar_val = "'" . $scalar_val;
                    }
                    $ordered[] = $scalar_val;
                }
            }

            return $ordered;
        }
    }
}