/**
 * Email Builder JavaScript
 */

jQuery(document).ready(function ($) {

    // Initialize color pickers
    $('.bet-color-picker').wpColorPicker({
        change: function () {
            // Color changed
        }
    });

    // Logo upload
    let mediaUploader;

    $('#upload_logo_btn').on('click', function (e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Vyberte logo',
            button: {
                text: 'Použít toto logo'
            },
            multiple: false
        });

        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#logo_url').val(attachment.url);

            // Show preview
            if ($('.bet-logo-preview').length) {
                $('.bet-logo-preview').attr('src', attachment.url);
            } else {
                $('#logo_url').after('<img src="' + attachment.url + '" class="bet-logo-preview" alt="Logo preview">');
            }
        });

        mediaUploader.open();
    });

    // Top Toolbar: Save & Preview
    $('.bet-save-btn-top').on('click', function (e) {
        e.preventDefault();
        $('#bet-template-form').submit();
    });

    $('.bet-preview-btn-top').on('click', function (e) {
        e.preventDefault();
        $('#bet-preview-btn').click();
    });

    // Preview template
    $('#bet-preview-btn').on('click', function (e) {
        e.preventDefault();

        const formData = getFormData();
        const $preview = $('#bet-preview-container');

        // Show loading state in button
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Načítám...');

        $preview.html('<p class="bet-preview-placeholder">Načítání náhledu...</p>');

        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bet_preview_template',
                nonce: betAjax.nonce,
                ...formData
            },
            success: function (response) {
                $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    // Create iframe for preview
                    const iframe = $('<iframe></iframe>');
                    $preview.html(iframe);

                    // Write HTML to iframe
                    const iframeDoc = iframe[0].contentDocument || iframe[0].contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(response.data);
                    iframeDoc.close();

                    // Adjust iframe height
                    iframe.on('load', function () {
                        const height = iframeDoc.body.scrollHeight;
                        iframe.css('min-height', height + 'px');
                    });
                } else {
                    showMessage('Chyba při načítání náhledu', 'error');
                }
            },
            error: function () {
                showMessage('Chyba při načítání náhledu', 'error');
            }
        });
    });

    // Save template
    $('#bet-template-form').on('submit', function (e) {
        e.preventDefault();

        const formData = getFormData();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');

        // Validation
        if (!formData.name) {
            showMessage('Vyplňte název šablony', 'error');
            return;
        }

        $submitBtn.prop('disabled', true).text('Ukládám...');

        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bet_save_template',
                nonce: betAjax.nonce,
                ...formData
            },
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');

                    // Redirect to list after 1 second
                    setTimeout(function () {
                        window.location.href = 'admin.php?page=bricks-email-builder';
                    }, 1000);
                } else {
                    showMessage('Chyba při ukládání: ' + response.data, 'error');
                    $submitBtn.prop('disabled', false).text('💾 Uložit šablonu');
                }
            },
            error: function () {
                showMessage('Chyba při ukládání šablony', 'error');
                $submitBtn.prop('disabled', false).text('💾 Uložit šablonu');
            }
        });
    });

    // Delete template
    $('.bet-delete-btn').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const templateId = $btn.data('id');
        const templateName = $btn.data('name');

        if (!confirm('Opravdu chcete smazat šablonu "' + templateName + '"?')) {
            return;
        }

        $btn.prop('disabled', true).text('Mazání...');

        $.ajax({
            url: betAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bet_delete_template',
                nonce: betAjax.nonce,
                template_id: templateId
            },
            success: function (response) {
                if (response.success) {
                    showMessage('Šablona smazána', 'success');
                    $btn.closest('.bet-template-item').fadeOut(300, function () {
                        $(this).remove();

                        // Check if no templates left
                        if ($('.bet-template-item').length === 0) {
                            $('.bet-templates-list').html('<p class="bet-no-templates">Zatím nemáte žádné šablony. Vytvořte první!</p>');
                        }
                    });
                } else {
                    showMessage('Chyba při mazání šablony', 'error');
                    $btn.prop('disabled', false).text('🗑️ Smazat');
                }
            },
            error: function () {
                showMessage('Chyba při mazání šablony', 'error');
                $btn.prop('disabled', false).text('🗑️ Smazat');
            }
        });
    });

    /**
     * Get form data
     */
    function getFormData() {
        return {
            template_id: $('#template_id').val(),
            name: $('#template_name').val(),
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

    /**
     * Show message
     */
    function showMessage(message, type) {
        const $message = $('<div class="bet-message bet-message-' + type + '">' + message + '</div>');
        $('.bet-builder-form-panel .bet-card').prepend($message);

        setTimeout(function () {
            $message.fadeOut(300, function () {
                $(this).remove();
            });
        }, 5000);
    }

});
