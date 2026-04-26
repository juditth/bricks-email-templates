# Bricks Email Templates

WordPress plugin for creating and assigning HTML email templates to Bricks Builder forms.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Bricks Builder active as the current theme or available Bricks runtime

The plugin blocks activation when Bricks is not active.

## What It Does

- Adds its admin pages under the Bricks admin menu.
- Lets you map detected Bricks forms to email templates.
- Provides a visual email template builder.
- Provides a custom HTML editor for full email markup.
- Shows detected form field placeholders so they can be inserted into custom HTML.
- Supports `{{all_fields}}` and individual field placeholders such as `{{email}}`.
- Loads file-based templates from the active child theme first, then the parent theme.

## Admin Templates

Go to **Bricks > Email Template Builder**.

You can create two types of templates:

1. **Visual builder**: configure layout, colors, logo, heading, intro text, footer text, and optional subject override.
2. **Custom HTML**: paste full email HTML and insert placeholders from a selected Bricks form.

Admin-created templates are stored in the WordPress database, so plugin updates do not overwrite them.

## Theme File Templates

Custom file templates should live in your theme, not in the plugin folder:

```text
wp-content/themes/your-child-theme/bricks-email-templates/contact.php
wp-content/themes/your-parent-theme/bricks-email-templates/contact.php
```

The child theme folder wins over the parent theme folder when a file has the same name.

A file template can use placeholders directly:

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

## Placeholders

### `{{all_fields}}`

Renders every submitted form field using the plugin's default field layout.

### `{{field_id}}`

Renders one submitted field by its Bricks field ID.

Examples:

- `{{name}}`
- `{{email}}`
- `{{message}}`

The builder can detect placeholders from saved Bricks form elements and list them next to the HTML editor.

## Mapping Forms

Go to **Bricks > Email Templates**.

For each detected form, choose:

- an admin-created template,
- a theme file template,
- or **Default Bricks email HTML** to leave the email unchanged.

## Notes

- Use inline CSS for email compatibility.
- Use a child theme for custom file templates when possible.
- Do not edit templates inside the plugin folder; those files can be replaced during plugin updates.

## License

GPL v2 or later
