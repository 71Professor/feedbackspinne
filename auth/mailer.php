<?php
/**
 * Feedbackspinne - Mailer (PHPMailer-Wrapper)
 *
 * Sends transactional e-mails via SMTP (PHPMailer).
 * All credentials come from the .env file loaded in config.php.
 *
 * Required .env keys:
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
 *   SMTP_FROM_EMAIL, SMTP_FROM_NAME
 *   APP_URL  (e.g. https://example.com) – used to build reset links
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Create a configured PHPMailer instance.
 *
 * @return PHPMailer
 * @throws MailerException
 */
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST')  ?: 'localhost';
    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER')  ?: '';
    $mail->Password   = getenv('SMTP_PASS')  ?: '';
    $mail->SMTPSecure = (int)(getenv('SMTP_PORT') ?: 587) === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->CharSet  = 'UTF-8';
    $mail->setFrom(
        getenv('SMTP_FROM_EMAIL') ?: 'noreply@example.com',
        getenv('SMTP_FROM_NAME')  ?: 'Feedbackspinne'
    );

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    }

    return $mail;
}

/**
 * Send a password-reset e-mail.
 *
 * @param string $toEmail  Recipient e-mail
 * @param string $toName   Recipient name (username)
 * @param string $token    Raw (unhashed) reset token
 * @return bool
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool {
    $appUrl   = rtrim(getenv('APP_URL') ?: '', '/');
    $resetUrl = $appUrl . '/auth/?page=reset&token=' . urlencode($token);

    $subject = 'Passwort zurücksetzen – Feedbackspinne';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family:ui-sans-serif,system-ui,Arial,sans-serif;background:#f8fafc;margin:0;padding:40px 20px;">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:40px;">
    <img src="cid:spider_logo" alt="Feedbackspinne" width="80" style="margin-bottom:16px;">
    <h2 style="margin:0 0 8px;color:#0f172a;">Passwort zurücksetzen</h2>
    <p style="color:#64748b;margin:0 0 24px;">Hallo {$toName},<br>
       du hast eine Anfrage zum Zurücksetzen deines Passworts gestellt.</p>
    <a href="{$resetUrl}"
       style="display:inline-block;background:#7ab800;color:#fff;text-decoration:none;
              padding:14px 28px;border-radius:10px;font-weight:600;font-size:15px;">
      Passwort zurücksetzen
    </a>
    <p style="color:#94a3b8;font-size:13px;margin:24px 0 0;">
      Dieser Link ist <strong>60 Minuten</strong> gültig.<br>
      Falls du keine Anfrage gestellt hast, kannst du diese E-Mail ignorieren.
    </p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="color:#cbd5e1;font-size:12px;margin:0;">
      Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:<br>
      <a href="{$resetUrl}" style="color:#7ab800;word-break:break-all;">{$resetUrl}</a>
    </p>
  </div>
</body>
</html>
HTML;

    $text = "Passwort zurücksetzen – Feedbackspinne\n\n"
          . "Hallo {$toName},\n\n"
          . "du hast eine Anfrage zum Zurücksetzen deines Passworts gestellt.\n\n"
          . "Link (gültig 60 Minuten):\n{$resetUrl}\n\n"
          . "Falls du keine Anfrage gestellt hast, ignoriere diese E-Mail.";

    return sendMail($toEmail, $toName, $subject, $html, $text);
}

/**
 * Send an e-mail-verification e-mail.
 *
 * @param string $toEmail  Recipient e-mail
 * @param string $toName   Recipient name (username)
 * @param string $token    Raw (plain) verification token
 * @return bool
 */
function sendVerificationEmail(string $toEmail, string $toName, string $token): bool {
    $appUrl     = rtrim(getenv('APP_URL') ?: '', '/');
    $verifyUrl  = $appUrl . '/auth/?verify_email=' . urlencode($token);

    $subject = 'E-Mail-Adresse bestätigen – Feedbackspinne';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>{$subject}</title></head>
<body style="font-family:ui-sans-serif,system-ui,Arial,sans-serif;background:#f8fafc;margin:0;padding:40px 20px;">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:40px;">
    <img src="cid:spider_logo" alt="Feedbackspinne" width="80" style="margin-bottom:16px;">
    <h2 style="margin:0 0 8px;color:#0f172a;">E-Mail-Adresse bestätigen</h2>
    <p style="color:#64748b;margin:0 0 24px;">Hallo {$toName},<br>
       bitte bestätige deine E-Mail-Adresse, um deinen Account zu aktivieren.</p>
    <a href="{$verifyUrl}"
       style="display:inline-block;background:#7ab800;color:#fff;text-decoration:none;
              padding:14px 28px;border-radius:10px;font-weight:600;font-size:15px;">
      E-Mail-Adresse bestätigen
    </a>
    <p style="color:#94a3b8;font-size:13px;margin:24px 0 0;">
      Dieser Link ist <strong>24 Stunden</strong> gültig.<br>
      Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.
    </p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="color:#cbd5e1;font-size:12px;margin:0;">
      Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:<br>
      <a href="{$verifyUrl}" style="color:#7ab800;word-break:break-all;">{$verifyUrl}</a>
    </p>
  </div>
</body>
</html>
HTML;

    $text = "E-Mail-Adresse bestätigen – Feedbackspinne\n\n"
          . "Hallo {$toName},\n\n"
          . "bitte bestätige deine E-Mail-Adresse über folgenden Link (gültig 24 Stunden):\n{$verifyUrl}\n\n"
          . "Falls du dich nicht registriert hast, ignoriere diese E-Mail.";

    return sendMail($toEmail, $toName, $subject, $html, $text);
}

/**
 * Send a generic e-mail (internal helper).
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $html
 * @param string $text   Plain-text fallback
 * @return bool
 */
function sendMail(string $toEmail, string $toName, string $subject, string $html, string $text): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $spiderPath = __DIR__ . '/../spider1.svg';
        if (file_exists($spiderPath)) {
            $mail->addEmbeddedImage($spiderPath, 'spider_logo', 'spider.svg', 'base64', 'image/svg+xml');
        }
        $mail->send();
        return true;
    } catch (MailerException $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}
