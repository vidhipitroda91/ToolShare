<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('toolshare_mail_config')) {
    function toolshare_mail_env_value(string $key, $default = null)
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    function toolshare_mail_env_bool(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $normalized === null ? $default : $normalized;
    }

    function toolshare_mail_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $configFile = __DIR__ . '/../config/mail.php';
        $fileConfig = file_exists($configFile) ? require $configFile : [];

        $config = array_merge($fileConfig, [
            'enabled' => toolshare_mail_env_bool('MAIL_ENABLED', !empty($fileConfig['enabled'])),
            'host' => (string)toolshare_mail_env_value('MAIL_HOST', $fileConfig['host'] ?? ''),
            'port' => (int)toolshare_mail_env_value('MAIL_PORT', $fileConfig['port'] ?? 0),
            'secure' => (string)toolshare_mail_env_value('MAIL_SECURE', $fileConfig['secure'] ?? ''),
            'username' => (string)toolshare_mail_env_value('MAIL_USERNAME', $fileConfig['username'] ?? ''),
            'password' => (string)toolshare_mail_env_value('MAIL_PASSWORD', $fileConfig['password'] ?? ''),
            'from_email' => (string)toolshare_mail_env_value('MAIL_FROM_EMAIL', $fileConfig['from_email'] ?? ''),
            'from_name' => (string)toolshare_mail_env_value('MAIL_FROM_NAME', $fileConfig['from_name'] ?? 'ToolShare'),
            'app_url' => rtrim((string)toolshare_mail_env_value('APP_URL', $fileConfig['app_url'] ?? ''), '/'),
            'verification_expiry_hours' => (int)toolshare_mail_env_value('MAIL_VERIFICATION_EXPIRY_HOURS', $fileConfig['verification_expiry_hours'] ?? 24),
            'password_reset_expiry_hours' => (int)toolshare_mail_env_value('MAIL_PASSWORD_RESET_EXPIRY_HOURS', $fileConfig['password_reset_expiry_hours'] ?? 1),
        ]);

        return $config;
    }

    function toolshare_mail_is_ready(): bool
    {
        $config = toolshare_mail_config();

        return !empty($config['enabled'])
            && !empty($config['host'])
            && !empty($config['port'])
            && !empty($config['username'])
            && !empty($config['password'])
            && !empty($config['from_email'])
            && !empty($config['app_url']);
    }

    function toolshare_mail_app_url(): string
    {
        $config = toolshare_mail_config();
        $configured = rtrim((string)($config['app_url'] ?? ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . ($scriptDir === '' ? '' : $scriptDir);
        }

        return '';
    }

    function toolshare_email_verification_expiry_hours(): int
    {
        $config = toolshare_mail_config();
        $hours = (int)($config['verification_expiry_hours'] ?? 24);
        return $hours > 0 ? $hours : 24;
    }

    function toolshare_password_reset_expiry_hours(): int
    {
        $config = toolshare_mail_config();
        $hours = (int)($config['password_reset_expiry_hours'] ?? 1);
        return $hours > 0 ? $hours : 1;
    }

    function toolshare_create_mailer(): PHPMailer
    {
        if (!toolshare_mail_is_ready()) {
            throw new RuntimeException('Mail delivery is not configured yet.');
        }

        $config = toolshare_mail_config();
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string)$config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = (string)$config['username'];
        $mailer->Password = (string)$config['password'];
        $mailer->SMTPSecure = (string)$config['secure'];
        $mailer->Port = (int)$config['port'];
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom((string)$config['from_email'], (string)($config['from_name'] ?? 'ToolShare'));

        return $mailer;
    }

    function toolshare_send_templated_email(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $heading,
        string $intro,
        array $details = [],
        ?string $buttonLabel = null,
        ?string $buttonUrl = null,
        array $extraParagraphs = [],
        ?string $closing = null
    ): void {
        try {
            $mailer = toolshare_create_mailer();
            $mailer->addAddress($recipientEmail, $recipientName);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;

            $escapedName = htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8');
            $escapedHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
            $escapedIntro = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'));
            $detailRows = '';
            $altLines = [];

            foreach ($details as $label => $value) {
                $labelText = trim((string)$label);
                $valueText = trim((string)$value);
                if ($labelText === '' || $valueText === '') {
                    continue;
                }

                $detailRows .= '<tr>'
                    . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:13px; font-weight:700; width:38%;">' . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; color:#15324a; font-size:13px;">' . htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
                $altLines[] = $labelText . ': ' . $valueText;
            }

            $buttonHtml = '';
            if ($buttonLabel !== null && $buttonUrl !== null && $buttonLabel !== '' && $buttonUrl !== '') {
                $escapedButtonLabel = htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8');
                $escapedButtonUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
                $buttonHtml = '
                    <p style="margin:24px 0 20px;">
                        <a href="' . $escapedButtonUrl . '" style="background:#15324a; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:999px; font-weight:700; display:inline-block;">' . $escapedButtonLabel . '</a>
                    </p>
                    <p style="font-size:13px; color:#64748b; margin:0 0 16px;">If the button does not work, copy and paste this link into your browser:<br><a href="' . $escapedButtonUrl . '" style="color:#1f6f78;">' . $escapedButtonUrl . '</a></p>
                ';
                $altLines[] = $buttonLabel . ': ' . $buttonUrl;
            }

            $paragraphHtml = '';
            foreach ($extraParagraphs as $paragraph) {
                $paragraphText = trim((string)$paragraph);
                if ($paragraphText === '') {
                    continue;
                }
                $paragraphHtml .= '<p style="margin:0 0 14px; color:#334155;">' . nl2br(htmlspecialchars($paragraphText, ENT_QUOTES, 'UTF-8')) . '</p>';
                $altLines[] = $paragraphText;
            }

            $closingText = trim((string)($closing ?? 'Thank you for using ToolShare.'));
            $escapedClosing = htmlspecialchars($closingText, ENT_QUOTES, 'UTF-8');

            $mailer->Body = '
                <div style="font-family:Arial,sans-serif; background:#f8fafc; padding:24px; color:#15324a;">
                    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden;">
                        <div style="padding:24px 28px; background:linear-gradient(135deg, #15324a 0%, #1f6f78 100%); color:#ffffff;">
                            <div style="font-size:13px; letter-spacing:0.12em; text-transform:uppercase; font-weight:800; opacity:0.9;">ToolShare</div>
                            <h1 style="margin:10px 0 0; font-size:28px; line-height:1.05;">' . $escapedHeading . '</h1>
                        </div>
                        <div style="padding:28px;">
                            <p style="margin:0 0 14px; color:#334155;">Hello ' . $escapedName . ',</p>
                            <p style="margin:0 0 16px; color:#334155;">' . $escapedIntro . '</p>
                            ' . ($detailRows !== '' ? '<table style="width:100%; border-collapse:collapse; margin:0 0 18px;">' . $detailRows . '</table>' : '') . '
                            ' . $paragraphHtml . '
                            ' . $buttonHtml . '
                            <p style="margin:20px 0 0; color:#475569;">' . $escapedClosing . '</p>
                        </div>
                    </div>
                </div>
            ';

            $altBody = [$heading, '', 'Hello ' . ($recipientName !== '' ? $recipientName : 'there') . ',', '', $intro];
            if (!empty($altLines)) {
                $altBody[] = '';
                foreach ($altLines as $line) {
                    $altBody[] = $line;
                }
            }
            $altBody[] = '';
            $altBody[] = $closingText;
            $mailer->AltBody = implode("\n", $altBody);
            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Unable to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    function toolshare_send_verification_email(string $recipientEmail, string $recipientName, string $verifyLink): void
    {
        $expiryHours = toolshare_email_verification_expiry_hours();
        toolshare_send_templated_email(
            $recipientEmail,
            $recipientName,
            'Verify your ToolShare email',
            'Verify your email',
            'Thanks for joining ToolShare. Please verify your email address to activate your account.',
            ['Link expiry' => $expiryHours . ' hour(s)'],
            'Verify Email',
            $verifyLink
        );
    }

    function toolshare_send_email_change_verification_email(string $recipientEmail, string $recipientName, string $verifyLink): void
    {
        $expiryHours = toolshare_email_verification_expiry_hours();
        toolshare_send_templated_email(
            $recipientEmail,
            $recipientName,
            'Confirm your new ToolShare email',
            'Confirm your new email',
            'You requested to change the email address on your ToolShare account. Please confirm your new email below.',
            ['Link expiry' => $expiryHours . ' hour(s)'],
            'Confirm New Email',
            $verifyLink
        );
    }

    function toolshare_send_password_reset_email(string $recipientEmail, string $recipientName, string $resetLink): void
    {
        $expiryHours = toolshare_password_reset_expiry_hours();
        toolshare_send_templated_email(
            $recipientEmail,
            $recipientName,
            'Reset your ToolShare password',
            'Reset your password',
            'We received a request to reset your ToolShare password.',
            ['Link expiry' => $expiryHours . ' hour(s)'],
            'Reset Password',
            $resetLink
        );
    }
}
