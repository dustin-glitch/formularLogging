<?php
namespace Signalfeuer\FormularLogs\Core;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Core\Notifier')) {
    class Notifier
    {
        const COOLDOWN_TRANSIENT = 'fl_notify_cooldown';

        // event_stage fragments that indicate user/spam errors — not worth alerting on
        private $non_critical_stages = array(
            'form_validation_failed',
            'form_field_invalid',
            'captcha',
            'honeypot',
            'recaptcha',
            'hcaptcha',
            'turnstile',
            'frcaptcha',
        );

        public function maybe_notify(array $row)
        {
            if (!get_option('fl_notify_enabled', false)) {
                return;
            }

            if (!$this->is_critical($row)) {
                return;
            }

            if ($this->is_on_cooldown()) {
                return;
            }

            $this->set_cooldown();

            $email_raw = trim((string) get_option('fl_notify_email', ''));
            $slack_url  = trim((string) get_option('fl_notify_slack_webhook', ''));

            if ($email_raw !== '') {
                $this->send_email($row, $email_raw);
            }

            if ($slack_url !== '') {
                $this->send_slack($row, $slack_url);
            }
        }

        private function is_critical(array $row)
        {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $stage  = isset($row['event_stage']) ? strtolower((string) $row['event_stage']) : '';

            // Mail failures are always critical
            if ($status === 'failed') {
                return true;
            }

            if ($status === 'error') {
                // Skip expected user/spam errors
                foreach ($this->non_critical_stages as $fragment) {
                    if (strpos($stage, $fragment) !== false) {
                        return false;
                    }
                }
                return true;
            }

            return false;
        }

        private function is_on_cooldown()
        {
            return (bool) get_transient(self::COOLDOWN_TRANSIENT);
        }

        private function set_cooldown()
        {
            $minutes = max(1, (int) get_option('fl_notify_cooldown', 15));
            set_transient(self::COOLDOWN_TRANSIENT, 1, $minutes * MINUTE_IN_SECONDS);
        }

        private function send_email(array $row, $email_raw)
        {
            $lines = preg_split('/\r\n|\r|\n/', $email_raw);
            $recipients = array();
            foreach ((array) $lines as $line) {
                $addr = sanitize_email(trim((string) $line));
                if ($addr !== '') {
                    $recipients[] = $addr;
                }
            }

            if (empty($recipients)) {
                return;
            }

            $site    = get_bloginfo('name');
            $subject = '[' . $site . '] Formular Logging: Kritischer Fehler';

            $status  = isset($row['status']) ? esc_html($row['status']) : '';
            $stage   = isset($row['event_stage']) ? esc_html($row['event_stage']) : '';
            $req_id  = isset($row['request_id']) ? esc_html($row['request_id']) : '';
            $err_msg = isset($row['error_message']) ? esc_html($row['error_message']) : '';
            $page    = isset($row['page_url']) ? esc_html($row['page_url']) : '';
            $time    = isset($row['timestamp_utc']) ? esc_html($row['timestamp_utc']) : gmdate('c');
            $ip      = isset($row['client_ip']) ? esc_html($row['client_ip']) : '';

            $admin_url = admin_url('admin.php?page=formular-logs&request_id=' . rawurlencode($req_id));

            $body  = "Ein kritischer Fehler wurde im Formular Logging erfasst.\n\n";
            $body .= "Zeitpunkt:    {$time}\n";
            $body .= "Status:       {$status}\n";
            $body .= "Stage:        {$stage}\n";
            $body .= "Request ID:   {$req_id}\n";
            $body .= "Seite:        {$page}\n";
            $body .= "IP:           {$ip}\n";
            if ($err_msg !== '') {
                $body .= "Fehlermeldung: {$err_msg}\n";
            }
            $body .= "\nLog ansehen: {$admin_url}\n";

            wp_mail($recipients, $subject, $body);
        }

        private function send_slack(array $row, $webhook_url)
        {
            $site    = get_bloginfo('name');
            $status  = isset($row['status']) ? (string) $row['status'] : '';
            $stage   = isset($row['event_stage']) ? (string) $row['event_stage'] : '';
            $req_id  = isset($row['request_id']) ? (string) $row['request_id'] : '';
            $err_msg = isset($row['error_message']) ? (string) $row['error_message'] : '';
            $page    = isset($row['page_url']) ? (string) $row['page_url'] : '';
            $time    = isset($row['timestamp_utc']) ? (string) $row['timestamp_utc'] : gmdate('c');
            $admin_url = admin_url('admin.php?page=formular-logs&request_id=' . rawurlencode($req_id));

            $text  = "*[{$site}] Formular Logging: Kritischer Fehler*\n";
            $text .= ">*Zeit:* {$time}\n";
            $text .= ">*Status:* {$status}  |  *Stage:* {$stage}\n";
            $text .= ">*Request ID:* `{$req_id}`\n";
            $text .= ">*Seite:* {$page}\n";
            if ($err_msg !== '') {
                $text .= ">*Fehler:* {$err_msg}\n";
            }
            $text .= "><{$admin_url}|Log ansehen>";

            wp_remote_post($webhook_url, array(
                'headers'     => array('Content-Type' => 'application/json'),
                'body'        => wp_json_encode(array('text' => $text)),
                'timeout'     => 5,
                'blocking'    => false,
            ));
        }
    }
}
