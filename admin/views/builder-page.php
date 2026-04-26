<?php
/**
 * Email Template Builder Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_editing = !empty($editing_template);
$template = $is_editing ? $editing_template : null;
$custom_html = $is_editing && isset($template->custom_html) ? (string) $template->custom_html : '';
$mode = $custom_html !== '' ? 'html' : 'visual';
$related_form_id = $is_editing && isset($template->related_form_id) ? (string) $template->related_form_id : '';
?>

<div class="wrap bet-builder-wrap">
    <h1>Email Template Builder</h1>
    <p>Create visual templates or paste full custom HTML. Use placeholders such as <code>{{all_fields}}</code> or field-specific placeholders from a detected Bricks form.</p>

    <div class="bet-builder-header-actions">
        <div class="bet-toolbar">
            <button type="button" class="button button-secondary bet-preview-btn-top">Preview</button>
            <button type="button" class="button button-primary bet-save-btn-top">Save template</button>
        </div>
    </div>

    <div class="bet-builder-container">
        <div class="bet-builder-form-panel">
            <div class="bet-card">
                <h2><?php echo $is_editing ? esc_html__('Edit template', 'bricks-email-templates') : esc_html__('New template', 'bricks-email-templates'); ?></h2>

                <form id="bet-template-form">
                    <input type="hidden" id="template_id" value="<?php echo $is_editing ? esc_attr($template->id) : ''; ?>">

                    <div class="bet-form-group">
                        <label for="template_name">Template name *</label>
                        <input type="text" id="template_name" class="bet-input" required value="<?php echo $is_editing ? esc_attr($template->name) : ''; ?>" placeholder="Contact form email">
                    </div>

                    <div class="bet-form-group">
                        <label for="related_form_id">Placeholder source form</label>
                        <select id="related_form_id" class="bet-input">
                            <option value="">Sample fields</option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($related_form_id, $form['id']); ?>><?php echo esc_html($form['name'] . ' (' . $form['id'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description">Choose a Bricks form to show its real field placeholders below.</small>
                    </div>

                    <div class="bet-form-group">
                        <label>Template type</label>
                        <div class="bet-layout-selector">
                            <label class="bet-layout-option"><input type="radio" name="template_mode" value="visual" <?php checked($mode, 'visual'); ?>><span class="bet-layout-card"><strong>Visual builder</strong><small>Simple styled layout</small></span></label>
                            <label class="bet-layout-option"><input type="radio" name="template_mode" value="html" <?php checked($mode, 'html'); ?>><span class="bet-layout-card"><strong>Custom HTML</strong><small>Full email markup</small></span></label>
                        </div>
                    </div>

                    <div class="bet-placeholder-panel">
                        <div class="bet-placeholder-panel-title">Available placeholders</div>
                        <div id="bet-placeholder-list" class="bet-placeholder-list"></div>
                        <p class="description">Click a placeholder to insert it into the custom HTML editor, subject, title, intro, or footer field.</p>
                    </div>

                    <div id="bet-html-fields" class="bet-mode-section">
                        <div class="bet-form-group">
                            <label for="custom_html">Custom HTML</label>
                            <textarea id="custom_html" class="bet-textarea bet-code-textarea bet-placeholder-target" rows="18" spellcheck="false" placeholder="<!DOCTYPE html>
<html>
<body>
  {{all_fields}}
</body>
</html>"><?php echo esc_textarea($custom_html); ?></textarea>
                            <small class="description">Saved in the database and safe from plugin updates. File-based custom templates should live in the active theme folder.</small>
                        </div>
                    </div>

                    <div id="bet-visual-fields" class="bet-mode-section">
                        <div class="bet-form-group">
                            <label>Layout</label>
                            <div class="bet-layout-selector">
                                <label class="bet-layout-option"><input type="radio" name="layout" value="card" <?php checked($is_editing ? $template->layout : 'card', 'card'); ?>><span class="bet-layout-card"><strong>Card</strong><small>Rounded, clean design</small></span></label>
                                <label class="bet-layout-option"><input type="radio" name="layout" value="modern" <?php checked($is_editing ? $template->layout : 'card', 'modern'); ?>><span class="bet-layout-card"><strong>Modern</strong><small>Balanced email layout</small></span></label>
                            </div>
                        </div>

                        <div class="bet-form-group"><label>Background color</label><input type="text" class="bet-color-picker" id="color_background" value="<?php echo $is_editing ? esc_attr($template->color_background) : '#f3f4f6'; ?>"></div>

                        <div class="bet-form-group">
                            <label for="logo_url">Logo (optional)</label>
                            <div class="bet-media-upload"><input type="text" id="logo_url" name="logo_url" class="bet-input" readonly value="<?php echo $is_editing ? esc_url($template->logo_url) : ''; ?>" placeholder="Logo URL"><button type="button" class="button bet-upload-btn" id="upload_logo_btn">Upload logo</button></div>
                            <div id="logo_preview_container" style="margin-top: 10px;"><?php if ($is_editing && $template->logo_url): ?><img src="<?php echo esc_url($template->logo_url); ?>" class="bet-logo-preview" alt="Logo" style="max-height: 50px;"><?php endif; ?></div>
                        </div>

                        <div class="bet-form-group">
                            <label for="header_text">Email heading</label>
                            <input type="text" id="header_text" name="header_text" class="bet-input bet-placeholder-target" value="<?php echo $is_editing ? esc_attr($template->header_text) : 'New message'; ?>" placeholder="New message">
                            <div style="margin-top: 8px;"><label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Heading text color</label><input type="text" class="bet-color-picker" id="color_title" value="<?php echo $is_editing ? esc_attr($template->color_title) : '#1e293b'; ?>"></div>
                        </div>

                        <div class="bet-form-group">
                            <label for="intro_text">Intro text</label>
                            <textarea id="intro_text" name="intro_text" class="bet-textarea bet-placeholder-target" rows="3" placeholder="A form was submitted on your website."><?php echo $is_editing ? esc_textarea($template->intro_text) : 'A form was submitted on your website.'; ?></textarea>
                            <div style="margin-top: 8px;"><label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Body text color</label><input type="text" class="bet-color-picker" id="color_text" value="<?php echo $is_editing ? esc_attr($template->color_text) : '#4b5563'; ?>"></div>
                        </div>

                        <div class="bet-form-group">
                            <label for="footer_text">Footer text</label>
                            <input type="text" id="footer_text" name="footer_text" class="bet-input bet-placeholder-target" value="<?php echo $is_editing ? esc_attr($template->footer_text) : esc_attr(get_bloginfo('name')); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            <div style="margin-top: 8px;"><label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Footer text color</label><input type="text" class="bet-color-picker" id="color_footer" value="<?php echo $is_editing ? esc_attr($template->color_footer) : '#9ca3af'; ?>"></div>
                        </div>

                        <details style="margin-top: 20px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;"><summary style="cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b;">Advanced colors</summary><div class="bet-color-grid" style="margin-top: 10px;"><div class="bet-color-item"><label>Header start</label><input type="text" class="bet-color-picker" id="color_header_start" value="<?php echo $is_editing ? esc_attr($template->color_header_start) : '#667eea'; ?>"></div><div class="bet-color-item"><label>Header end</label><input type="text" class="bet-color-picker" id="color_header_end" value="<?php echo $is_editing ? esc_attr($template->color_header_end) : '#764ba2'; ?>"></div><div class="bet-color-item"><label>Accent</label><input type="text" class="bet-color-picker" id="color_accent" value="<?php echo $is_editing ? esc_attr($template->color_accent) : '#2563eb'; ?>"></div></div></details>
                    </div>

                    <div class="bet-form-group">
                        <label for="email_subject">Email subject override (optional)</label>
                        <input type="text" id="email_subject" name="email_subject" class="bet-input bet-placeholder-target" value="<?php echo $is_editing ? esc_attr($template->email_subject) : ''; ?>" placeholder="New website enquiry">
                        <small class="description">Overrides the subject configured in Bricks.</small>
                    </div>

                    <div class="bet-form-actions"><button type="button" class="button button-secondary" id="bet-preview-btn">Preview</button><button type="submit" class="button button-primary">Save template</button><?php if ($is_editing): ?><a href="<?php echo esc_url(admin_url('admin.php?page=bricks-email-builder')); ?>" class="button">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>

        <div class="bet-builder-preview-panel">
            <div class="bet-card bet-sticky-preview"><h3>Preview</h3><div id="bet-preview-container" class="bet-preview"><p class="bet-preview-placeholder">Click Preview to render the template.</p></div></div>
            <div class="bet-card"><h3>Saved templates</h3><?php if (empty($templates)): ?><p class="bet-no-templates">No templates yet.</p><?php else: ?><div class="bet-templates-list"><?php foreach ($templates as $tmpl): ?><div class="bet-template-item"><div class="bet-template-info"><strong><?php echo esc_html($tmpl->name); ?></strong><small><?php echo !empty($tmpl->custom_html) ? esc_html__('Custom HTML', 'bricks-email-templates') : esc_html(ucfirst($tmpl->layout) . ' layout'); ?></small></div><div class="bet-template-actions"><a href="<?php echo esc_url(admin_url('admin.php?page=bricks-email-builder&edit=' . absint($tmpl->id))); ?>" class="button button-small">Edit</a><button type="button" class="button button-small bet-delete-btn" data-id="<?php echo esc_attr($tmpl->id); ?>" data-name="<?php echo esc_attr($tmpl->name); ?>">Delete</button></div></div><?php endforeach; ?></div><?php endif; ?></div>
        </div>
    </div>
</div>
