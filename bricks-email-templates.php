<?php
/**
 * Plugin Name: Bricks Email Templates
 * Plugin URI: https://github.com/yourusername/bricks-email-templates
 * Description: Build and map HTML email templates for Bricks Builder forms.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bricks-email-templates
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BET_VERSION', '1.0.0');
define('BET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BET_LEGACY_TEMPLATES_DIR', BET_PLUGIN_DIR . 'templates/');
define('BET_THEME_TEMPLATES_FOLDER', 'bricks-email-templates');

class Bricks_Email_Templates
{
    private static $instance = null;
    private static $captured_fields = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_upgrade_database'));
        add_action('admin_notices', array($this, 'render_dependency_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_bet_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_bet_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_bet_preview_template', array($this, 'ajax_preview_template'));
        add_filter('bricks/form/email_content', array($this, 'process_email_template'), 999, 2);
        add_filter('wp_mail', array($this, 'intercept_wp_mail'), 999);
        add_action('bricks/form/submit', array($this, 'capture_form_data'), 5, 1);
    }

    public static function is_bricks_active()
    {
        if (defined('BRICKS_VERSION') || class_exists('Bricks\Helpers')) {
            return true;
        }
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            $template = strtolower((string) $theme->get_template());
            $stylesheet = strtolower((string) $theme->get_stylesheet());
            if ($template === 'bricks' || $stylesheet === 'bricks') {
                return true;
            }
        }
        return false;
    }

    public function activate()
    {
        if (!self::is_bricks_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Bricks Email Templates requires Bricks Builder to be active. Activate Bricks first, then activate this plugin.', 'bricks-email-templates'),
                esc_html__('Plugin dependency missing', 'bricks-email-templates'),
                array('back_link' => true)
            );
        }
        $this->create_or_update_tables();
    }

    public function maybe_upgrade_database()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (get_option('bet_db_version') !== BET_VERSION) {
            $this->create_or_update_tables();
        }
    }

    private function create_or_update_tables()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            related_form_id varchar(100) DEFAULT NULL,
            layout varchar(50) NOT NULL DEFAULT 'modern',
            color_header_start varchar(7) NOT NULL DEFAULT '#667eea',
            color_header_end varchar(7) NOT NULL DEFAULT '#764ba2',
            color_accent varchar(7) NOT NULL DEFAULT '#2563eb',
            color_background varchar(7) NOT NULL DEFAULT '#f3f4f6',
            color_title varchar(7) DEFAULT '#1e293b',
            color_text varchar(7) DEFAULT '#4b5563',
            color_footer varchar(7) DEFAULT '#9ca3af',
            logo_url varchar(500) DEFAULT NULL,
            email_subject varchar(255) DEFAULT NULL,
            header_text varchar(255) DEFAULT NULL,
            intro_text text DEFAULT NULL,
            footer_text text DEFAULT NULL,
            custom_html longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('bet_db_version', BET_VERSION);
    }

    public function render_dependency_notice()
    {
        if (!self::is_bricks_active()) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Bricks Email Templates requires Bricks Builder. The plugin cannot process Bricks forms until Bricks is active.', 'bricks-email-templates') . '</p></div>';
        }
    }

    public function capture_form_data($form)
    {
        if (method_exists($form, 'get_fields')) {
            self::$captured_fields = $form->get_fields();
        } elseif (isset($form->fields)) {
            self::$captured_fields = $form->fields;
        }
    }

    public function add_admin_menu()
    {
        if (!self::is_bricks_active()) {
            return;
        }
        add_submenu_page('bricks', __('Email Templates', 'bricks-email-templates'), __('Email Templates', 'bricks-email-templates'), 'manage_options', 'bricks-email-templates', array($this, 'render_admin_page'));
        add_submenu_page('bricks', __('Email Template Builder', 'bricks-email-templates'), __('Email Template Builder', 'bricks-email-templates'), 'manage_options', 'bricks-email-builder', array($this, 'render_builder_page'));
    }

    public function register_settings()
    {
        register_setting('bet_settings', 'bet_form_mappings');
        register_setting('bet_settings', 'bet_system_mappings');
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['check_db'])) {
            $this->create_or_update_tables();
            echo '<div class="notice notice-success"><p>' . esc_html__('Database checked and updated.', 'bricks-email-templates') . '</p></div>';
        }
        if (isset($_POST['bet_save_mappings']) && check_admin_referer('bet_save_mappings')) {
            $mappings = isset($_POST['bet_mappings']) && is_array($_POST['bet_mappings']) ? array_map('sanitize_text_field', wp_unslash($_POST['bet_mappings'])) : array();
            update_option('bet_form_mappings', $mappings);
            $system_mappings = isset($_POST['bet_system_mappings']) && is_array($_POST['bet_system_mappings']) ? array_map('sanitize_text_field', wp_unslash($_POST['bet_system_mappings'])) : array();
            update_option('bet_system_mappings', $system_mappings);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'bricks-email-templates') . '</p></div>';
        }
        $mappings = get_option('bet_form_mappings', array());
        $system_mappings = get_option('bet_system_mappings', array());
        $forms = $this->get_bricks_forms();
        $templates = $this->get_available_templates();
        $file_templates = array_values(array_filter($templates, function ($template) { return isset($template['type']) && $template['type'] === 'file'; }));
        $theme_dirs = $this->get_theme_template_directories();
        ?>
        <div class="wrap bet-admin-wrap">
            <h1><?php esc_html_e('Bricks Email Templates', 'bricks-email-templates'); ?></h1>
            <div class="card" style="max-width:100%;padding:20px;margin-bottom:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Email templates for Bricks forms', 'bricks-email-templates'); ?></h2>
                <p><?php esc_html_e('Create reusable email templates, assign them to Bricks forms, and insert form field placeholders into custom HTML.', 'bricks-email-templates'); ?></p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=bricks-email-builder')); ?>" class="button button-primary"><?php esc_html_e('Open Email Template Builder', 'bricks-email-templates'); ?></a></p>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field('bet_save_mappings'); ?>
                <h2><?php esc_html_e('Form template mapping', 'bricks-email-templates'); ?></h2>
                <p><?php esc_html_e('Assign a template to each detected Bricks form.', 'bricks-email-templates'); ?></p>
                <table class="widefat striped" style="margin-bottom:30px;">
                    <thead><tr><th><?php esc_html_e('Form', 'bricks-email-templates'); ?></th><th><?php esc_html_e('Assigned template', 'bricks-email-templates'); ?></th><th><?php esc_html_e('Available placeholders', 'bricks-email-templates'); ?></th></tr></thead>
                    <tbody>
                        <?php if (empty($forms)): ?>
                            <tr><td colspan="3"><em><?php esc_html_e('No Bricks forms were found. Create a form in Bricks Builder and save the page/template.', 'bricks-email-templates'); ?></em></td></tr>
                        <?php else: ?>
                            <?php foreach ($forms as $form): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($form['name']); ?></strong><br><small><?php esc_html_e('ID:', 'bricks-email-templates'); ?> <code><?php echo esc_html($form['id']); ?></code></small><?php if (!empty($form['post_id'])): ?><div style="margin-top:5px;font-size:12px;"><a href="<?php echo esc_url(get_edit_post_link($form['post_id'])); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html(!empty($form['page_title']) ? $form['page_title'] : sprintf(__('Page #%d', 'bricks-email-templates'), $form['post_id'])); ?></a></div><?php endif; ?></td>
                                    <td><select name="bet_mappings[<?php echo esc_attr($form['id']); ?>]" style="width:100%;max-width:320px;"><option value="" <?php selected(!isset($mappings[$form['id']]) || $mappings[$form['id']] === '', true); ?>><?php esc_html_e('Select a template', 'bricks-email-templates'); ?></option><option value="none" <?php selected(isset($mappings[$form['id']]) && $mappings[$form['id']] === 'none', true); ?>><?php esc_html_e('Default Bricks email HTML', 'bricks-email-templates'); ?></option><?php $this->render_template_options(isset($mappings[$form['id']]) ? $mappings[$form['id']] : '', $templates); ?></select></td>
                                    <td><?php $this->render_placeholder_list($form['fields']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <h2><?php esc_html_e('Site email templates', 'bricks-email-templates'); ?></h2>
                <p><?php esc_html_e('Optionally wrap standard WordPress or plugin emails in one of your templates.', 'bricks-email-templates'); ?></p>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Email type / detection', 'bricks-email-templates'); ?></th><th><?php esc_html_e('Assigned template', 'bricks-email-templates'); ?></th></tr></thead><tbody>
                <?php $system_types = array(
                    'global_catchall' => array('label' => __('Global catch-all', 'bricks-email-templates'), 'desc' => __('Use for every outgoing email that does not have a more specific rule.', 'bricks-email-templates')),
                    'reset_password' => array('label' => __('Password reset', 'bricks-email-templates'), 'desc' => __('Detected by common password reset subjects.', 'bricks-email-templates')),
                    'password_changed' => array('label' => __('Password changed confirmation', 'bricks-email-templates'), 'desc' => __('Detected by common password changed subjects.', 'bricks-email-templates')),
                    'new_user' => array('label' => __('New user', 'bricks-email-templates'), 'desc' => __('Emails sent when a new user account is created.', 'bricks-email-templates')),
                ); foreach ($system_types as $id => $type): $current_val = isset($system_mappings[$id]) ? $system_mappings[$id] : ''; ?>
                    <tr><td><strong><?php echo esc_html($type['label']); ?></strong><br><small><?php echo esc_html($type['desc']); ?></small></td><td><select name="bet_system_mappings[<?php echo esc_attr($id); ?>]" style="width:100%;max-width:320px;"><option value="" <?php selected($current_val, ''); ?>><?php esc_html_e('None, keep original email', 'bricks-email-templates'); ?></option><?php $this->render_template_options($current_val, $templates); ?></select></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="submit"><input type="submit" name="bet_save_mappings" class="button button-primary" value="<?php esc_attr_e('Save settings', 'bricks-email-templates'); ?>"></p>
            </form>
            <details><summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e('Advanced: file-based templates', 'bricks-email-templates'); ?></summary><div style="padding:15px;background:#fff;border:1px solid #ccd0d4;margin-top:10px;border-radius:4px;">
                <p><?php esc_html_e('Custom file templates are loaded from the active child theme first, then the parent theme:', 'bricks-email-templates'); ?></p>
                <ul style="list-style:disc;margin-left:20px;"><?php foreach ($theme_dirs as $dir): ?><li><code><?php echo esc_html($dir); ?></code></li><?php endforeach; ?></ul>
                <p><?php esc_html_e('This keeps your custom templates safe during plugin updates.', 'bricks-email-templates'); ?></p>
                <?php if (!empty($file_templates)): ?><ul style="list-style:disc;margin-left:20px;"><?php foreach ($file_templates as $template): ?><li><strong><?php echo esc_html($template['name']); ?></strong> <code><?php echo esc_html($template['file']); ?></code></li><?php endforeach; ?></ul><?php else: ?><p><em><?php esc_html_e('No file-based templates found in the active theme.', 'bricks-email-templates'); ?></em></p><?php endif; ?>
                <p style="margin-top:20px;border-top:1px solid #eee;padding-top:10px;"><a href="<?php echo esc_url(admin_url('admin.php?page=bricks-email-templates&check_db=1')); ?>" class="button button-small button-secondary"><?php esc_html_e('Repair database', 'bricks-email-templates'); ?></a></p>
            </div></details>
        </div>
        <?php
    }

    private function render_template_options($selected_id, $templates)
    {
        $visual_templates = array();
        $file_templates = array();
        foreach ($templates as $template) {
            if (isset($template['type']) && $template['type'] === 'visual') { $visual_templates[] = $template; } else { $file_templates[] = $template; }
        }
        if (!empty($visual_templates)) {
            echo '<optgroup label="' . esc_attr__('Admin templates', 'bricks-email-templates') . '">';
            foreach ($visual_templates as $template) { echo '<option value="' . esc_attr($template['id']) . '" ' . selected($selected_id, $template['id'], false) . '>' . esc_html($template['name']) . '</option>'; }
            echo '</optgroup>';
        }
        if (!empty($file_templates)) {
            echo '<optgroup label="' . esc_attr__('Theme file templates', 'bricks-email-templates') . '">';
            foreach ($file_templates as $template) { echo '<option value="' . esc_attr($template['id']) . '" ' . selected($selected_id, $template['id'], false) . '>' . esc_html($template['name']) . '</option>'; }
            echo '</optgroup>';
        }
    }

    private function render_placeholder_list($fields)
    {
        if (empty($fields)) { echo '<em>' . esc_html__('No fields detected.', 'bricks-email-templates') . '</em>'; return; }
        echo '<div class="bet-placeholder-list">';
        foreach ($fields as $field) {
            $id = isset($field['id']) ? $field['id'] : '';
            $label = isset($field['label']) ? $field['label'] : $id;
            if (!$id) { continue; }
            echo '<button type="button" class="button button-small bet-placeholder-chip" data-placeholder="{{' . esc_attr($id) . '}}">' . esc_html($label) . ': <code>{{' . esc_html($id) . '}}</code></button> ';
        }
        echo '<button type="button" class="button button-small bet-placeholder-chip" data-placeholder="{{all_fields}}"><code>{{all_fields}}</code></button></div>';
    }

    private function get_bricks_forms()
    {
        global $wpdb;
        $forms = array();
        $posts = $wpdb->get_results("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('page','post','bricks_template')");
        foreach ($posts as $post) {
            $bricks_data = get_post_meta($post->ID, '_bricks_page_content_2', true);
            if (empty($bricks_data)) { $bricks_data = get_post_meta($post->ID, '_bricks_page_content', true); }
            if (empty($bricks_data)) { continue; }
            $this->find_forms_in_data($bricks_data, $forms, $post);
        }
        return $forms;
    }

    private function find_forms_in_data($data, &$forms, $post)
    {
        if (!is_array($data)) { return; }
        foreach ($data as $element) {
            if (!is_array($element)) { continue; }
            if (isset($element['name']) && $element['name'] === 'form') {
                $form_id = isset($element['id']) ? $element['id'] : '';
                $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();
                $form_name = isset($element['label']) ? $element['label'] : (isset($settings['submitButtonText']) ? $settings['submitButtonText'] : __('Form', 'bricks-email-templates'));
                if ($form_id) {
                    $forms[$form_id] = array('id' => $form_id, 'name' => $form_name, 'page_title' => $post->post_title, 'post_id' => $post->ID, 'fields' => $this->extract_form_fields($element));
                }
            }
            if (isset($element['children']) && is_array($element['children'])) { $this->find_forms_in_data($element['children'], $forms, $post); }
        }
    }

    private function extract_form_fields($element)
    {
        $fields = array();
        $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();
        foreach (array('fields', 'formFields', '_fields') as $key) {
            if (!empty($settings[$key]) && is_array($settings[$key])) { $this->collect_field_candidates($settings[$key], $fields); }
        }
        return array_values($fields);
    }

    private function collect_field_candidates($items, &$fields)
    {
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $id = !empty($item['id']) ? (string) $item['id'] : (!empty($item['name']) ? (string) $item['name'] : '');
            if ($id) {
                $label = '';
                foreach (array('label', 'placeholder', 'name') as $label_key) { if (!empty($item[$label_key]) && is_string($item[$label_key])) { $label = $item[$label_key]; break; } }
                $fields[$id] = array('id' => sanitize_key($id), 'label' => sanitize_text_field($label ? $label : $id), 'type' => !empty($item['type']) ? sanitize_text_field($item['type']) : '');
            }
            foreach ($item as $value) { if (is_array($value)) { $this->collect_field_candidates($value, $fields); } }
        }
    }

    private function get_available_templates()
    {
        $templates = array();
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name) {
            $visual_templates = $wpdb->get_results("SELECT id, name, custom_html FROM $table_name ORDER BY name ASC");
            foreach ($visual_templates as $template) { $templates[] = array('id' => 'visual_' . $template->id, 'name' => $template->name . (!empty($template->custom_html) ? ' (HTML)' : ' (Builder)'), 'type' => 'visual'); }
        }
        foreach ($this->get_file_templates() as $template) { $templates[] = $template; }
        return $templates;
    }

    private function get_theme_template_directories()
    {
        $dirs = array();
        if (function_exists('get_stylesheet_directory')) { $dirs[] = trailingslashit(get_stylesheet_directory()) . BET_THEME_TEMPLATES_FOLDER; }
        if (function_exists('get_template_directory')) {
            $parent_dir = trailingslashit(get_template_directory()) . BET_THEME_TEMPLATES_FOLDER;
            if (!in_array($parent_dir, $dirs, true)) { $dirs[] = $parent_dir; }
        }
        return $dirs;
    }

    private function get_file_templates()
    {
        $templates = array();
        $seen = array();
        foreach ($this->get_theme_template_directories() as $dir) {
            if (!is_dir($dir)) { continue; }
            foreach (scandir($dir) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') { continue; }
                $template_id = sanitize_key(pathinfo($file, PATHINFO_FILENAME));
                if (!$template_id || isset($seen[$template_id])) { continue; }
                $seen[$template_id] = true;
                $templates[] = array('id' => 'file_' . $template_id, 'name' => ucwords(str_replace(array('-', '_'), ' ', $template_id)) . ' (File)', 'file' => trailingslashit($dir) . $file, 'type' => 'file');
            }
        }
        return $templates;
    }

    private function resolve_file_template_path($file_id)
    {
        $file_id = sanitize_key($file_id);
        foreach ($this->get_theme_template_directories() as $dir) {
            $path = trailingslashit($dir) . $file_id . '.php';
            if (file_exists($path)) { return $path; }
        }
        return '';
    }

    public function process_email_template($content, $form_settings)
    {
        $debug_log = array('Bricks hook triggered');
        $form_id = '';
        if (is_array($form_settings)) { $form_id = isset($form_settings['id']) ? $form_settings['id'] : (isset($form_settings['formId']) ? $form_settings['formId'] : ''); }
        if (!$form_id) { return $content; }
        $mappings = get_option('bet_form_mappings', array());
        $template_id = isset($mappings[$form_id]) ? $mappings[$form_id] : '';
        if (!$template_id || $template_id === 'none') { return $content; }
        $fields = isset($form_settings['fields']) ? $form_settings['fields'] : array();
        if (empty($fields) && !empty(self::$captured_fields)) { $fields = self::$captured_fields; }
        return $this->apply_template_to_content($template_id, $this->normalize_fields($fields), $content, $debug_log);
    }

    private function apply_template_to_content($template_id, $fields, $raw_content, $debug_log = array())
    {
        $debug_log[] = 'Applying template ' . $template_id;
        $template_content = '';
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        if (strpos($template_id, 'visual_') === 0 || is_numeric($template_id)) {
            $visual_id = absint(str_replace('visual_', '', $template_id));
            $template_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $visual_id), ARRAY_A);
            if ($template_data) {
                $template_content = !empty($template_data['custom_html']) ? $template_data['custom_html'] : $this->generate_visual_template_html($template_data, $fields, $raw_content);
                if (!empty($template_data['email_subject'])) {
                    add_filter('wp_mail', function ($args) use ($template_data) { $args['subject'] = $template_data['email_subject']; return $args; });
                }
            }
        } else {
            $file_id = str_replace('file_', '', $template_id);
            $template_file = $this->resolve_file_template_path($file_id);
            if (!$template_file) { return $raw_content . "
<!-- BET DEBUG: File template not found: " . esc_html($file_id) . " -->"; }
            ob_start(); include $template_file; $template_content = ob_get_clean();
        }
        if (empty($template_content)) { return $raw_content . "
<!-- BET DEBUG: Empty template generated for ID " . esc_html($template_id) . " -->"; }
        $template_content = $this->replace_placeholders($template_content, $fields, $raw_content);
        add_filter('wp_mail_content_type', function () { return 'text/html'; });
        return $template_content . "
<!-- BET DEBUG SUCCESS: " . esc_html(implode(' | ', $debug_log)) . " -->";
    }

    private function replace_placeholders($template_content, $fields, $raw_content)
    {
        if (strpos($template_content, '{{all_fields}}') !== false) {
            $all_fields_html = !empty($fields) ? $this->format_all_fields($fields) : '<div style="color:#4b5563;font-size:14px;line-height:1.6;">' . nl2br(esc_html($raw_content)) . '</div>';
            $template_content = str_replace('{{all_fields}}', $all_fields_html, $template_content);
        }
        foreach ($fields as $field) {
            $field_id = isset($field['id']) ? $field['id'] : '';
            $field_value = isset($field['value']) ? $field['value'] : '';
            if ($field_id) { $template_content = str_replace('{{' . $field_id . '}}', esc_html($field_value), $template_content); }
        }
        return $template_content;
    }

    public function intercept_wp_mail($args)
    {
        $debug_log = array('WP Mail intercept triggered');
        if (isset($args['message']) && (strpos($args['message'], 'BET DEBUG') !== false || strpos($args['message'], 'bet-email-wrapper') !== false)) { return $args; }
        $form_id = '';
        if (isset($_POST['form_id'])) { $form_id = sanitize_text_field(wp_unslash($_POST['form_id'])); } elseif (isset($_POST['formId'])) { $form_id = sanitize_text_field(wp_unslash($_POST['formId'])); }
        $fields = !empty(self::$captured_fields) ? self::$captured_fields : array();
        if (empty($fields) && !empty($args['message']) && is_string($args['message'])) { $fields = $this->parse_raw_content_to_fields($args['message']); }
        $fields = $this->normalize_fields($fields);
        $template_id = '';
        $is_system_email = false;
        $mappings = get_option('bet_form_mappings', array());
        if ($form_id && isset($mappings[$form_id]) && $mappings[$form_id] !== 'none') {
            $template_id = $mappings[$form_id];
        } else {
            $system_mappings = get_option('bet_system_mappings', array());
            $subject = isset($args['subject']) ? $args['subject'] : '';
            $subject_normalized = strtolower(remove_accents($subject));
            if (strpos($subject_normalized, 'password reset') !== false || strpos($subject_normalized, 'obnova hesla') !== false) { $template_id = isset($system_mappings['reset_password']) ? $system_mappings['reset_password'] : ''; $is_system_email = true; }
            elseif (strpos($subject_normalized, 'password changed') !== false || strpos($subject_normalized, 'zmena hesla') !== false) { $template_id = isset($system_mappings['password_changed']) ? $system_mappings['password_changed'] : ''; $is_system_email = true; }
            elseif (strpos($subject_normalized, 'new user') !== false || strpos($subject_normalized, 'novy uzivatel') !== false) { $template_id = isset($system_mappings['new_user']) ? $system_mappings['new_user'] : ''; $is_system_email = true; }
            if (empty($template_id) && !empty($system_mappings['global_catchall'])) { $template_id = $system_mappings['global_catchall']; $is_system_email = true; }
        }
        if (!$template_id || $template_id === 'none') { return $args; }
        if ($is_system_email && empty($fields)) { $fields[] = array('id' => '_bet_raw_content', 'label' => 'Content', 'value' => $args['message']); }
        $new_content = $this->apply_template_to_content($template_id, $fields, $args['message'], $debug_log);
        if ($new_content !== $args['message']) {
            $args['message'] = $new_content;
            if (!isset($args['headers'])) { $args['headers'] = array(); }
            if (is_array($args['headers'])) { $args['headers'][] = 'Content-Type: text/html; charset=UTF-8'; } else { $args['headers'] .= "
Content-Type: text/html; charset=UTF-8"; }
        }
        return $args;
    }

    private function normalize_fields($fields)
    {
        $normalized = array();
        if (!is_array($fields)) { return $normalized; }
        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                $normalized[] = array('id' => sanitize_key(is_string($key) ? $key : 'field_' . count($normalized)), 'label' => sanitize_text_field(is_string($key) ? $key : 'Field ' . (count($normalized) + 1)), 'value' => $field);
                continue;
            }
            $id = isset($field['id']) ? $field['id'] : (is_string($key) ? $key : 'field_' . count($normalized));
            $label = isset($field['label']) ? $field['label'] : $id;
            $value = isset($field['value']) ? $field['value'] : (isset($field['raw_value']) ? $field['raw_value'] : '');
            $normalized[] = array('id' => sanitize_key($id), 'label' => sanitize_text_field($label), 'value' => is_array($value) ? implode(', ', array_map('sanitize_text_field', $value)) : (string) $value);
        }
        return $normalized;
    }

    private function parse_raw_content_to_fields($content)
    {
        $fields = array();
        $text = wp_strip_all_tags(preg_replace('/<brs*/?>/i', "
