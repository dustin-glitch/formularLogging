<?php
namespace Signalfeuer\FormularLogs\Core;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Core\Crypto')) {
    class Crypto
    {
        const METHOD = 'aes-256-cbc';
        const OPTION_KEY = 'fl_encryption_key';

        private $key;

        public function __construct()
        {
            $this->init_key();
        }

        private function init_key()
        {
            $encoded_key = get_option(self::OPTION_KEY, '');
            if (empty($encoded_key)) {
                try {
                    $raw_key = random_bytes(32);
                }
                catch (\Exception $e) {
                    // Fallback if random_bytes fails cleanly, though extremely rare in modern PHP
                    $raw_key = wp_generate_password(32, true, true);
                }
                $encoded_key = base64_encode($raw_key);
                update_option(self::OPTION_KEY, $encoded_key);
            }

            $this->key = base64_decode($encoded_key);
        }

        public function encrypt($data)
        {
            if (empty($data) || !is_string($data)) {
                return $data;
            }

            $iv_length = openssl_cipher_iv_length(self::METHOD);
            if ($iv_length === false) {
                return $data; // openssl might not be available or method invalid
            }

            try {
                $iv = random_bytes($iv_length);
            }
            catch (\Exception $e) {
                return $data;
            }

            $encrypted = openssl_encrypt($data, self::METHOD, $this->key, 0, $iv);
            if ($encrypted === false) {
                return $data; // encryption failed
            }

            // Combine IV and encrypted data, then encode
            return 'FLENC:' . base64_encode($iv . $encrypted);
        }

        public function decrypt($data)
        {
            if (empty($data) || !is_string($data)) {
                return $data;
            }

            // Check signature to see if it's actually encrypted by us and not a legacy log
            if (strpos($data, 'FLENC:') !== 0) {
                return $data;
            }

            $payload = substr($data, 6);
            $decoded = base64_decode($payload);
            if ($decoded === false) {
                return $data;
            }

            $iv_length = openssl_cipher_iv_length(self::METHOD);
            if ($iv_length === false || strlen($decoded) <= $iv_length) {
                return $data;
            }

            $iv = substr($decoded, 0, $iv_length);
            $encrypted = substr($decoded, $iv_length);

            $decrypted = openssl_decrypt($encrypted, self::METHOD, $this->key, 0, $iv);
            if ($decrypted === false) {
                return $data; // decryption failed, maybe key changed
            }

            return $decrypted;
        }
    }
}