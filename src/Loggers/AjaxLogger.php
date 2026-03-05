<?php
namespace Signalfeuer\FormularLogs\Loggers;

use Signalfeuer\FormularLogs\Storage\LogStorage;
use Signalfeuer\FormularLogs\Core\RequestContext;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Loggers\AjaxLogger')) {
    class AjaxLogger
    {
        /** @var LogStorage */
        private $storage;

        /** @var RequestContext */
        private $context;

        /** @var string */
        private $nonce_action;

        public function __construct(LogStorage $storage, RequestContext $context, $nonce_action)
        {
            $this->storage = $storage;
            $this->context = $context;
            $this->nonce_action = (string)$nonce_action;
        }

        public function handle_frontend_event()
        {
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, $this->nonce_action)) {
                wp_send_json_error(array('message' => 'Invalid nonce'), 403);
            }

            $request_id = isset($_POST['request_id']) ? sanitize_text_field(wp_unslash($_POST['request_id'])) : '';
            if ($request_id === '') {
                $request_id = $this->context->generate_request_id();
            }
            $this->context->set_request_id($request_id);

            $event_type = isset($_POST['event_type']) ? sanitize_text_field(wp_unslash($_POST['event_type'])) : '';
            $event_stage = isset($_POST['event_stage']) ? sanitize_text_field(wp_unslash($_POST['event_stage'])) : '';
            $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

            if ($event_type === '' || $event_stage === '' || $status === '') {
                wp_send_json_error(array('message' => 'Missing required fields'), 422);
            }

            if ($status === 'error') {
                \Signalfeuer\FormularLogs\Core\Plugin::instance()->track_ip_error();
            }

            $ok = $this->storage->write_log(
                array(
                'request_id' => $request_id,
                'event_type' => $event_type,
                'event_stage' => $event_stage,
                'status' => $status,
                'source' => 'frontend_ajax',
                'form_identifier' => isset($_POST['form_identifier']) ? sanitize_text_field(wp_unslash($_POST['form_identifier'])) : $this->context->detect_form_identifier(),
                'page_url' => isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : $this->context->get_page_url(),
                'browser' => isset($_POST['browser']) ? sanitize_text_field(wp_unslash($_POST['browser'])) : '',
                'os' => isset($_POST['os']) ? sanitize_text_field(wp_unslash($_POST['os'])) : '',
                'payload_json' => $this->context->ensure_json_string($this->context->read_raw_post_field('payload_json')),
                'attachments_json' => $this->context->ensure_json_string($this->context->read_raw_post_field('attachments_json')),
                'extra_json' => $this->context->ensure_json_string($this->context->read_raw_post_field('extra_json')),
            ),
                $this->context
            );

            if (!$ok) {
                wp_send_json_error(array('message' => 'Could not write log'), 500);
            }

            wp_send_json_success(array('request_id' => $request_id));
        }
    }
}