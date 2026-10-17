<?php
/**
 * EEMT 2027 - Mail Notification Service
 * Sends registration confirmation with unique Reference ID & login credentials,
 * designed identically to the official registration slip.
 */

function sendRegistrationEmail(string $toEmail, array $data): bool {
    $refId       = htmlspecialchars($data['reference_id'] ?? '');
    $schoolName  = htmlspecialchars($data['school_name'] ?? '');
    $contactName = htmlspecialchars($data['contact_person'] ?? '');
    $designation = htmlspecialchars($data['designation'] ?? '');
    $district    = htmlspecialchars($data['district'] ?? '');
    $mandal      = htmlspecialchars($data['mandal'] ?? '');
    $uniqueCode  = htmlspecialchars($data['unique_code'] ?? '');
    $mobile      = htmlspecialchars($data['mobile'] ?? '');
    $regDate     = htmlspecialchars($data['created_at'] ?? date('d M Y, h:i A'));

    // Base URL resolution
    $baseUrl = 'http://localhost:8000';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
    } elseif (function_exists('env') && env('APP_URL')) {
        $baseUrl = rtrim(env('APP_URL'), '/');
    } elseif (defined('APP_URL') && constant('APP_URL')) {
        $baseUrl = rtrim(constant('APP_URL'), '/');
    }

    $loginUrl = $baseUrl . '/school_login.php';
    $qpUrl = $baseUrl . '/prelims_qp.php?udise=' . urlencode($data['unique_code'] ?? '') . '&eemtsrc=' . urlencode($data['reference_id'] ?? '');
    $logoUrl = $baseUrl . '/assets/images/logo.png';

    $subject = "Official Registration Slip & Logins - EEMT 2027 (Ref: $refId)";

    $fromEmail = (function_exists('env') && env('MAIL_FROM_EMAIL')) ? env('MAIL_FROM_EMAIL') : 'no-reply@educationalepiphany.org';
    $fromName  = (function_exists('env') && env('MAIL_FROM_NAME')) ? env('MAIL_FROM_NAME') : 'EEMT 2027';
    $replyTo   = (function_exists('env') && env('MAIL_REPLY_TO')) ? env('MAIL_REPLY_TO') : 'support@educationalepiphany.org';

    $htmlBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>EEMT 2027 Registration Confirmation Slip</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px 12px; color: #1e293b; }
        .slip-card { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06); }
        .slip-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #ffffff; padding: 28px 24px; text-align: center; }
        .slip-header h1 { margin: 10px 0 0; font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
        .slip-header p { margin: 6px 0 0; font-size: 13px; color: #bfdbfe; font-weight: 500; }
        .slip-content { padding: 28px 24px; }
        .alert-success-box { background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #065f46; margin-bottom: 22px; line-height: 1.5; }
        .code-box { background: #eff6ff; border: 2px dashed #3b82f6; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
        .code-label { font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; letter-spacing: 0.08em; }
        .code-val { font-size: 30px; font-weight: 900; color: #1e3a8a; letter-spacing: 0.1em; margin: 8px 0; font-family: monospace; }
        .code-hint { font-size: 12px; color: #475569; margin: 0; }
        .login-box { background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 20px; margin: 22px 0; }
        .login-header { font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
        .cred-item { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .cred-label { font-weight: 600; color: #64748b; }
        .cred-val { font-family: monospace; font-weight: 800; color: #0f172a; font-size: 14px; }
        .btn-wrapper { text-align: center; margin: 22px 0 10px; }
        .btn-primary { background: #2563eb; color: #ffffff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; box-shadow: 0 4px 12px rgba(37,99,235,0.25); }
        .btn-secondary { background: #059669; color: #ffffff !important; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; margin-left: 8px; }
        .table-title { font-size: 14px; font-weight: 800; color: #0f172a; margin: 26px 0 10px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 6px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table tr { border-bottom: 1px solid #f1f5f9; }
        .info-table td { padding: 10px 8px; font-size: 13px; }
        .info-table td.label { font-weight: 600; color: #64748b; width: 38%; }
        .info-table td.val { font-weight: 700; color: #0f172a; }
        .notice-box { background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 14px 16px; font-size: 12px; color: #854d0e; line-height: 1.5; margin-top: 22px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; line-height: 1.6; }
      </style>
    </head>
    <body style='margin:0; padding:24px 12px; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'>
      <div class='slip-card' style='max-width:650px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 10px 25px -5px rgba(0,0,0,0.06);'>
        
        <!-- Slip Header -->
        <div class='slip-header' style='background:linear-gradient(135deg, #1e3a8a, #2563eb); color:#ffffff; padding:28px 24px; text-align:center;'>
          <div style='margin-bottom:8px;'>
            <div style='display:inline-block; width:68px; height:68px; background:#ffffff; border-radius:14px; padding:5px; box-shadow:0 4px 12px rgba(0,0,0,0.15); text-align:center;'>
              <img src='$logoUrl' alt='EEMT 2027 Logo' width='58' height='58' style='display:block; margin:0 auto; max-width:100%; height:auto;' />
            </div>
          </div>
          <h1 style='margin:8px 0 0; font-size:22px; font-weight:800; letter-spacing:-0.02em; color:#ffffff;'>Educational Epiphany Merit Test 2027</h1>
          <p style='margin:6px 0 0; font-size:13px; color:#bfdbfe; font-weight:500;'>Official School Prelims Registration Confirmation Slip</p>
        </div>

        <div class='slip-content' style='padding:28px 24px;'>
          
          <!-- Success Alert -->
          <div class='alert-success-box' style='background-color:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:14px 16px; font-size:13px; color:#065f46; margin-bottom:22px; line-height:1.5;'>
            &#10003; <strong>Registration submitted successfully!</strong> Your school <strong>$schoolName</strong> is officially registered. Keep this confirmation slip and credentials safe for all school operations.
          </div>

          <p style='font-size:14px; line-height:1.6; color:#334155; margin:0 0 18px;'>
            Dear <strong>$contactName</strong> ($designation),<br>
            Thank you for enrolling your school in <strong>EEMT 2027 Prelims</strong>. Below are your official registration details and login credentials.
          </p>

          <!-- Official Code Box -->
          <div class='code-box' style='background:#eff6ff; border:2px dashed #3b82f6; border-radius:12px; padding:20px; text-align:center; margin:20px 0;'>
            <div class='code-label' style='font-size:11px; font-weight:800; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.08em;'>EEMT School Registration Code (EEMTSRC / EEMT SR Code)</div>
            <div class='code-val' style='font-size:32px; font-weight:900; color:#1e3a8a; letter-spacing:0.1em; margin:8px 0; font-family:monospace;'>$refId</div>
            <p class='code-hint' style='font-size:12px; color:#475569; margin:0;'>Official EEMT SR Code Series: <code>26275000001, 26275000002...</code></p>
          </div>

          <!-- Official School Login & Prelims Credentials Box -->
          <div class='login-box' style='background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:12px; padding:20px; margin:22px 0;'>
            <div class='login-header' style='font-size:14px; font-weight:800; color:#0f172a; margin-bottom:12px;'>
              &#128274; Mandatory Credentials For School Logins & Prelims Question Papers
            </div>
            <p style='font-size:12.5px; color:#475569; margin:0 0 14px; line-height:1.5;'>
              When accessing the School Dashboard or downloading EEMT 2027 Prelims Question Papers, log in with:
            </p>
            
            <table style='width:100%; border-collapse:collapse; margin-bottom:14px;'>
              <tr>
                <td style='padding:8px 12px; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px 0 0 8px; font-weight:600; color:#64748b; font-size:13px;'>1. School UDISE Code (Username):</td>
                <td style='padding:8px 12px; background:#ffffff; border:1px solid #e2e8f0; border-radius:0 8px 8px 0; font-family:monospace; font-weight:800; color:#0f172a; font-size:14px; text-align:right;'>$uniqueCode</td>
              </tr>
              <tr><td style='height:8px;'></td></tr>
              <tr>
                <td style='padding:8px 12px; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px 0 0 8px; font-weight:600; color:#64748b; font-size:13px;'>2. EEMTSRC (Registration Code / Password):</td>
                <td style='padding:8px 12px; background:#ffffff; border:1px solid #e2e8f0; border-radius:0 8px 8px 0; font-family:monospace; font-weight:800; color:#1e40af; font-size:14px; text-align:right;'>$refId</td>
              </tr>
            </table>

            <div class='btn-wrapper' style='text-align:center; margin:20px 0 10px;'>
              <a href='$loginUrl' class='btn-primary' style='background:#2563eb; color:#ffffff !important; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13px; display:inline-block; margin-right:6px; margin-bottom:8px;'>Access School Dashboard &rarr;</a>
              <a href='$qpUrl' class='btn-secondary' style='background:#059669; color:#ffffff !important; padding:12px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13px; display:inline-block; margin-bottom:8px;'>Access Prelims QP Portal &rarr;</a>
            </div>
          </div>

          <!-- School Information Table -->
          <div class='table-title' style='font-size:14px; font-weight:800; color:#0f172a; margin:26px 0 10px; border-bottom:1.5px solid #e2e8f0; padding-bottom:6px;'>
            School Registration Details
          </div>
          <table class='info-table' style='width:100%; border-collapse:collapse; margin-bottom:20px;'>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; width:38%; border-bottom:1px solid #f1f5f9;'>School Name:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$schoolName</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>District:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$district</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>Mandal:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$mandal</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>School UDISE Code:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'><span style='background:#f1f5f9; padding:3px 8px; border-radius:4px; font-family:monospace; border:1px solid #cbd5e1;'>$uniqueCode</span></td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>EEMT SR Code (EEMTSRC):</td>
              <td class='val' style='padding:10px 8px; font-size:14px; font-weight:800; color:#1e40af; border-bottom:1px solid #f1f5f9;'>$refId</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>In-Charge Name:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$contactName ($designation)</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>Contact Mobile:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$mobile</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>Registered Email:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$toEmail</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b; border-bottom:1px solid #f1f5f9;'>Registration Date:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:700; color:#0f172a; border-bottom:1px solid #f1f5f9;'>$regDate</td>
            </tr>
            <tr>
              <td class='label' style='padding:10px 8px; font-size:13px; font-weight:600; color:#64748b;'>Status:</td>
              <td class='val' style='padding:10px 8px; font-size:13px; font-weight:800; color:#16a34a;'>&#10003; CONFIRMED & ACTIVE</td>
            </tr>
          </table>

          <div class='notice-box' style='background:#fefce8; border:1px solid #fef08a; border-radius:8px; padding:14px 16px; font-size:12px; color:#854d0e; line-height:1.5; margin-top:22px;'>
            <strong>Important Note for Teachers:</strong> Please print or preserve this email slip for your school records. When accessing and downloading EEMT 2027 Prelims Question Papers, you will be prompted to enter your 11-digit UDISE Code (<code>$uniqueCode</code>) and your EEMTSRC code (<code>$refId</code>).
          </div>
        </div>

        <div class='footer' style='background:#f8fafc; padding:20px; text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #e2e8f0; line-height:1.6;'>
          &copy; " . date('Y') . " Educational Epiphany Merit Test (EEMT 2027). All rights reserved.<br>
          Andhra Pradesh Government Schools Prelims Program &bull; Official Portal: <a href='$baseUrl' style='color:#64748b;'>$baseUrl</a><br>
          For queries or assistance, contact: <a href='mailto:support@educationalepiphany.org' style='color:#64748b;'>support@educationalepiphany.org</a>
        </div>

      </div>
    </body>
    </html>
    ";

    // 1. Save full rendered HTML copy to uploads/sent_emails/ for verification and instant viewing
    $archiveDir = dirname(__DIR__) . '/uploads/sent_emails';
    if (!is_dir($archiveDir)) {
        @mkdir($archiveDir, 0777, true);
    }
    $safeFileName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $refId . '_' . ($data['unique_code'] ?? 'school'));
    @file_put_contents($archiveDir . '/' . $safeFileName . '.html', $htmlBody);

    // 2. Log metadata to uploads/mail_logs.txt
    $logDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf(
        "[%s] Reference ID: %s | UDISE: %s | To: %s | School: %s | File: uploads/sent_emails/%s.html\n",
        date('Y-m-d H:i:s'),
        $refId,
        $data['unique_code'] ?? '',
        $toEmail,
        $data['school_name'] ?? '',
        $safeFileName
    );
    @file_put_contents($logDir . '/mail_logs.txt', $logEntry, FILE_APPEND);

    // 3. Dispatch via SMTP if configured in .env, otherwise via PHP mail()
    $smtpHost = (function_exists('env') && env('SMTP_HOST')) ? env('SMTP_HOST') : '';
    if (!empty($smtpHost)) {
        $smtpPort   = (function_exists('env') && env('SMTP_PORT')) ? (int)env('SMTP_PORT') : 587;
        $smtpUser   = (function_exists('env') && env('SMTP_USER')) ? env('SMTP_USER') : '';
        $smtpPass   = (function_exists('env') && env('SMTP_PASS')) ? env('SMTP_PASS') : '';
        $smtpSecure = (function_exists('env') && env('SMTP_SECURE')) ? env('SMTP_SECURE') : 'tls';

        return sendViaSocketSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpSecure, $fromEmail, $fromName, $toEmail, $subject, $htmlBody, $replyTo);
    }

    // Standard HTML MIME Headers for PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    @mail($toEmail, $subject, $htmlBody, $headers);

    return true;
}

/**
 * Lightweight socket-based SMTP client (no external dependencies required)
 */
function sendViaSocketSmtp(string $host, int $port, string $user, string $pass, string $secure, string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, string $replyTo): bool {
    $remoteHost = ($secure === 'ssl') ? "ssl://{$host}" : $host;
    $socket = @fsockopen($remoteHost, $port, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }

    $read = function() use ($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $response;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send("EHLO " . gethostname());

    if ($secure === 'tls') {
        $send("STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send("EHLO " . gethostname());
    }

    if (!empty($user) && !empty($pass)) {
        $send("AUTH LOGIN");
        $send(base64_encode($user));
        $send(base64_encode($pass));
    }

    $send("MAIL FROM:<{$fromEmail}>");
    $send("RCPT TO:<{$toEmail}>");
    $send("DATA");

    $mime = "MIME-Version: 1.0\r\n";
    $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mime .= "From: {$fromName} <{$fromEmail}>\r\n";
    $mime .= "To: <{$toEmail}>\r\n";
    $mime .= "Reply-To: {$replyTo}\r\n";
    $mime .= "Subject: {$subject}\r\n";
    $mime .= "Date: " . date('r') . "\r\n\r\n";
    $mime .= $htmlBody . "\r\n.\r\n";

    fputs($socket, $mime);
    $read();
    $send("QUIT");
    fclose($socket);

    return true;
}
