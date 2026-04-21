(function($){
    'use strict';

    function getAjaxUrl() {
        if (typeof sc_ajax !== 'undefined' && sc_ajax.url) {
            return sc_ajax.url;
        }
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }
        return window.location.origin + '/wp-admin/admin-ajax.php';
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || '').replace(/[&<>"']/g, function(m){ return map[m]; });
    }

    function showError(message) {
        var userMessage = getErrorMessage(message);
        var html = '<div class="sc-error-content">' +
            '<div class="sc-error-icon">❌</div>' +
            '<div class="sc-error-text">' +
            '<strong>Certificate Download Failed</strong><br>' +
            '<span class="sc-error-reason">' + escapeHtml(userMessage) + '</span>' +
            '</div>' +
            '</div>';

        $('#sc_success_container').hide();
        $('#sc_alert').removeClass('error').addClass('sc-error-message').html(html).show();

        if ($('#sc_alert').length) {
            $('html, body').animate({
                scrollTop: $('#sc_alert').offset().top - 100
            }, 300);
        }
    }

    function getErrorMessage(msg) {
        msg = (msg || '').toLowerCase();

        if (!msg) {
            return 'An unexpected error occurred. Please try again.';
        }
        if (msg.indexOf('session expired') !== -1 || msg.indexOf('nonce') !== -1) {
            return 'Your page session expired because of cache or inactivity. Please try again.';
        }
        if (msg.indexOf('log in') !== -1 || msg.indexOf('login') !== -1) {
            return 'Please log in with your website account before downloading your certificate.';
        }
        if (msg.indexOf('download limit') !== -1) {
            return 'Download limit reached for this code.';
        }
        if (msg.indexOf('invalid code') !== -1) {
            return 'Invalid code for the selected class and batch.';
        }
        if (msg.indexOf('batch') !== -1) {
            return 'Please select a valid batch for your class.';
        }
        if (msg.indexOf('assigned to another student') !== -1) {
            return 'This code is locked to another student name.';
        }
        if (msg.indexOf('account email') !== -1) {
            return 'This code is linked to another account email.';
        }
        if (msg.indexOf('template') !== -1) {
            return 'Certificate template is not configured correctly. Please contact the administrator.';
        }
        if (msg.indexOf('generate') !== -1) {
            return 'Certificate generation failed. Please try again.';
        }
        if (msg.indexOf('expired') !== -1) {
            return 'Certificate expired. Please generate a new one.';
        }

        return msg;
    }

    function refreshNonce() {
        return $.post(getAjaxUrl(), { action: 'smartcertify_refresh_nonce' }).then(function(resp){
            var json = (typeof resp === 'object') ? resp : JSON.parse(resp);
            if (!json.success) {
                throw new Error('Unable to refresh security token.');
            }
            return json.data;
        });
    }

    function loadBatches(classId) {
        return $.post(getAjaxUrl(), {
            action: 'smartcertify_get_batches',
            class_id: classId
        }).then(function(resp){
            return (typeof resp === 'object') ? resp : JSON.parse(resp);
        });
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function initTemplateDesigner() {
        var $designer = $('#sc_template_designer');
        if (!$designer.length) {
            return;
        }

        var $stage = $designer.find('.sc-designer-stage-inner');
        var $image = $designer.find('.sc-designer-image');
        var $nodes = $designer.find('.sc-designer-node');
        var $cards = $('.sc-designer-control-card');
        var $switcherButtons = $('.sc-designer-switcher-button');
        var $verticalGuide = $designer.find('.sc-designer-guide--vertical');
        var $horizontalGuide = $designer.find('.sc-designer-guide--horizontal');
        var defaults = {};
        var fontPreviewMap = {};
        var dragState = null;
        var snapThreshold = 1.2;

        try {
            defaults = JSON.parse($designer.attr('data-defaults') || '{}');
        } catch (err) {
            defaults = {};
        }

        try {
            fontPreviewMap = JSON.parse($designer.attr('data-font-preview-map') || '{}');
        } catch (err) {
            fontPreviewMap = {};
        }

        function getInput(key) {
            return $designer.closest('form').find('[data-layout-key="' + key + '"]');
        }

        function getNaturalWidth() {
            return parseFloat($image.attr('data-natural-width')) || ($image[0] && $image[0].naturalWidth) || $image.width() || 1;
        }

        function getNaturalHeight() {
            return parseFloat($image.attr('data-natural-height')) || ($image[0] && $image[0].naturalHeight) || $image.height() || 1;
        }

        function getScale() {
            return ($image.width() || 1) / getNaturalWidth();
        }

        function parsePercentPosition(value, axis) {
            var stringValue = String(value || '').trim();
            var reference = axis === 'x' ? getNaturalWidth() : getNaturalHeight();

            if (!stringValue) {
                return 0;
            }
            if (stringValue.indexOf('%') !== -1) {
                return clamp(parseFloat(stringValue) || 0, 0, 100);
            }

            return clamp(((parseFloat(stringValue) || 0) / reference) * 100, 0, 100);
        }

        function formatPercent(value) {
            var normalized = Math.round(clamp(value, 0, 100) * 100) / 100;
            var output = normalized.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
            return output + '%';
        }

        function parsePercentValue(value, fallback) {
            var stringValue = String(value || '').trim();
            if (!stringValue) {
                return fallback || 0;
            }
            if (stringValue.indexOf('%') !== -1) {
                return parseFloat(stringValue) || fallback || 0;
            }
            return parseFloat(stringValue) || fallback || 0;
        }

        function dimensionToCss(value, axis) {
            var stringValue = String(value || '').trim();
            if (!stringValue) {
                return '';
            }
            if (stringValue.indexOf('%') !== -1) {
                return stringValue;
            }

            return Math.max(10, (parseFloat(stringValue) || 0) * getScale()) + 'px';
        }

        function fontToCss(value) {
            var size = parseFloat(String(value || '').trim()) || 0;
            return Math.max(4, size * getScale()) + 'px';
        }

        function setGuideState(verticalSnapped, horizontalSnapped) {
            $verticalGuide.toggleClass('is-snapped', !!verticalSnapped);
            $horizontalGuide.toggleClass('is-snapped', !!horizontalSnapped);
        }

        function selectField(fieldId) {
            $nodes.removeClass('is-active');
            $cards.removeClass('is-active');
            $switcherButtons.removeClass('is-active');

            var $node = $nodes.filter('[data-field-id="' + fieldId + '"]');
            var $card = $cards.filter('[data-field-id="' + fieldId + '"]');
            var $switcher = $switcherButtons.filter('[data-field-id="' + fieldId + '"]');

            $node.addClass('is-active');
            $card.addClass('is-active');
            $switcher.addClass('is-active');

            if ($card.length && typeof $card[0].scrollIntoView === 'function') {
                $card[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }

        function syncNode($node) {
            var xKey = $node.data('x-key');
            var yKey = $node.data('y-key');
            var fontKey = $node.data('font-key');
            var fontFamilyKey = $node.data('font-family-key');
            var widthKey = $node.data('width-key');
            var heightKey = $node.data('height-key');
            var sizeKey = $node.data('size-key');

            var xValue = getInput(xKey).val();
            var yValue = getInput(yKey).val();

            $node.css({
                left: parsePercentPosition(xValue, 'x') + '%',
                top: parsePercentPosition(yValue, 'y') + '%'
            });

            if (fontFamilyKey) {
                var fontChoice = getInput(fontFamilyKey).val();
                $node.css('font-family', fontPreviewMap[fontChoice] || fontPreviewMap.sans || 'inherit');
            } else {
                $node.css('font-family', '');
            }

            if (fontKey) {
                $node.css('font-size', fontToCss(getInput(fontKey).val()));
            } else {
                $node.css('font-size', '');
            }

            if (sizeKey) {
                var sizeValue = dimensionToCss(getInput(sizeKey).val(), 'x');
                $node.css({
                    width: sizeValue,
                    height: sizeValue
                });
            } else {
                $node.css({
                    width: widthKey ? dimensionToCss(getInput(widthKey).val(), 'x') : '',
                    height: heightKey ? dimensionToCss(getInput(heightKey).val(), 'y') : ''
                });
            }
        }

        function getNodePosition($node) {
            return {
                x: parsePercentPosition(getInput($node.data('x-key')).val(), 'x'),
                y: parsePercentPosition(getInput($node.data('y-key')).val(), 'y')
            };
        }

        function syncAllNodes() {
            $nodes.each(function(){
                syncNode($(this));
            });
        }

        function updateDraggedNode(event) {
            if (!dragState) {
                return;
            }

            var rect = $stage[0].getBoundingClientRect();
            var xPercent = ((event.clientX - rect.left) / rect.width) * 100;
            var yPercent = ((event.clientY - rect.top) / rect.height) * 100;
            var $node = dragState.$node;
            var snappedX = false;
            var snappedY = false;

            xPercent = clamp(xPercent + dragState.offsetX, 0, 100);
            yPercent = clamp(yPercent + dragState.offsetY, 0, 100);

            if (Math.abs(xPercent - 50) <= snapThreshold) {
                xPercent = 50;
                snappedX = true;
            }

            if (Math.abs(yPercent - 50) <= snapThreshold) {
                yPercent = 50;
                snappedY = true;
            }

            getInput($node.data('x-key')).val(formatPercent(xPercent));
            getInput($node.data('y-key')).val(formatPercent(yPercent));
            setGuideState(snappedX, snappedY);
            syncNode($node);
        }

        function stopDragging() {
            dragState = null;
            $(document).off('.scDesignerDrag');
            $('body').removeClass('sc-designer-dragging');
            setGuideState(false, false);
        }

        $stage.on('pointerdown', '.sc-designer-node', function(event){
            if (event.button && event.button !== 0) {
                return;
            }

            event.preventDefault();
            var $node = $(this);
            var rect = $stage[0].getBoundingClientRect();
            var pointerX = ((event.clientX - rect.left) / rect.width) * 100;
            var pointerY = ((event.clientY - rect.top) / rect.height) * 100;
            var currentPosition = getNodePosition($node);

            dragState = {
                $node: $node,
                offsetX: currentPosition.x - pointerX,
                offsetY: currentPosition.y - pointerY
            };

            selectField($node.data('field-id'));
            $('body').addClass('sc-designer-dragging');

            $(document)
                .on('pointermove.scDesignerDrag', function(moveEvent){
                    updateDraggedNode(moveEvent);
                })
                .on('pointerup.scDesignerDrag pointercancel.scDesignerDrag', function(){
                    stopDragging();
                });
        });

        $(document).on('click', '.sc-designer-focus', function(event){
            event.preventDefault();
            selectField($(this).data('field-id'));
        });

        $(document).on('click', '.sc-designer-switcher-button', function(event){
            event.preventDefault();
            selectField($(this).data('field-id'));
        });

        $(document).on('click', '.sc-designer-control-card', function(event){
            if ($(event.target).is('input, select, button, .button-link')) {
                return;
            }
            selectField($(this).data('field-id'));
        });

        $(document).on('keydown', function(event){
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            if ($(event.target).is('input, textarea, select')) {
                return;
            }

            var $activeNode = $nodes.filter('.is-active').first();
            if (!$activeNode.length) {
                return;
            }

            event.preventDefault();

            var step = event.shiftKey ? 0.05 : 0.25;
            var xKey = $activeNode.data('x-key');
            var yKey = $activeNode.data('y-key');
            var xValue = parsePercentValue(getInput(xKey).val(), 50);
            var yValue = parsePercentValue(getInput(yKey).val(), 50);

            if (event.key === 'ArrowLeft') {
                xValue -= step;
            } else if (event.key === 'ArrowRight') {
                xValue += step;
            } else if (event.key === 'ArrowUp') {
                yValue -= step;
            } else if (event.key === 'ArrowDown') {
                yValue += step;
            }

            getInput(xKey).val(formatPercent(xValue));
            getInput(yKey).val(formatPercent(yValue));
            syncNode($activeNode);
        });

        $(document).on('input change', '[data-layout-key]', function(){
            syncAllNodes();
        });

        $('#sc_designer_reset').on('click', function(){
            if (!window.confirm('Reset all layout values to the recommended defaults?')) {
                return;
            }

            Object.keys(defaults).forEach(function(key){
                getInput(key).val(defaults[key]);
            });

            syncAllNodes();
        });

        if ($nodes.length) {
            selectField($nodes.first().data('field-id'));
        }

        if ($image[0] && !$image[0].complete) {
            $image.on('load', syncAllNodes);
        }

        $(window).on('resize', syncAllNodes);
        syncAllNodes();
    }

    function initQrScanner() {
        $('.sc-qr-scanner').each(function(){
            var $scanner = $(this);
            var $video = $scanner.find('.sc-qr-video');
            var $status = $scanner.find('.sc-qr-status');
            var $start = $scanner.find('.sc-qr-start');
            var $stop = $scanner.find('.sc-qr-stop');
            var $uploadTrigger = $scanner.find('.sc-qr-upload-trigger');
            var $uploadInput = $scanner.find('.sc-qr-upload');
            var targetSelector = $scanner.attr('data-target-input') || '';
            var submitOnScan = String($scanner.attr('data-submit-on-scan') || '0') === '1';
            var redirectOnUrl = String($scanner.attr('data-redirect-on-url') || '1') === '1';
            var detector = null;
            var stream = null;
            var scanTimer = null;
            var isDetecting = false;

            if ('BarcodeDetector' in window) {
                try {
                    detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                } catch (err) {
                    detector = null;
                }
            }

            function setStatus(message, type) {
                $status
                    .removeClass('is-error is-success')
                    .toggleClass('is-error', type === 'error')
                    .toggleClass('is-success', type === 'success')
                    .text(message);
            }

            function getTargetInput() {
                if (!targetSelector) {
                    return $();
                }

                return $(targetSelector).first();
            }

            function parseScannedValue(rawValue) {
                var value = $.trim(String(rawValue || ''));
                var parsed = {
                    raw: value,
                    serial: value,
                    url: ''
                };

                if (!value) {
                    return parsed;
                }

                if (!/^https?:\/\//i.test(value) && value.indexOf('smartcertify_verify=') === -1) {
                    return parsed;
                }

                try {
                    var url = /^https?:\/\//i.test(value) ? new URL(value) : new URL(value, window.location.href);
                    var serial = url.searchParams.get('smartcertify_verify');

                    parsed.url = url.toString();
                    if (serial) {
                        parsed.serial = serial;
                    }
                } catch (err) {
                    parsed.url = '';
                }

                return parsed;
            }

            function stopScanner() {
                if (scanTimer) {
                    window.clearInterval(scanTimer);
                    scanTimer = null;
                }

                if (stream) {
                    stream.getTracks().forEach(function(track){
                        track.stop();
                    });
                    stream = null;
                }

                if ($video.length) {
                    $video.prop('hidden', true);
                    if ($video[0]) {
                        $video[0].pause();
                        $video[0].srcObject = null;
                    }
                }

                $scanner.removeClass('is-scanning');
                $start.prop('disabled', false);
                $stop.prop('disabled', true);
                isDetecting = false;
            }

            function submitTarget($input) {
                if (!submitOnScan || !$input.length) {
                    return false;
                }

                var $form = $input.closest('form');
                if (!$form.length) {
                    return false;
                }

                window.setTimeout(function(){
                    $form.trigger('submit');
                }, 60);

                return true;
            }

            function handleScanValue(rawValue) {
                var parsed = parseScannedValue(rawValue);
                var $input = getTargetInput();

                if ($input.length && parsed.serial) {
                    $input.val(parsed.serial).trigger('input').trigger('change');
                    setStatus('QR code detected. The serial number was filled automatically.', 'success');
                    stopScanner();
                    submitTarget($input);
                    return;
                }

                if (redirectOnUrl && parsed.url) {
                    setStatus('QR code detected. Opening the verification page...', 'success');
                    stopScanner();
                    window.location.href = parsed.url;
                    return;
                }

                if (parsed.serial) {
                    setStatus('QR code detected: ' + parsed.serial, 'success');
                    stopScanner();
                    return;
                }

                setStatus('A QR code was found, but it could not be read properly. Please try again.', 'error');
            }

            function detectFromSource(source, emptyMessage) {
                if (!detector) {
                    setStatus('Direct QR scanning is not supported in this browser. Please enter the serial manually.', 'error');
                    return Promise.resolve();
                }

                return detector.detect(source).then(function(codes){
                    if (!codes || !codes.length || !codes[0].rawValue) {
                        if (emptyMessage) {
                            setStatus(emptyMessage, 'error');
                        }
                        return;
                    }

                    handleScanValue(codes[0].rawValue);
                }).catch(function(){
                    setStatus('Unable to read the QR code right now. Please try again.', 'error');
                });
            }

            function loadImageSource(file) {
                if (window.createImageBitmap) {
                    return window.createImageBitmap(file);
                }

                return new Promise(function(resolve, reject){
                    var objectUrl = URL.createObjectURL(file);
                    var image = new Image();

                    image.onload = function() {
                        URL.revokeObjectURL(objectUrl);
                        resolve(image);
                    };

                    image.onerror = function() {
                        URL.revokeObjectURL(objectUrl);
                        reject(new Error('image-load-failed'));
                    };

                    image.src = objectUrl;
                });
            }

            function scanUploadedFile(file) {
                if (!file) {
                    return;
                }

                setStatus('Reading the uploaded QR image...', 'success');

                loadImageSource(file).then(function(source){
                    return detectFromSource(source, 'No QR code was found in that image. Please upload a clearer image.').finally(function(){
                        if (source && typeof source.close === 'function') {
                            source.close();
                        }
                    });
                }).catch(function(){
                    setStatus('Unable to read that QR image. Please try another file.', 'error');
                });
            }

            function startScanner() {
                if (!detector) {
                    setStatus('Direct QR scanning is not supported in this browser. Please enter the serial manually.', 'error');
                    return;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    setStatus('Camera access is not available in this browser. Please upload a QR image instead.', 'error');
                    return;
                }

                stopScanner();
                $start.prop('disabled', true);
                setStatus('Starting the camera. Hold the QR code inside the square box.', 'success');

                navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' }
                    },
                    audio: false
                }).then(function(mediaStream){
                    stream = mediaStream;

                    if (!$video[0]) {
                        setStatus('The QR scanner preview could not be started on this page.', 'error');
                        stopScanner();
                        return;
                    }

                    $video[0].srcObject = mediaStream;
                    $video[0].setAttribute('playsinline', 'true');

                    return $video[0].play().then(function(){
                        $video.prop('hidden', false);
                        $scanner.addClass('is-scanning');
                        $stop.prop('disabled', false);

                        scanTimer = window.setInterval(function(){
                            if (isDetecting || !$video[0] || $video[0].readyState < 2) {
                                return;
                            }

                            isDetecting = true;
                            detectFromSource($video[0], '').finally(function(){
                                isDetecting = false;
                            });
                        }, 450);
                    });
                }).catch(function(){
                    stopScanner();
                    setStatus('Unable to start the camera. Please allow camera access or upload a QR image.', 'error');
                });
            }

            $start.on('click', function(event){
                event.preventDefault();
                startScanner();
            });

            $stop.on('click', function(event){
                event.preventDefault();
                stopScanner();
                setStatus('Camera stopped. You can start scanning again or upload a QR image.', 'success');
            });

            $uploadTrigger.on('click', function(event){
                event.preventDefault();
                $uploadInput.trigger('click');
            });

            $uploadInput.on('change', function(){
                var file = this.files && this.files[0] ? this.files[0] : null;
                scanUploadedFile(file);
                this.value = '';
            });

            $(window).on('beforeunload', function(){
                stopScanner();
            });
        });
    }

    $(function(){
        var $form = $('#smartcertify_form');
        var $classSelect = $('#sc_class_id');
        var $batchGroup = $('#sc_batch_group');
        var $batchSelect = $('#sc_batch_id');
        var $details = $('#sc_form_details');

        function resetBatchState() {
            $batchSelect.prop('disabled', true).html('<option value="">Select batch</option>');
            $batchGroup.addClass('sc-hidden');
            $details.addClass('sc-hidden');
        }

        if ($classSelect.length) {
            resetBatchState();

            $classSelect.on('change', function(){
                var classId = $(this).val();
                $('#sc_alert').hide().html('');
                $('#sc_success_container').hide();
                resetBatchState();

                if (!classId) {
                    return;
                }

                loadBatches(classId)
                    .done(function(json){
                        if (!json.success) {
                            showError((json.data && json.data.message) || 'Unable to load batches.');
                            return;
                        }

                        var options = ['<option value="">Select batch</option>'];
                        if (!json.data.batches || !json.data.batches.length) {
                            showError('No active batches are available for this class.');
                            return;
                        }

                        $.each(json.data.batches, function(_, batch){
                            options.push('<option value="' + escapeHtml(batch.id) + '">' + escapeHtml(batch.name) + '</option>');
                        });

                        $batchSelect.html(options.join('')).prop('disabled', false);
                        $batchGroup.removeClass('sc-hidden');
                    })
                    .fail(function(){
                        showError('Unable to load batches. Please refresh and try again.');
                    });
            });

            $batchSelect.on('change', function(){
                if ($(this).val()) {
                    $details.removeClass('sc-hidden');
                } else {
                    $details.addClass('sc-hidden');
                }
            });
        }

        $(document).on('submit', '#smartcertify_form', function(e){
            e.preventDefault();

            var $submit = $form.find('button[type=submit]');
            $('#sc_alert').hide().html('');
            $('#sc_success_container').hide();
            $submit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing');

            refreshNonce()
                .done(function(tokens){
                    $form.find('input[name="_wpnonce"]').val(tokens.nonce);

                    $.post(getAjaxUrl(), $form.serialize())
                        .done(function(resp){
                            var json = (typeof resp === 'object') ? resp : JSON.parse(resp);
                            $submit.prop('disabled', false).text('Get Certificate');

                            if (!json.success) {
                                showError(json.data || 'Unable to generate certificate.');
                                return;
                            }

                            var data = json.data || {};
                            $('#sc_download_button').attr('href', data.url || '#');
                            $('#sc_view_button').attr('href', data.url || '#');

                            var note = [];
                            if (data.serial) {
                                note.push('Serial: ' + data.serial);
                            }
                            if (data.generated_at) {
                                note.push('Generated: ' + data.generated_at);
                            }
                            $('#sc_serial_note').text(note.join(' | '));
                            $('#sc_success_container h3').text(data.reused ? 'Certificate Ready Again!' : 'Certificate Ready!');
                            $('#sc_success_container p').first().text(data.reused ? 'Your existing active certificate was found and is ready to download.' : 'Your certificate has been generated successfully.');

                            $('#sc_success_container').fadeIn(300);
                        })
                        .fail(function(xhr){
                            $submit.prop('disabled', false).text('Get Certificate');
                            try {
                                var json = $.parseJSON(xhr.responseText);
                                showError(json.data || 'Server error. Please try again.');
                            } catch (err) {
                                showError('Server error. Please try again.');
                            }
                        });
                })
                .fail(function(){
                    $submit.prop('disabled', false).text('Get Certificate');
                    showError('Unable to refresh security token. Please reload the page.');
                });
        });

        initTemplateDesigner();
        initQrScanner();
    });
})(jQuery);
