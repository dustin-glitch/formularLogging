<?php
namespace Signalfeuer\FormularLogs\Core;

use Signalfeuer\FormularLogs\Storage\LogStorage;
use Signalfeuer\FormularLogs\Loggers\MailLogger;
use Signalfeuer\FormularLogs\Loggers\AjaxLogger;
use Signalfeuer\FormularLogs\Admin\AdminUI;
use Signalfeuer\FormularLogs\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Core\Plugin')) {
    class Plugin
    {
        const CRON_HOOK = 'fl_cleanup_logs_daily';
        const AJAX_ACTION = 'fl_log_frontend_event';
        const NONCE_ACTION = 'fl_log_frontend_event';
        const REQUEST_FIELD = 'fl_request_id';

        /** @var Plugin|null */
        private static $instance = null;

        /** @var RequestContext */
        private $context;

        /** @var LogStorage */
        private $storage;

        /** @var MailLogger */
        private $mail_logger;

        /** @var AjaxLogger */
        private $ajax_logger;

        /** @var AdminUI */
        private $admin_ui;

        /** @var Settings */
        private $settings;

        /** @var Crypto */
        private $crypto;

        /** @var Notifier */
        private $notifier;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct()
        {
            $this->crypto = new Crypto();
            $this->notifier = new Notifier();
            $this->context = new RequestContext(self::REQUEST_FIELD);
            $this->storage = new LogStorage($this->crypto);
            $this->mail_logger = new MailLogger($this->storage, $this->context);
            $this->ajax_logger = new AjaxLogger($this->storage, $this->context, self::NONCE_ACTION);
            $this->admin_ui = new AdminUI($this->storage, $this->crypto);
            $this->settings = new Settings();

            $this->register_hooks();
        }

        private function register_hooks()
        {
            add_action('init', array($this, 'schedule_cleanup_event'));
            add_action('init', array($this, 'check_ip_block'), 1);
            add_action('fl_log_written', array($this->notifier, 'maybe_notify'));
            add_action(self::CRON_HOOK, array($this, 'run_cleanup'));

            add_filter('wp_mail', array($this->mail_logger, 'log_mail_pre_send'), 10, 1);
            add_action('phpmailer_init', array($this->mail_logger, 'log_phpmailer_init'), 10, 1);
            add_action('wp_mail_succeeded', array($this->mail_logger, 'log_mail_succeeded'), 10, 1);
            add_action('wp_mail_failed', array($this->mail_logger, 'log_mail_failed'), 10, 1);

            add_action('wp_mail_smtp_mailcatcher_send_failed', array($this->mail_logger, 'log_wp_mail_smtp_failed'), 10, 3);

            add_action('wp_ajax_' . self::AJAX_ACTION, array($this->ajax_logger, 'handle_frontend_event'));
            add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this->ajax_logger, 'handle_frontend_event'));

            add_action('wp_ajax_fl_unblock_ip', array($this, 'ajax_unblock_ip'));

            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
            add_action('plugins_loaded', array($this, 'register_optional_form_engine_hooks'), 20);
            add_action('after_setup_theme', array($this, 'register_yootheme_events'), 20);

            add_action('admin_menu', array($this->admin_ui, 'register_admin_page'));
            add_action('admin_init', array($this->admin_ui, 'maybe_handle_admin_download'));
            add_action('admin_notices', array($this->admin_ui, 'render_admin_notice'));

            add_action('admin_menu', array($this->settings, 'register_admin_page'));
            add_action('admin_init', array($this->settings, 'register_settings'));
            add_action('admin_notices', array($this->settings, 'render_empty_pages_notice'));
        }

        public function schedule_cleanup_event()
        {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
            }
        }

        public function check_ip_block()
        {
            global $pagenow;

            // Do not block admin dashboard (but not AJAX), login page, or logged-in administrators
            if ((is_admin() && !wp_doing_ajax()) || $pagenow === 'wp-login.php' || current_user_can('manage_options')) {
                return;
            }

            $ip = $this->context->get_raw_client_ip();
            if ($ip === '') {
                return;
            }

            // 1. Check permanent block
            $permanently_blocked = get_option('fl_permanently_blocked_ips', array());
            if (is_array($permanently_blocked) && isset($permanently_blocked[$ip])) {
                wp_die('Abgewiesen durch Signalfeuer Formular Logging: Diese IP-Adresse wurde permanent blockiert.', 'Access Denied', array('response' => 403));
            }

            // 2. Check temporary rate limit block
            if (!get_option('fl_rate_limit_enabled', false)) {
                return;
            }

            $block_key = 'fl_rl_block_' . md5($ip);
            if (get_transient($block_key)) {
                wp_die('Abgewiesen durch Signalfeuer Formular Logging: Zu viele Fehler in kurzer Zeit. Die IP ist vorübergehend blockiert.', 'Rate Limit Exceeded', array('response' => 429));
            }
        }

        public function track_ip_error()
        {
            $ip = $this->context->get_raw_client_ip();
            if ($ip === '') {
                return;
            }

            // Rate Limiting Logic Check
            if (!get_option('fl_rate_limit_enabled', false)) {
                return;
            }

            $transient_key = 'fl_rl_err_' . md5($ip);
            $errors = (int)get_transient($transient_key);
            $errors++;

            set_transient($transient_key, $errors, 5 * MINUTE_IN_SECONDS);

            $threshold = (int)get_option('fl_rate_limit_threshold', 20);
            if ($errors >= $threshold) {
                $action = get_option('fl_rate_limit_action', 'temporary');
                if ($action === 'permanent') {
                    $permanently_blocked = get_option('fl_permanently_blocked_ips', array());
                    if (!is_array($permanently_blocked)) {
                        $permanently_blocked = array();
                    }
                    $permanently_blocked[$ip] = array(
                        'time' => time(),
                        'reason' => 'Rate Limit (' . $threshold . ' Fehler) überschritten'
                    );
                    update_option('fl_permanently_blocked_ips', $permanently_blocked);
                }
                else {
                    $block_key = 'fl_rl_block_' . md5($ip);
                    $duration = (int)get_option('fl_rate_limit_duration', 60);
                    set_transient($block_key, 1, $duration * MINUTE_IN_SECONDS);
                }

                delete_transient($transient_key);
            }
        }

        public function ajax_unblock_ip()
        {
            check_ajax_referer('fl_unblock_ip', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Berechtigung fehlt');
            }

            $ip = isset($_POST['ip']) ? sanitize_text_field(wp_unslash($_POST['ip'])) : '';
            if ($ip === '') {
                wp_send_json_error('IP fehlt');
            }

            $blocked_ips = get_option('fl_permanently_blocked_ips', array());
            if (is_array($blocked_ips) && isset($blocked_ips[$ip])) {
                unset($blocked_ips[$ip]);
                update_option('fl_permanently_blocked_ips', $blocked_ips);
            }

            wp_send_json_success();
        }

        public function run_cleanup()
        {
            $retention_days = get_option('fl_retention_days', 30);
            $this->storage->cleanup_old_logs($retention_days);
        }

        public function enqueue_frontend_assets()
        {
            if (is_admin()) {
                return;
            }

            if (!$this->settings->should_enqueue_for_current_request()) {
                return;
            }

            $logger_rel = 'assets/js/logger.js';
            $upload_rel = 'assets/js/uploadLogger.js';

            $logger_src = apply_filters('fl_logger_script_url', FL_FORMULAR_LOGGING_PLUGIN_URL . $logger_rel);
            $upload_src = apply_filters('fl_upload_logger_script_url', FL_FORMULAR_LOGGING_PLUGIN_URL . $upload_rel);

            $logger_file = FL_FORMULAR_LOGGING_PLUGIN_DIR . $logger_rel;
            $upload_file = FL_FORMULAR_LOGGING_PLUGIN_DIR . $upload_rel;

            $logger_ver = file_exists($logger_file) ? (string)filemtime($logger_file) : FL_FORMULAR_LOGGING_VERSION;
            $upload_ver = file_exists($upload_file) ? (string)filemtime($upload_file) : FL_FORMULAR_LOGGING_VERSION;

            wp_enqueue_script('fl-logger', $logger_src, array(), $logger_ver, true);
            wp_localize_script(
                'fl-logger',
                'FLLoggerConfig',
                array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => self::AJAX_ACTION,
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'requestField' => $this->context->get_request_field(),
            )
            );

            wp_enqueue_script('fl-upload-logger', $upload_src, array('fl-logger'), $upload_ver, true);
        }

        public function register_optional_form_engine_hooks()
        {
            $candidate_hooks = apply_filters(
                'fl_form_engine_hooks',
                array(
                'essentials_form_submission',
                'essentials_form_before_send',
                'essentials_form_after_send',
                'essentials_form_validation_failed',
            )
            );

            if (!is_array($candidate_hooks)) {
                return;
            }

            foreach ($candidate_hooks as $hook) {
                $hook = (string)$hook;
                if ($hook === '') {
                    continue;
                }

                add_action(
                    $hook,
                    function () use ($hook) {
                    $args = func_get_args();
                    $this->storage->write_log(
                        array(
                        'request_id' => $this->context->resolve_request_id(),
                        'event_type' => 'form_engine_hook',
                        'event_stage' => $hook,
                        'status' => 'info',
                        'source' => 'essentials_hook',
                        'form_identifier' => $this->context->detect_form_identifier(),
                        'extra_json' => $this->context->json_encode_safe($args),
                    ),
                        $this->context
                    );
                },
                    99,
                    10
                );
            }
        }

        public function register_yootheme_events()
        {
            if (class_exists('\\YOOtheme\\Event')) {
                \YOOtheme\Event::on('form.submission', function ($response, $next = null) {
                    if (is_callable($next)) {
                        $response = $next($response);
                    }

                    $args = array();
                    if ($response && method_exists($response, 'toArray')) {
                        $args['response'] = $response->toArray();
                    }

                    $status = 'info';
                    $stage = 'yootheme_form_submission';

                    if ($response && method_exists($response, 'hasErrors') && $response->hasErrors()) {
                        $status = 'error';
                        $stage = 'form_validation_failed';
                    }

                    $this->storage->write_log(
                        array(
                        'request_id' => $this->context->resolve_request_id(),
                        'event_type' => 'form_engine_hook',
                        'event_stage' => $stage,
                        'status' => $status,
                        'source' => 'essentials_hook',
                        'form_identifier' => $this->context->detect_form_identifier(),
                        'extra_json' => $this->context->json_encode_safe($args),
                    ),
                        $this->context
                    );

                    return $response;
                });
            }
        }
    }
}