", $content));
        foreach (explode("
", $text) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'Message sent from:') !== false) { continue; }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $label = trim($parts[0]); $value = trim($parts[1]);
                if (strlen($label) > 0 && strlen($label) < 80) { $fields[] = array('id' => sanitize_key(sanitize_title($label)), 'label' => $label, 'value' => $value); }
            } elseif (!empty($fields)) { $last_idx = count($fields) - 1; $fields[$last_idx]['value'] .= "
" . $line; }
        }
        return $fields;
    }

    private function format_all_fields($fields)
    {
        $html = '';
        foreach ($fields as $field) {
            $label = isset($field['label']) ? $field['label'] : '';
            $value = isset($field['value']) ? $field['value'] : '';
            if ($label === '' || (isset($field['id']) && strpos((string) $field['id'], '_') === 0)) { continue; }
            $html .= '<div class="field-row" style="margin:15px 0;padding:10px;background:#f9fafb;border-left:3px solid #2563eb;">';
            $html .= '<div class="field-label" style="font-weight:bold;color:#374151;margin-bottom:5px;">' . esc_html($label) . '</div>';
            $html .= '<div class="field-value" style="color:#1f2937;">' . nl2br(esc_html($value)) . '</div></div>';
        }
        return $html;
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'bricks-email-builder') === false && strpos($hook, 'bricks-email-templates') === false) { return; }
        wp_enqueue_style('bet-builder-css', BET_PLUGIN_URL . 'admin/css/template-builder.css', array(), BET_VERSION);
        if (strpos($hook, 'bricks-email-builder') === false) { return; }
        wp_enqueue_media(); wp_enqueue_style('wp-color-picker'); wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('bet-builder-js', BET_PLUGIN_URL . 'admin/js/template-builder.js', array('jquery', 'wp-color-picker'), BET_VERSION, true);
        wp_localize_script('bet-builder-js', 'betAjax', array('ajaxurl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('bet_ajax_nonce'), 'forms' => array_values($this->get_bricks_forms()), 'allFieldsLabel' => __('All fields', 'bricks-email-templates')));
    }

    public function render_builder_page()
    {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        $templates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
        $editing_template = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) { $editing_template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", absint($_GET['edit']))); }
        $forms = array_values($this->get_bricks_forms());
        include BET_PLUGIN_DIR . 'admin/views/builder-page.php';
    }

    public function ajax_save_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error('Insufficient permissions'); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        $custom_html = isset($_POST['custom_html']) ? wp_unslash($_POST['custom_html']) : '';
        if (!current_user_can('unfiltered_html')) { $custom_html = wp_kses_post($custom_html); }
        $data = array(
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'related_form_id' => sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? '')),
            'layout' => sanitize_text_field(wp_unslash($_POST['layout'] ?? 'card')),
            'color_header_start' => sanitize_hex_color(wp_unslash($_POST['color_header_start'] ?? '#667eea')),
            'color_header_end' => sanitize_hex_color(wp_unslash($_POST['color_header_end'] ?? '#764ba2')),
            'color_accent' => sanitize_hex_color(wp_unslash($_POST['color_accent'] ?? '#2563eb')),
            'color_background' => sanitize_hex_color(wp_unslash($_POST['color_background'] ?? '#f3f4f6')),
            'color_title' => sanitize_hex_color(wp_unslash($_POST['color_title'] ?? '#1e293b')),
            'color_text' => sanitize_hex_color(wp_unslash($_POST['color_text'] ?? '#4b5563')),
            'color_footer' => sanitize_hex_color(wp_unslash($_POST['color_footer'] ?? '#9ca3af')),
            'logo_url' => esc_url_raw(wp_unslash($_POST['logo_url'] ?? '')),
            'email_subject' => sanitize_text_field(wp_unslash($_POST['email_subject'] ?? '')),
            'header_text' => sanitize_text_field(wp_unslash($_POST['header_text'] ?? '')),
            'intro_text' => sanitize_textarea_field(wp_unslash($_POST['intro_text'] ?? '')),
            'footer_text' => sanitize_textarea_field(wp_unslash($_POST['footer_text'] ?? '')),
            'custom_html' => $custom_html,
        );
        if ($data['name'] === '') { wp_send_json_error('Template name is required.'); }
        if (isset($_POST['template_id']) && is_numeric($_POST['template_id'])) { $wpdb->update($table_name, $data, array('id' => absint($_POST['template_id']))); wp_send_json_success(array('message' => 'Template updated.', 'id' => absint($_POST['template_id']))); }
        $wpdb->insert($table_name, $data); wp_send_json_success(array('message' => 'Template created.', 'id' => $wpdb->insert_id));
    }

    public function ajax_delete_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error('Insufficient permissions'); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        $wpdb->delete($table_name, array('id' => absint($_POST['template_id'])));
        wp_send_json_success('Template deleted.');
    }

    public function ajax_preview_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        $form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $sample_fields = $this->get_sample_fields_for_form($form_id);
        $custom_html = isset($_POST['custom_html']) ? wp_unslash($_POST['custom_html']) : '';
        if (trim($custom_html) !== '') { wp_send_json_success($this->replace_placeholders($custom_html, $sample_fields, '')); }
        $template_data = array(
            'layout' => sanitize_text_field(wp_unslash($_POST['layout'] ?? 'card')),
            'color_header_start' => sanitize_hex_color(wp_unslash($_POST['color_header_start'] ?? '#667eea')),
            'color_header_end' => sanitize_hex_color(wp_unslash($_POST['color_header_end'] ?? '#764ba2')),
            'color_accent' => sanitize_hex_color(wp_unslash($_POST['color_accent'] ?? '#2563eb')),
            'color_background' => sanitize_hex_color(wp_unslash($_POST['color_background'] ?? '#f3f4f6')),
            'color_title' => sanitize_hex_color(wp_unslash($_POST['color_title'] ?? '#1e293b')),
            'color_text' => sanitize_hex_color(wp_unslash($_POST['color_text'] ?? '#4b5563')),
            'color_footer' => sanitize_hex_color(wp_unslash($_POST['color_footer'] ?? '#9ca3af')),
            'logo_url' => esc_url_raw(wp_unslash($_POST['logo_url'] ?? '')),
            'header_text' => sanitize_text_field(wp_unslash($_POST['header_text'] ?? '')),
            'intro_text' => sanitize_textarea_field(wp_unslash($_POST['intro_text'] ?? '')),
            'footer_text' => sanitize_textarea_field(wp_unslash($_POST['footer_text'] ?? '')),
            'custom_html' => '',
        );
        wp_send_json_success($this->generate_visual_template_html($template_data, $sample_fields));
    }

    private function get_sample_fields_for_form($form_id)
    {
        $forms = $this->get_bricks_forms();
        if ($form_id && isset($forms[$form_id]) && !empty($forms[$form_id]['fields'])) {
            $sample = array();
            foreach ($forms[$form_id]['fields'] as $field) { $sample[] = array('id' => $field['id'], 'label' => $field['label'], 'value' => sprintf('[%s]', $field['label'])); }
            return $sample;
        }
        return array(array('id' => 'name', 'label' => 'Name', 'value' => 'Jane Smith'), array('id' => 'email', 'label' => 'Email', 'value' => 'jane@example.com'), array('id' => 'message', 'label' => 'Message', 'value' => 'This is a sample form message.'));
    }

    private function generate_visual_template_html($template, $fields, $raw_content = '')
    {
        $layout = $template['layout'];
        $bg_color = $template['color_background'];
        $text_color = !empty($template['color_text']) ? $template['color_text'] : '#4b5563';
        $footer_color = !empty($template['color_footer']) ? $template['color_footer'] : '#9ca3af';
        $logo_url = $template['logo_url'];
        $header_text = !empty($template['header_text']) ? $template['header_text'] : 'New website message';
        $intro_text = !empty($template['intro_text']) ? $template['intro_text'] : 'A form was submitted on your website.';
        $footer_text = !empty($template['footer_text']) ? $template['footer_text'] : get_bloginfo('name');
        $is_card = $layout === 'card';
        if ($is_card) { $padding = '20px'; $header_bg = 'transparent'; $header_text_color = !empty($template['color_title']) ? $template['color_title'] : $template['color_header_start']; $container_style = 'max-width:600px;margin:20px auto;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;'; $body_bg_style = 'margin:0;padding:0;font-family:Arial,sans-serif;background-color:' . esc_attr($bg_color) . ';line-height:1.5;'; }
        else { $padding = '40px'; $header_bg = 'linear-gradient(135deg,' . esc_attr($template['color_header_start']) . ' 0%,' . esc_attr($template['color_header_end']) . ' 100%)'; $header_text_color = '#ffffff'; $container_style = 'max-width:600px;margin:30px auto;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'; $body_bg_style = 'margin:0;padding:0;font-family:Arial,sans-serif;background-color:' . esc_attr($bg_color) . ';'; }
        $fields_content = !empty($fields) ? $this->format_all_fields($fields) : nl2br(esc_html($raw_content));
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="<?php echo esc_attr($body_bg_style); ?>">
    <div class="bet-email-wrapper" style="<?php echo esc_attr($container_style); ?>">
        <div style="background: <?php echo esc_attr($header_bg); ?>; padding: <?php echo esc_attr($is_card ? '30px ' . $padding . ' 10px ' . $padding : $padding); ?>; text-align: <?php echo esc_attr($is_card ? 'left' : 'center'); ?>;">
            <?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width:180px;height:auto;margin-bottom:20px;"><?php endif; ?>
            <h1 style="margin:0;color:<?php echo esc_attr($header_text_color); ?>;font-size:24px;font-weight:700;letter-spacing:-0.5px;"><?php echo esc_html($header_text); ?></h1>
        </div>
        <div style="padding:<?php echo esc_attr($padding); ?>;"><p style="color:<?php echo esc_attr($text_color); ?>;font-size:15px;margin-bottom:20px;"><?php echo esc_html($intro_text); ?></p><div class="bet-content"><?php echo $fields_content; ?></div><p style="color:#9ca3af;font-size:12px;margin-top:25px;text-align:right;">Sent: <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></p></div>
        <div style="<?php echo esc_attr($is_card ? 'padding:0 ' . $padding . ' ' . $padding . ' ' . $padding . ';' : 'background-color:#f9fafb;padding:25px;text-align:center;border-top:1px solid #e5e7eb;'); ?>"><p style="margin:5px 0;color:<?php echo esc_attr($footer_color); ?>;font-size:13px;<?php echo esc_attr($is_card ? 'opacity:0.85;' : ''); ?>"><?php echo esc_html($footer_text); ?></p></div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

Bricks_Email_Templates::get_instance();
