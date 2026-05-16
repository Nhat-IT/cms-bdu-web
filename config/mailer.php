<?php
/**
 * CMS BDU - Mail Helper (PHPMailer + Gmail SMTP)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Gửi OTP qua email.
 * @return array ['success' => bool, 'message' => string]
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): array {
    $host     = envOrDefault('MAIL_HOST', '');
    $port     = (int) envOrDefault('MAIL_PORT', '587');
    $username = envOrDefault('MAIL_USERNAME', '');
    $password = envOrDefault('MAIL_PASSWORD', '');
    $fromAddr = envOrDefault('MAIL_FROM_ADDRESS', $username);
    $fromName = envOrDefault('MAIL_FROM_NAME', 'CMS BDU');

    if (!$host || !$username || !$password) {
        error_log('[Mailer] Chưa cấu hình SMTP trong .env.local');
        return ['success' => false, 'message' => 'Chưa cấu hình email. Liên hệ quản trị viên.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = '[CMS BDU] Mã OTP khôi phục mật khẩu';
        $mail->Body    = buildOtpEmailHtml($toName, $otp);
        $mail->AltBody = "Xin chào {$toName},\n\nMã OTP của bạn là: {$otp}\nMã có hiệu lực trong 10 phút.\n\nCMS BDU";

        $mail->send();
        return ['success' => true, 'message' => 'Email đã được gửi.'];
    } catch (Exception $e) {
        error_log('[Mailer] Lỗi gửi email: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Không thể gửi email. Vui lòng thử lại.'];
    }
}

function buildOtpEmailHtml(string $name, string $otp): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#0d6efd,#0a58ca);padding:32px;text-align:center;">
            <div style="font-size:2.5rem;">🎓</div>
            <h1 style="color:#fff;margin:8px 0 0;font-size:1.4rem;letter-spacing:.5px;">CMS BDU</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="color:#374151;margin:0 0 8px;">Xin chào <strong>{$name}</strong>,</p>
            <p style="color:#6b7280;margin:0 0 28px;font-size:.95rem;">
              Chúng tôi nhận được yêu cầu khôi phục mật khẩu cho tài khoản của bạn.
              Sử dụng mã OTP bên dưới để tiếp tục:
            </p>
            <div style="background:#f0f9ff;border:2px dashed #0d6efd;border-radius:10px;padding:20px;text-align:center;margin-bottom:28px;">
              <div style="font-size:2.4rem;font-weight:700;letter-spacing:.35em;color:#0d6efd;font-family:monospace;">{$otp}</div>
              <div style="color:#6b7280;font-size:.82rem;margin-top:8px;">⏱ Hiệu lực trong <strong>10 phút</strong></div>
            </div>
            <p style="color:#9ca3af;font-size:.82rem;margin:0;">
              Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.<br>
              Mã OTP chỉ sử dụng được <strong>một lần</strong>.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;text-align:center;color:#9ca3af;font-size:.8rem;border-top:1px solid #e5e7eb;">
            © CMS BDU — Hệ thống Quản lý Lớp học Thông minh
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
