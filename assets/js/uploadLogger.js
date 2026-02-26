(function () {
    'use strict';

    function hasApi() {
        return !!(window.FLLoggerAPI && typeof window.FLLoggerAPI.sendEvent === 'function');
    }

    function fallbackRequestId() {
        if (hasApi() && typeof window.FLLoggerAPI.generateRequestId === 'function') {
            return window.FLLoggerAPI.generateRequestId();
        }
        return 'upload_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

    function getFormIdentifier(form) {
        if (hasApi() && typeof window.FLLoggerAPI.formIdentifier === 'function') {
            return window.FLLoggerAPI.formIdentifier(form);
        }
        return (form && (form.id || form.getAttribute('name') || form.getAttribute('action'))) || 'unknown_form';
    }

    function getRequestId(form) {
        if (!form) {
            return fallbackRequestId();
        }

        if (hasApi() && typeof window.FLLoggerAPI.ensureRequestId === 'function') {
            return window.FLLoggerAPI.ensureRequestId(form, false);
        }

        var existing = form.querySelector('input[name="fl_request_id"]');
        if (existing && existing.value) {
            return existing.value;
        }

        return fallbackRequestId();
    }

    function getClient() {
        if (hasApi() && typeof window.FLLoggerAPI.detectClient === 'function') {
            return window.FLLoggerAPI.detectClient();
        }
        return { browser: '', os: '' };
    }

    function fileMetaList(input) {
        var files = [];
        if (!input || !input.files) {
            return files;
        }

        for (var i = 0; i < input.files.length; i += 1) {
            files.push({
                name: input.files[i].name,
                size: input.files[i].size,
                type: input.files[i].type,
                lastModified: input.files[i].lastModified
            });
        }

        return files;
    }

    function sendUploadEvent(input, stage, status, extra) {
        if (!hasApi()) {
            return;
        }

        var form = input.form || input.closest('form');
        var requestId = getRequestId(form);
        var client = getClient();

        window.FLLoggerAPI.sendEvent(
            {
                request_id: requestId,
                event_type: 'upload_event',
                event_stage: stage,
                status: status,
                form_identifier: getFormIdentifier(form),
                browser: client.browser,
                os: client.os,
                attachments_json: JSON.stringify(fileMetaList(input)),
                extra_json: JSON.stringify(extra || {})
            },
            false
        );
    }

    function bindFileInput(input) {
        input.addEventListener('change', function () {
            if (!input.files || input.files.length === 0) {
                return;
            }

            sendUploadEvent(input, 'upload_selected', 'info', {
                field: input.name || '',
                fileCount: input.files.length
            });

            if (input.validity && !input.validity.valid) {
                sendUploadEvent(input, 'upload_failed', 'failed', {
                    field: input.name || '',
                    reason: input.validationMessage || 'Invalid file selection'
                });
            }
        });

        input.addEventListener('invalid', function () {
            sendUploadEvent(input, 'upload_failed', 'failed', {
                field: input.name || '',
                reason: input.validationMessage || 'File input invalid'
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(function (input) {
            bindFileInput(input);
        });
    });
})();
