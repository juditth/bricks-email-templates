<?php
/**
 * Email Builder Admin Page
 */

if (!defined('ABSPATH')) exit;

$is_editing = !empty($editing_template);
$template = $is_editing ? $editing_template : null;
?>

<div class="wrap bet-builder-wrap">
    <h1>📧 Email Builder</h1>
    <p>Vytvořte krásné email šablony bez psaní HTML kódu.</p>
    
    <div class="bet-builder-header-actions">
        <!-- Top Toolbar -->
        <div class="bet-toolbar">
            <button type="button" class="button button-secondary bet-preview-btn-top">
                👁️ Náhled
            </button>
            <button type="button" class="button button-primary bet-save-btn-top">
                💾 Uložit šablonu
            </button>
        </div>
    </div>
    
    <div class="bet-builder-container">
        <!-- Left Panel: Form (50%) -->
        <div class="bet-builder-form-panel">
            <div class="bet-card">
                <h2><?php echo $is_editing ? 'Upravit šablonu' : 'Nová šablona'; ?></h2>
                
                <form id="bet-template-form">
                    <input type="hidden" id="template_id" value="<?php echo $is_editing ? esc_attr($template->id) : ''; ?>">
                    
                    <!-- Template Name -->
                    <div class="bet-form-group">
                        <label for="template_name">Název šablony *</label>
                        <input type="text" id="template_name" class="bet-input" required 
                               value="<?php echo $is_editing ? esc_attr($template->name) : ''; ?>" 
                               placeholder="Např. Kontaktní formulář">
                    </div>
                    
                    <!-- Layout -->
                    <div class="bet-form-group">
                        <label>Layout</label>
                        <div class="bet-layout-selector">
                            <label class="bet-layout-option">
                                <input type="radio" name="layout" value="card" 
                                       <?php checked($is_editing ? $template->layout : 'card', 'card'); ?>>
                                <span class="bet-layout-card">
                                    <strong>Karta (Doporučeno)</strong>
                                    <small>Kulaté rohy, čistý design</small>
                                </span>
                            </label>
                            <label class="bet-layout-option">
                                <input type="radio" name="layout" value="modern" 
                                       <?php checked($is_editing ? $template->layout : 'card', 'modern'); ?>>
                                <span class="bet-layout-card">
                                    <strong>Moderní</strong>
                                    <small>Vyvážený design</small>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Colors Section (Ordered as requested) -->
                    <div class="bet-form-group">
                        <label>Barva pozadí</label>
                        <input type="text" class="bet-color-picker" id="color_background" 
                               value="<?php echo $is_editing ? esc_attr($template->color_background) : '#f3f4f6'; ?>">
                    </div>

                    <!-- Logo -->
                    <div class="bet-form-group">
                        <label for="logo_url">Logo (volitelné)</label>
                        <div class="bet-media-upload">
                            <input type="text" id="logo_url" name="logo_url" class="bet-input" readonly 
                                   value="<?php echo $is_editing ? esc_url($template->logo_url) : ''; ?>" 
                                   placeholder="URL loga">
                            <button type="button" class="button bet-upload-btn" id="upload_logo_btn">
                                Nahrát logo
                            </button>
                        </div>
                        <div id="logo_preview_container" style="margin-top: 10px;">
                            <?php if ($is_editing && $template->logo_url): ?>
                                <img src="<?php echo esc_url($template->logo_url); ?>" class="bet-logo-preview" alt="Logo" style="max-height: 50px;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Email Subject -->
                    <div class="bet-form-group">
                        <label for="email_subject">Předmět emailu (volitelné)</label>
                        <input type="text" id="email_subject" name="email_subject" class="bet-input" 
                               value="<?php echo $is_editing ? esc_attr($template->email_subject) : ''; ?>" 
                               placeholder="Např. Nová poptávka z webu">
                        <small class="description">Přepíše předmět nastavený v Bricks.</small>
                    </div>

                    <!-- Header/Title -->
                    <div class="bet-form-group">
                        <label for="header_text">Nadpis e-mailu (v hlavičce)</label>
                        <input type="text" id="header_text" name="header_text" class="bet-input" 
                               value="<?php echo $is_editing ? esc_attr($template->header_text) : 'Nová zpráva'; ?>" 
                               placeholder="Nová zpráva">
                        <div style="margin-top: 8px;">
                            <label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Barva textu nadpisu</label>
                            <input type="text" class="bet-color-picker" id="color_title" 
                                   value="<?php echo $is_editing ? esc_attr($template->color_title) : '#1e293b'; ?>">
                        </div>
                    </div>

                    <!-- Intro Text -->
                    <div class="bet-form-group">
                        <label for="intro_text">Text emailu (Úvod)</label>
                        <textarea id="intro_text" name="intro_text" class="bet-textarea" rows="3" 
                                  placeholder="Zasíláme souhrn údajů z formuláře:"><?php echo $is_editing ? esc_textarea($template->intro_text) : 'Zasíláme souhrn údajů z formuláře:'; ?></textarea>
                        <div style="margin-top: 8px;">
                            <label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Barva textu emailu</label>
                            <input type="text" class="bet-color-picker" id="color_text" 
                                   value="<?php echo $is_editing ? esc_attr($template->color_text) : '#4b5563'; ?>">
                        </div>
                    </div>

                    <!-- Footer Text -->
                    <div class="bet-form-group">
                        <label for="footer_text">Text v patičce</label>
                        <input type="text" id="footer_text" name="footer_text" class="bet-input" 
                               value="<?php echo $is_editing ? esc_attr($template->footer_text) : get_bloginfo('name'); ?>" 
                               placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <div style="margin-top: 8px;">
                            <label style="font-size: 12px; font-weight: normal; display: block; margin-bottom: 4px;">Barva textu v patičce</label>
                            <input type="text" class="bet-color-picker" id="color_footer" 
                                   value="<?php echo $is_editing ? esc_attr($template->color_footer) : '#9ca3af'; ?>">
                        </div>
                    </div>

                    <!-- Advanced Styles (Hidden by default or at bottom) -->
                    <details style="margin-top: 20px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <summary style="cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b;">Pokročilé barvy (Hlavička & Akcent)</summary>
                        <div class="bet-color-grid" style="margin-top: 10px;">
                            <div class="bet-color-item">
                                <label>Header - Start</label>
                                <input type="text" class="bet-color-picker" id="color_header_start" 
                                       value="<?php echo $is_editing ? esc_attr($template->color_header_start) : '#667eea'; ?>">
                            </div>
                            <div class="bet-color-item">
                                <label>Header - Konec</label>
                                <input type="text" class="bet-color-picker" id="color_header_end" 
                                       value="<?php echo $is_editing ? esc_attr($template->color_header_end) : '#764ba2'; ?>">
                            </div>
                            <div class="bet-color-item">
                                <label>Akcent (rámeček polí)</label>
                                <input type="text" class="bet-color-picker" id="color_accent" 
                                       value="<?php echo $is_editing ? esc_attr($template->color_accent) : '#2563eb'; ?>">
                            </div>
                        </div>
                    </details>
                    
                    <!-- Actions -->
                    <div class="bet-form-actions">
                        <button type="button" class="button button-secondary" id="bet-preview-btn">
                            👁️ Náhled
                        </button>
                        <button type="submit" class="button button-primary">
                            💾 Uložit šablonu
                        </button>
                        <?php if ($is_editing): ?>
                            <a href="<?php echo admin_url('admin.php?page=bricks-email-builder'); ?>" class="button">
                                Zrušit
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Right Panel: Preview & Templates List (50%) -->
        <div class="bet-builder-preview-panel">
            <!-- Preview -->
            <div class="bet-card bet-sticky-preview">
                <h3>📱 Náhled</h3>
                <div id="bet-preview-container" class="bet-preview">
                    <p class="bet-preview-placeholder">Klikněte na "Náhled" pro zobrazení šablony</p>
                </div>
            </div>
            
            <!-- Existing Templates -->
            <div class="bet-card">
                <h3>📋 Uložené šablony</h3>
                <?php if (empty($templates)): ?>
                    <p class="bet-no-templates">Zatím nemáte žádné šablony. Vytvořte první!</p>
                <?php else: ?>
                    <div class="bet-templates-list">
                        <?php foreach ($templates as $tmpl): ?>
                            <div class="bet-template-item">
                                <div class="bet-template-info">
                                    <strong><?php echo esc_html($tmpl->name); ?></strong>
                                    <small><?php echo esc_html(ucfirst($tmpl->layout)); ?> layout</small>
                                </div>
                                <div class="bet-template-actions">
                                    <a href="?page=bricks-email-builder&edit=<?php echo $tmpl->id; ?>" 
                                       class="button button-small">✏️ Upravit</a>
                                    <button type="button" class="button button-small bet-delete-btn" 
                                            data-id="<?php echo $tmpl->id; ?>" 
                                            data-name="<?php echo esc_attr($tmpl->name); ?>">
                                        🗑️ Smazat
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
