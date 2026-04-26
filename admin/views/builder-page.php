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
$template_name = $is_editing && isset($template->name) ? (string) $template->name : '';
$custom_html = $is_editing && isset($template->content) ? (string) $template->content : '';
$related_form_id = $is_editing && isset($template->related_form_id) ? (string) $template->related_form_id : '';
?>

<div class="wrap bet-builder-wrap">
    <h1>HTML Email Template Builder</h1>
    <p>Create, edit, and assign HTML email template files in your active child theme or parent theme.</p>

    <?php if (empty($template_dir)): ?>
        <div class="notice notice-error"><p>No writable theme template directory was found. Create <code>bricks-email-templates</code> in your child theme and make it writable.</p></div>
    <?php else: ?>
        <div class="notice notice-info inline"><p>Templates are saved to: <code><?php echo esc_html($template_dir); ?></code></p></div>
    <?php endif; ?>

    <div class="bet-builder-header-actions">
        <div class="bet-toolbar">
            <button type="button" class="button button-secondary bet-preview-btn-top">Preview</button>
            <button type="button" class="button button-primary bet-save-btn-top">Save template</button>
        </div>
    </div>

    <div class="bet-builder-container">
        <div class="bet-builder-form-panel">
            <div class="bet-card">
                <h2><?php echo $is_editing ? esc_html__('Edit HTML template file', 'bricks-email-templates') : esc_html__('New HTML template file', 'bricks-email-templates'); ?></h2>

                <form id="bet-template-form">
                    <input type="hidden" id="template_slug" value="<?php echo esc_attr($template_slug); ?>">

                    <div class="bet-form-group">
                        <label for="existing_template_slug">Load saved template</label>
                        <select id="existing_template_slug" class="bet-input">
                            <option value="">Create new template</option>
                            <?php foreach ($templates as $tmpl): ?>
                                <option value="<?php echo esc_attr($tmpl['slug']); ?>" <?php selected($template_slug, $tmpl['slug']); ?>>
                                    <?php echo esc_html($tmpl['name'] . ' (' . basename($tmpl['file']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description">Selecting a saved template loads its file and automatically selects its related form below.</small>
                    </div>

                    <div class="bet-form-group">
                        <label for="template_name">Template name *</label>
                        <input type="text" id="template_name" class="bet-input" required value="<?php echo esc_attr($template_name); ?>" placeholder="Customer notification">
                        <small class="description">This name is stored in a metadata comment inside the template file.</small>
                    </div>

                    <div class="bet-form-group">
                        <label for="related_form_id">Used Bricks form</label>
                        <select id="related_form_id" class="bet-input">
                            <option value="">None</option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($related_form_id, $form['id']); ?>><?php echo esc_html($form['name'] . ' (' . $form['id'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description">The selected form controls available placeholders and is saved as metadata in the template file.</small>
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
                    </div>

                    <div class="bet-form-actions">
                        <button type="button" class="button button-secondary" id="bet-preview-btn">Preview</button>
                        <button type="submit" class="button button-primary">Save template</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bet-builder-preview-panel">
            <div class="bet-card bet-sticky-preview">
                <h3>Preview</h3>
                <div id="bet-preview-container" class="bet-preview"><p class="bet-preview-placeholder">Click Preview to render the template.</p></div>
            </div>
        </div>
    </div>
</div>
