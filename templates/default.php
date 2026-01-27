<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="x-apple-disable-message-reformatting" />
    <title>Nová zpráva</title>
</head>

<body style="margin:0; padding:0; background-color:#F2F2EA;">
    <!-- Preheader (hidden) -->
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        Nová zpráva z webu <?php echo get_bloginfo('name'); ?>
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
        style="background-color:#F2F2EA;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <!-- Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
                    style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px;">

                    <!-- Title -->
                    <tr>
                        <td align="left" style="padding: 28px 28px 8px 28px;">
                            <h2
                                style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:32px; color:#FA8B6B; font-weight:700;">
                                <?php echo get_bloginfo('name'); ?>
                            </h2>
                        </td>
                    </tr>

                    <!-- Body text -->
                    <tr>
                        <td align="left" style="padding:0 28px 24px 28px;">
                            <p
                                style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:rgb(117, 117, 117);">
                                Ahoj,<br> na webu byl odeslán nový formulář.
                            </p>

                            <!-- Fields -->
                            <div
                                style="margin: 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:rgb(117, 117, 117);">
                                {{all_fields}}
                            </div>

                            <p
                                style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:rgb(117, 117, 117);">
                                Pěkný den!<br /> (Automaticky odesláno z <?php echo get_bloginfo('url'); ?>)
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="left" style="padding:0 28px 28px 28px;">
                            <p
                                style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgb(117, 117, 117); opacity:0.85;">
                                <?php echo get_bloginfo('name'); ?> &middot; <span
                                    style="white-space:nowrap;"><?php echo date('Y'); ?></span>
                            </p>
                        </td>
                    </tr>
                </table>
                <!-- /Container -->
            </td>
        </tr>
    </table>
</body>

</html>