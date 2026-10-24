<?php
declare(strict_types=1);

final class Mailer
{
    public static function brandedHtml(string $title, string $intro, string $highlight = '', string $note = ''): string
    {
        $safe = static fn(string $value): string => nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $highlightBlock = $highlight !== '' ? '<div style="margin:24px 0;padding:18px 22px;border:1px solid #ead9b7;border-radius:14px;background:#fff9ec;color:#17324d;font-size:26px;font-weight:800;letter-spacing:3px;text-align:center;direction:ltr">'.$safe($highlight).'</div>' : '';
        $noteBlock = $note !== '' ? '<p style="margin:20px 0 0;color:#71808a;font-size:13px;line-height:1.9">'.$safe($note).'</p>' : '';
        return '<!doctype html><html lang="ar" dir="rtl"><body style="margin:0;background:#f6f2e9;color:#17324d;font-family:Tahoma,Arial,sans-serif;line-height:1.8">'
            .'<div style="max-width:620px;margin:28px auto;padding:0 16px">'
            .'<div style="overflow:hidden;border:1px solid #e5dccb;border-radius:22px;background:#fffdfa;box-shadow:0 12px 35px rgba(23,50,77,.08)">'
            .'<div style="padding:24px 28px;background:linear-gradient(135deg,#17324d,#2d536a);color:#fff">'
            .'<div style="font-size:22px;font-weight:800;letter-spacing:.5px">أثر<span style="color:#f0c778">.</span></div>'
            .'<div style="margin-top:5px;color:#dbe7ef;font-size:12px">مساحة تحفظ ما تصنعه من أثر</div></div>'
            .'<div style="padding:30px 28px"><div style="margin-bottom:8px;color:#9b6c24;font-size:12px;font-weight:700">رسالة من منصة أثر</div>'
            .'<h1 style="margin:0 0 14px;font-size:25px;line-height:1.5">'.$safe($title).'</h1>'
            .'<p style="margin:0;color:#425568;font-size:15px;line-height:2">'.$safe($intro).'</p>'
            .$highlightBlock.$noteBlock
            .'</div><div style="padding:16px 28px;border-top:1px solid #eee7da;color:#89949c;font-size:11px">هذه رسالة آلية من منصة أثر. لا ترد على هذه الرسالة.</div></div></div></body></html>';
    }

