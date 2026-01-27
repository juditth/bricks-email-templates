<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8fafc;
            line-height: 1.6;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 35px 30px;
            text-align: center;
        }

        .email-header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 600;
        }

        .email-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .email-body {
            padding: 35px 30px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .info-label {
            font-weight: 600;
            color: #1e40af;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #1f2937;
            font-size: 15px;
        }

        .message-box {
            background: #f9fafb;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            margin: 15px 0;
        }

        .message-box p {
            margin: 0;
            color: #374151;
            line-height: 1.7;
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .email-footer p {
            margin: 5px 0;
            color: #6b7280;
            font-size: 13px;
        }

        .timestamp {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 20px;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1>✉️ Kontaktní formulář</h1>
            <p>Nová zpráva z webu</p>
        </div>

        <!-- Body -->
        <div class="email-body">

            <!-- Kontaktní informace -->
            <div class="section">
                <div class="section-title">Kontaktní údaje</div>

                <!-- Příklad použití konkrétních field ID z Bricks -->
                <!-- Nahraďte ID níže za skutečná ID z vašeho formuláře -->

                <div class="info-box">
                    <div class="info-label">Jméno a příjmení</div>
                    <div class="info-value">{{jmeno}}</div>
                </div>

                <div class="info-box">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{email}}</div>
                </div>

                <div class="info-box">
                    <div class="info-label">Telefon</div>
                    <div class="info-value">{{telefon}}</div>
                </div>
            </div>

            <!-- Zpráva -->
            <div class="section">
                <div class="section-title">Zpráva</div>
                <div class="message-box">
                    <p>{{zprava}}</p>
                </div>
            </div>

            <!-- Nebo použijte {{all_fields}} pro automatické zobrazení všech polí -->
            <!-- 
            <div class="section">
                <div class="section-title">Všechna pole formuláře</div>
                {{all_fields}}
            </div>
            -->

            <p class="timestamp">
                Odesláno:
                <?php echo date('d.m.Y H:i:s'); ?>
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>
                    <?php echo get_bloginfo('name'); ?>
                </strong></p>
            <p>
                <?php echo get_bloginfo('url'); ?>
            </p>
            <p style="margin-top: 15px; font-size: 11px;">
                Tento email byl automaticky vygenerován z kontaktního formuláře.
            </p>
        </div>
    </div>
</body>

</html>