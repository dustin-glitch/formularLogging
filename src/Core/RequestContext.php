<?php
namespace Signalfeuer\FormularLogs\Core;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Core\RequestContext')) {
    class RequestContext
    {
        /** @var string */
        private $request_field;

        /** @var string */
        private $request_id_cache = '';

        public function __construct($request_field = 'fl_request_id')
        {
            $this->request_field = (string)$request_field;
        }

        public function get_request_field()
        {
            return $this->request_field;
        }

        public function set_request_id($request_id)
        {
            $request_id = sanitize_text_field((string)$request_id);
            if ($request_id !== '') {
                $this->request_id_cache = $request_id;
            }
        }

        public function resolve_request_id()
        {
            if ($this->request_id_cache !== '') {
                return $this->request_id_cache;
            }

            $candidates = array(
                isset($_REQUEST[$this->request_field]) ? sanitize_text_field(wp_unslash($_REQUEST[$this->request_field])) : '',
                isset($_REQUEST['request_id']) ? sanitize_text_field(wp_unslash($_REQUEST['request_id'])) : '',
                isset($_SERVER['HTTP_X_FORM_REQUEST_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORM_REQUEST_ID'])) : '',
            );

            foreach ($candidates as $candidate) {
                if ($candidate !== '') {
                    $this->request_id_cache = $candidate;
                    return $candidate;
                }
            }

            $this->request_id_cache = $this->generate_request_id();
            return $this->request_id_cache;
        }

        public function generate_request_id()
        {
            try {
                return bin2hex(random_bytes(16));
            }
            catch (Exception $e) {
                return uniqid('fl_', true);
            }
        }

        public function append_request_header(array $args, $request_id)
        {
            $request_header = 'X-Form-Request-ID: ' . $request_id;
            $headers = isset($args['headers']) ? $args['headers'] : array();

            if (is_string($headers)) {
                if (stripos($headers, 'X-Form-Request-ID:') === false) {
                    $headers = trim($headers);
                    $headers .= ($headers === '' ? '' : "\r\n") . $request_header;
                }
                return $headers;
            }

            if (!is_array($headers)) {
                return array($request_header);
            }

            foreach ($headers as $header) {
                if (is_array($header) && isset($header[0], $header[1])) {
                    if (strtolower((string)$header[0]) === 'x-form-request-id') {
                        return $headers;
                    }
                    continue;
                }

                if (is_string($header) && stripos($header, 'X-Form-Request-ID:') === 0) {
                    return $headers;
                }
            }

            $headers[] = $request_header;
            return $headers;
        }

        public function extract_request_id_from_phpmailer($phpmailer)
        {
            if (!is_object($phpmailer) || !isset($phpmailer->CustomHeader) || !is_array($phpmailer->CustomHeader)) {
                return '';
            }

            foreach ($phpmailer->CustomHeader as $header) {
                if (is_array($header) && isset($header[0], $header[1])) {
                    if (strtolower((string)$header[0]) === 'x-form-request-id') {
                        return sanitize_text_field((string)$header[1]);
                    }
                }
            }

            return '';
        }

        public function extract_request_id_from_headers($headers)
        {
            $headers_array = array();

            if (is_string($headers)) {
                $headers_array = preg_split('/\r\n|\r|\n/', $headers);
            }
            elseif (is_array($headers)) {
                $headers_array = $headers;
            }

            foreach ($headers_array as $header) {
                if (is_array($header) && isset($header[0], $header[1])) {
                    if (strtolower((string)$header[0]) === 'x-form-request-id') {
                        return sanitize_text_field((string)$header[1]);
                    }
                    continue;
                }

                if (is_string($header) && stripos($header, 'X-Form-Request-ID:') === 0) {
                    return sanitize_text_field(trim(substr($header, strlen('X-Form-Request-ID:'))));
                }
            }

            return '';
        }

        public function collect_request_payload()
        {
            if (empty($_POST)) {
                return array();
            }

            $payload = wp_unslash($_POST);
            if (isset($payload['nonce'])) {
                unset($payload['nonce']);
            }

            return $payload;
        }

        public function detect_form_identifier()
        {
            $keys = array('form_id', 'form-name', 'form_name', '_form_id', 'yootheme_form_id');

            foreach ($keys as $key) {
                if (isset($_REQUEST[$key])) {
                    $value = sanitize_text_field(wp_unslash($_REQUEST[$key]));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            return '';
        }

        public function read_raw_post_field($key)
        {
            if (!isset($_POST[$key])) {
                return '';
            }

            $value = wp_unslash($_POST[$key]);
            if (is_array($value) || is_object($value)) {
                return $this->json_encode_safe($value);
            }

            return (string)$value;
        }

        public function ensure_json_string($value)
        {
            if ($value === '' || $value === null) {
                return '';
            }

            if (is_array($value) || is_object($value)) {
                return $this->json_encode_safe($value);
            }

            $value = (string)$value;
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }

            return $this->json_encode_safe(array('value' => $value));
        }

        public function normalize_recipients($to)
        {
            if (is_array($to)) {
                return implode(', ', array_map('strval', $to));
            }

            return is_scalar($to) ? (string)$to : '';
        }

        public function get_request_method()
        {
            return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        }

        public function get_user_agent()
        {
            return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        }

        public function get_client_ip()
        {
            $keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
            $raw_ip = '';

            foreach ($keys as $key) {
                if (empty($_SERVER[$key])) {
                    continue;
                }

                $raw = wp_unslash($_SERVER[$key]);
                $parts = explode(',', (string)$raw);
                $candidate = trim((string)$parts[0]);
                if ($candidate !== '') {
                    $raw_ip = sanitize_text_field($candidate);
                    break;
                }
            }

            if ($raw_ip === '') {
                return '';
            }

            // Anonymize IPv4 address (GDPR) e.g., 192.168.1.*
            if (filter_var($raw_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip_parts = explode('.', $raw_ip);
                if (count($ip_parts) === 4) {
                    return $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.***';
                }
            }

            // Anonymize IPv6 address roughly
            if (filter_var($raw_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ip_parts = explode(':', $raw_ip);
                if (count($ip_parts) > 1) {
                    $ip_parts[count($ip_parts) - 1] = '****';
                    // We only strip the last segment. For rigorous IPv6 GDPR, 
                    // a regex or taking only the first 2-3 blocks is safer.
                    return implode(':', $ip_parts);
                }
            }

            return $raw_ip;
        }

        public function get_page_url()
        {
            if (isset($_POST['page_url'])) {
                return esc_url_raw(wp_unslash($_POST['page_url']));
            }

            $scheme = is_ssl() ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
            $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

            if ($host === '' || $uri === '') {
                return '';
            }

            return esc_url_raw($scheme . '://' . $host . $uri);
        }

        public function json_encode_safe($value)
        {
            $json = wp_json_encode($value);
            return $json === false ? '{}' : $json;
        }
    }
}