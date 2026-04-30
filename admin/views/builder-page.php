<?php
/**
 * HTML Email Template Builder Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_editing = !empty($editing_template);
$template = $is_editing ? $editing_template : null;
$template_slug = $is_editing && isset($template->slug) ? (string) $template->slug : '';
$template_uuid = $is_editing && isset($template->uuid) ? (string) $template->uuid : '';
$template_name = $is_editing && isset($template->name) ? (string) $template->name : '';
$custom_html = $is_editing && isset($template->content) ? (string) $template->content : '';
$related_form_id = $is_editing && isset($template->related_form_id) ? (string) $template->related_form_id : '';
$template_target = $is_editing && isset($template->template_target) ? (string) $template->template_target : 'none';
$current_file = $is_editing && isset($template->current_file) ? (string) $template->current_file : '';
$target_email_checked = in_array($template_target, array('email', 'both'), true);
$target_confirmation_checked = in_array($template_target, array('confirmation', 'both'), true);
$template_storage_mode = isset($template_storage_mode) ? (string) $template_storage_mode : 'theme';
?>

<div class="wrap bet-builder-wrap">
    <h1>HTML Email Template Builder</h1>
    <div id="bet-message-container" class="bet-message-container"></div>
    <?php if ($template_storage_mode === 'uploads'): ?>
        <p>Create, edit, and assign HTML email template files in this site's uploads folder.</p>
    <?php else: ?>
        <p>Create, edit, and assign HTML email template files in your active child theme or parent theme.</p>
    <?php endif; ?>

    <?php if (empty($template_dir)): ?>
        <?php if ($template_storage_mode === 'uploads'): ?>
            <div class="notice notice-error"><p>No writable uploads template directory was found. Check that this site's uploads folder is writable.</p></div>
        <?php else: ?>
            <div class="notice notice-error"><p>No writable theme template directory was found. Create <code>bricks-email-templates</code> in your child theme and make it writable.</p></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bet-builder-header-actions">
        <div class="bet-toolbar bet-template-switcher">
            <button type="button" class="button button-secondary" id="bet-create-template-btn">Create new template</button>
            <span class="bet-template-switcher-text">or select existing one:</span>
            <label class="screen-reader-text" for="existing_template_slug">Select existing template to edit</label>
            <select id="existing_template_slug" class="bet-input bet-existing-template-select">
                <option value="">Select existing template to edit</option>
                <?php foreach ($templates as $tmpl): ?>
                    <?php
                    $saved_template_target = !empty($tmpl['template_target']) ? (string) $tmpl['template_target'] : 'email';
                    $saved_template_target_label = array(
                        'none' => 'Unassigned',
                        'email' => 'Email',
                        'confirmation' => 'Confirmation email',
                        'both' => 'Both',
                    )[$saved_template_target] ?? 'Email';
                    ?>
                    <option value="<?php echo esc_attr($tmpl['slug']); ?>" <?php selected($template_slug, $tmpl['slug']); ?>>
                        <?php echo esc_html($tmpl['name'] . ' - ' . $saved_template_target_label . ' (' . basename($tmpl['file']) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bet-toolbar bet-toolbar-primary">
            <button type="button" class="button button-secondary bet-preview-btn-top">Refresh preview</button>
            <button type="button" class="button button-primary bet-save-btn-top">Save template</button>
        </div>
    </div>

    <div class="bet-builder-container">
        <div class="bet-builder-form-panel">
            <div class="bet-card">
                <h2><?php echo $is_editing ? esc_html__('Edit HTML template file', 'bricks-email-templates') : esc_html__('New HTML template file', 'bricks-email-templates'); ?></h2>

                <form id="bet-template-form">
                    <input type="hidden" id="template_slug" value="<?php echo esc_attr($template_slug); ?>">
                    <input type="hidden" id="template_uuid" value="<?php echo esc_attr($template_uuid); ?>">

                    <div class="bet-form-group">
                        <label for="template_name">Template name *</label>
                        <input type="text" id="template_name" class="bet-input" required value="<?php echo esc_attr($template_name); ?>" placeholder="Customer notification">
                        <small class="description">This name is stored in WordPress settings; changing it does not rename the HTML file.</small>
                    </div>

                    <div class="bet-form-group">
                        <label for="related_form_id">Used Bricks form</label>
                        <select id="related_form_id" class="bet-input">
                            <option value="">None</option>
                            <?php foreach ($forms as $form): ?>
                                <?php
                                $form_page_title = !empty($form['page_title']) ? (string) $form['page_title'] : '';
                                $form_label = $form_page_title !== '' ? $form_page_title : __('Untitled page', 'bricks-email-templates');
                                if (!empty($form['id'])) {
                                    $form_label .= ' - ' . sprintf(__('Form %s', 'bricks-email-templates'), (string) $form['id']);
                                }
                                ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($related_form_id, $form['id']); ?>><?php echo esc_html($form_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description">The selected form controls available placeholders and is stored in WordPress settings.</small>
                    </div>

                    <div class="bet-form-group">
                        <label>Template target/receiver</label>
                        <div class="bet-target-row">
                            <fieldset class="bet-target-checkboxes">
                                <legend class="screen-reader-text">Template target/receiver</legend>
                                <label class="bet-checkbox-label">
                                    <input type="checkbox" id="template_target_email" value="email" <?php checked($target_email_checked); ?>>
                                    Email
                                </label>
                                <label class="bet-checkbox-label">
                                    <input type="checkbox" id="template_target_confirmation" value="confirmation" <?php checked($target_confirmation_checked); ?>>
                                    Confirmation email
                                </label>
                            </fieldset>
                        </div>
                        <small class="description">Choose which Bricks email this template should replace for the selected form. Leave both unchecked to save the file without assigning it to any email.</small>
                    </div>

                    <div class="bet-placeholder-panel">
                        <div class="bet-placeholder-panel-title">Available placeholders</div>
                        <div id="bet-placeholder-list" class="bet-placeholder-list"></div>
                        <p class="description">Click a placeholder to insert it at the current cursor position in the HTML editor.</p>
                    </div>

                    <div class="bet-form-group">
                        <label for="custom_html">HTML template *</label>
                        <textarea id="custom_html" class="bet-textarea bet-code-textarea bet-placeholder-target" rows="26" spellcheck="false" placeholder="<!DOCTYPE html>\n<html>\n<body>\n  {{all_fields}}\n</body>\n</html>"><?php echo esc_textarea($custom_html); ?></textarea>
                        <small class="description">Use HTML, inline CSS, images, and placeholders. PHP code is not executed.</small>
                        <?php if ($current_file !== ''): ?>
                            <small class="description">Editing file: <code><?php echo esc_html($current_file); ?></code></small>
                        <?php else: ?>
                            <small class="description">Editing file: the file will be created after saving.</small>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="button button-secondary bet-hidden-action" id="bet-preview-btn">Refresh preview</button>
                    <button type="submit" class="button button-primary bet-hidden-action">Save template</button>
                </form>
            </div>
        </div>

        <div class="bet-builder-preview-panel">
            <div class="bet-card bet-sticky-preview">
                <h3>Preview</h3>
                <div id="bet-preview-container" class="bet-preview"><p class="bet-preview-placeholder">Click Refresh preview to render the template.</p></div>
            </div>
        </div>
    </div>

    <div class="bet-template-notes">
        <p><strong>Template priority:</strong> If a template is mapped here for the selected Bricks form and target, this plugin overrides the matching Bricks email body. Keep the Bricks email action enabled so Bricks still sends the email.</p>
        <?php if (!empty($template_dir)): ?>
            <p><strong>Templates are saved to:</strong> <code><?php echo esc_html($template_dir); ?></code></p>
        <?php endif; ?>
        <?php if ($template_storage_mode === 'uploads'): ?>
            <p><strong>Template files:</strong> HTML templates are stored in this site's uploads folder under <code><?php echo esc_html(BET_THEME_TEMPLATES_FOLDER); ?></code>, so they are not removed by theme or plugin updates. Existing theme-based templates are copied to uploads when the builder opens.</p>
        <?php else: ?>
            <p><strong>Template files:</strong> All HTML templates are stored in the theme folder <code><?php echo esc_html(BET_THEME_TEMPLATES_FOLDER); ?></code>. The active child theme is used first; the parent theme is checked after that.</p>
        <?php endif; ?>
        <?php if (!empty($template_dirs) && is_array($template_dirs)): ?>
            <ul>
                <?php foreach ($template_dirs as $dir): ?>
                    <li><code><?php echo esc_html($dir); ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
