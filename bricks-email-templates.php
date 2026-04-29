<?php
/**
 * Plugin Name: Bricks Email Templates
 * Plugin URI: https://github.com/juditth/bricks-email-templates
 * Description: Build and map file-based HTML email templates for Bricks Builder forms.
 * Version:     1.0.3
 * Author:      Jitka Klingenbergová
 * Author URI:  https://vyladeny-web.cz/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bricks-email-templates
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Domain Path: /languages
 * Stable Tag: 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BET_VERSION', '1.0.3');
define('BET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BET_THEME_TEMPLATES_FOLDER', 'bricks-email-templates');
define('BET_UPDATE_INFO_URL', 'https://vyladeny-web.cz/plugins/bricks-email-templates/info.json');

$bet_update_checker = BET_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($bet_update_checker)) {
    require_once $bet_update_checker;
}
unset($bet_update_checker);

class Bricks_Email_Templates
{
    private static $instance = null;
    private static $captured_fields = null;
    private static $captured_settings = array();
    private static $active_form_id = '';
    private static $primary_email_seen = false;
    private static $processed_message_hashes = array();
    private $update_checker = null;

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
        $this->init_update_checker();
        $this->init_hooks();
    }

    private function init_update_checker()
    {
        if (!class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            return;
        }

        $this->update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            BET_UPDATE_INFO_URL,
            __FILE__,
            'bricks-email-templates'
        );
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        add_action('admin_notices', array($this, 'render_dependency_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_bet_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_bet_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_bet_preview_template', array($this, 'ajax_preview_template'));
        add_action('wp_ajax_bet_find_template_for_target', array($this, 'ajax_find_template_for_target'));
        add_filter('bricks/form/email_content', array($this, 'process_email_template'), 999, 3);
        add_filter('wp_mail', array($this, 'intercept_wp_mail'), 999);
        add_filter('bricks/form/validate', array($this, 'capture_form_context_from_validation'), 5, 2);
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
        $this->capture_form_context($form);
    }

    public function capture_form_context_from_validation($errors, $form)
    {
        $this->capture_form_context($form);
        return $errors;
    }

    private function capture_form_context($form)
    {
        if (method_exists($form, 'get_fields')) {
            self::$captured_fields = $form->get_fields();
        } elseif (isset($form->fields)) {
            self::$captured_fields = $form->fields;
        }
        self::$captured_settings = method_exists($form, 'get_settings') ? (array) $form->get_settings() : array();

        self::$active_form_id = $this->extract_form_id_from_form($form, self::$captured_fields);
        self::$primary_email_seen = false;
    }

    private function extract_form_id_from_form($form, $fields = array())
    {
        if (is_array($fields)) {
            foreach (array('formId', 'form_id', 'id') as $key) {
                if (!empty($fields[$key])) {
                    return sanitize_text_field($fields[$key]);
                }
            }
        }

        if (method_exists($form, 'get_settings')) {
            $settings = $form->get_settings();
            if (is_array($settings)) {
                foreach (array('id', 'formId', 'form_id') as $key) {
                    if (!empty($settings[$key])) {
                        return sanitize_text_field($settings[$key]);
                    }
                }
            }
        }

        foreach (array('form_id', 'formId', 'id') as $property) {
            if (isset($form->{$property}) && $form->{$property} !== '') {
                return sanitize_text_field($form->{$property});
            }
        }

        return '';
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
        if ($this->use_upload_template_storage()) {
            $upload_template_dir = $this->get_upload_template_directory();
            return $upload_template_dir ? array($upload_template_dir) : array();
        }

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

    private function use_upload_template_storage()
    {
        return function_exists('is_multisite') && is_multisite();
    }

    private function get_upload_template_directory()
    {
        if (!function_exists('wp_upload_dir')) {
            return '';
        }

        $upload_dir = wp_upload_dir(null, false);
        if (!empty($upload_dir['error']) || empty($upload_dir['basedir'])) {
            return '';
        }

        return trailingslashit($upload_dir['basedir']) . BET_THEME_TEMPLATES_FOLDER;
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
        $template_index = $this->get_template_index();

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
                $meta = $this->get_template_meta($slug, isset($template_index[$slug]) ? $template_index[$slug] : array());
                $seen[$slug] = true;
                $templates[] = array(
                    'id' => 'file_' . $slug,
                    'slug' => $slug,
                    'uuid' => $meta['uuid'],
                    'name' => $meta['name'],
                    'related_form_id' => $meta['related_form_id'],
                    'template_target' => $meta['template_target'],
                    'file' => $path,
                    'type' => 'file',
                );
            }
        }

        return $templates;
    }

    private function get_template_index()
    {
        $index = get_option('bet_template_index', array());
        return is_array($index) ? $index : array();
    }

    private function save_template_index($index)
    {
        update_option('bet_template_index', is_array($index) ? $index : array());
    }

    private function get_template_meta($slug, $stored_meta = array())
    {
        $stored_meta = is_array($stored_meta) ? $stored_meta : array();

        return array(
            'uuid' => !empty($stored_meta['uuid']) ? sanitize_key($stored_meta['uuid']) : '',
            'name' => !empty($stored_meta['name']) ? sanitize_text_field($stored_meta['name']) : ucwords(str_replace(array('-', '_'), ' ', $slug)),
            'related_form_id' => !empty($stored_meta['related_form_id']) ? sanitize_text_field($stored_meta['related_form_id']) : '',
            'template_target' => !empty($stored_meta['template_target']) ? $this->normalize_template_target($stored_meta['template_target']) : 'email',
        );
    }

    private function build_template_file_content($html)
    {
        return ltrim((string) $html);
    }

    private function contains_php_code($content)
    {
        return preg_match('/<\?(php|=)?/i', (string) $content) === 1;
    }

    private function normalize_template_target($target)
    {
        $target = sanitize_key((string) $target);
        return in_array($target, array('none', 'email', 'confirmation', 'both'), true) ? $target : 'email';
    }

    private function make_template_uuid()
    {
        return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('bet-', true);
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

    private function make_template_slug($name, $related_form_id, $template_target = 'email')
    {
        $template_target = $this->normalize_template_target($template_target);
        $name_slug = sanitize_title($name);
        $slug_parts = array_filter(array(
            sanitize_key($related_form_id),
            $template_target,
            $name_slug ? $name_slug : 'email-template',
        ));

        return sanitize_key(implode('_', $slug_parts));
    }

    private function make_unique_template_slug($base_slug, $exclude_slug = '')
    {
        $base_slug = sanitize_key($base_slug ? $base_slug : 'email-template');
        $exclude_slug = sanitize_key($exclude_slug);
        $slug = $base_slug;
        $counter = 2;

        while ($slug !== $exclude_slug && $this->resolve_file_template_path($slug)) {
            $slug = $base_slug . '_' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function get_template_slug_for_form_target($form_id, $target)
    {
        $template_id = $this->get_mapped_template_id($form_id, $target);
        if (!$template_id) {
            return '';
        }

        $slug = $this->get_template_slug_from_id($template_id);
        return $this->resolve_file_template_path($slug) ? $slug : '';
    }

    private function find_template_slug_by_uuid($uuid, $target = '')
    {
        $uuid = sanitize_key($uuid);
        $target = $target !== '' ? $this->normalize_template_target($target) : '';
        if ($uuid === '') {
            return '';
        }

        foreach ($this->get_file_templates() as $template) {
            if (empty($template['uuid']) || $template['uuid'] !== $uuid) {
                continue;
            }
            if ($target !== '' && $template['template_target'] !== $target) {
                continue;
            }
            return $template['slug'];
        }

        return '';
    }

    public function process_email_template($content, $fields_or_settings = array(), $form_settings = array())
    {
        $debug_log = array('Bricks hook triggered');
        $settings = is_array($form_settings) && !empty($form_settings) ? $form_settings : array();
        if (empty($settings) && is_array($fields_or_settings) && !$this->looks_like_submitted_fields($fields_or_settings)) {
            $settings = $fields_or_settings;
        }
        $fields = $this->get_submitted_fields_from_filter_args($fields_or_settings, $form_settings);
        $form_id = $this->extract_runtime_form_id($fields, $settings);

        if (!$form_id) {
            return $content;
        }
        self::$active_form_id = sanitize_text_field($form_id);
        if (!empty($settings)) {
            self::$captured_settings = $settings;
        }

        $template_id = $this->get_mapped_template_id($form_id, 'email');
        if (!$template_id || $template_id === 'none') {
            return $content;
        }

        if (empty($fields) && !empty(self::$captured_fields)) {
            $fields = self::$captured_fields;
        }

        $processed_content = $this->apply_template_to_content(
            $template_id,
            $this->normalize_fields($fields),
            $content,
            $debug_log,
            array('strip_document_shell' => true)
        );
        if ($processed_content !== $content) {
            self::$processed_message_hashes[] = md5($processed_content);
        }

        return $processed_content;
    }

    private function get_submitted_fields_from_filter_args($fields_or_settings, $form_settings)
    {
        if (is_array($fields_or_settings) && $this->looks_like_submitted_fields($fields_or_settings)) {
            return $fields_or_settings;
        }

        if (is_array($form_settings) && isset($form_settings['fields']) && is_array($form_settings['fields']) && $this->looks_like_submitted_fields($form_settings['fields'])) {
            return $form_settings['fields'];
        }

        return !empty(self::$captured_fields) && is_array(self::$captured_fields) ? self::$captured_fields : array();
    }

    private function looks_like_submitted_fields($fields)
    {
        if (!is_array($fields)) {
            return false;
        }

        if (isset($fields['formId']) || isset($fields['form_id'])) {
            return true;
        }

        foreach ($fields as $key => $value) {
            if (is_string($key) && strpos($key, 'form-field-') === 0) {
                return true;
            }
        }

        return false;
    }

    private function extract_runtime_form_id($fields = array(), $form_settings = array())
    {
        if (is_array($fields)) {
            foreach (array('formId', 'form_id') as $key) {
                if (!empty($fields[$key])) {
                    return sanitize_text_field($fields[$key]);
                }
            }
        }

        if (is_array($form_settings)) {
            foreach (array('id', 'formId', 'form_id') as $key) {
                if (!empty($form_settings[$key])) {
                    return sanitize_text_field($form_settings[$key]);
                }
            }
        }

        return self::$active_form_id;
    }

    private function apply_template_to_content($template_id, $fields, $raw_content, $debug_log = array(), $options = array())
    {
        $debug_log[] = 'Applying template ' . $template_id;
        $slug = $this->get_template_slug_from_id($template_id);
        $template_file = $this->resolve_file_template_path($slug);

        if (!$template_file) {
            return $raw_content;
        }

        $template_content = (string) file_get_contents($template_file);

        if (trim($template_content) === '') {
            return $raw_content;
        }

        $template_content = $this->replace_placeholders($template_content, $fields, $raw_content);
        if (!empty($options['strip_document_shell'])) {
            $template_content = $this->strip_html_document_shell($template_content);
        }
        add_filter('wp_mail_content_type', array($this, 'force_html_mail_content_type'), 999);

        return $template_content;
    }

    private function strip_html_document_shell($html)
    {
        $html = (string) $html;
        if (!$this->contains_html_document_shell($html)) {
            return $html;
        }

        $body = $this->extract_tag_inner_html($html, 'body');
        if ($body !== null) {
            return trim($body);
        }

        $without_doctype = preg_replace('/^\s*<!doctype[^>]*>\s*/i', '', $html);
        $without_html = preg_replace('/^\s*<html\b[^>]*>\s*|\s*<\/html>\s*$/i', '', $without_doctype);
        $without_head = preg_replace('/<head\b[^>]*>.*?<\/head>\s*/is', '', $without_html);

        return trim((string) $without_head);
    }

    private function contains_html_document_shell($html)
    {
        return preg_match('/<!doctype\b|<html\b|<head\b|<body\b/i', (string) $html) === 1;
    }

    private function extract_tag_inner_html($html, $tag)
    {
        $tag = preg_quote($tag, '/');
        if (preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', (string) $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function force_html_mail_content_type($content_type)
    {
        remove_filter('wp_mail_content_type', array($this, 'force_html_mail_content_type'), 999);
        return 'text/html';
    }

    private function replace_placeholders($template_content, $fields, $raw_content)
    {
        if (strpos($template_content, '{{all_fields}}') !== false) {
            $template_content = str_replace('{{all_fields}}', esc_html($this->format_all_fields_text($fields, $raw_content)), $template_content);
        }

        foreach ($fields as $field) {
            $field_id = isset($field['id']) ? $field['id'] : '';
            $field_key = isset($field['key']) ? $field['key'] : '';
            $field_value = isset($field['value']) ? $field['value'] : '';
            if ($field_id) {
                $template_content = str_replace('{{' . $field_id . '}}', esc_html($field_value), $template_content);
            }
            if ($field_key && $field_key !== $field_id) {
                $template_content = str_replace('{{' . $field_key . '}}', esc_html($field_value), $template_content);
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
        if (isset($args['message']) && strpos($args['message'], 'bet-email-wrapper') !== false) {
            $args['message'] = $this->normalize_nested_html_documents($args['message']);
            return $args;
        }
        if (isset($args['message']) && in_array(md5((string) $args['message']), self::$processed_message_hashes, true)) {
            self::$primary_email_seen = true;
            return $args;
        }

        $form_id = self::$active_form_id;
        if ($form_id === '') {
            return $args;
        }

        $fields = !empty(self::$captured_fields) ? self::$captured_fields : array();
        if (empty($fields)) {
            return $args;
        }
        $fields = $this->normalize_fields($fields);

        if (!self::$primary_email_seen) {
            $template_id = $this->get_mapped_template_id($form_id, 'email');
            if ($template_id && $template_id !== 'none') {
                $args = $this->apply_template_to_mail_args($args, $template_id, $fields, $debug_log);
            }
            self::$primary_email_seen = true;
            return $args;
        }

        $template_id = $form_id ? $this->get_mapped_template_id($form_id, 'confirmation') : '';

        if (!$template_id || $template_id === 'none') {
            self::$active_form_id = '';
            self::$captured_fields = null;
            self::$captured_settings = array();
            self::$primary_email_seen = false;
            return $args;
        }

        $old_message = isset($args['message']) ? $args['message'] : '';
        $args = $this->apply_template_to_mail_args($args, $template_id, $fields, $debug_log);
        if (isset($args['message']) && $args['message'] !== $old_message) {
            self::$active_form_id = '';
            self::$captured_fields = null;
            self::$captured_settings = array();
            self::$primary_email_seen = false;
        }

        return $args;
    }

    private function normalize_nested_html_documents($message)
    {
        $message = (string) $message;
        if (substr_count(strtolower($message), '<html') < 2 && substr_count(strtolower($message), '<body') < 2) {
            return $message;
        }

        $outer_body = $this->extract_tag_inner_html($message, 'body');
        if ($outer_body === null || !$this->contains_html_document_shell($outer_body)) {
            return $message;
        }

        $clean_body = $this->strip_html_document_shell($outer_body);
        return preg_replace_callback(
            '/(<body\b[^>]*>).*?(<\/body>)/is',
            function ($matches) use ($clean_body) {
                return $matches[1] . $clean_body . $matches[2];
            },
            $message,
            1
        );
    }

    private function apply_template_to_mail_args($args, $template_id, $fields, $debug_log = array())
    {
        if (!isset($args['message'])) {
            return $args;
        }

        $new_content = $this->apply_template_to_content($template_id, $fields, $args['message'], $debug_log);
        if ($new_content === $args['message']) {
            return $args;
        }

        $args['message'] = $new_content;
        if (!isset($args['headers'])) {
            $args['headers'] = array();
        }
        if (is_array($args['headers'])) {
            $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $args['headers'] .= "\r\nContent-Type: text/html; charset=UTF-8";
        }

        self::$processed_message_hashes[] = md5((string) $new_content);

        return $args;
    }

    private function get_mapped_template_id($form_id, $target)
    {
        $form_id = sanitize_text_field($form_id);
        $target = $this->normalize_template_target($target);
        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            return '';
        }

        $target_key = $form_id . '|' . $target;
        if (!empty($mappings[$target_key])) {
            $template_id = sanitize_text_field($mappings[$target_key]);
            $slug = $this->get_template_slug_from_id($template_id);
            if (!$slug || !$this->resolve_file_template_path($slug)) {
                $this->cleanup_template_references($slug);
                return '';
            }

            return $template_id;
        }

        return '';
    }

    private function cleanup_template_references($slug)
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return;
        }

        $template_id = 'file_' . $slug;
        $index = $this->get_template_index();
        if (isset($index[$slug])) {
            unset($index[$slug]);
            $this->save_template_index($index);
        }

        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            return;
        }

        $changed = false;
        foreach ($mappings as $mapping_key => $mapped_template_id) {
            if ($mapped_template_id === $template_id) {
                unset($mappings[$mapping_key]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option('bet_form_mappings', $mappings);
        }
    }

    private function normalize_fields($fields)
    {
        $normalized = array();
        if (!is_array($fields)) {
            return $normalized;
        }

        foreach ($fields as $key => $field) {
            if (!is_array($field)) {
                $field_key = is_string($key) ? sanitize_key($key) : 'field_' . count($normalized);
                $normalized[] = array(
                    'id' => $this->normalize_field_placeholder_id($field_key),
                    'key' => $field_key,
                    'label' => sanitize_text_field(is_string($key) ? $key : 'Field ' . (count($normalized) + 1)),
                    'value' => $field,
                );
                continue;
            }

            $id = isset($field['id']) ? $field['id'] : (is_string($key) ? $key : 'field_' . count($normalized));
            $field_key = is_string($key) ? sanitize_key($key) : sanitize_key($id);
            $label = isset($field['label']) ? $field['label'] : $id;
            $value = isset($field['value']) ? $field['value'] : (isset($field['raw_value']) ? $field['raw_value'] : '');
            $normalized[] = array(
                'id' => $this->normalize_field_placeholder_id($id),
                'key' => $field_key,
                'label' => sanitize_text_field($label),
                'value' => is_array($value) ? implode(', ', array_map('sanitize_text_field', $value)) : (string) $value,
            );
        }

        return $normalized;
    }

    private function normalize_field_placeholder_id($id)
    {
        $id = sanitize_key($id);
        if (strpos($id, 'form-field-') === 0) {
            return substr($id, 11);
        }

        return $id;
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
                    $template['content'] = $content;
                    $template['current_file'] = $template['file'];
                    if (empty($template['related_form_id'])) {
                        $mapped = $this->get_mapped_template_context_for_slug($editing_slug);
                        $template['related_form_id'] = $mapped['form_id'];
                        $template['template_target'] = $mapped['target'];
                    }
                    $editing_template = (object) $template;
                    break;
                }
            }
        }

        $forms = array_values($this->get_bricks_forms());
        $template_dir = $this->get_writable_template_directory();
        $template_dirs = $this->get_theme_template_directories();
        $template_storage_mode = $this->use_upload_template_storage() ? 'uploads' : 'theme';
        include BET_PLUGIN_DIR . 'admin/views/builder-page.php';
    }

    public function ajax_find_template_for_target()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $target = $this->normalize_template_target(wp_unslash($_POST['template_target'] ?? 'email'));
        if ($form_id === '') {
            wp_send_json_success(array('slug' => ''));
        }

        wp_send_json_success(array(
            'slug' => $this->get_template_slug_for_form_target($form_id, $target),
        ));
    }

    private function get_mapped_template_context_for_slug($slug)
    {
        $target = 'file_' . sanitize_key($slug);
        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            return array('form_id' => '', 'target' => 'none');
        }

        foreach ($mappings as $mapping_key => $template_id) {
            if ($template_id === $target) {
                $parts = explode('|', (string) $mapping_key, 2);
                return array(
                    'form_id' => sanitize_text_field($parts[0]),
                    'target' => isset($parts[1]) ? $this->normalize_template_target($parts[1]) : 'email',
                );
            }
        }

        return array('form_id' => '', 'target' => 'none');
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
        if ($this->contains_php_code($custom_html)) {
            wp_send_json_error('PHP code is not allowed in email templates.');
        }

        $related_form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $template_target = $this->normalize_template_target(wp_unslash($_POST['template_target'] ?? 'email'));
        $template_uuid = sanitize_key(wp_unslash($_POST['template_uuid'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        if ($name === '') {
            wp_send_json_error('Template name is required.');
        }

        $slug = isset($_POST['template_slug']) ? sanitize_key(wp_unslash($_POST['template_slug'])) : '';
        if ($template_target === 'none') {
            $result = $this->save_template_file_for_target($slug, $template_uuid, $name, '', 'none', $custom_html);

            wp_send_json_success(array(
                'message' => 'Template file saved without email assignment.',
                'slug' => $result['slug'],
                'uuid' => $result['uuid'],
                'path' => $result['path'],
            ));
        }

        if ($template_target === 'both') {
            $email_result = $this->save_template_file_for_target($slug, $template_uuid, $name, $related_form_id, 'email', $custom_html);
            $confirmation_result = $this->save_template_file_for_target('', '', $name, $related_form_id, 'confirmation', $custom_html);
            $primary_result = $email_result;
            if ($slug) {
                if ($confirmation_result['slug'] === $slug) {
                    $primary_result = $confirmation_result;
                } elseif ($email_result['slug'] === $slug) {
                    $primary_result = $email_result;
                }
            }

            wp_send_json_success(array(
                'message' => 'Email and confirmation templates saved.',
                'slug' => $primary_result['slug'],
                'uuid' => $primary_result['uuid'],
                'path' => $primary_result['path'],
                'created' => array($email_result, $confirmation_result),
            ));
        }

        $result = $this->save_template_file_for_target($slug, $template_uuid, $name, $related_form_id, $template_target, $custom_html);

        wp_send_json_success(array(
            'message' => 'Template file saved.',
            'slug' => $result['slug'],
            'uuid' => $result['uuid'],
            'path' => $result['path'],
        ));
    }

    private function save_template_file_for_target($slug, $template_uuid, $name, $related_form_id, $template_target, $custom_html)
    {
        $template_target = $this->normalize_template_target($template_target);
        $template_uuid = sanitize_key($template_uuid);
        $requested_slug = $slug;
        $desired_slug = $this->make_template_slug($name, $related_form_id, $template_target);
        $resolved_slug = '';

        if ($slug) {
            $existing_path_for_meta = $this->resolve_file_template_path($slug);
            if ($existing_path_for_meta) {
                $template_index = $this->get_template_index();
                $existing_meta = $this->get_template_meta($slug, isset($template_index[$slug]) ? $template_index[$slug] : array());
                $resolved_slug = $slug;
                if ($template_uuid === '' && !empty($existing_meta['uuid'])) {
                    $template_uuid = $existing_meta['uuid'];
                }
            }
        }

        if (!$resolved_slug && $template_uuid !== '') {
            $resolved_slug = $this->find_template_slug_by_uuid($template_uuid, $template_target);
        }

        if (!$resolved_slug && $slug) {
            $existing_path_for_meta = $this->resolve_file_template_path($slug);
            if ($existing_path_for_meta) {
                $template_index = $this->get_template_index();
                $existing_meta = $this->get_template_meta($slug, isset($template_index[$slug]) ? $template_index[$slug] : array());
                $existing_uuid = isset($existing_meta['uuid']) ? sanitize_key($existing_meta['uuid']) : '';
                $existing_form_id = isset($existing_meta['related_form_id']) ? (string) $existing_meta['related_form_id'] : '';
                $existing_target = isset($existing_meta['template_target']) ? $this->normalize_template_target($existing_meta['template_target']) : 'email';
                if (($template_uuid !== '' && $existing_uuid === $template_uuid) || ($existing_form_id === $related_form_id && $existing_target === $template_target)) {
                    $resolved_slug = $slug;
                    if ($template_uuid === '' && !empty($existing_meta['uuid'])) {
                        $template_uuid = $existing_meta['uuid'];
                    }
                }
            }
        }

        if (!$resolved_slug && $template_uuid === '' && $related_form_id !== '') {
            $resolved_slug = $this->get_template_slug_for_form_target($related_form_id, $template_target);
        }

        if (!$resolved_slug && $template_uuid === '') {
            $desired_path = $this->resolve_file_template_path($desired_slug);
            if ($desired_path) {
                $template_index = isset($template_index) ? $template_index : $this->get_template_index();
                $desired_meta = $this->get_template_meta($desired_slug, isset($template_index[$desired_slug]) ? $template_index[$desired_slug] : array());
                $desired_form_id = isset($desired_meta['related_form_id']) ? (string) $desired_meta['related_form_id'] : '';
                $desired_target = isset($desired_meta['template_target']) ? $this->normalize_template_target($desired_meta['template_target']) : 'email';
                if ($desired_form_id === $related_form_id && $desired_target === $template_target) {
                    $resolved_slug = $desired_slug;
                }
            }
        }

        $slug = $resolved_slug ? $resolved_slug : $this->make_unique_template_slug($desired_slug, $requested_slug);
        if ($template_uuid === '') {
            $template_uuid = $this->make_template_uuid();
        }

        $existing_path = $this->resolve_file_template_path($slug);
        $target_dir = $existing_path ? dirname($existing_path) : $this->get_writable_template_directory();
        if (!$target_dir) {
            if ($this->use_upload_template_storage()) {
                wp_send_json_error('No writable uploads template directory was found. Check that this site uploads folder is writable.');
            }
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

        $written = file_put_contents($target_path, $this->build_template_file_content($custom_html));
        if ($written === false) {
            wp_send_json_error('Template file could not be written.');
        }

        $this->sync_template_index($slug, $template_uuid, $name, $related_form_id, $template_target);
        $this->sync_template_form_mapping($slug, $related_form_id, $template_target);

        return array(
            'slug' => $slug,
            'uuid' => $template_uuid,
            'path' => $target_path,
            'target' => $template_target,
        );
    }

    private function sync_template_form_mapping($slug, $related_form_id, $template_target)
    {
        $template_id = 'file_' . sanitize_key($slug);
        $related_form_id = sanitize_text_field($related_form_id);
        $template_target = $this->normalize_template_target($template_target);
        $mappings = get_option('bet_form_mappings', array());
        if (!is_array($mappings)) {
            $mappings = array();
        }

        foreach ($mappings as $form_id => $mapped_template_id) {
            if ($mapped_template_id === $template_id) {
                $parts = explode('|', (string) $form_id, 2);
                $mapped_target = isset($parts[1]) ? $this->normalize_template_target($parts[1]) : 'email';
                if ($template_target === 'none' || $template_target === 'both' || $mapped_target === $template_target) {
                    unset($mappings[$form_id]);
                }
            }
        }

        if ($template_target !== 'none' && $related_form_id !== '') {
            $conflicting_keys = array($related_form_id, $related_form_id . '|' . $template_target);

            foreach ($conflicting_keys as $conflicting_key) {
                unset($mappings[$conflicting_key]);
            }

            $mappings[$related_form_id . '|' . $template_target] = $template_id;
        }

        update_option('bet_form_mappings', $mappings);
    }

    private function sync_template_index($slug, $uuid, $name, $related_form_id, $template_target)
    {
        $slug = sanitize_key($slug);
        $index = $this->get_template_index();
        $index[$slug] = array(
            'uuid' => sanitize_key($uuid),
            'name' => sanitize_text_field($name),
            'related_form_id' => sanitize_text_field($related_form_id),
            'template_target' => $this->normalize_template_target($template_target),
        );
        $this->save_template_index($index);
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

        $this->cleanup_template_references($slug);

        wp_send_json_success('Template file deleted.');
    }

    public function ajax_preview_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $form_id = sanitize_text_field(wp_unslash($_POST['related_form_id'] ?? ''));
        $sample_fields = $this->get_sample_fields_for_form($form_id);
        $custom_html = isset($_POST['custom_html']) ? wp_unslash($_POST['custom_html']) : '';
        if (trim($custom_html) === '') {
            wp_send_json_error('HTML template is required.');
        }
        if ($this->contains_php_code($custom_html)) {
            wp_send_json_error('PHP code is not allowed in email templates.');
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
