/**
 * Email Template Builder JavaScript
 */

jQuery(document).ready(function ($) {
    let lastFocusedTarget = $('#custom_html');

    $('.bet-color-picker').wpColorPicker();

    $('.bet-placeholder-target, #custom_html, #email_subject, #header_text, #intro_text, #footer_text').on('focus click', function () {
        lastFocusedTarget = $(this);
    });

    function updateModeVisibility() {
        const mode = $('input[name="template_mode"]:checked').val();
        $('#bet-html-fields').toggle(mode === 'html');
        $('#bet-visual-fields').toggle(mode !== 'html');
    }

    $('input[name="template_mode"]').on('change', updateModeVisibility);
    updateModeVisibility();

    function renderPlaceholders() {
        const formId = $('#related_form_id').val();
        const forms = (window.betAjax && Array.isArray(betAjax.forms)) ? betAjax.forms : [];
        const form = forms.find(function (item) { return item.id === formId; });
        const fields = form && Array.isArray(form.fields) ? form.fields : [
            { id: 'name', label: 'Name' },
            { id: 'email', label: 'Email' },
            { id: 'message', label: 'Message' }
        ];

        const $list = $('#bet-placeholder-list').empty();
        fields.forEach(function (field) {
            if (!field.id) {
                return;
            }
            $('<button type="button" class="button button-small bet-placeholder-chip"></button>')
                .attr('data-placeholder', '{{' + field.id + '}}')
                .html(escapeHtml(field.label || field.id) + ': <code>{{' + escapeHtml(field.id) + '}}</code>')
                .appendTo($list);
        });

        $('<button type="button" class="button button-small bet-placeholder-chip"><code>{{all_fields}}</code></button>')
            .attr('data-placeholder', '{{all_fields}}')
            .appendTo($list);
    }

    $('#related_form_id').on('change', renderPlaceholders);
    renderPlaceholders();

    $(document).on('click', '.bet-placeholder-chip', function () {
        insertAtCursor(lastFocusedTarget && lastFocusedTarget.length ? lastFocusedTarget : $('#custom_html'), $(this).data('placeholder'));
    });

    let mediaUploader;
    $('#upload_logo_btn').on('click', function (e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media({
            title: 'Select logo',
            button: { text: 'Use this logo' },
            multiple: false
        });
        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#logo_url').val(attachment.url);
            if ($('.bet-logo-preview').length) {
                $('.bet-logo-preview').attr('src', attachment.url);
            } else {
                $('#logo_preview_container').html('<img src="' + escapeHtml(attachment.url) + '" class="bet-logo-preview" alt="Logo preview">');
            }
        });
        mediaUploader.open();
    });

    $('.bet-save-btn-top').on('click', function (e) {
        e.preventDefault();
        $('#bet-template-form').trigger('submit');
    });

    $('.bet-preview-btn-top').on('click', function (e) {
        e.preventDefault();
        $('#bet-preview-btn').trigger('click');
    });

    $('#bet-preview-btn').on('click', function (e) {
        e.preventDefault();
        const formData = getFormData();
        const $preview = $('#bet-preview-container');
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Loading...');
        $preview.html('<p class="bet-preview-placeholder">Loading preview...</p>');

        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: { action: 'bet_preview_template', nonce: betAjax.nonce, ...formData },
            success: function (response) {
                $btn.prop('disabled', false).text(originalText);
                if (!response.success) {
                    showMessage('Preview failed.', 'error');
                    return;
                }
                const iframe = $('<iframe></iframe>');
                $preview.html(iframe);
                const iframeDoc = iframe[0].contentDocument || iframe[0].contentWindow.document;
                iframeDoc.open();
                iframeDoc.write(response.data);
                iframeDoc.close();
                iframe.on('load', function () {
                    iframe.css('min-height', Math.max(500, iframeDoc.body.scrollHeight) + 'px');
                });
            },
            error: function () {
                $btn.prop('disabled', false).text(originalText);
                showMessage('Preview failed.', 'error');
            }
        });
    });

    $('#bet-template-form').on('submit', function (e) {
        e.preventDefault();
        const formData = getFormData();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');

        if (!formData.name) {
            showMessage('Template name is required.', 'error');
            return;
        }

        $submitBtn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: { action: 'bet_save_template', nonce: betAjax.nonce, ...formData },
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function () { window.location.href = 'admin.php?page=bricks-email-builder'; }, 700);
                    return;
                }
                showMessage('Save failed: ' + response.data, 'error');
                $submitBtn.prop('disabled', false).text('Save template');
            },
            error: function () {
                showMessage('Save failed.', 'error');
                $submitBtn.prop('disabled', false).text('Save template');
            }
        });
    });

    $('.bet-delete-btn').on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const templateId = $btn.data('id');
        const templateName = $btn.data('name');
        if (!confirm('Delete template "' + templateName + '"?')) {
            return;
        }
        $btn.prop('disabled', true).text('Deleting...');
        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: { action: 'bet_delete_template', nonce: betAjax.nonce, template_id: templateId },
            success: function (response) {
                if (response.success) {
                    showMessage('Template deleted.', 'success');
                    $btn.closest('.bet-template-item').fadeOut(300, function () { $(this).remove(); });
                    return;
                }
                showMessage('Delete failed.', 'error');
                $btn.prop('disabled', false).text('Delete');
            },
            error: function () {
                showMessage('Delete failed.', 'error');
                $btn.prop('disabled', false).text('Delete');
            }
        });
    });

    function getFormData() {
        const mode = $('input[name="template_mode"]:checked').val();
        return {
            template_id: $('#template_id').val(),
            name: $('#template_name').val(),
            related_form_id: $('#related_form_id').val(),
            template_mode: mode,
            custom_html: mode === 'html' ? $('#custom_html').val() : '',
            layout: $('input[name="layout"]:checked').val(),
            color_header_start: $('#color_header_start').val(),
            color_header_end: $('#color_header_end').val(),
            color_accent: $('#color_accent').val(),
            color_background: $('#color_background').val(),
            color_title: $('#color_title').val(),
            color_text: $('#color_text').val(),
            color_footer: $('#color_footer').val(),
            logo_url: $('#logo_url').val(),
            email_subject: $('#email_subject').val(),
            header_text: $('#header_text').val(),
            intro_text: $('#intro_text').val(),
            footer_text: $('#footer_text').val()
        };
    }

    function insertAtCursor($target, text) {
        const el = $target[0];
        if (!el) {
            return;
        }
        const current = $target.val();
        if (typeof el.selectionStart === 'number') {
            const start = el.selectionStart;
            const end = el.selectionEnd;
            $target.val(current.substring(0, start) + text + current.substring(end));
            el.selectionStart = el.selectionEnd = start + text.length;
        } else {
            $target.val(current + text);
        }
        $target.trigger('focus').trigger('input');
    }

    function showMessage(message, type) {
        const $message = $('<div class="bet-message bet-message-' + type + '">' + escapeHtml(message) + '</div>');
        $('.bet-builder-form-panel .bet-card').first().prepend($message);
        setTimeout(function () { $message.fadeOut(300, function () { $(this).remove(); }); }, 5000);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }
});
