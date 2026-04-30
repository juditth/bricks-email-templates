# Bricks Email Templates

WordPress plugin for creating, editing, and assigning file-based HTML email templates to Bricks Builder forms.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Bricks Builder active as the current theme or available Bricks runtime

The plugin blocks activation when Bricks is not active.

## What It Does

- Adds its admin pages under the Bricks admin menu.
- Lets you assign a detected Bricks form while editing a template.
- Creates and edits template files in the site's uploads folder, so they survive theme and plugin updates.
- Overrides the matching Bricks email body when a template is mapped to a form and target.
- Shows detected form field placeholders so they can be inserted into custom HTML.
- Supports `{{all_fields}}` and individual field placeholders such as `{{email}}`.
- Still reads older theme-based template files as a legacy fallback.

## Template Storage

Template HTML is not stored in the database.

Templates live in the current site's uploads folder:

```text
wp-content/uploads/bricks-email-templates/contact.html
wp-content/uploads/sites/site-id/bricks-email-templates/contact.html
```

On multisite installs, WordPress automatically uses the current site's uploads path:

```text
wp-content/uploads/sites/site-id/bricks-email-templates/contact.html
```

This keeps template files isolated per site even when multiple sites use the same theme.

Older versions stored templates in the active child theme or parent theme:

```text
wp-content/themes/your-child-theme/bricks-email-templates/contact.html
wp-content/themes/your-parent-theme/bricks-email-templates/contact.html
```

Those legacy files are still detected and copied to uploads when the builder opens. Future saves write to uploads.

The plugin uses normal WordPress options to store template labels, stable IDs, targets, and form-to-template mappings.

## Builder

Go to **Bricks > Email Template Builder**.

The builder edits HTML template files only:

1. Click **Create new template** or select an existing template to edit.
2. Select a Bricks form as the placeholder source.
3. Choose the template target: Email, Confirmation email, or both checkboxes.
4. Enter any template name you want. It is stored in WordPress settings, so changing it does not rename the HTML file.
5. Paste or edit your HTML.
6. Click placeholders to insert them at the cursor position in the HTML editor.
7. Save the template. A `.html` template file is created or updated in the uploads template folder and assigned to the selected Bricks form target.

Leave both target checkboxes unchecked to save the template file without assigning it to an email.

## Sending Priority

The Bricks email action must stay enabled on the form, because Bricks still performs the actual sending.

When a template is mapped in this plugin, it replaces the matching Bricks email body:

- `Email` replaces the first Bricks email action message.
- `Confirmation email` replaces the confirmation email message.
- Checking both targets creates or updates separate mappings for both emails.
- Leaving both targets unchecked saves the template without assigning it to sending.

## Automatic Updates

The plugin bundles Plugin Update Checker and reads update metadata from:

```text
https://vyladeny-web.cz/plugins/bricks-email-templates/info.json
```

The update metadata should point to the release ZIP package and use the `bricks-email-templates` slug, for example:

```json
{
  "name": "Bricks Email Templates",
  "slug": "bricks-email-templates",
  "version": "1.0.4",
  "download_url": "https://github.com/juditth/bricks-email-templates/archive/refs/tags/1.0.4.zip",
  "requires": "6.0",
  "tested": "6.9",
  "requires_php": "8.0"
}
```

Keep the server copy of `info.json` aligned with the root `info.json` file before publishing a new release tag.

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
