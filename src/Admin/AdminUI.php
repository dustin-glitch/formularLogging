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
            $stats         = $this->storage->get_aggregated_stats_for_last_days(7);
            $today_success = (int) end($stats['success']);
            $today_errors  = (int) end($stats['errors']);
            $today_total   = $today_success + $today_errors;
            $week_success  = array_sum($stats['success']);
            $week_errors   = array_sum($stats['errors']);
            $error_rate    = ($today_total > 0) ? round($today_errors / $today_total * 100) : 0;
            $today_label   = wp_date('d.m.Y');
            ?>
            <div class="fl-widget">
                <div class="fl-widget__today">
                    <div class="fl-widget__stat fl-widget__stat--total">
                        <span class="fl-widget__num"><?php echo esc_html($today_total); ?></span>
                        <span class="fl-widget__label">Anfragen heute<br><small><?php echo esc_html($today_label); ?></small></span>
                    </div>
                    <div class="fl-widget__divider"></div>
                    <div class="fl-widget__stat">
                        <span class="fl-widget__num fl-widget__num--success"><?php echo esc_html($today_success); ?></span>
                        <span class="fl-widget__label">Erfolgreich</span>
                    </div>
                    <div class="fl-widget__stat">
                        <span class="fl-widget__num fl-widget__num--error"><?php echo esc_html($today_errors); ?></span>
                        <span class="fl-widget__label">Fehler</span>
                    </div>
                    <?php if ($today_total > 0) : ?>
                    <div class="fl-widget__stat">
                        <span class="fl-widget__num fl-widget__num--<?php echo $error_rate > 20 ? 'error' : 'neutral'; ?>"><?php echo esc_html($error_rate); ?>%</span>
                        <span class="fl-widget__label">Fehlerrate</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="fl-widget__chart-wrap">
                    <canvas id="fl-stats-chart"></canvas>
                </div>

                <script>var flStatsData = <?php echo wp_json_encode($stats); ?>;</script>

                <div class="fl-widget__week">
                    <span class="fl-widget__week-label">7 Tage gesamt:</span>
                    <span class="fl-widget__week-val fl-widget__num--success"><?php echo esc_html($week_success); ?> ✓</span>
                    <span class="fl-widget__week-val fl-widget__num--error"><?php echo esc_html($week_errors); ?> ✗</span>
                </div>

                <div class="fl-widget__footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=formular-logs')); ?>" class="sf-btn-primary">Logs öffnen</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=formular-logs-about')); ?>" class="sf-btn-secondary">Info & Update</a>
                </div>
            </div>
            <?php
        }

        public function enqueue_admin_assets($hook_suffix)
        {
            if (in_array($hook_suffix, array('toplevel_page_formular-logs', 'formular-logs_page_formular-logs', 'formular-logs_page_formular-logs-about', 'index.php'), true)) {
                wp_enqueue_style(
                    'fl-admin-style',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/css/admin.css',
                    array(),
                    file_exists(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') ? filemtime(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') : FL_FORMULAR_LOGGING_VERSION
                );

                wp_enqueue_script(
                    'chart-js',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/js/chart.umd.min.js',
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

            add_submenu_page(
                'formular-logs',
                'Info & Update',
                'Info & Update',
                'manage_options',
                'formular-logs-about',
                array($this, 'render_about_page')
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

        /** Persistent plugin header shown on every plugin page */
        public static function render_plugin_header($current = 'logs')
        {
            $tabs = array(
                'logs'     => array('label' => 'Logs',           'url' => admin_url('admin.php?page=formular-logs')),
                'settings' => array('label' => 'Einstellungen',  'url' => admin_url('admin.php?page=formular-logging-settings')),
                'about'    => array('label' => 'Info & Update',  'url' => admin_url('admin.php?page=formular-logs-about')),
            );
            ?>
            <div class="sf-plugin-header">
                <div class="sf-plugin-header__brand">
                    <span class="dashicons dashicons-list-view"></span>
                    <span class="sf-plugin-header__name">Formular Logging</span>
                    <span class="sf-version-badge">v<?php echo esc_html(FL_FORMULAR_LOGGING_VERSION); ?></span>
                </div>
                <nav class="sf-plugin-header__nav" aria-label="Plugin-Navigation">
                    <?php foreach ($tabs as $key => $tab) : ?>
                        <a href="<?php echo esc_url($tab['url']); ?>"
                           class="sf-plugin-header__tab<?php echo $current === $key ? ' sf-plugin-header__tab--active' : ''; ?>">
                            <?php echo esc_html($tab['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php
        }

        /** Human-readable column labels */
        private $column_labels = array(
            'timestamp_utc'   => 'Zeitstempel',
            'request_id'      => 'Request-ID',
            'event_type'      => 'Typ',
            'event_stage'     => 'Stage',
            'status'          => 'Status',
            'source'          => 'Quelle',
            'form_identifier' => 'Formular',
            'page_url'        => 'Seite',
            'http_method'     => 'Methode',
            'client_ip'       => 'IP',
            'user_agent'      => 'User Agent',
            'browser'         => 'Browser',
            'os'              => 'OS',
            'recipient'       => 'Empfänger',
            'subject'         => 'Betreff',
            'mailer'          => 'Mailer',
            'smtp_host'       => 'SMTP Host',
            'smtp_port'       => 'SMTP Port',
            'error_code'      => 'Fehlercode',
            'error_message'   => 'Fehlermeldung',
            'payload_json'    => 'Payload',
            'attachments_json'=> 'Anhänge',
            'extra_json'      => 'Extra',
        );

        /** Short request ID: first 8 chars */
        private function short_id($req_id)
        {
            return strpos($req_id, 'unknown_') === 0 ? '–' : substr($req_id, 0, 8) . '…';
        }

        /** Format ISO timestamp to readable local time */
        private function format_time($ts)
        {
            if ($ts === '') return '';
            $t = strtotime($ts);
            return $t ? wp_date('d.m.Y · H:i:s', $t) : esc_html($ts);
        }

        /** Status dot class */
        private function status_dot_class($status)
        {
            switch ($status) {
                case 'success': return 'sf-dot--success';
                case 'error':   return 'sf-dot--error';
                case 'failed':  return 'sf-dot--error';
                case 'info':    return 'sf-dot--info';
                case 'started': return 'sf-dot--started';
                default:        return 'sf-dot--unknown';
            }
        }

        public function render_admin_page()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $per_page = 25;
            $today    = wp_date('Y-m-d');

            $legacy_date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';
            $date_from   = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : $legacy_date;
            $date_to     = isset($_GET['date_to'])   ? sanitize_text_field(wp_unslash($_GET['date_to']))   : '';

            if (!$this->is_valid_date($date_from)) $date_from = $today;
            if (!$this->is_valid_date($date_to) || $date_to < $date_from) $date_to = $date_from;

            $date_from_ts = strtotime($date_from);
            $date_to_ts   = min(strtotime($date_to), $date_from_ts + 13 * DAY_IN_SECONDS);
            $date_to      = wp_date('Y-m-d', $date_to_ts);

            $request_id = isset($_GET['request_id']) ? sanitize_text_field(wp_unslash($_GET['request_id'])) : '';
            $status     = isset($_GET['status'])     ? sanitize_text_field(wp_unslash($_GET['status']))     : '';
            $event_type = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';
            $paged      = isset($_GET['paged'])      ? max(1, (int)$_GET['paged'])                         : 1;

            $filters = array('request_id' => $request_id, 'status' => $status, 'event_type' => $event_type);
            $rows    = array();
            $current = $date_from_ts;
            while ($current <= $date_to_ts && count($rows) < 5000) {
                $date_str = wp_date('Y-m-d', $current);
                $path     = $this->storage->get_daily_log_path($date_str);
                if (file_exists($path)) {
                    $rows = array_merge($rows, $this->storage->read_filtered_rows($path, $filters, 5000));
                }
                $current += DAY_IN_SECONDS;
            }

            $download_url = wp_nonce_url(add_query_arg(
                array('page' => 'formular-logs', 'date' => $date_from, 'fl_download' => '1'),
                admin_url('admin.php')
            ), 'fl_download_csv');

            $cleanup_url = wp_nonce_url(add_query_arg(
                array('page' => 'formular-logs', 'fl_cleanup' => '1'),
                admin_url('admin.php')
            ), 'fl_manual_cleanup');

            $pagination_base = add_query_arg(array(
                'page' => 'formular-logs', 'date_from' => $date_from, 'date_to' => $date_to,
                'request_id' => $request_id, 'status' => $status, 'event_type' => $event_type,
            ), admin_url('admin.php'));

            // Build groups
            $all_grouped = array();
            foreach ($rows as $row) {
                $req = isset($row['request_id']) && $row['request_id'] !== '' ? $row['request_id'] : 'unknown_' . uniqid();
                if (!isset($all_grouped[$req])) {
                    $all_grouped[$req] = array('rows' => array(), 'level' => -1, 'badge' => '', 'time' => '', 'page' => '', 'form' => '');
                }
                $all_grouped[$req]['rows'][] = $row;
                if (empty($all_grouped[$req]['time']) && !empty($row['timestamp_utc'])) {
                    $all_grouped[$req]['time'] = $row['timestamp_utc'];
                }
                if (empty($all_grouped[$req]['page']) && !empty($row['page_url'])) {
                    $all_grouped[$req]['page'] = $row['page_url'];
                }
                if (empty($all_grouped[$req]['form']) && !empty($row['form_identifier'])) {
                    $all_grouped[$req]['form'] = $row['form_identifier'];
                }

                $badgeHtml = $this->classify_problem($row);
                $level = 1;
                if (strpos($badgeHtml, 'JS Fehler') !== false || strpos($badgeHtml, 'System-/Mailerfehler') !== false) {
                    $level = 3;
                } elseif (strpos($badgeHtml, 'Nutzer/Validierung') !== false) {
                    $level = 2;
                } elseif (strpos($badgeHtml, 'Erfolgreich / Info') !== false) {
                    $level = 0;
                }
                if ($level > $all_grouped[$req]['level']) {
                    $all_grouped[$req]['level'] = $level;
                    $all_grouped[$req]['badge'] = $badgeHtml;
                }
            }

            $all_grouped  = array_reverse($all_grouped, true);
            $total_groups = count($all_grouped);
            $total_pages  = $total_groups > 0 ? (int)ceil($total_groups / $per_page) : 1;
            $paged        = min($paged, $total_pages);
            $grouped      = array_slice($all_grouped, ($paged - 1) * $per_page, $per_page, true);

            $status_options = array(
                ''        => 'Alle Status',
                'success' => 'Erfolgreich',
                'error'   => 'Fehler',
                'failed'  => 'Fehlgeschlagen',
                'info'    => 'Info',
                'started' => 'Gestartet',
            );
            $type_options = array(
                ''                  => 'Alle Typen',
                'mail_event'        => 'Mail',
                'frontend_ajax'     => 'Frontend AJAX',
                'form_engine_hook'  => 'Form Engine',
                'frontend_js_error' => 'JS Fehler',
            );
            ?>
            <div class="wrap sf-wrap">
                <?php self::render_plugin_header('logs'); ?>

                <?php /* ---- Filter Bar ---- */ ?>
                <form method="get" class="sf-log-filter">
                    <input type="hidden" name="page" value="formular-logs" />
                    <div class="sf-log-filter__fields">
                        <div class="sf-log-filter__group">
                            <label for="fl-date-from">Von</label>
                            <input type="date" id="fl-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                        </div>
                        <div class="sf-log-filter__sep">→</div>
                        <div class="sf-log-filter__group">
                            <label for="fl-date-to">Bis</label>
                            <input type="date" id="fl-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                        </div>
                        <div class="sf-log-filter__divider"></div>
                        <div class="sf-log-filter__group">
                            <label for="fl-request-id">Request-ID</label>
                            <input type="text" id="fl-request-id" name="request_id" value="<?php echo esc_attr($request_id); ?>" placeholder="a3f2b1…" />
                        </div>
                        <div class="sf-log-filter__group">
                            <label for="fl-status">Status</label>
                            <select id="fl-status" name="status">
                                <?php foreach ($status_options as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sf-log-filter__group">
                            <label for="fl-event-type">Typ</label>
                            <select id="fl-event-type" name="event_type">
                                <?php foreach ($type_options as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($event_type, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="sf-log-filter__actions">
                        <button type="submit" class="sf-btn-primary">Filtern</button>
                        <a class="sf-btn-secondary" href="<?php echo esc_url($download_url); ?>">
                            <span class="dashicons dashicons-download"></span> CSV
                        </a>
                        <a class="sf-btn-secondary sf-btn-danger" href="<?php echo esc_url($cleanup_url); ?>"
                           onclick="return confirm('Veraltete Logs jetzt unwiderruflich löschen?');">
                            <span class="dashicons dashicons-trash"></span> Bereinigen
                        </a>
                    </div>
                </form>

                <?php /* ---- Stats + Pagination Bar ---- */ ?>
                <div class="sf-log-bar">
                    <span class="sf-log-bar__count">
                        <?php if ($total_groups > 0) : ?>
                            <strong><?php echo esc_html($total_groups); ?></strong> Anfrage<?php echo $total_groups !== 1 ? 'n' : ''; ?>
                            <?php if ($total_pages > 1) : ?>
                                &middot; Seite <?php echo esc_html($paged); ?> von <?php echo esc_html($total_pages); ?>
                            <?php endif; ?>
                        <?php else : ?>
                            Keine Einträge
                        <?php endif; ?>
                    </span>
                    <?php if ($total_pages > 1) : ?>
                    <div class="sf-log-bar__pagination">
                        <?php if ($paged > 1) : ?>
                            <a class="sf-page-btn" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $pagination_base)); ?>">&#8592; Zurück</a>
                        <?php endif; ?>
                        <?php if ($paged < $total_pages) : ?>
                            <a class="sf-page-btn" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $pagination_base)); ?>">Weiter &#8594;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php /* ---- Log Groups ---- */ ?>
                <?php if (empty($all_grouped)) : ?>
                    <div class="sf-empty-state">
                        <span class="dashicons dashicons-search"></span>
                        <p>Keine Log-Einträge für den gewählten Zeitraum und Filter.</p>
                    </div>
                <?php else : ?>
                    <div class="sf-log-groups">
                    <?php foreach ($grouped as $req_id => $group) :
                        $is_error   = $group['level'] >= 3;
                        $is_warning = $group['level'] === 2;
                        $group_class = 'sf-log-group';
                        if ($is_error)        $group_class .= ' sf-log-group--error';
                        elseif ($is_warning)  $group_class .= ' sf-log-group--warning';
                        else                  $group_class .= ' sf-log-group--success';
                        $open = $is_error || $is_warning;
                        $short = $this->short_id($req_id);
                        $time  = $this->format_time($group['time']);
                        $page  = $group['page'];
                        $form  = $group['form'];
                        // Parse page path only
                        $page_display = $page !== '' ? (wp_parse_url($page, PHP_URL_PATH) ?: $page) : '';
                    ?>
                        <div class="<?php echo esc_attr($group_class); ?>" data-open="<?php echo $open ? '1' : '0'; ?>">
                            <button type="button" class="sf-log-group__header" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
                                <span class="sf-log-group__badge">
                                    <?php echo wp_kses($group['badge'], array('span' => array('class' => array(), 'style' => array()))); ?>
                                </span>
                                <span class="sf-log-group__meta">
                                    <?php if ($time !== '') : ?><span class="sf-log-group__time"><?php echo esc_html($time); ?></span><?php endif; ?>
                                    <?php if ($page_display !== '') : ?><span class="sf-log-group__page"><?php echo esc_html($page_display); ?></span><?php endif; ?>
                                    <?php if ($form !== '') : ?><span class="sf-log-group__form"><?php echo esc_html($form); ?></span><?php endif; ?>
                                </span>
                                <span class="sf-log-group__id" title="<?php echo esc_attr($req_id); ?>"><?php echo esc_html($short); ?></span>
                                <span class="sf-log-group__chevron">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </span>
                            </button>
                            <div class="sf-log-group__body">
                                <ol class="sf-timeline">
                                <?php foreach ($group['rows'] as $row) :
                                    $row_status  = isset($row['status']) ? $row['status'] : '';
                                    $row_stage   = isset($row['event_stage']) ? $row['event_stage'] : '';
                                    $row_source  = isset($row['source']) ? $row['source'] : '';
                                    $row_time    = isset($row['timestamp_utc']) ? $row['timestamp_utc'] : '';
                                    $row_err_msg = isset($row['error_message']) ? $row['error_message'] : '';
                                    $row_err_code= isset($row['error_code']) ? $row['error_code'] : '';
                                    $row_recip   = isset($row['recipient']) ? $row['recipient'] : '';
                                    $row_subject = isset($row['subject']) ? $row['subject'] : '';
                                    $dot_class   = $this->status_dot_class($row_status);
                                    $time_str    = $row_time !== '' ? substr($row_time, 11, 8) : '';
                                    $payload     = isset($row['payload_json']) ? $row['payload_json'] : '';
                                    $extra       = isset($row['extra_json']) ? $row['extra_json'] : '';
                                    $attachments = isset($row['attachments_json']) ? $row['attachments_json'] : '';
                                ?>
                                    <li class="sf-timeline__item">
                                        <span class="sf-dot <?php echo esc_attr($dot_class); ?>"></span>
                                        <div class="sf-timeline__content">
                                            <div class="sf-timeline__main">
                                                <?php if ($time_str !== '') : ?><span class="sf-timeline__time"><?php echo esc_html($time_str); ?></span><?php endif; ?>
                                                <span class="sf-timeline__stage"><?php echo esc_html($row_stage !== '' ? $row_stage : '–'); ?></span>
                                                <?php if ($row_source !== '') : ?><span class="sf-timeline__source"><?php echo esc_html($row_source); ?></span><?php endif; ?>
                                                <span class="sf-timeline__status sf-status--<?php echo esc_attr($row_status); ?>"><?php echo esc_html($row_status); ?></span>
                                            </div>
                                            <?php if ($row_err_msg !== '' || $row_err_code !== '') : ?>
                                            <div class="sf-timeline__error">
                                                <?php if ($row_err_code !== '') : ?><code><?php echo esc_html($row_err_code); ?></code><?php endif; ?>
                                                <?php if ($row_err_msg !== '') : ?><span><?php echo esc_html($row_err_msg); ?></span><?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($row_recip !== '' || $row_subject !== '') : ?>
                                            <div class="sf-timeline__mail">
                                                <?php if ($row_recip !== '') : ?><span class="sf-timeline__mail-recip"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html($row_recip); ?></span><?php endif; ?>
                                                <?php if ($row_subject !== '') : ?><span class="sf-timeline__mail-subject"><?php echo esc_html($row_subject); ?></span><?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($payload !== '' || $extra !== '' || $attachments !== '') : ?>
                                            <div class="sf-timeline__json-btns">
                                                <?php if ($payload !== '') :
                                                    $dec = $this->crypto->decrypt($payload); ?>
                                                    <button type="button" class="sf-json-btn fl-view-json" data-label="Payload" data-json="<?php echo esc_attr($dec); ?>">Payload</button>
                                                <?php endif; ?>
                                                <?php if ($extra !== '') : ?>
                                                    <button type="button" class="sf-json-btn fl-view-json" data-label="Extra" data-json="<?php echo esc_attr($extra); ?>">Extra</button>
                                                <?php endif; ?>
                                                <?php if ($attachments !== '') : ?>
                                                    <button type="button" class="sf-json-btn fl-view-json" data-label="Anhänge" data-json="<?php echo esc_attr($attachments); ?>">Anhänge</button>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                    <div class="sf-log-bar sf-log-bar--bottom">
                        <span class="sf-log-bar__count">Seite <?php echo esc_html($paged); ?> von <?php echo esc_html($total_pages); ?></span>
                        <div class="sf-log-bar__pagination">
                            <?php if ($paged > 1) : ?>
                                <a class="sf-page-btn" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $pagination_base)); ?>">&#8592; Zurück</a>
                            <?php endif; ?>
                            <?php if ($paged < $total_pages) : ?>
                                <a class="sf-page-btn" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $pagination_base)); ?>">Weiter &#8594;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div id="fl-json-modal" class="sf-modal">
                <div class="sf-modal-content">
                    <div class="sf-modal-header">
                        <h2 id="fl-modal-title">JSON</h2>
                        <button id="fl-modal-close" class="sf-modal-close" aria-label="Schließen">&times;</button>
                    </div>
                    <div id="fl-modal-summary"></div>
                    <pre><code id="fl-modal-content"></code></pre>
                </div>
            </div>
            <?php
        }

        public function render_about_page()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $version    = FL_FORMULAR_LOGGING_VERSION;
            $php_ok     = version_compare(PHP_VERSION, '7.4', '>=');
            $wp_version = get_bloginfo('version');
            $wp_ok      = version_compare($wp_version, '5.8', '>=');
            $ssl_ok     = function_exists('openssl_encrypt');
            $log_dir    = $this->storage->get_log_dir();
            $log_ok     = is_dir($log_dir) && is_writable($log_dir);
            $token_set  = defined('FL_GITHUB_UPDATE_TOKEN') ? true : !empty(get_option('fl_github_update_token', ''));

            $changelog = array(
                '1.3.1' => array(
                    'date'  => '2026-03',
                    'items' => array(
                        'Fix: Update-Card Layout im Info-Panel korrigiert',
                    ),
                ),
                '1.3.0' => array(
                    'date'  => '2026-03',
                    'items' => array(
                        'Dashboard-Widget neu gestaltet: Fehlerrate, 7-Tage-Übersicht',
                        'Neue "Info & Update"-Seite mit System-Check und Changelog',
                        'Persistente Plugin-Navigation auf allen Seiten',
                        'Einstellungen full width',
                    ),
                ),
                '1.2.0' => array(
                    'date'  => '2026-03',
                    'items' => array(
                        'Fehler-Benachrichtigungen per E-Mail und Slack',
                        'Datumsbereich-Filter (bis zu 14 Tage)',
                        'Log-Seite komplett neu gestaltet: Accordion, Timeline, Status-Dots',
                        'Settings-Seite mit Card-Layout und Toggle-Switches',
                        'Security: Rate Limiting AJAX-Bypass gefixt, Payload-Größenlimit (64 KB)',
                        'CSV-Injection-Schutz, Stats-Caching, Chart.js lokal gebündelt',
                    ),
                ),
                '1.1.0' => array(
                    'date'  => '2025',
                    'items' => array(
                        'Permanentes IP-Blocking',
                        'Dashboard-Widget mit Chart.js',
                        'AES-256-CBC Payload-Verschlüsselung',
                    ),
                ),
                '1.0.0' => array(
                    'date'  => '2025',
                    'items' => array(
                        'Erste stabile Version',
                        'End-to-End-Logging, Request-ID-Gruppierung',
                        'Rate Limiting, CSV-Export',
                    ),
                ),
            );

            $update_url = admin_url('update-core.php');
            $force_url  = add_query_arg('force-check', '1', $update_url);
            ?>
            <div class="wrap sf-wrap sf-about">
                <?php self::render_plugin_header('about'); ?>

                <div class="sf-about-grid">
                    <div class="sf-about-main">

                        <?php /* ---- Changelog ---- */ ?>
                        <div class="sf-card">
                            <div class="sf-card-header">
                                <div class="sf-card-icon sf-card-icon--blue">
                                    <span class="dashicons dashicons-update"></span>
                                </div>
                                <div>
                                    <h2>Changelog</h2>
                                    <p>Versionshistorie und neue Features</p>
                                </div>
                            </div>
                            <div class="sf-card-body sf-card-body--flush">
                                <?php foreach ($changelog as $ver => $release) :
                                    $is_current = ($ver === $version);
                                ?>
                                <div class="sf-changelog-release<?php echo $is_current ? ' sf-changelog-release--current' : ''; ?>">
                                    <div class="sf-changelog-release__header">
                                        <span class="sf-changelog-ver">v<?php echo esc_html($ver); ?></span>
                                        <?php if ($is_current) : ?>
                                            <span class="sf-badge sf-badge-success" style="font-size:10px; padding:2px 7px;">Aktuell</span>
                                        <?php endif; ?>
                                        <span class="sf-changelog-date"><?php echo esc_html($release['date']); ?></span>
                                    </div>
                                    <ul class="sf-changelog-items">
                                        <?php foreach ($release['items'] as $item) : ?>
                                            <li><?php echo esc_html($item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                    <div class="sf-about-side">

                        <?php /* ---- System-Check ---- */ ?>
                        <div class="sf-card">
                            <div class="sf-card-header">
                                <div class="sf-card-icon sf-card-icon--orange">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                </div>
                                <div>
                                    <h2>System-Check</h2>
                                    <p>Kompatibilität und Umgebung</p>
                                </div>
                            </div>
                            <div class="sf-card-body sf-card-body--flush">
                                <?php
                                $checks = array(
                                    array('label' => 'PHP-Version', 'value' => PHP_VERSION, 'ok' => $php_ok, 'req' => '≥ 7.4'),
                                    array('label' => 'WordPress', 'value' => $wp_version, 'ok' => $wp_ok, 'req' => '≥ 5.8'),
                                    array('label' => 'OpenSSL (AES)', 'value' => $ssl_ok ? 'Verfügbar' : 'Fehlt', 'ok' => $ssl_ok, 'req' => 'Pflicht'),
                                    array('label' => 'Log-Verzeichnis', 'value' => $log_ok ? 'Beschreibbar' : 'Nicht beschreibbar', 'ok' => $log_ok, 'req' => 'Pflicht'),
                                    array('label' => 'GitHub-Token', 'value' => $token_set ? 'Gesetzt' : 'Nicht gesetzt', 'ok' => $token_set, 'req' => 'Für Updates'),
                                );
                                foreach ($checks as $i => $check) :
                                    $last = ($i === count($checks) - 1);
                                ?>
                                <div class="sf-syscheck<?php echo $last ? ' sf-setting-row--last' : ''; ?>">
                                    <span class="sf-syscheck__label"><?php echo esc_html($check['label']); ?></span>
                                    <span class="sf-syscheck__value"><?php echo esc_html($check['value']); ?></span>
                                    <span class="sf-syscheck__status sf-syscheck__status--<?php echo $check['ok'] ? 'ok' : 'fail'; ?>">
                                        <?php echo $check['ok'] ? '✓' : '✗'; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php /* ---- Updates ---- */ ?>
                        <div class="sf-card">
                            <div class="sf-card-header">
                                <div class="sf-card-icon sf-card-icon--blue">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                </div>
                                <div>
                                    <h2>Plugin-Updates</h2>
                                    <p>Updates via GitHub (Plugin Update Checker)</p>
                                </div>
                            </div>
                            <div class="sf-card-body">
                                <div class="sf-about-update-row">
                                    <div>
                                        <span class="sf-version-badge">v<?php echo esc_html($version); ?></span>
                                        <span class="sf-hint" style="margin-top:4px;">Branch: <code>main</code></span>
                                    </div>
                                    <a href="<?php echo esc_url($force_url); ?>" class="sf-btn-secondary">
                                        <span class="dashicons dashicons-update"></span> Jetzt prüfen
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <?php
        }

        private function is_valid_date($date)
        {
            $date = (string)$date;
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
                return false;
            }
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
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