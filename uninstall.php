<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// CSV log files are intentionally kept for audit/debug history.
// Clean up all plugin options and scheduled events.
$options = array(
    'fl_form_pages',
    'fl_retention_days',
    'fl_custom_log_dir',
    'fl_github_update_token',
    'fl_rate_limit_enabled',
    'fl_rate_limit_threshold',
    'fl_rate_limit_duration',
    'fl_rate_limit_action',
    'fl_permanently_blocked_ips',
    'fl_encryption_key',
);

foreach ($options as $option) {
    delete_option($option);
}

wp_clear_scheduled_hook('fl_cleanup_logs_daily');
