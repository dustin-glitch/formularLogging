<?php
namespace Signalfeuer\FormularLogs\Admin;

use Signalfeuer\FormularLogs\Storage\LogStorage;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Admin\AdminUI')) {
    class AdminUI
    {
        /** @var LogStorage */
        private $storage;

        /** @var \Signalfeuer\FormularLogs\Core\Crypto */
        private $crypto;

        public function __construct(LogStorage $storage, \Signalfeuer\FormularLogs\Core\Crypto $crypto)
        {
            $this->storage = $storage;
            $this->crypto = $crypto;
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
        }

        public function register_dashboard_widget()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            wp_add_dashboard_widget(
                'fl_dashboard_widget',
                'Formular Logs: Übersicht',
                array($this, 'render_dashboard_widget')
            );
        }

        public function render_dashboard_widget()
        {
            $stats = $this->storage->get_aggregated_stats_for_last_days(7);

            $today_success = end($stats['success']);
            $today_errors = end($stats['errors']);
            $today_total = $today_success + $today_errors;
            $today_date = wp_date('Y-m-d');

            echo '<div style="background:#f6f7f7; padding:15px; border-radius:4px; text-align:center; border:1px solid #ccd0d4;">';
            echo '<h3 style="margin-top:0; color:#1d2327;">Heute (' . esc_html($today_date) . ')</h3>';
            echo '<p style="font-size:24px; font-weight:bold; margin:10px 0; color:#1d2327;">' . esc_html((string)$today_total) . ' <span style="font-size:14px; font-weight:normal; color:#646970;">Anfragen</span></p>';
            echo '<div style="display:flex; justify-content:space-around; margin-top:10px; padding-bottom:15px; border-bottom:1px solid #ccd0d4;">';
            echo '  <div style="flex:1;"><strong style="color:#00a32a; font-size:18px;">' . esc_html((string)$today_success) . '</strong><br><span style="color:#646970; font-size:12px;">Erfolgreich</span></div>';
            echo '  <div style="flex:1;"><strong style="color:#d63638; font-size:18px;">' . esc_html((string)$today_errors) . '</strong><br><span style="color:#646970; font-size:12px;">Fehler</span></div>';
            echo '</div>';

            echo '<div style="margin-top:15px; margin-bottom:15px;">';
            echo '<canvas id="fl-stats-chart" style="max-height: 200px; width: 100%;"></canvas>';
            echo '</div>';

            echo '<script>';
            echo 'var flStatsData = ' . wp_json_encode($stats) . ';';
            echo '</script>';

            echo '<div style="margin-top:20px;">';
            echo '  <a href="' . esc_url(admin_url('admin.php?page=formular-logs')) . '" class="button button-primary" style="background:#F26B22; border-color:#F26B22;">Zu den Logs</a>';
            echo '</div>';
            echo '</div>';
        }

        public function enqueue_admin_assets($hook_suffix)
        {
            if ($hook_suffix === 'toplevel_page_formular-logs' || $hook_suffix === 'formular-logs_page_formular-logs' || $hook_suffix === 'index.php') {
                wp_enqueue_style(
                    'fl-admin-style',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/css/admin.css',
                    array(),
                    file_exists(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') ? filemtime(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') : FL_FORMULAR_LOGGING_VERSION
                );

                wp_enqueue_script(
                    'chart-js',
                    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                    array(),
                    '4.4.1',
                    true
                );

                wp_enqueue_script(
                    'fl-admin-script',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/js/admin.js',
                    array('chart-js'),
                    file_exists(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/js/admin.js') ? filemtime(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/js/admin.js') : FL_FORMULAR_LOGGING_VERSION,
                    true
                );
            }
        }

        public function register_admin_page()
        {
            add_menu_page(
                'Formular Logs',
                'Formular Logs',
                'manage_options',
                'formular-logs',
                array($this, 'render_admin_page'),
                'dashicons-list-view',
                30
            );

            add_submenu_page(
                'formular-logs',
                'Alle Logs',
                'Alle Logs',
                'manage_options',
                'formular-logs',
                array($this, 'render_admin_page')
            );
        }

        public function maybe_handle_admin_download()
        {
            if (!is_admin() || !current_user_can('manage_options')) {
                return;
            }

            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            $download = isset($_GET['fl_download']) ? sanitize_text_field(wp_unslash($_GET['fl_download'])) : '';
            $cleanup = isset($_GET['fl_cleanup']) ? sanitize_text_field(wp_unslash($_GET['fl_cleanup'])) : '';

            if ($page !== 'formular-logs') {
                return;
            }

            if ($cleanup === '1') {
                check_admin_referer('fl_manual_cleanup');
                \Signalfeuer\FormularLogs\Core\Plugin::instance()->run_cleanup();
                wp_safe_redirect(admin_url('admin.php?page=formular-logs&fl_message=cleanup_done'));
                exit;
            }

            if ($download !== '1') {
                return;
            }

            check_admin_referer('fl_download_csv');

            $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : wp_date('Y-m-d');
            if (!$this->is_valid_date($date)) {
                wp_die('Invalid date.');
            }

            $path = $this->storage->get_daily_log_path($date);
            if (!file_exists($path)) {
                wp_die('No log file for selected date.');
            }

            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . (string)filesize($path));
            readfile($path);
            exit;
        }

        public function render_admin_notice()
        {
            if (!is_admin() || !current_user_can('manage_options')) {
                return;
            }

            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            if ($page !== 'formular-logs') {
                return;
            }

            $message = isset($_GET['fl_message']) ? sanitize_text_field(wp_unslash($_GET['fl_message'])) : '';
            if ($message === 'cleanup_done') {
                echo '<div class="notice notice-success is-dismissible"><p>Formular Logging: Veraltete Log-Dateien wurden gemäß Speicherdauer-Regel erfolgreich gelöscht.</p></div>';
            }

            $log_dir = $this->storage->get_log_dir();
            if (!is_dir($log_dir) || !is_writable($log_dir)) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Formular Logging: Log directory is not writable: ', 'formular-logging');
                echo esc_html($log_dir);
                echo '</p></div>';
            }
        }

        public function render_admin_page()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : wp_date('Y-m-d');
            if (!$this->is_valid_date($date)) {
                $date = wp_date('Y-m-d');
            }

            $request_id = isset($_GET['request_id']) ? sanitize_text_field(wp_unslash($_GET['request_id'])) : '';
            $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
            $event_type = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';

            $path = $this->storage->get_daily_log_path($date);
            $rows = $this->storage->read_filtered_rows(
                $path,
                array(
                'request_id' => $request_id,
                'status' => $status,
                'event_type' => $event_type,
            ),
                200
            );

            $download_url = wp_nonce_url(
                add_query_arg(
                array(
                'page' => 'formular-logs',
                'date' => $date,
                'fl_download' => '1',
            ),
                admin_url('admin.php')
            ),
                'fl_download_csv'
            );

            $columns = $this->storage->get_columns();

            $empty_columns = array();
            foreach ($columns as $column) {
                $empty_columns[$column] = true;
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $val = isset($row[$column]) ? (string)$row[$column] : '';
                        if ($val !== '') {
                            $empty_columns[$column] = false;
                            break;
                        }
                    }
                }
            }

            echo '<div class="wrap sf-wrap">';
            echo '<h1>Formular Logs</h1>';
            echo '<form method="get" class="sf-filter-form">';
            echo '<input type="hidden" name="page" value="formular-logs" />';
            echo '<div><label for="fl-date">Datum: </label>';
            echo '<input type="date" id="fl-date" name="date" value="' . esc_attr($date) . '" /></div>';
            echo '<div><label for="fl-request-id">Request ID: </label>';
            echo '<input type="text" id="fl-request-id" name="request_id" value="' . esc_attr($request_id) . '" /></div>';
            echo '<div><label for="fl-status">Status: </label>';
            echo '<input type="text" id="fl-status" name="status" value="' . esc_attr($status) . '" /></div>';
            echo '<div><label for="fl-event-type">Event Type: </label>';
            echo '<input type="text" id="fl-event-type" name="event_type" value="' . esc_attr($event_type) . '" /></div>';
            $cleanup_url = wp_nonce_url(
                add_query_arg(
                array(
                'page' => 'formular-logs',
                'fl_cleanup' => '1',
            ),
                admin_url('admin.php')
            ),
                'fl_manual_cleanup'
            );

            echo '<div>';
            submit_button('Filter anwenden', '', '', false, array('class' => 'sf-btn-primary'));
            echo ' <a class="sf-btn-secondary" href="' . esc_url($download_url) . '">CSV herunterladen</a>';
            echo ' <a class="sf-btn-secondary" href="' . esc_url($cleanup_url) . '" onclick="return confirm(\'Veraltete Logs (gemäß Einstellung) jetzt unwiderruflich löschen?\');">Logs bereinigen</a>';
            echo '</div>';
            echo '</form>';

            if (!file_exists($path)) {
                echo '<p>Keine Log-Datei gefunden fuer ' . esc_html($date) . '.</p>';
                echo '</div>';
                return;
            }

            echo '<table class="sf-table">';
            echo '<thead><tr>';
            echo '<th>Zusammenfassung</th>';
            foreach ($columns as $column) {
                if ($empty_columns[$column]) {
                    continue;
                }
                echo '<th>' . esc_html($column) . '</th>';
            }
            echo '</tr></thead><tbody>';

            if (empty($rows)) {
                $visible_cols = count(array_filter($empty_columns, function ($isEmpty) {
                    return !$isEmpty;
                })) + 1;
                echo '<tr><td colspan="' . esc_attr($visible_cols) . '">Keine passenden Eintraege.</td></tr>';
            }
            else {
                $grouped = array();
                foreach ($rows as $row) {
                    $req = isset($row['request_id']) && $row['request_id'] !== '' ? $row['request_id'] : 'unknown_' . uniqid();
                    if (!isset($grouped[$req])) {
                        $grouped[$req] = array('rows' => array(), 'level' => -1, 'badge' => '', 'time' => '');
                    }
                    $grouped[$req]['rows'][] = $row;

                    if (empty($grouped[$req]['time']) && !empty($row['timestamp'])) {
                        $grouped[$req]['time'] = $row['timestamp'];
                    }

                    $badgeHtml = $this->classify_problem($row);
                    $level = 1;
                    if (strpos($badgeHtml, 'JS Fehler') !== false || strpos($badgeHtml, 'System-/Mailerfehler') !== false) {
                        $level = 3;
                    }
                    elseif (strpos($badgeHtml, 'Nutzer/Validierungsfehler') !== false) {
                        $level = 2;
                    }
                    elseif (strpos($badgeHtml, 'Erfolgreich / Info') !== false) {
                        $level = 0;
                    }

                    if ($level > $grouped[$req]['level']) {
                        $grouped[$req]['level'] = $level;
                        $grouped[$req]['badge'] = $badgeHtml;
                    }
                }

                $visible_cols = count(array_filter($empty_columns, function ($isEmpty) {
                    return !$isEmpty;
                })) + 1;

                $grouped = array_reverse($grouped, true);

                $group_idx = 0;
                foreach ($grouped as $req_id => $group) {
                    $bg_color = ($group_idx % 2 === 0) ? '#ffffff' : '#f6f7f7';

                    echo '<tr class="sf-group-header">';
                    echo '<td colspan="' . esc_attr($visible_cols) . '">';
                    echo '<span class="sf-group-title">Request: ' . esc_html(strpos($req_id, 'unknown_') === 0 ? 'Unbekannt' : $req_id) . '</span> &mdash; ';
                    echo wp_kses($group['badge'], array('span' => array('class' => array()))) . ' ';
                    if (!empty($group['time'])) {
                        echo '<span class="sf-group-time">(' . esc_html($group['time']) . ')</span>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    foreach ($group['rows'] as $row) {
                        echo '<tr style="background:' . esc_attr($bg_color) . ';">';
                        echo '<td>' . wp_kses($this->classify_problem($row), array('span' => array('class' => array()))) . '</td>';
                        foreach ($columns as $column) {
                            if ($empty_columns[$column]) {
                                continue;
                            }

                            $value = isset($row[$column]) ? (string)$row[$column] : '';

                            if ($value !== '' && in_array($column, array('payload_json', 'attachments_json', 'extra_json'), true)) {
                                $decrypted = ($column === 'payload_json') ? $this->crypto->decrypt($value) : $value;
                                echo '<td style="max-width:300px;"><button type="button" class="sf-btn-secondary fl-view-json" data-json="' . esc_attr($decrypted) . '">JSON ansehen</button></td>';
                            }
                            else {
                                echo '<td style="max-width:300px;word-break:break-word;">' . esc_html($value) . '</td>';
                            }
                        }
                        echo '</tr>';
                    }

                    $group_idx++;
                }
            }

            echo '</tbody></table>';

