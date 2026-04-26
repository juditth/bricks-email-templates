# Bricks Email Templates

WordPress plugin for creating, editing, and assigning theme-based HTML email templates to Bricks Builder forms.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Bricks Builder active as the current theme or available Bricks runtime

The plugin blocks activation when Bricks is not active.

## What It Does

- Adds its admin pages under the Bricks admin menu.
- Lets you assign a detected Bricks form while editing a template.
- Creates and edits template files in the active child theme or parent theme.
- Overrides the matching Bricks email body when a template is mapped to a form and target.
- Shows detected form field placeholders so they can be inserted into custom HTML.
- Supports `{{all_fields}}` and individual field placeholders such as `{{email}}`.
- Loads templates from the active child theme first, then the parent theme.

## Template Storage

Template HTML is not stored in the database.

They live in your active theme folder:

```text
wp-content/themes/your-child-theme/bricks-email-templates/contact.html
wp-content/themes/your-parent-theme/bricks-email-templates/contact.html
```

Use a child theme whenever possible. The child theme folder wins over the parent theme folder when a file has the same name.

The plugin uses normal WordPress options to store template labels, stable IDs, targets, and form-to-template mappings.

## Builder

Go to **Bricks > Email Template Builder**.

The builder edits HTML template files only:

1. Select a Bricks form as the placeholder source.
2. Choose the template target: None, Email, Confirmation email, or Both.
3. Enter any template name you want. It is stored in WordPress settings, so changing it does not rename the HTML file.
4. Paste or edit your HTML.
5. Click placeholders to insert them at the cursor position in the HTML editor.
6. Save the template. A `.html` template file is created or updated in the theme template folder and assigned to the selected Bricks form target.

Choose **None** to save the template file without assigning it to an email.

## Sending Priority

The Bricks email action must stay enabled on the form, because Bricks still performs the actual sending.

When a template is mapped in this plugin, it replaces the matching Bricks email body:

- `Email` replaces the first Bricks email action message.
- `Confirmation email` replaces the confirmation email message.
- `Both` creates or updates separate mappings for both targets.
- `None` disconnects the template from sending.

## Placeholders

### `{{all_fields}}`

Renders every submitted form field as escaped text. The plugin does not add a default HTML layout.

### `{{field_id}}`

Renders one submitted field by its Bricks field ID.

Examples:

- `{{name}}`
- `{{email}}`
- `{{message}}`

## File Template Example

```php
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif;">
    <h1>New website enquiry</h1>
    {{all_fields}}
    <p><strong>Email:</strong> {{email}}</p>
</body>
</html>
```

## Notes

- Use inline CSS for email compatibility.
- Do not edit templates inside the plugin folder; plugin files can be replaced during plugin updates.
- Template HTML files stay clean. Template labels, stable IDs, targets, and mappings are stored in WordPress options.

## License

GPL v2 or later
