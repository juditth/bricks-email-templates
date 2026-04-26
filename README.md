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
- Shows detected form field placeholders so they can be inserted into custom HTML.
- Supports `{{all_fields}}` and individual field placeholders such as `{{email}}`.
- Loads templates from the active child theme first, then the parent theme.

## Template Storage

Templates are not stored as template records in the database.

They live in your active theme folder:

```text
wp-content/themes/your-child-theme/bricks-email-templates/contact.html
wp-content/themes/your-parent-theme/bricks-email-templates/contact.html
```

Use a child theme whenever possible. The child theme folder wins over the parent theme folder when a file has the same name.

The plugin still uses normal WordPress options to store form-to-template mappings.

## Builder

Go to **Bricks > Email Template Builder**.

The builder edits HTML template files only:

1. Select a Bricks form as the placeholder source.
2. Enter any template name you want. It is stored in a metadata comment inside the template file.
3. Paste or edit your HTML.
4. Click placeholders to insert them at the cursor position in the HTML editor.
5. Save the template. A `.html` template file is created or updated in the theme template folder and assigned to the selected Bricks form.

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
- Template metadata is stored as an HTML comment at the top of each generated template file.

## License

GPL v2 or later
