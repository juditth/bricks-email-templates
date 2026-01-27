<?php
/**
 * Plugin Name: Bricks Email Templates
 * Plugin URI: https://github.com/yourusername/bricks-email-templates
 * Description: Formátování emailů z Bricks Builder formulářů pomocí externích HTML šablon
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bricks-email-templates
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BET_VERSION', '1.0.0');
define('BET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BET_TEMPLATES_DIR', BET_PLUGIN_DIR . 'templates/');

/**
 * Main Plugin Class
 */
class Bricks_Email_Templates
{

    private static $instance = null;

    /**
     * @var string
     */
    private $plugin_path;

    /**
     * @var array|null
     * Captured fields from Bricks form submission
     */
    private static $captured_fields = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_bet_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_bet_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_bet_preview_template', array($this, 'ajax_preview_template'));

        // Filter Bricks form email content
        // Priority 999 to definitely run last
        add_filter('bricks/form/email_content', array($this, 'process_email_template'), 999, 2);


        // FALLBACK: Hook into global wp_mail if Bricks hook fails
        add_filter('wp_mail', array($this, 'intercept_wp_mail'), 999);

        // DATA CAPTURE: Capture form data before email is sent
        add_action('bricks/form/submit', array($this, 'capture_form_data'), 5, 1);
    }

    /**
     * Capture Bricks form data
     * This runs before the email action and stores structured data
     */
    public function capture_form_data($form)
    {
        // Try to get fields from the form object
        if (method_exists($form, 'get_fields')) {
            self::$captured_fields = $form->get_fields();
        } elseif (isset($form->fields)) {
            self::$captured_fields = $form->fields;
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Main Menu Item "Bricks Emails"
        add_menu_page(
            'Bricks Emails',
            'Bricks Emails',
            'manage_options',
            'bricks-email-templates',
            array($this, 'render_admin_page'), // Defaults to mapping page
            'dashicons-email-alt',
            30
        );

        // Submenu: Formuláře a šablony (same as parent)
        add_submenu_page(
            'bricks-email-templates',
            'Formuláře a šablony',
            'Formuláře a šablony',
            'manage_options',
            'bricks-email-templates',
            array($this, 'render_admin_page')
        );

        // Submenu: Vytvořit vlastní šablonu (Builder)
        add_submenu_page(
            'bricks-email-templates',
            'Vytvořit vlastní šablonu',
            'Vytvořit vlastní šablonu',
            'manage_options',
            'bricks-email-builder',
            array($this, 'render_builder_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('bet_settings', 'bet_form_mappings');
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Ensure table exists (just in case)
        if (isset($_GET['check_db'])) {
            $this->activate();
            echo '<div class="notice notice-success"><p>Databáze byla zkontrolována a tabulky vytvořeny.</p></div>';
        }

        // Save settings
        if (isset($_POST['bet_save_mappings']) && check_admin_referer('bet_save_mappings')) {
            $mappings = isset($_POST['bet_mappings']) ? $_POST['bet_mappings'] : array();
            update_option('bet_form_mappings', $mappings);
            echo '<div class="notice notice-success"><p>Nastavení uloženo!</p></div>';
        }

        $mappings = get_option('bet_form_mappings', array());
        $forms = $this->get_bricks_forms();
        $templates = $this->get_available_templates();

        ?>
        <div class="wrap bet-admin-wrap">
            <h1>Bricks Email Templates</h1>

            <div class="card"
                style="max-width: 100%; padding: 0; margin-bottom: 20px; display: flex; align-items: center; overflow: hidden; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <div
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; color: white; min-width: 200px; text-align: center; align-self: stretch; display: flex; flex-direction: column; justify-content: center;">
                    <span style="font-size: 40px; display: block; margin-bottom: 10px;">📧</span>
                    <strong style="font-size: 18px;">Email Builder</strong>
                </div>
                <div style="padding: 20px 30px;">
                    <h2 style="margin-top: 0; color: #1d2327;">Vytvořte krásné emaily vizuálně</h2>
                    <p style="font-size: 15px; line-height: 1.5; color: #50575e; margin-bottom: 20px;">Už žádné psaní HTML kódu.
                        Použijte náš vizuální editor pro tvorbu profesionálních šablon s vlastním logem a barvami.</p>
                    <a href="<?php echo admin_url('admin.php?page=bricks-email-builder'); ?>"
                        class="button button-primary button-hero">Otevřít Email Builder &rarr;</a>
                </div>
            </div>

            <h2>Přiřazení šablon formulářům</h2>
            <p>Zde můžete namapovat své Bricks formuláře na vytvořené šablony.</p>

            <form method="post" action="">
                <?php wp_nonce_field('bet_save_mappings'); ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Formulář</th>
                            <th>Přiřazená šablona</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($forms)): ?>
                            <tr>
                                <td colspan="2">
                                    <em>Nebyly nalezeny žádné Bricks formuláře. Vytvořte formulář v Bricks Builderu a uložte
                                        stránku.</em>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($forms as $form): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($form['name']); ?></strong><br>
                                        <small style="color: #64748b;">ID: <code><?php echo esc_html($form['id']); ?></code></small>

                                        <?php if (isset($form['post_id'])): ?>
                                            <div style="margin-top: 5px; font-size: 12px;">
                                                <span class="dashicons dashicons-admin-links"
                                                    style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                                                <a href="<?php echo get_edit_post_link($form['post_id']); ?>" target="_blank"
                                                    style="text-decoration: none;">
                                                    <?php echo esc_html(isset($form['page_title']) ? $form['page_title'] : 'Stránka #' . $form['post_id']); ?>
                                                    <span style="font-size: 10px;">↗</span>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select name="bet_mappings[<?php echo esc_attr($form['id']); ?>]"
                                            style="width: 100%; max-width: 300px;">
                                            <option value="" <?php selected(!isset($mappings[$form['id']]) || $mappings[$form['id']] === '', true); ?>>-- Vyberte šablonu --</option>
                                            <option value="none" <?php selected(isset($mappings[$form['id']]) && $mappings[$form['id']] === 'none', true); ?>>-- Výchozí Bricks form HTML --</option>

                                            <?php
                                            // Group templates
                                            $visual_templates = array();
                                            $file_templates = array();
                                            foreach ($templates as $t) {
                                                if (isset($t['type']) && $t['type'] === 'visual') {
                                                    $visual_templates[] = $t;
                                                } else {
                                                    $file_templates[] = $t;
                                                }
                                            }
                                            ?>

                                            <?php if (!empty($visual_templates)): ?>
                                                <optgroup label="Vizuální šablony (Builder)">
                                                    <?php foreach ($visual_templates as $template): ?>
                                                        <option value="<?php echo esc_attr($template['id']); ?>" <?php selected(isset($mappings[$form['id']]) ? $mappings[$form['id']] : '', $template['id']); ?>>
                                                            <?php echo esc_html(str_replace(' (Visual)', '', $template['name'])); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>

                                            <?php if (!empty($file_templates)): ?>
                                                <optgroup label="HTML Soubory">
                                                    <?php foreach ($file_templates as $template): ?>
                                                        <option value="<?php echo esc_attr($template['id']); ?>" <?php selected(isset($mappings[$form['id']]) ? $mappings[$form['id']] : '', $template['id']); ?>>
                                                            <?php echo esc_html($template['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="bet_save_mappings" class="button button-primary" value="Uložit přiřazení">
                </p>
            </form>

            <br>
            <details>
                <summary style="cursor: pointer; color: #2271b1;">Pokročilé: Správa souborových šablon</summary>
                <div style="padding: 15px; background: #fff; border: 1px solid #ccd0d4; margin-top: 10px; border-radius: 4px;">
                    <p>HTML šablony se nacházejí ve složce: <code><?php echo esc_html(BET_TEMPLATES_DIR); ?></code></p>

                    <?php if (!empty($file_templates)): ?>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <?php foreach ($file_templates as $template): ?>
                                <li>
                                    <strong><?php echo esc_html($template['name']); ?></strong>
                                    <code style="color: #64748b; font-size: 12px;"><?php echo esc_html($template['file']); ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><em>Žádné souborové šablony nenalezeny.</em></p>
                    <?php endif; ?>

                    <p style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
                        <a href="?page=bricks-email-templates&check_db=1" class="button button-small button-secondary">Opravit
                            databázi (pokud nevidíte vizuální šablony)</a>
                    </p>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Get all Bricks forms from the database
     */
    private function get_bricks_forms()
    {
        global $wpdb;

        $forms = array();

        // Query all posts that might contain Bricks data
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_type 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish'
            AND post_type IN ('page', 'post', 'bricks_template')
        ");

        foreach ($posts as $post) {
            // Get Bricks data
            $bricks_data = get_post_meta($post->ID, '_bricks_page_content_2', true);

            if (empty($bricks_data)) {
                continue;
            }

            // Search for form elements
            $this->find_forms_in_data($bricks_data, $forms, $post);
        }

        return $forms;
    }

    /**
     * Recursively find forms in Bricks data
     */
    private function find_forms_in_data($data, &$forms, $post)
    {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Check if this is a form element
            if (isset($element['name']) && $element['name'] === 'form') {
                $form_id = isset($element['id']) ? $element['id'] : '';
                $form_name = isset($element['label']) ? $element['label'] : 'Formulář';

                if ($form_id) {
                    $forms[$form_id] = array(
                        'id' => $form_id,
                        'name' => $form_name,
                        'page_title' => $post->post_title,
                        'post_id' => $post->ID,
                    );
                }
            }

            // Check children
            if (isset($element['children']) && is_array($element['children'])) {
                $this->find_forms_in_data($element['children'], $forms, $post);
            }
        }
    }

    /**
     * Get available email templates
     */
    private function get_available_templates()
    {
        $templates = array();

        // Get visual templates from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $visual_templates = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC");

            foreach ($visual_templates as $template) {
                $templates[] = array(
                    'id' => 'visual_' . $template->id,
                    'name' => $template->name . ' (Visual)',
                    'type' => 'visual',
                );
            }
        }

        // Get file-based templates
        if (is_dir(BET_TEMPLATES_DIR)) {
            $files = scandir(BET_TEMPLATES_DIR);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $template_id = pathinfo($file, PATHINFO_FILENAME);
                    $templates[] = array(
                        'id' => 'file_' . $template_id,
                        'name' => ($template_id === 'analog' ? 'Jednoduchá šablona (File)' : ucfirst(str_replace(array('-', '_'), ' ', $template_id)) . ' (File)'),
                        'file' => $file,
                        'type' => 'file',
                    );

                    // Skip default.php from UI if it's just a code pattern
                    if ($template_id === 'default') {
                        array_pop($templates);
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * Process email template
     */
    public function process_email_template($content, $form_settings)
    {
        $debug_log = [];
        $debug_log[] = "Plugin ver: " . BET_VERSION;

        // Robust Form ID Detection
        $form_id = '';
        if (is_array($form_settings)) {
            if (isset($form_settings['id']))
                $form_id = $form_settings['id'];
            elseif (isset($form_settings['formId']))
                $form_id = $form_settings['formId']; // Common alternative
            elseif (isset($form_settings['elementId']))
                $form_id = $form_settings['elementId'];
        } elseif (is_object($form_settings)) {
            if (isset($form_settings->id))
                $form_id = $form_settings->id;
        }

        $debug_log[] = "Detected ID: " . ($form_id ? $form_id : 'NONE');

        if (!$form_id) {
            $keys = is_array($form_settings) ? implode(',', array_keys($form_settings)) : 'not_array';
            return $content . "\n<br><!-- BET DEBUG: ID not found. Keys: $keys -->";
        }

        // Get mapping
        $mappings = get_option('bet_form_mappings', array());

        // Debug mappings
        $is_mapped = isset($mappings[$form_id]) ? 'YES' : 'NO';
        $mapped_value = $is_mapped === 'YES' ? $mappings[$form_id] : 'N/A';
        $debug_log[] = "Mapped: $is_mapped ($mapped_value)";

        if (!isset($mappings[$form_id]) || empty($mappings[$form_id])) {
            return $content . "\n<br><!-- BET DEBUG: No mapping for $form_id. Log: " . implode('|', $debug_log) . " -->";
        }

        $template_id = $mappings[$form_id];

        // If explicitly set to 'none', return original content
        if ($template_id === 'none') {
            return $content . "\n<!-- BET DEBUG: Template set to NONE -->";
        }

        // Get form fields and their values
        $fields = isset($form_settings['fields']) ? $form_settings['fields'] : array();

        // Fallback: Check if fields are passed differently (sometimes Bricks changes this)
        if (empty($fields) && isset($_POST['form-fields'])) {
            // Basic reconstruction if Bricks doesn't pass processed fields
            // This is risky but helpful for debugging
            $debug_log[] = "Fields empty in settings, checking $_POST";
        }

        // DATA RETRIEVAL: Check if we captured data earlier in the request
        if (empty($fields) && !empty(self::$captured_fields)) {
            $fields = self::$captured_fields;
            $debug_log[] = "Retrieved " . count($fields) . " fields from static capture (bricks/form/submit)";
        }

        // Try to parse fields from content if fields are missing (Fallback mode)
        if (empty($fields) && !empty($content) && is_string($content)) {
            $parsed_fields = $this->parse_raw_content_to_fields($content);
            if (!empty($parsed_fields)) {
                $fields = $parsed_fields;
                $debug_log[] = "Parsed " . count($fields) . " fields from raw content";
            }
        }

        $template_content = '';

        // Check if it's a visual template
        if (strpos($template_id, 'visual_') === 0) {
            $visual_id = str_replace('visual_', '', $template_id);
            
            // Get template data to check for subject
            global $wpdb;
            $table_name = $wpdb->prefix . 'bet_templates';
            $template_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $visual_id));
            
            if ($template_data) {
                // Generate content
                $template_content = $this->generate_visual_template_from_data($template_data, $fields);
                
                // Handle Subject if set
                if (!empty($template_data->email_subject)) {
                    add_filter('wp_mail', function($args) use ($template_data) {
                        $args['subject'] = $template_data->email_subject;
                        return $args;
                    });
                }
            }
        } else {
            // File-based template
            $file_id = str_replace('file_', '', $template_id);
            // Fix: remove ' (File)' suffix if stored in mapping by mistake, though ID should be clean

            $template_file = BET_TEMPLATES_DIR . $file_id . '.php';

            if (!file_exists($template_file)) {
                return $content . "\n<!-- BET DEBUG: File not found: $template_file -->";
            }

            // Start output buffering
            ob_start();
            include $template_file;
            $template_content = ob_get_clean();
        }

        if (empty($template_content)) {
            return $content . "\n<!-- BET DEBUG: Empty template generated -->";
        }

        // Try to parse fields from content if using fallback
        if (empty($fields) && !empty($content) && is_string($content)) {
            $parsed_fields = $this->parse_raw_content_to_fields($content);
            if (!empty($parsed_fields)) {
                $fields = $parsed_fields;
                $debug_log[] = "Parsed " . count($fields) . " fields from raw content";
            }
        }

        // Replace {{all_fields}} with formatted fields
        if (strpos($template_content, '{{all_fields}}') !== false) {
            $all_fields_html = '';

            if (!empty($fields)) {
                $all_fields_html = $this->format_all_fields($fields);
            } else {
                // Fallback: If no structured fields found, use the original content
                $all_fields_html = '<div style="padding: 15px; border-left: 4px solid #ffc107;">' . $content . '</div>';
                $debug_log[] = "Using original content fallback";
            }

            $template_content = str_replace('{{all_fields}}', $all_fields_html, $template_content);
        }

        // Replace individual field placeholders
        foreach ($fields as $field) {
            $field_id = isset($field['id']) ? $field['id'] : '';
            $field_value = isset($field['value']) ? $field['value'] : '';

            if ($field_id) {
                $template_content = str_replace('{{' . $field_id . '}}', esc_html($field_value), $template_content);
            }
        }

        // Force HTML content type
        add_filter('wp_mail_content_type', function () {
            return 'text/html';
        });

        // Add debug comment
        $template_content .= "\n<!-- BET DEBUG SUCCESS (Source: " . (empty($fields) ? 'WP_MAIL_INTERCEPT' : 'BRICKS_HOOK') . "): " . implode(' | ', $debug_log) . " -->";

        return $template_content;
    }

    /**
     * Intercept WP Mail as fallback
     */
    public function intercept_wp_mail($args)
    {
        // Prevent creating infinite loops or double wrapping
        if (isset($args['message']) && strpos($args['message'], 'BET DEBUG') !== false) {
            return $args;
        }

        // Detect if this is likely a Bricks form submission
        // Bricks sends 'formId' in POST request
        $form_id = '';
        if (isset($_POST['formId']))
            $form_id = sanitize_text_field($_POST['formId']);
        elseif (isset($_POST['form_id']))
            $form_id = sanitize_text_field($_POST['form_id']);

        // If we found a form ID in POST, let's try to process it
        if ($form_id) {
            // Fake the form settings object
            $form_settings = array('id' => $form_id);

            // Apply template
            // Note: We pass empty fields, so process_email_template will use args['message'] as content fallback
            $new_content = $this->process_email_template($args['message'], $form_settings);

            // If content changed (template applied), update args
            if ($new_content !== $args['message']) {
                $args['message'] = $new_content;
                // Ensure HTML headers
                if (!isset($args['headers'])) {
                    $args['headers'] = array();
                }
                if (is_array($args['headers'])) {
                    $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
                } else {
                    $args['headers'] .= "\r\nContent-Type: text/html; charset=UTF-8";
                }
            }
        }

        return $args;
    }

    /**
     * Parse raw content string into fields array
     * Tries to handle "Label: Value<br>" format
     */
    private function parse_raw_content_to_fields($content)
    {
        $fields = array();

        // Convert BR tags to newlines for processing
        $text = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // Skip common footer lines
            if (strpos($line, 'Message sent from:') !== false)
                continue;

            // Try to split by first colon
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $label = trim($parts[0]);
                $value = trim($parts[1]);

                // Only add if likely a real field (label not too long)
                if (strlen($label) < 50 && strlen($label) > 0) {
                    $fields[] = array(
                        'id' => sanitize_title($label),
                        'label' => $label,
                        'value' => $value
                    );
                }
            } else {
                // Handle multiline values or lines without colon?
                // For now, maybe append to previous field value if exists?
                if (!empty($fields)) {
                    $last_idx = count($fields) - 1;
                    $fields[$last_idx]['value'] .= "\n" . $line;
                }
            }
        }

        return $fields;
    }

    /**
     * Format all fields for email
     */
    private function format_all_fields($fields)
    {
        $html = '';

        foreach ($fields as $field) {
            $label = isset($field['label']) ? $field['label'] : '';
            $value = isset($field['value']) ? $field['value'] : '';

            if (empty($label)) {
                continue;
            }

            $html .= '<div class="field-row" style="margin: 15px 0; padding: 10px; background: #f9fafb; border-left: 3px solid #2563eb;">';
            $html .= '<div class="field-label" style="font-weight: bold; color: #374151; margin-bottom: 5px;">' . esc_html($label) . '</div>';
            $html .= '<div class="field-value" style="color: #1f2937;">' . nl2br(esc_html($value)) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Plugin activation - create database table
     */
    public function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'bricks-emails_page_bricks-email-builder') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Custom CSS
        wp_enqueue_style(
            'bet-builder-css',
            BET_PLUGIN_URL . 'admin/css/template-builder.css',
            array(),
            BET_VERSION
        );

        // Custom JS
        wp_enqueue_script(
            'bet-builder-js',
            BET_PLUGIN_URL . 'admin/js/template-builder.js',
            array('jquery', 'wp-color-picker'),
            BET_VERSION,
            true
        );

        wp_localize_script('bet-builder-js', 'betAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bet_ajax_nonce')
        ));
    }

    /**
     * Render Email Builder page
     */
    public function render_builder_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';

        // Get all templates
        $templates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");

        // Check if editing
        $editing_template = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing_template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $_GET['edit']
            ));
        }

        include BET_PLUGIN_DIR . 'admin/views/builder-page.php';
    }

    /**
     * AJAX: Save template
     */
    public function ajax_save_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'layout' => sanitize_text_field($_POST['layout']),
            'color_header_start' => sanitize_hex_color($_POST['color_header_start']),
            'color_header_end' => sanitize_hex_color($_POST['color_header_end']),
            'color_accent' => sanitize_hex_color($_POST['color_accent']),
            'color_background' => sanitize_hex_color($_POST['color_background']),
            'color_title' => sanitize_hex_color($_POST['color_title']),
            'color_text' => sanitize_hex_color($_POST['color_text']),
            'color_footer' => sanitize_hex_color($_POST['color_footer']),
            'logo_url' => esc_url_raw($_POST['logo_url']),
            'email_subject' => sanitize_text_field($_POST['email_subject']),
            'header_text' => sanitize_text_field($_POST['header_text']),
            'intro_text' => sanitize_textarea_field($_POST['intro_text']),
            'footer_text' => sanitize_textarea_field($_POST['footer_text']),
        );

        if (isset($_POST['template_id']) && is_numeric($_POST['template_id'])) {
            // Update existing
            $wpdb->update($table_name, $data, array('id' => $_POST['template_id']));
            wp_send_json_success(array('message' => 'Template updated!', 'id' => $_POST['template_id']));
        } else {
            // Insert new
            $wpdb->insert($table_name, $data);
            wp_send_json_success(array('message' => 'Template created!', 'id' => $wpdb->insert_id));
        }
    }

    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';

        $wpdb->delete($table_name, array('id' => $_POST['template_id']));
        wp_send_json_success('Template deleted!');
    }

    /**
     * AJAX: Preview template
     */
    public function ajax_preview_template()
    {
        check_ajax_referer('bet_ajax_nonce', 'nonce');

        $template_data = array(
            'layout' => sanitize_text_field($_POST['layout']),
            'color_header_start' => sanitize_hex_color($_POST['color_header_start']),
            'color_header_end' => sanitize_hex_color($_POST['color_header_end']),
            'color_accent' => sanitize_hex_color($_POST['color_accent']),
            'color_background' => sanitize_hex_color($_POST['color_background']),
            'color_title' => sanitize_hex_color($_POST['color_title']),
            'color_text' => sanitize_hex_color($_POST['color_text']),
            'color_footer' => sanitize_hex_color($_POST['color_footer']),
            'logo_url' => esc_url_raw($_POST['logo_url']),
            'header_text' => sanitize_text_field($_POST['header_text']),
            'intro_text' => sanitize_textarea_field($_POST['intro_text']),
            'footer_text' => sanitize_textarea_field($_POST['footer_text']),
        );

        // Sample fields for preview
        $sample_fields = array(
            array('label' => 'Jméno', 'value' => 'Jan Novák'),
            array('label' => 'Email', 'value' => 'jan@example.com'),
            array('label' => 'Zpráva', 'value' => 'Toto je ukázková zpráva z formuláře.'),
        );

        $html = $this->generate_visual_template_html($template_data, $sample_fields);
        wp_send_json_success($html);
    }

    /**
     * Generate visual template from database
     */
    private function generate_visual_template($template_id, $fields)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bet_templates';

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ), ARRAY_A);

        if (!$template) {
            return '';
        }

        return $this->generate_visual_template_html($template, $fields);
    }

    /**
     * Generate HTML from visual template settings
     */
    private function generate_visual_template_html($template, $fields)
    {
        $layout = $template['layout'];
        $header_gradient = "linear-gradient(135deg, {$template['color_header_start']} 0%, {$template['color_header_end']} 100%)";
        $accent_color = $template['color_accent'];
        $bg_color = $template['color_background'];
        $title_color = !empty($template['color_title']) ? $template['color_title'] : '#1e293b';
        $text_color = !empty($template['color_text']) ? $template['color_text'] : '#4b5563';
        $footer_color = !empty($template['color_footer']) ? $template['color_footer'] : '#9ca3af';

        $logo_url = $template['logo_url'];
        $header_text = $template['header_text'] ?: 'Nová zpráva z webu';
        $intro_text = $template['intro_text'] ?: 'Obdrželi jste novou zprávu z formuláře.';
        $footer_text = $template['footer_text'] ?: get_bloginfo('name');

        // Layout specifics
        $is_card = $layout === 'card';

        $padding = $layout === 'compact' ? '20px' : ($layout === 'spacious' ? '50px' : '35px');
        $field_margin = $layout === 'compact' ? '10px' : ($layout === 'spacious' ? '20px' : '15px');

        // Card layout overrides
        if ($is_card) {
            $padding = '20px'; // Reduced from 32px
            $header_bg = 'transparent';
            $header_text_color = !empty($template['color_title']) ? $template['color_title'] : $template['color_header_start']; 
            $container_style = 'max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;';
            $body_bg_style = 'margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: ' . esc_attr($bg_color) . '; line-height: 1.5;';
        } else {
            $header_bg = $header_gradient;
            $header_text_color = '#ffffff';
            $container_style = 'max-width: 600px; margin: 0 auto; background-color: #ffffff;';
            $body_bg_style = 'margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: ' . esc_attr($bg_color) . '; line-height: 1.6;';
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>

        <body style="<?php echo $body_bg_style; ?>">
            <div style="<?php echo $container_style; ?>">
                <!-- Header -->
                <div
                    style="background: <?php echo $header_bg; ?>; color: <?php echo esc_attr($header_text_color); ?>; padding: <?php echo esc_attr($padding); ?>; padding-bottom: 10px; text-align: <?php echo $is_card ? 'left' : 'center'; ?>;">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 150px; margin-bottom: 15px;">
                    <?php endif; ?>
                    <h1
                        style="margin: 0; font-size: <?php echo $is_card ? '24px' : '26px'; ?>; font-weight: <?php echo $is_card ? '700' : '600'; ?>;">
                        <?php echo esc_html($header_text); ?></h1>
                </div>

                <!-- Body -->
                <div style="padding: <?php echo esc_attr($padding); ?>; padding-top: 0;">
                    <?php if ($intro_text): ?>
                        <p style="color: <?php echo esc_attr($text_color); ?>; font-size: 15px; margin-bottom: 20px;"><?php echo esc_html($intro_text); ?></p>
                    <?php endif; ?>

                    <!-- Fields -->
                    <div style="margin: 20px 0;">
                        <?php foreach ($fields as $field): ?>
                            <?php if (empty($field['label']))
                                continue; ?>
                            <div
                                style="margin: <?php echo $is_card ? '0' : esc_attr($field_margin); ?>; padding: <?php echo $is_card ? '12px 0' : '15px'; ?>; <?php echo $is_card ? '' : 'background: #f9fafb; border-left: 4px solid ' . esc_attr($accent_color) . '; border-radius: 4px;'; ?>">
                                <div
                                    style="font-weight: 600; color: #9ca3af; margin-bottom: 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?php echo esc_html($field['label']); ?></div>
                                <div style="color: <?php echo esc_attr($text_color); ?>; font-size: 14px;"><?php echo nl2br(esc_html($field['value'])); ?></div>
                            </div>
                            <?php if ($is_card): ?>
                                <hr style="border: 0; border-bottom: 1px solid #eee; margin: 0;">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <p style="color: #9ca3af; font-size: 12px; margin-top: 25px; text-align: right;">
                        Odesláno: <?php echo date('d.m.Y H:i:s'); ?>
                    </p>
                </div>

                <!-- Footer -->
                <div
                    style="<?php echo $is_card ? 'padding: 0 ' . $padding . ' ' . $padding . ' ' . $padding . ';' : 'background-color: #f9fafb; padding: 25px; text-align: center; border-top: 1px solid #e5e7eb;'; ?>">
                    <p style="margin: 5px 0; color: <?php echo esc_attr($footer_color); ?>; font-size: 13px; <?php echo $is_card ? 'opacity: 0.85;' : ''; ?>">
                        <?php echo esc_html($footer_text); ?></p>
                    <p style="margin: 5px 0; color: #9ca3af; font-size: 12px;"><?php echo get_bloginfo('url'); ?></p>
                </div>
            </div>
        </body>

        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
Bricks_Email_Templates::get_instance();
