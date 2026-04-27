/**
 * HTML Email Template Builder JavaScript
 */

jQuery(document).ready(function ($) {
    let lastFocusedTarget = $('#custom_html');

    $('#custom_html').on('focus click keyup mouseup', function () {
        lastFocusedTarget = $(this);
    });

    function renderPlaceholders() {
        const formId = $('#related_form_id').val();
        const forms = (window.betAjax && Array.isArray(betAjax.forms)) ? betAjax.forms : [];
        const form = forms.find(function (item) { return item.id === formId; });
        const fields = form && Array.isArray(form.fields) ? form.fields : [];

        const $list = $('#bet-placeholder-list').empty();
        if (!fields.length) {
            $('.bet-placeholder-panel').hide();
            return;
        }

        $('.bet-placeholder-panel').show();
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

    $('#related_form_id').on('change', function () {
        renderPlaceholders();
    });
    renderPlaceholders();

    $('#existing_template_slug').on('change', function () {
        const slug = $(this).val();
        const url = new URL(window.location.href);
        url.searchParams.set('page', 'bricks-email-builder');
        url.searchParams.set('preview', '1');
        if (slug) {
            url.searchParams.set('edit', slug);
        } else {
            url.searchParams.delete('edit');
            url.searchParams.delete('preview');
        }
        window.location.href = url.toString();
    });

    $('#bet-create-template-btn').on('click', function (e) {
        e.preventDefault();
        const url = new URL(window.location.href);
        url.searchParams.set('page', 'bricks-email-builder');
        url.searchParams.delete('edit');
        url.searchParams.delete('preview');
        window.location.href = url.toString();
    });

    $(document).on('click', '.bet-placeholder-chip', function () {
        insertAtCursor(lastFocusedTarget && lastFocusedTarget.length ? lastFocusedTarget : $('#custom_html'), $(this).data('placeholder'));
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
                const iframe = document.createElement('iframe');
                iframe.setAttribute('sandbox', '');
                iframe.setAttribute('srcdoc', response.data);
                iframe.style.height = getPreviewFallbackHeight() + 'px';
                $preview.empty();
                $preview[0].appendChild(iframe);
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
        const $topSubmitBtn = $('.bet-save-btn-top');

        if (!formData.name) {
            showMessage('Template name is required.', 'error');
            return;
        }
        if (!formData.custom_html) {
            showMessage('HTML template is required.', 'error');
            return;
        }

        $submitBtn.prop('disabled', true).text('Saving...');
        $topSubmitBtn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: { action: 'bet_save_template', nonce: betAjax.nonce, ...formData },
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    const slug = response.data && response.data.slug ? response.data.slug : $('#template_slug').val();
                    if (slug) {
                        $('#template_slug').val(slug);
                        if (response.data && response.data.uuid) {
                            $('#template_uuid').val(response.data.uuid);
                        }
                        $('#existing_template_slug').val(slug);
                        setTimeout(function () {
                            window.location.href = 'admin.php?page=bricks-email-builder&edit=' + encodeURIComponent(slug) + '&preview=1';
                        }, 500);
                    } else {
                        $submitBtn.prop('disabled', false).text('Save template');
                        $topSubmitBtn.prop('disabled', false).text('Save template');
                    }
                    return;
                }
                showMessage('Save failed: ' + response.data, 'error');
                $submitBtn.prop('disabled', false).text('Save template');
                $topSubmitBtn.prop('disabled', false).text('Save template');
            },
            error: function () {
                showMessage('Save failed.', 'error');
                $submitBtn.prop('disabled', false).text('Save template');
                $topSubmitBtn.prop('disabled', false).text('Save template');
            }
        });
    });

    function getFormData() {
        return {
            template_slug: $('#template_slug').val(),
            template_uuid: $('#template_uuid').val(),
            name: String($('#template_name').val() || '').trim(),
            related_form_id: $('#related_form_id').val(),
            template_target: getSelectedTemplateTarget(),
            custom_html: String($('#custom_html').val() || '').trim()
        };
    }

    function getSelectedTemplateTarget() {
        const email = $('#template_target_email').is(':checked');
        const confirmation = $('#template_target_confirmation').is(':checked');
        if (email && confirmation) {
            return 'both';
        }
        if (confirmation) {
            return 'confirmation';
        }
        if (email) {
            return 'email';
        }
        return 'none';
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

    function getPreviewFallbackHeight() {
        return 1000;
    }

    function showMessage(message, type) {
        const $message = $('<div class="bet-message bet-message-' + type + '">' + escapeHtml(message) + '</div>');
        const $container = $('#bet-message-container');
        if ($container.length) {
            $container.empty().append($message);
        } else {
            $('.bet-builder-form-panel .bet-card').first().prepend($message);
        }
        setTimeout(function () { $message.fadeOut(300, function () { $(this).remove(); }); }, 5000);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    if (new URLSearchParams(window.location.search).get('preview') === '1' && String($('#custom_html').val() || '').trim()) {
        $('#bet-preview-btn').trigger('click');
    }
});
