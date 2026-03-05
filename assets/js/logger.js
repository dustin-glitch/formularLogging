(function () {
    'use strict';

    var config = window.FLLoggerConfig || {};

    function detectClient() {
        var ua = navigator.userAgent || '';
        var browser = 'Other';
        var os = 'Other';

        function getVersion(pattern) {
            var m = ua.match(pattern);
            return m ? m[1] : '';
        }

        if (ua.indexOf('Firefox') !== -1) {
            browser = 'Firefox ' + getVersion(/Firefox\/([0-9.]+)/);
        } else if (ua.indexOf('Edg') !== -1) {
            browser = 'Edge ' + getVersion(/Edg\/([0-9.]+)/);
        } else if (ua.indexOf('Chrome') !== -1 && ua.indexOf('Edg') === -1) {
            browser = 'Chrome ' + getVersion(/Chrome\/([0-9.]+)/);
        } else if (ua.indexOf('Safari') !== -1) {
            browser = 'Safari ' + getVersion(/Version\/([0-9.]+)/);
        }

        if (ua.indexOf('Windows') !== -1) {
            os = 'Windows';
        } else if (/iPhone|iPad|iPod/.test(ua)) {
            os = 'iOS';
        } else if (ua.indexOf('Mac') !== -1) {
            os = 'macOS';
        } else if (ua.indexOf('Android') !== -1) {
            os = 'Android';
        }

        return { browser: browser, os: os };
    }

    function generateRequestId() {
        if (window.crypto && window.crypto.getRandomValues) {
            var bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            var hex = [];
            for (var i = 0; i < bytes.length; i += 1) {
                hex.push((bytes[i] + 0x100).toString(16).slice(1));
            }
            return hex.join('');
        }

        return 'fl_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12);
    }

    function ensureHiddenInput(form, name) {
        var input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        return input;
    }

    function getRequestFieldName() {
        return config.requestField || 'fl_request_id';
    }

    function ensureRequestId(form, forceNew) {
        var fieldName = getRequestFieldName();
        var requestInput = ensureHiddenInput(form, fieldName);

        if (!requestInput.value || forceNew) {
            requestInput.value = generateRequestId();
        }

        // Keep compatibility with different backends expecting request_id.
        ensureHiddenInput(form, 'request_id').value = requestInput.value;

        return requestInput.value;
    }

    function getRequestId(form) {
        var fieldName = getRequestFieldName();
        var input = form.querySelector('input[name="' + fieldName + '"]');
        return input ? input.value : '';
    }

    function formIdentifier(form) {
        return (
            form.getAttribute('data-form-id') ||
            form.id ||
            form.getAttribute('name') ||
            form.getAttribute('action') ||
            'unknown_form'
        );
    }

    function addValue(target, key, value) {
        if (Object.prototype.hasOwnProperty.call(target, key)) {
            if (!Array.isArray(target[key])) {
                target[key] = [target[key]];
            }
            target[key].push(value);
            return;
        }
        target[key] = value;
    }

    function serializeForm(form) {
        var payload = {};
        var elements = form.querySelectorAll('input, select, textarea');

        elements.forEach(function (el) {
            var name = el.name;
            if (!name) {
                return;
            }

            if (el.type === 'file') {
                var files = [];
                for (var i = 0; i < el.files.length; i += 1) {
                    files.push({
                        name: el.files[i].name,
                        size: el.files[i].size,
                        type: el.files[i].type,
                        lastModified: el.files[i].lastModified
                    });
                }
                addValue(payload, name, files);
                return;
            }

            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                return;
            }

            if (el.tagName === 'SELECT' && el.multiple) {
                var values = Array.from(el.selectedOptions).map(function (o) {
                    return o.value;
                });
                addValue(payload, name, values);
                return;
            }

            addValue(payload, name, el.value);
        });

        return payload;
    }

    function isVisible(element) {
        return !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
    }

    function safeSerializeCtx(ctx) {
        var result = {};
        if (!ctx || typeof ctx !== 'object') {
            return result;
        }

        // Collect non-DOM, non-function properties from ctx
        Object.keys(ctx).forEach(function (key) {
            if (key === 'form' || key === 'el') return;
            var val = ctx[key];
            if (typeof val === 'function') return;
            if (val instanceof HTMLElement || val instanceof Node) return;
            result[key] = val;
        });

        // Collect validation errors from the DOM if form is available
        var form = ctx.form || ctx.el;
        if (form && form instanceof HTMLElement) {
            var validation = {};

            // HTML5 constraint validation
            var fields = form.querySelectorAll('input, select, textarea');
            fields.forEach(function (field) {
                if (!field.name) return;
                if (field.validity && !field.validity.valid) {
                    validation[field.name] = [field.validationMessage || 'Invalid'];
                }
            });

            // YOOtheme / UIkit visible error messages
            var errorEls = form.querySelectorAll(
                '.uk-form-danger, .uk-alert-danger, .el-form-error, ' +
                '[class*="error-message"], [class*="form-error"], ' +
                '.yooessentials-form-error'
            );
            var messages = [];
            errorEls.forEach(function (el) {
                var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
                if (text && messages.indexOf(text) === -1) {
                    messages.push(text);
                }
                // Try to associate error with its field
                var fieldEl = el.closest('.uk-margin, .el-form-row, .form-group');
                if (fieldEl) {
                    var input = fieldEl.querySelector('input, select, textarea');
                    if (input && input.name && !validation[input.name]) {
                        validation[input.name] = [text];
                    }
                }
            });

            if (Object.keys(validation).length > 0) {
                result.validation = validation;
            }
            if (messages.length > 0) {
                result.error_messages = messages;
            }
        }

        return result;
    }

    function debugLog(title, data) {
        if (!config.debugMode) return;
        console.log('[Formular Logging] ' + title, data || '');

        var overlayArea = document.getElementById('fl-debug-overlay');
        if (!overlayArea) {
            overlayArea = document.createElement('div');
            overlayArea.id = 'fl-debug-overlay';
            overlayArea.style.position = 'fixed';
            overlayArea.style.bottom = '10px';
            overlayArea.style.left = '10px';
            overlayArea.style.background = 'rgba(0,0,0,0.8)';
            overlayArea.style.color = '#fff';
            overlayArea.style.padding = '10px';
            overlayArea.style.borderRadius = '5px';
            overlayArea.style.zIndex = '999999';
            overlayArea.style.fontSize = '12px';
            overlayArea.style.fontFamily = 'monospace';
            overlayArea.style.maxHeight = '200px';
            overlayArea.style.maxWidth = '300px';
            overlayArea.style.overflowY = 'auto';
            document.body.appendChild(overlayArea);
        }

        var entry = document.createElement('div');
        entry.style.marginBottom = '5px';
        entry.style.borderBottom = '1px solid #444';
        entry.style.paddingBottom = '5px';
        var statusColor = (data && (data.status === 'error' || data.status === 'failed')) ? '#ff6b6b' : '#4dabf7';
        var strong = document.createElement('strong');
        strong.style.color = statusColor;
        strong.textContent = title;
        entry.appendChild(strong);
        if (data) {
            entry.appendChild(document.createElement('br'));
            var detail = document.createElement('span');
            detail.textContent = JSON.stringify(data.event_stage || data);
            entry.appendChild(detail);
        }
        overlayArea.appendChild(entry);
        overlayArea.scrollTop = overlayArea.scrollHeight;
    }

    function sendEvent(data, preferBeacon) {
        if (!config.ajaxUrl || !config.action || !config.nonce) {
            return;
        }

        var client = detectClient();
        var formBody = new URLSearchParams();

        var basePayload = {
            action: config.action,
            nonce: config.nonce,
            request_id: data.request_id || '',
            event_type: data.event_type || '',
            event_stage: data.event_stage || '',
            status: data.status || 'info',
            form_identifier: data.form_identifier || '',
            page_url: data.page_url || window.location.href,
            browser: data.browser || client.browser,
            os: data.os || client.os,
            payload_json: data.payload_json || '',
            attachments_json: data.attachments_json || '',
            extra_json: data.extra_json || ''
        };

        debugLog('Event queued: ' + basePayload.status, { event_stage: basePayload.event_stage, status: basePayload.status, event_type: basePayload.event_type });

        Object.keys(basePayload).forEach(function (key) {
            formBody.append(key, basePayload[key]);
        });

        if (preferBeacon && navigator.sendBeacon) {
            var blob = new Blob([formBody.toString()], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
            navigator.sendBeacon(config.ajaxUrl, blob);
            return;
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: formBody.toString()
        }).catch(function (e) {
            debugLog('Transport Error', { error: e.message });
        });
    }

    function fillMetaFields(form, client) {
        var timeInput = form.querySelector('input[name*="current_time"]');
        var browserInput = form.querySelector('input[name*="browser_name"]');
        var osInput = form.querySelector('input[name*="os_name"]');
        var urlInput = form.querySelector('input[name*="page_url"]');

        if (timeInput) {
            timeInput.value = new Date().toISOString();
        }
        if (browserInput) {
            browserInput.value = client.browser;
        }
        if (osInput) {
            osInput.value = client.os;
        }
        if (urlInput) {
            urlInput.value = window.location.href;
        }
    }

    function setupFormListeners(form) {
        var client = detectClient();
        fillMetaFields(form, client);

        var actionStr = form.getAttribute('action') || form.action || '';
        var isYooEssentials = actionStr.indexOf('yooessentials/form') !== -1 || (form.className || '').indexOf('yooessentials') !== -1;

        if (isYooEssentials) {
            // YOOtheme Essentials forms are handled by global UIkit events below.
            return;
        }

        form.addEventListener('submit', function () {
            var requestId = ensureRequestId(form, true);
            var payload = serializeForm(form);

            sendEvent(
                {
                    request_id: requestId,
                    event_type: 'form_event',
                    event_stage: 'form_submit_started',
                    status: 'started',
                    form_identifier: formIdentifier(form),
                    payload_json: JSON.stringify(payload)
                },
                true
            );

            window.setTimeout(function () {
                var errorAlert = form.querySelector('.uk-alert-danger, .wpcf7-not-valid-tip, .validation-error, .form-error, .error');
                if (!isVisible(errorAlert)) {
                    return;
                }

                if (form.dataset.flValidationReq === requestId) {
                    return;
                }

                form.dataset.flValidationReq = requestId;
                sendEvent(
                    {
                        request_id: requestId,
                        event_type: 'form_event',
                        event_stage: 'form_validation_failed',
                        status: 'failed',
                        form_identifier: formIdentifier(form),
                        extra_json: JSON.stringify({
                            message: (errorAlert.textContent || '').replace(/\s+/g, ' ').trim()
                        })
                    },
                    false
                );
            }, 900);
        }, true);

        form.addEventListener('invalid', function (event) {
            var target = event.target;
            if (!target || !target.name) {
                return;
            }

            var requestId = ensureRequestId(form, false);
            sendEvent(
                {
                    request_id: requestId,
                    event_type: 'form_event',
                    event_stage: 'form_field_invalid',
                    status: 'failed',
                    form_identifier: formIdentifier(form),
                    extra_json: JSON.stringify({
                        field: target.name,
                        message: target.validationMessage || 'Field validation failed'
                    })
                },
                false
            );
        }, true);
    }

    window.FLLoggerAPI = {
        sendEvent: sendEvent,
        ensureRequestId: ensureRequestId,
        getRequestId: getRequestId,
        formIdentifier: formIdentifier,
        serializeForm: serializeForm,
        detectClient: detectClient,
        generateRequestId: generateRequestId
    };

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form');
        forms.forEach(function (form) {
            setupFormListeners(form);
        });

        if (typeof window.UIkit !== 'undefined' && window.UIkit.util) {
            window.UIkit.util.ready(function () {
                var util = window.UIkit.util;

                util.on(document, 'yooessentials-form:submit', function (e, ctx) {
                    if (!ctx || !ctx.form) return;
                    var requestId = ensureRequestId(ctx.form, true);
                    sendEvent({
                        request_id: requestId,
                        event_type: 'form_event',
                        event_stage: 'form_submit_started',
                        status: 'started',
                        form_identifier: formIdentifier(ctx.form),
                        payload_json: JSON.stringify(ctx.data || {})
                    }, true);
                });

                util.on(document, 'yooessentials-form:submitted', function (e, ctx) {
                    if (!ctx || !ctx.form) return;
                    var requestId = getRequestId(ctx.form) || ensureRequestId(ctx.form, false);
                    sendEvent({
                        request_id: requestId,
                        event_type: 'form_event',
                        event_stage: 'form_submitted_success',
                        status: 'success',
                        form_identifier: formIdentifier(ctx.form),
                        extra_json: JSON.stringify(safeSerializeCtx(ctx))
                    }, false);
                });

                util.on(document, 'yooessentials-form:submission-error', function (e, ctx) {
                    if (!ctx || !ctx.form) return;
                    var requestId = getRequestId(ctx.form) || ensureRequestId(ctx.form, false);
                    sendEvent({
                        request_id: requestId,
                        event_type: 'form_event',
                        event_stage: 'form_submission_error',
                        status: 'error',
                        form_identifier: formIdentifier(ctx.form),
                        extra_json: JSON.stringify(safeSerializeCtx(ctx))
                    }, false);
                });

                util.on(document, 'yooessentials-form:validation-error', function (e, ctx) {
                    if (!ctx || !ctx.form) return;
                    var requestId = getRequestId(ctx.form) || ensureRequestId(ctx.form, false);
                    sendEvent({
                        request_id: requestId,
                        event_type: 'form_event',
                        event_stage: 'form_validation_failed',
                        status: 'failed',
                        form_identifier: formIdentifier(ctx.form),
                        extra_json: JSON.stringify(safeSerializeCtx(ctx))
                    }, false);
                });
            });
        }
    });

})();