?>
<div id="fl-json-modal" class="sf-modal">
    <div class="sf-modal-content">
        <span id="fl-modal-close" class="sf-modal-close">&times;</span>
        <h2>JSON Daten</h2>
        <pre><code id="fl-modal-content"></code></pre>
    </div>
</div>
<?php

            echo '</div>';
        }

        private function is_valid_date($date)
        {
            return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date);
        }

        private function classify_problem($row)
        {
            $status = isset($row['status']) ? (string)$row['status'] : '';
            $stage = isset($row['event_stage']) ? (string)$row['event_stage'] : '';
            $type = isset($row['event_type']) ? (string)$row['event_type'] : '';

            $extra = isset($row['extra_json']) ? (string)$row['extra_json'] : '';
            $extraDecoded = $extra !== '' ? @json_decode($extra, true) : null;

            if ($type === 'frontend_js_error') {
                return '<span class="sf-badge sf-badge-error">JS Fehler</span>';
            }

            // Check Extra JSON for Captchas & Validation Details
            $is_captcha = false;
            $has_validation_errors = false;
            $captcha_type = '';
            $failed_fields = array();

            $captcha_keywords = array(
                'recaptcha' => 'reCAPTCHA',
                'hcaptcha' => 'hCaptcha',
                'turnstile' => 'Cloudflare Turnstile',
                'frcaptcha' => 'FriendlyCaptcha',
                'friendly' => 'FriendlyCaptcha',
                'honeypot' => 'Honeypot'
            );

            if (is_array($extraDecoded)) {
                // Determine if there are general errors
                if (isset($extraDecoded['error']) && !empty($extraDecoded['error'])) {
                    $has_validation_errors = true; // Fallback so it doesn't default to system error
                }

                // Check direct field
                if (isset($extraDecoded['field'])) {
                    $has_validation_errors = true;
                    $field_val = (string)$extraDecoded['field'];
                    $failed_fields[] = $field_val;

                    foreach ($captcha_keywords as $ck => $name) {
                        if (stripos($field_val, $ck) !== false || (isset($extraDecoded['message']) && stripos((string)$extraDecoded['message'], $ck) !== false)) {
                            $is_captcha = true;
                            $captcha_type = $name;
                            break;
                        }
                    }
                }

                // Check nested validation object (e.g. YOOtheme)
                if (isset($extraDecoded['validation']) && is_array($extraDecoded['validation'])) {
                    if (!empty($extraDecoded['validation'])) {
                        $has_validation_errors = true;
                        foreach ($extraDecoded['validation'] as $v_key => $v_messages) {
                            $failed_fields[] = $v_key;

                            foreach ($captcha_keywords as $ck => $name) {
                                if (stripos((string)$v_key, $ck) !== false || (is_array($v_messages) && stripos(implode(' ', $v_messages), $ck) !== false)) {
                                    $is_captcha = true;
                                    $captcha_type = $name;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // Categorize
            if ($is_captcha) {
                return '<span class="sf-badge sf-badge-warning" style="background-color:#ffd54f !important; color:#3e2723 !important; border: 1px solid #ffca28 !important;">Spamschutz (' . esc_html($captcha_type) . ')</span>';
            }

            if ($stage === 'form_validation_failed' || $stage === 'form_field_invalid' || $has_validation_errors) {
                $desc = 'Nutzer/Validierung';
                if (!empty($failed_fields)) {
                    // Remove duplicates and limit to 2 fields + "..."
                    $failed_fields = array_unique($failed_fields);
                    if (count($failed_fields) > 2) {
                        $desc .= ' (' . esc_html($failed_fields[0]) . ', ' . esc_html($failed_fields[1]) . ', ...)';
                    }
                    else {
                        $desc .= ' (' . esc_html(implode(', ', $failed_fields)) . ')';
                    }
                }
                return '<span class="sf-badge sf-badge-warning">' . $desc . '</span>';
            }

            // In YOOessentials, submission errors that aren't validation/captcha are often API or config issues.
            // But if it reached here from frontend as form_submission_error with status error, it's a Systemfehler
            if ($status === 'error' || $status === 'failed' || $stage === 'wp_mail_failed' || $stage === 'form_submission_error' || $stage === 'mail_send_failed') {
                return '<span class="sf-badge sf-badge-error">System-/Mailerfehler</span>';
            }

            if ($status === 'info' || $status === 'success' || $status === 'started') {
                return '<span class="sf-badge sf-badge-success">Erfolgreich / Info</span>';
            }

            return '<span class="sf-badge sf-badge-unknown">Unbekannt</span>';
        }
    }
}