    public static function send(PDO $db, string $recipient, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $settings = SiteSettings::all($db);
        $from = trim((string)($settings['mail_from_email'] ?? '')) ?: trim((string)(getenv('ATHAR_MAIL_FROM_EMAIL') ?: ''));
        $fromName = trim((string)($settings['mail_from_name'] ?? '')) ?: trim((string)(getenv('ATHAR_MAIL_FROM_NAME') ?: 'أثر'));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('تحقق من بريد المرسل في لوحة إعدادات البريد.');
        }
        $mode = (string)($settings['mail_transport'] ?? 'smtp');
        if ($mode === 'php_mail') {
            if ($htmlBody !== null && trim($htmlBody) !== '') {
                $boundary = '=_athar_' . bin2hex(random_bytes(12));
                $headers = ['MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="'.$boundary.'"', 'From: ' . self::encodedName($fromName) . ' <' . $from . '>'];
                $message = self::multipartBody($boundary, $body, $htmlBody);
            } else {
                $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'From: ' . self::encodedName($fromName) . ' <' . $from . '>'];
                $message = $body;
            }
            return @mail($recipient, self::encodedHeader($subject), $message, implode("\r\n", $headers));
        }
        return self::smtp($settings, $recipient, $from, $fromName, $subject, $body, $htmlBody);
    }

    private static function smtp(array $s, string $recipient, string $from, string $fromName, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $base=dirname(__DIR__).'/app/PHPMailer/';
        if (is_readable($base.'PHPMailer.php') && is_readable($base.'SMTP.php') && is_readable($base.'Exception.php')) {
            require_once $base.'Exception.php'; require_once $base.'PHPMailer.php'; require_once $base.'SMTP.php';
            return self::smtpWithPhpMailer($s,$recipient,$from,$fromName,$subject,$body,$htmlBody);
        }
        $host = trim((string)($s['mail_smtp_host'] ?? '')) ?: trim((string)(getenv('ATHAR_MAIL_SMTP_HOST') ?: ''));
        $port = (int)(($s['mail_smtp_port'] ?? '') ?: (getenv('ATHAR_MAIL_SMTP_PORT') ?: 587));
        $encryption = (string)(($s['mail_smtp_encryption'] ?? '') ?: (getenv('ATHAR_MAIL_SMTP_ENCRYPTION') ?: 'tls'));
        $username = trim((string)($s['mail_smtp_username'] ?? '')) ?: trim((string)(getenv('ATHAR_MAIL_SMTP_USERNAME') ?: ''));
        $password = (string)($s['mail_smtp_password'] ?? '') ?: (string)(getenv('ATHAR_MAIL_SMTP_PASSWORD') ?: '');
        if ($host === '' || preg_match('/[\r\n\s]/', $host) || $port < 1 || $port > 65535) throw new RuntimeException('أدخل مضيف SMTP ومنفذًا صالحًا.');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) throw new RuntimeException('نوع تشفير SMTP غير صالح.');
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $error, 15, STREAM_CLIENT_CONNECT);
        if (!$socket) throw new RuntimeException('تعذر الاتصال بخادم SMTP: ' . ($error ?: 'تحقق من المضيف والمنفذ أو قيود الاستضافة.'));
        stream_set_timeout($socket, 15);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO athar.local', [250]);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('تعذر تفعيل تشفير TLS لخادم البريد.');
                self::command($socket, 'EHLO athar.local', [250]);
            }
            if ($username !== '') {
                if ($password === '') throw new RuntimeException('أدخل كلمة مرور SMTP أو امسحها من إعدادات البريد.');
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode($password), [235]);
            }
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            $headers = [
                'From: ' . self::encodedName($fromName) . ' <' . $from . '>',
                'To: <' . $recipient . '>',
                'Subject: ' . self::encodedHeader($subject),
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url('https://' . $host, PHP_URL_HOST) ?: 'athar.local') . '>',
                'MIME-Version: 1.0',
            ];
            if ($htmlBody !== null && trim($htmlBody) !== '') {
                $boundary = '=_athar_' . bin2hex(random_bytes(12));
                $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';
                $message = implode("\r\n", $headers) . "\r\n\r\n" . self::multipartBody($boundary, $body, $htmlBody);
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: base64';
                $body = str_replace(["\r\n", "\r"], "\n", $body);
                $encodedBody = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
                $message = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
            }
            $wireMessage = str_replace(["\r\n", "\r"], "\n", $message);
            fwrite($socket, str_replace("\n", "\r\n", $wireMessage) . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
            return true;
        } finally {
            fclose($socket);
        }
    }

    private static function smtpWithPhpMailer(array $s,string $recipient,string $from,string $fromName,string $subject,string $body,?string $htmlBody): bool
    {
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true); $mail->CharSet='UTF-8'; $mail->isSMTP();
        $host=trim((string)($s['mail_smtp_host']??'')) ?: trim((string)(getenv('ATHAR_MAIL_SMTP_HOST')?:''));
        $port=(int)(($s['mail_smtp_port']??'') ?: (getenv('ATHAR_MAIL_SMTP_PORT')?:587));
        $username=trim((string)($s['mail_smtp_username']??'')) ?: trim((string)(getenv('ATHAR_MAIL_SMTP_USERNAME')?:''));
        $password=(string)($s['mail_smtp_password']??'') ?: (string)(getenv('ATHAR_MAIL_SMTP_PASSWORD')?:'');
        $mail->Host=$host; $mail->Port=$port; $mail->SMTPAuth=$username!==''; $mail->Username=$username; $mail->Password=$password;
        $enc=(string)(($s['mail_smtp_encryption']??'') ?: (getenv('ATHAR_MAIL_SMTP_ENCRYPTION')?:'tls')); if($enc==='tls') $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; elseif($enc==='ssl') $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->setFrom($from,$fromName); $mail->addAddress($recipient); $mail->Subject=$subject; $mail->msgHTML($htmlBody?:nl2br(htmlspecialchars($body,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))); $mail->AltBody=$body;
        if(!$mail->send()) throw new RuntimeException('رفض PHPMailer إرسال الرسالة.'); return true;
    }

    private static function command($socket, string $command, array $codes): string
    {
        if (str_contains($command, "\r") || str_contains($command, "\n")) throw new RuntimeException('أمر بريد غير صالح.');
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $codes);
    }

    private static function expect($socket, array $codes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) throw new RuntimeException('انقطع الاتصال بخادم البريد.');
            $response .= $line;
            $code = (int)substr($line, 0, 3);
            $more = isset($line[3]) && $line[3] === '-';
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) throw new RuntimeException('انتهت مهلة الاتصال بخادم البريد.');
        } while ($more);
        if (!in_array($code, $codes, true)) throw new RuntimeException('رفض خادم البريد الطلب (' . $code . '). راجع إعدادات SMTP وبيانات الدخول.');
        return $response;
    }

    private static function encodedHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $value)) . '?=';
    }

    private static function encodedName(string $value): string
    {
        return self::encodedHeader($value);
    }

    private static function multipartBody(string $boundary, string $text, string $html): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            .rtrim(chunk_split(base64_encode($text),76,"\r\n"))."\r\n"
            .'--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            .rtrim(chunk_split(base64_encode($html),76,"\r\n"))."\r\n"
            .'--'.$boundary."--\r\n";
    }
}
