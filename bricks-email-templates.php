<?php
/**
 * Plugin Name: Bricks Email Templates
 * Plugin URI: https://github.com/yourusername/bricks-email-templates
 * Description: Build and map file-based HTML email templates for Bricks Builder forms.
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
        if (defined('BRICKS_VERSION') || class_exists('Bricks\\Helpers')) {
            return true;
        }

        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            return strtolower((string) $theme->get_template()) === 'bricks' || strtolower((string) $theme->get_stylesheet()) === 'bricks';
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

        add_submenu_page('bricks', __('Email Template Builder', 'bricks-email-templates'), __('Email Template Builder', 'bricks-email-templates'), 'manage_options', 'bricks-email-builder', array($this, 'render_builder_page'));
    }

    private function get_bricks_forms()
    {
        global $wpdb;
        $forms = array();
        $posts = $wpdb->get_results("SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('page','post','bricks_template')");

        foreach ($posts as $post) {
            $bricks_data = get_post_meta($post->ID, '_bricks_page_content_2', true);
            if (empty($bricks_data)) {
                $bricks_data = get_post_meta($post->ID, '_bricks_page_content', true);
            }
            if (empty($bricks_data)) {
                continue;
            }
            $this->find_forms_in_data($bricks_data, $forms, $post);
        }
        return $forms;
    }

    private function find_forms_in_data($data, &$forms, $post)
    {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (isset($element['name']) && $element['name'] === 'form') {
                $form_id = isset($element['id']) ? $element['id'] : '';
                $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();
                $form_name = isset($element['label']) ? $element['label'] : (isset($settings['submitButtonText']) ? $settings['submitButtonText'] : __('Form', 'bricks-email-templates'));
                if ($form_id) {
                    $forms[$form_id] = array(
                        'id' => $form_id,
                        'name' => $form_name,
                        'page_title' => $post->post_title,
                        'post_id' => $post->ID,
                        'fields' => $this->extract_form_fields($element),
                    );
                }
            }

            if (isset($element['children']) && is_array($element['children'])) {
                $this->find_forms_in_data($element['children'], $forms, $post);
            }
        }
    }

    private function extract_form_fields($element)
    {
        $fields = array();
        $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();

        foreach (array('fields', 'formFields', '_fields') as $key) {
            if (!empty($settings[$key]) && is_array($settings[$key])) {
                $this->collect_field_candidates($settings[$key], $fields);
            }
        }

        return array_values($fields);
    }

    private function collect_field_candidates($items, &$fields)
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = !empty($item['id']) ? (string) $item['id'] : (!empty($item['name']) ? (string) $item['name'] : '');
            if ($id) {
                $label = '';
                foreach (array('label', 'placeholder', 'name') as $label_key) {
                    if (!empty($item[$label_key]) && is_string($item[$label_key])) {
                        $label = $item[$label_key];
                        break;
                    }
                }
                $fields[$id] = array(
                    'id' => sanitize_key($id),
                    'label' => sanitize_text_field($label ? $label : $id),
                    'type' => !empty($item['type']) ? sanitize_text_field($item['type']) : '',
                );
            }

            foreach ($item as $value) {
                if (is_array($value)) {
                    $this->collect_field_candidates($value, $fields);
                }
            }
        }
    }

    private function get_available_templates()
    {
        return $this->get_file_templates();
    }

    private function get_theme_template_directories()
    {
        $dirs = array();
        if (function_exists('get_stylesheet_directory')) {
            $dirs[] = trailingslashit(get_stylesheet_directory()) . BET_THEME_TEMPLATES_FOLDER;
        }
        if (function_exists('get_template_directory')) {
            $parent_dir = trailingslashit(get_template_directory()) . BET_THEME_TEMPLATES_FOLDER;
            if (!in_array($parent_dir, $dirs, true)) {
                $dirs[] = $parent_dir;
            }
        }
        return $dirs;
    }

    private function get_writable_template_directory()
    {
        foreach ($this->get_theme_template_directories() as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            if (is_dir($dir) && wp_is_writable($dir)) {
                return $dir;
            }
        }
        return '';
    }

    private function get_file_templates()
    {
        $templates = array();
        $seen = array();

        foreach ($this->get_theme_template_directories() as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) as $file) {
                if (strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'html') {
                    continue;
                }

                $slug = sanitize_key(pathinfo($file, PATHINFO_FILENAME));
                if (!$slug || isset($seen[$slug])) {
                    continue;
                }

                $path = trailingslashit($dir) . $file;
                $meta = $this->read_template_file_metadata($path, $slug);
                $seen[$slug] = true;
                $templates[] = array(
                    'id' => 'file_' . $slug,
                    'slug' => $slug,
                    'name' => $meta['name'],
                    'related_form_id' => $meta['related_form_id'],
                    'file' => $path,
                    'type' => 'file',
                );
            }
        }

        return $templates;
    }

    private function read_template_file_metadata($path, $slug)
    {
        $content = file_exists($path) ? (string) file_get_contents($path) : '';
        $name = ucwords(str_replace(array('-', '_'), ' ', $slug));
        $related_form_id = '';

        if (preg_match('/BET Template Name:\s*(.+)/i', $content, $match)) {
            $name = sanitize_text_field(trim($match[1]));
        }
        if (preg_match('/BET Related Form ID:\s*(.+)/i', $content, $match)) {
            $related_form_id = sanitize_text_field(trim($match[1]));
        }

        return array(
            'name' => $name,
            'related_form_id' => $related_form_id,
        );
    }

    private function strip_template_file_metadata($content)
    {
        return preg_replace('/^\s*<!--\s*BET Template Name:.*?BET End Metadata\s*-->\s*/is', '', (string) $content);
    }

    private function build_template_file_content($name, $related_form_id, $html)
    {
        return "<!--\nBET Template Name: " . $name . "\nBET Related Form ID: " . $related_form_id . "\nBET End Metadata\n-->\n" . ltrim($html);
    }

    private function resolve_file_template_path($slug)
    {
        $slug = sanitize_key($slug);
        foreach ($this->get_theme_template_directories() as $dir) {
            $path = trailingslashit($dir) . $slug . '.html';
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }

    private function get_template_slug_from_id($template_id)
    {
        return sanitize_key(str_replace('file_', '', (string) $template_id));
    }

    private function make_template_slug($name, $related_form_id)
    {
        $base = $related_form_id ? $related_form_id . '-' . $name : $name;
        $slug = sanitize_title($base);
        return sanitize_key($slug ? $slug : 'email-template');
    }

    public function process_email_template($content, $form_settings)
    {
        $debug_log = array('Bricks hook triggered');
        $form_id = '';

        if (is_array($form_settings)) {
            $form_id = isset($form_settings['id']) ? $form_settings['id'] : (isset($form_settings['formId']) ? $form_settings['formId'] : '');
        }
        if (!$form_id) {
            return $content;
        }

        $mappings = get_option('bet_form_mappings', array());
        $template_id = isset($mappings[$form_id]) ? $mappings[$form_id] : '';
        if (!$template_id || $template_id === 'none') {
            return $content;
        }

        $fields = isset($form_settings['fields']) ? $form_settings['fields'] : array();
        if (empty($fields) && !empty(self::$captured_fields)) {
            $fields = self::$captured_fields;
        }

        return $this->apply_template_to_content($template_id, $this->normalize_fields($fields), $content, $debug_log);
    }

    private function apply_template_to_content($template_id, $fields, $raw_content, $debug_log = array())
    {
        $debug_log[] = 'Applying template ' . $template_id;
        $slug = $this->get_template_slug_from_id($template_id);
        $template_file = $this->resolve_file_template_path($slug);

        if (!$template_file) {
            return $raw_content . "\n<!-- BET DEBUG: File template not found: " . esc_html($slug) . " -->";
        }

        $template_content = (string) file_get_contents($template_file);
        $template_content = $this->strip_template_file_metadata($template_content);

        if (trim($template_content) === '') {
            return $raw_content . "\n<!-- BET DEBUG: Empty template generated for ID " . esc_html($template_id) . " -->";
        }

        $template_content = $this->replace_placeholders($template_content, $fields, $raw_content);
        add_filter('wp_mail_content_type', function () { return 'text/html'; });

        return $template_content . "\n<!-- BET DEBUG SUCCESS: " . esc_html(implode(' | ', $debug_log)) . " -->";
    }

    private function replace_placeholders($template_content, $fields, $raw_content)
    {
        if (strpos($template_content, '{{all_fields}}') !== false) {
            $template_content = str_replace('{{all_fields}}', esc_html($this->format_all_fields_text($fields, $raw_content)), $template_content);
        }

        foreach ($fields as $field) {
            $field_id = isset($field['id']) ? $field['id'] : '';
            $field_value = isset($field['value']) ? $field['value'] : '';
            if ($field_id) {
                $template_content = str_replace('{{' . $field_id . '}}', esc_html($field_value), $template_content);
            }
        }

        return $template_content;
    }

    private function format_all_fields_text($fields, $raw_content = '')
    {
        if (empty($fields)) {
            return (string) $raw_content;
        }

        $lines = array();
        foreach ($fields as $field) {
            $label = isset($field['label']) ? trim((string) $field['label']) : '';
            $value = isset($field['value']) ? (string) $field['value'] : '';
            if ($label === '' || (isset($field['id']) && strpos((string) $field['id'], '_') === 0)) {
                continue;
            }

            $lines[] = $label . ': ' . $value;
        }

        return implode("\n", $lines);
    }

    public function intercept_wp_mail($args)
    {
        $debug_log = array('WP Mail intercept triggered');
        if (isset($args['message']) && (strpos($args['message'], 'BET DEBUG') !== false || strpos($args['message'], 'bet-email-wrapper') !== false)) {
            return $args;
        }

        $form_id = '';
        if (isset($_POST['form_id'])) {
            $form_id = sanitize_text_field(wp_unslash($_POST['form_id']));
        } elseif (isset($_POST['formId'])) {
            $form_id = sanitize_text_field(wp_unslash($_POST['formId']));
        }

        $fields = !empty(self::$captured_fields) ? self::$captured_fields : array();
        if (empty($fields) && !empty($args['message']) && is_string($args['message'])) {
            $fields = $this->parse_raw_content_to_fields($args['message']);
        }
        $fields = $this->normalize_fields($fields);

        $mappings = get_option('bet_form_mappings', array());
        $template_id = ($form_id && isset($mappings[$form_id]) && $mappings[$form_id] !== 'none') ? $mappings[$form_id] : '';

        if (!$template_id || $template_id === 'none') {
            return $args;
        }

        $new_content = $this->apply_template_to_content($template_id, $fields, $args['message'], $debug_log);
        if ($new_content !== $args['message']) {
            $args['message'] = $new_content;
            if (!isset($args['headers'])) {
                $args['headers'] = array();
            }
            if (is_array($args['headers'])) {
                $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
            } else {
                $args['headers'] .= "\r\nContent-Type: text/html; charset=UTF-8";
            }
        }

        return $args;
    }

    private function normalize_fields($fields)
    {
        $normalized = array();
        if (!is_array($fields)) {
            return $normalized;
        }

        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                $normalized[] = array(
                    'id' => sanitize_key(is_string($key) ? $key : 'field_' . count($normalized)),
                    'label' => sanitize_text_field(is_string($key) ? $key : 'Field ' . (count($normalized) + 1)),
                    'value' => $field,
                );
                continue;
            }

            $id = isset($field['id']) ? $field['id'] : (is_string($key) ? $key : 'field_' . count($normalized));
            $label = isset($field['label']) ? $field['label'] : $id;
            $value = isset($field['value']) ? $field['value'] : (isset($field['raw_value']) ? $field['raw_value'] : '');
            $normalized[] = array(
                'id' => sanitize_key($id),
                'label' => sanitize_text_field($label),
                'value' => is_array($value) ? implode(', ', array_map('sanitize_text_field', $value)) : (string) $value,
            );
        }

        return $normalized;
    }

    private function parse_raw_content_to_fields($content)
    {
        $fields = array();
        $text = wp_strip_all_tags(preg_replace('/<br\s*\/?>/i', "\n", $content));

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'Message sent from:') !== false) {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $label = trim($parts[0]);
                $value = trim($parts[1]);
                if (strlen($label) > 0 && strlen($label) < 80) {
                    $fields[] = array('id' => sanitize_key(sanitize_title($label)), 'label' => $label, 'value' => $value);
                }
            } elseif (!empty($fields)) {
                $last_idx = count($fields) - 1;
                $fields[$last_idx]['value'] .= "\n" . $line;
            }
        }

        return $fields;
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'bricks-email-builder') === false) {
            return;
        }

        wp_enqueue_style('bet-builder-css', BET_PLUGIN_URL . 'admin/css/template-builder.css', array(), BET_VERSION);

        wp_enqueue_script('bet-builder-js', BET_PLUGIN_URL . 'admin/js/template-builder.js', array('jquery'), BET_VERSION, true);
        wp_localize_script('bet-builder-js', 'betAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bet_ajax_nonce'),
            'forms' => array_values($this->get_bricks_forms()),
        ));
    }

    public function render_builder_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $templates = $this->get_file_templates();
        $editing_template = null;
        $editing_slug = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        if ($editing_slug) {
            foreach ($templates as $template) {
                if ($template['slug'] === $editing_slug) {
                    $content = file_get_contents($template['file']);
                    $template['content'] = $this->strip_template_file_metadata($content);
                    if (empty($template['related_form_id'])) {
                        $template['related_form_id'] = $this->get_mapped_form_id_for_template_slug($editing_slug);
                    }
                    $editing_template = (object) $template;
                    break;
                }
            }
        }

        $forms = array_values($this->get_bricks_forms());
        $template_dir = $this->get_writable_template_directory();
        include BET_PLUGIN_DIR . 'admin/views/builder-page.php';
    }

    private function get_mapped_form_id_for_template_slug($slug)
    {
        $target = 'file_' . sanitize_key($slug);
        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            return '';
        }

        foreach ($mappings as $form_id => $template_id) {
            if ($template_id === $target) {
                return sanitize_text_field($form_id);
            }
        }

        return '';
    }

    public function ajax_save_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $custom_html = isset($_POST['custom_html']) ? wp_unslash($_POST['custom_html']) : '';
        if (!current_user_can('unfiltered_html')) {
            $custom_html = wp_kses_post($custom_html);
        }
        if (trim($custom_html) === '') {
            wp_send_json_error('HTML template is required.');
        }

        $related_form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        if ($name === '') {
            wp_send_json_error('Template name is required.');
        }

        $slug = isset($_POST['template_slug']) ? sanitize_key(wp_unslash($_POST['template_slug'])) : '';
        if (!$slug) {
            $slug = $this->make_template_slug($name, $related_form_id);
        }

        $existing_path = $this->resolve_file_template_path($slug);
        $target_dir = $existing_path ? dirname($existing_path) : $this->get_writable_template_directory();
        if (!$target_dir) {
            wp_send_json_error('No writable theme template directory was found. Create the folder in your child theme and make it writable.');
        }

        $target_path = $existing_path ? $existing_path : trailingslashit($target_dir) . $slug . '.html';
        $allowed_dirs = array_map('wp_normalize_path', $this->get_theme_template_directories());
        $normalized_target = wp_normalize_path($target_path);
        $allowed = false;
        foreach ($allowed_dirs as $allowed_dir) {
            if (strpos($normalized_target, trailingslashit($allowed_dir)) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            wp_send_json_error('Invalid template path.');
        }

        $written = file_put_contents($target_path, $this->build_template_file_content($name, $related_form_id, $custom_html));
        if ($written === false) {
            wp_send_json_error('Template file could not be written.');
        }

        $this->sync_template_form_mapping($slug, $related_form_id);

        wp_send_json_success(array(
            'message' => 'Template file saved.',
            'slug' => $slug,
            'path' => $target_path,
        ));
    }

    private function sync_template_form_mapping($slug, $related_form_id)
    {
        $template_id = 'file_' . sanitize_key($slug);
        $related_form_id = sanitize_text_field($related_form_id);
        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            $mappings = array();
        }

        foreach ($mappings as $form_id => $mapped_template_id) {
            if ($mapped_template_id === $template_id) {
                unset($mappings[$form_id]);
            }
        }

        if ($related_form_id !== '') {
            $mappings[$related_form_id] = $template_id;
        }

        update_option('bet_form_mappings', $mappings);
    }

    public function ajax_delete_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $slug = isset($_POST['template_slug']) ? sanitize_key(wp_unslash($_POST['template_slug'])) : '';
        if (!$slug && isset($_POST['template_id'])) {
            $slug = $this->get_template_slug_from_id(wp_unslash($_POST['template_id']));
        }
        if (!$slug) {
            wp_send_json_error('Template slug is required.');
        }

        $path = $this->resolve_file_template_path($slug);
        if (!$path || !file_exists($path)) {
            wp_send_json_error('Template file was not found.');
        }

        if (!unlink($path)) {
            wp_send_json_error('Template file could not be deleted.');
        }

        wp_send_json_success('Template file deleted.');
    }

    public function ajax_preview_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        $form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $sample_fields = $this->get_sample_fields_for_form($form_id);
        $custom_html = isset($_POST['custom_html']) ? wp_unslash($_POST['custom_html']) : '';
        if (trim($custom_html) === '') {
            wp_send_json_error('HTML template is required.');
        }
        wp_send_json_success($this->replace_placeholders($custom_html, $sample_fields, ''));
    }

    private function get_sample_fields_for_form($form_id)
    {
        if (!$form_id) {
            return array();
        }

        $forms = $this->get_bricks_forms();
        if (isset($forms[$form_id]) && !empty($forms[$form_id]['fields'])) {
            $sample = array();
            foreach ($forms[$form_id]['fields'] as $field) {
                $sample[] = array('id' => $field['id'], 'label' => $field['label'], 'value' => sprintf('[%s]', $field['label']));
            }
            return $sample;
        }

        return array();
    }
}

Bricks_Email_Templates::get_instance();
