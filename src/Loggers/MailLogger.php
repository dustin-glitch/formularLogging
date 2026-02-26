<?php
namespace Signalfeuer\FormularLogs\Loggers;

use Signalfeuer\FormularLogs\Storage\LogStorage;
use Signalfeuer\FormularLogs\Core\RequestContext;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Loggers\MailLogger')) {
    class MailLogger
    {
        /** @var LogStorage */
        private $storage;

        /** @var RequestContext */
        private $context;

        public function __construct(LogStorage $storage, RequestContext $context)
        {
            $this->storage = $storage;
            $this->context = $context;
        }

        public function log_mail_pre_send($args)
        {
            if (!is_array($args)) {
                return $args;
            }

            $request_id = $this->context->resolve_request_id();
            $this->context->set_request_id($request_id);
            $args['headers'] = $this->context->append_request_header($args, $request_id);

            $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => 'mail_event',
                'event_stage' => 'mail_pre_send',
                'status' => 'started',
                'source' => 'wp_mail',
                'form_identifier' => $this->context->detect_form_identifier(),
                'recipient' => $this->context->normalize_recipients(isset($args['to']) ? $args['to'] : ''),
                'subject' => isset($args['subject']) ? (string)$args['subject'] : '',
                'payload_json' => $this->context->json_encode_safe($this->context->collect_request_payload()),
                'attachments_json' => $this->context->json_encode_safe(isset($args['attachments']) ? $args['attachments'] : array()),
                'extra_json' => $this->context->json_encode_safe(array('headers' => isset($args['headers']) ? $args['headers'] : array())),
            ),
                $this->context
            );

            return $args;
        }

        public function log_phpmailer_init($phpmailer)
        {
            $request_id = $this->context->extract_request_id_from_phpmailer($phpmailer);
            if ($request_id === '') {
                $request_id = $this->context->resolve_request_id();
            }
            $this->context->set_request_id($request_id);

            $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => 'mail_event',
                'event_stage' => 'mail_transport_config',
                'status' => 'info',
                'source' => 'phpmailer_init',
                'form_identifier' => $this->context->detect_form_identifier(),
                'mailer' => isset($phpmailer->Mailer) ? (string)$phpmailer->Mailer : '',
                'smtp_host' => isset($phpmailer->Host) ? (string)$phpmailer->Host : '',
                'smtp_port' => isset($phpmailer->Port) ? (string)$phpmailer->Port : '',
                'extra_json' => $this->context->json_encode_safe(
                    array(
                    'smtp_auth' => isset($phpmailer->SMTPAuth) ? (bool)$phpmailer->SMTPAuth : null,
                    'smtp_secure' => isset($phpmailer->SMTPSecure) ? (string)$phpmailer->SMTPSecure : '',
                )
            ),
            ),
                $this->context
            );
        }

        public function log_mail_succeeded($mail_data)
        {
            if (!is_array($mail_data)) {
                $mail_data = array();
            }

            $request_id = $this->context->extract_request_id_from_headers(isset($mail_data['headers']) ? $mail_data['headers'] : array());
            if ($request_id === '') {
                $request_id = $this->context->resolve_request_id();
            }
            $this->context->set_request_id($request_id);

            $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => 'mail_event',
                'event_stage' => 'mail_sent_success',
                'status' => 'success',
                'source' => 'wp_mail_succeeded',
                'form_identifier' => $this->context->detect_form_identifier(),
                'recipient' => $this->context->normalize_recipients(isset($mail_data['to']) ? $mail_data['to'] : ''),
                'subject' => isset($mail_data['subject']) ? (string)$mail_data['subject'] : '',
                'attachments_json' => $this->context->json_encode_safe(isset($mail_data['attachments']) ? $mail_data['attachments'] : array()),
                'extra_json' => $this->context->json_encode_safe(array('headers' => isset($mail_data['headers']) ? $mail_data['headers'] : array())),
            ),
                $this->context
            );
        }

        public function log_mail_failed($wp_error)
        {
            $data = is_object($wp_error) && method_exists($wp_error, 'get_error_data') ? $wp_error->get_error_data() : array();
            if (!is_array($data)) {
                $data = array();
            }

            $request_id = $this->context->extract_request_id_from_headers(isset($data['headers']) ? $data['headers'] : array());
            if ($request_id === '') {
                $request_id = $this->context->resolve_request_id();
            }
            $this->context->set_request_id($request_id);

            $error_code = is_object($wp_error) && method_exists($wp_error, 'get_error_code') ? (string)$wp_error->get_error_code() : 'mail_failed';
            $error_message = is_object($wp_error) && method_exists($wp_error, 'get_error_message') ? (string)$wp_error->get_error_message() : 'Unknown mail error';

            $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => 'mail_event',
                'event_stage' => 'mail_send_failed',
                'status' => 'failed',
                'source' => 'wp_mail_failed',
                'form_identifier' => $this->context->detect_form_identifier(),
                'recipient' => $this->context->normalize_recipients(isset($data['to']) ? $data['to'] : ''),
                'subject' => isset($data['subject']) ? (string)$data['subject'] : '',
                'error_code' => $error_code,
                'error_message' => $error_message,
                'extra_json' => $this->context->json_encode_safe($data),
            ),
                $this->context
            );
        }

        public function log_wp_mail_smtp_failed($error_message, $mailer_instance, $mailer_slug)
        {
            $request_id = $this->context->extract_request_id_from_phpmailer($mailer_instance);
            if ($request_id === '') {
                $request_id = $this->context->resolve_request_id();
            }
            $this->context->set_request_id($request_id);

            // Access to PHPMailer to get email context
            $to_address = '';
            if (isset($mailer_instance->to) && is_array($mailer_instance->to) && !empty($mailer_instance->to)) {
                $to_address = isset($mailer_instance->to[0][0]) ? $mailer_instance->to[0][0] : '';
            }

            $subject = isset($mailer_instance->Subject) ? (string)$mailer_instance->Subject : '';

            $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => 'mail_event',
                'event_stage' => 'mail_send_failed',
                'status' => 'failed',
                'source' => 'wp_mail_smtp',
                'form_identifier' => $this->context->detect_form_identifier(),
                'recipient' => $this->context->normalize_recipients($to_address),
                'subject' => $subject,
                'error_code' => $mailer_slug,
                'error_message' => is_string($error_message) ? $error_message : 'WP Mail SMTP send failed',
                'extra_json' => $this->context->json_encode_safe(array('mailer_slug' => $mailer_slug)),
            ),
                $this->context
            );
        }
    }
}