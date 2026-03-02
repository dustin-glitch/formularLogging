<?php
/**
 * Plugin Name: Formular Logging
 * Plugin URI: https://example.com/
 * Description: End-to-end logging for form submissions and mail delivery into daily CSV files.
 * Version: 1.0.0
 * Author: Dustin
 * License: GPLv2 or later
 * Text Domain: formular-logging
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FL_FORMULAR_LOGGING_VERSION')) {
    define('FL_FORMULAR_LOGGING_VERSION', '1.0.0');
}

if (!defined('FL_FORMULAR_LOGGING_PLUGIN_FILE')) {
    define('FL_FORMULAR_LOGGING_PLUGIN_FILE', __FILE__);
}

if (!defined('FL_FORMULAR_LOGGING_PLUGIN_DIR')) {
    define('FL_FORMULAR_LOGGING_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('FL_FORMULAR_LOGGING_PLUGIN_URL')) {
    define('FL_FORMULAR_LOGGING_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!function_exists('fl_formular_logging_activate')) {
    function fl_formular_logging_activate()
    {
        if (!wp_next_scheduled('fl_cleanup_logs_daily')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'fl_cleanup_logs_daily');
        }
    }
}

if (!function_exists('fl_formular_logging_deactivate')) {
    function fl_formular_logging_deactivate()
    {
        wp_clear_scheduled_hook('fl_cleanup_logs_daily');
    }
}

register_activation_hook(__FILE__, 'fl_formular_logging_activate');
register_deactivation_hook(__FILE__, 'fl_formular_logging_deactivate');

require_once FL_FORMULAR_LOGGING_PLUGIN_DIR . 'autoloader.php';
require_once FL_FORMULAR_LOGGING_PLUGIN_DIR . 'plugin-update-checker-5.6/plugin-update-checker.php';

use Signalfeuer\FormularLogs\Core\Plugin;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

Plugin::instance();

// -----------------------------------------------------------------------------
// Update Checker Configuration
// -----------------------------------------------------------------------------
$fl_github_token = trim(get_option('fl_github_update_token', ''));

$flUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/dustin-glitch/formularLogging',
    __FILE__,
    'formular-logging'
);

$flUpdateChecker->setBranch('main');

if (!empty($fl_github_token)) {
    $flUpdateChecker->setAuthentication($fl_github_token);
}