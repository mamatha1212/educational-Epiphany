<?php
/**
 * EEMT 2027 - Mail Notification Service
 * Sends registration confirmation with unique Reference ID.
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
    } elseif (defined('APP_URL') && constant('APP_URL')) {
        $baseUrl = constant('APP_URL');
    }

    $loginUrl = $baseUrl . '/school_login.php';
    $qpUrl = $baseUrl . '/prelims_qp.php?udise=' . urlencode($data['unique_code'] ?? '') . '&eemtsrc=' . urlencode($data['reference_id'] ?? '');

    $subject = "Official Registration Slip & Logins - EEMT 2027 (Ref: $refId)";

    $htmlBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>EEMT 2027 Registration Confirmation Slip</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px 12px; color: #1e293b; }
        .slip-card { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06); }
        .slip-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #ffffff; padding: 28px 24px; text-align: center; }
        .slip-header h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
        .slip-header p { margin: 6px 0 0; font-size: 13px; color: #bfdbfe; font-weight: 500; }
        .slip-content { padding: 28px 24px; }
        .greeting { font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 20px; }
        
        /* Official Code Highlight Card */
        .code-box { background: #eff6ff; border: 2px dashed #3b82f6; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
        .code-label { font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; letter-spacing: 0.08em; }
        .code-val { font-size: 28px; font-weight: 900; color: #1e3a8a; letter-spacing: 0.1em; margin: 6px 0; font-family: monospace; }
        .code-hint { font-size: 12px; color: #475569; margin: 0; }

        /* Login Portal Box */
        .login-box { background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 20px; margin: 22px 0; }
        .login-header { font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
        .cred-item { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .cred-label { font-weight: 600; color: #64748b; }
        .cred-val { font-family: monospace; font-weight: 800; color: #0f172a; font-size: 14px; }

        /* Action Buttons */
        .btn-wrapper { text-align: center; margin: 24px 0 16px; }
        .btn-primary { background: #2563eb; color: #ffffff !important; padding: 12px 26px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(37,99,235,0.25); }
        .btn-secondary { background: #059669; color: #ffffff !important; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; margin-left: 8px; }

        /* Detail Table */
        .table-title { font-size: 14px; font-weight: 800; color: #0f172a; margin: 24px 0 10px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 6px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table tr { border-bottom: 1px solid #f1f5f9; }
        .info-table td { padding: 9px 8px; font-size: 13px; }
        .info-table td.label { font-weight: 600; color: #64748b; width: 38%; }
        .info-table td.val { font-weight: 700; color: #0f172a; }

        .notice-box { background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #854d0e; line-height: 1.5; margin-top: 20px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; line-height: 1.6; }
      </style>
    </head>
    <body>
      <div class='slip-card'>
        
        <!-- Header -->
        <div class='slip-header'>
          <h1>Educational Epiphany Merit Test 2027</h1>
          <p>Official School Prelims Registration Confirmation Slip</p>
        </div>

        <div class='slip-content'>
          <p class='greeting'>
            Dear <strong>$contactName</strong> ($designation),<br>
            Congratulations! Your school <strong>$schoolName</strong> has been successfully registered for <strong>EEMT 2027 Prelims</strong>.
          </p>

          <!-- EEMTSR Code Callout -->
          <div class='code-box'>
            <div class='code-label'>Official EEMT School Registration Code (EEMTSRC)</div>
            <div class='code-val'>$refId</div>
            <p class='code-hint'>Save this code carefully &mdash; it is required along with your UDISE code for all school portal operations.</p>
          </div>

          <!-- School Dashboard & QP Login Credentials Box -->
          <div class='login-box'>
            <div class='login-header'>
              🔐 Official School Login & Prelims Credentials
            </div>
            
            <div class='cred-item'>
              <span class='cred-label'>1. School UDISE Code:</span>
              <span class='cred-val'>$uniqueCode</span>
            </div>

            <div class='cred-item'>
              <span class='cred-label'>2. EEMTSRC (Registration Code):</span>
              <span class='cred-val'>$refId</span>
            </div>

            <div class='cred-item'>
              <span class='cred-label'>Official School Dashboard URL:</span>
              <span class='cred-val'><a href='$loginUrl' style='color:#2563eb; text-decoration:none;'>$loginUrl</a></span>
            </div>

            <div class='btn-wrapper'>
              <a href='$loginUrl' class='btn-primary'>Access School Dashboard &rarr;</a>
              <a href='$qpUrl' class='btn-secondary'>Direct Prelims QP Download &rarr;</a>
            </div>
          </div>

          <!-- School Information Table -->
          <div class='table-title'>School Registration Details</div>
          <table class='info-table'>
            <tr>
              <td class='label'>School Name:</td>
              <td class='val'>$schoolName</td>
            </tr>
            <tr>
              <td class='label'>District:</td>
              <td class='val'>$district</td>
            </tr>
            <tr>
              <td class='label'>Mandal:</td>
              <td class='val'>$mandal</td>
            </tr>
            <tr>
              <td class='label'>School UDISE Code:</td>
              <td class='val'><code style='background:#f1f5f9; padding:2px 6px; border-radius:4px;'>$uniqueCode</code></td>
            </tr>
            <tr>
              <td class='label'>EEMT SR Code (EEMTSRC):</td>
              <td class='val' style='color:#1e40af;'><strong>$refId</strong></td>
            </tr>
            <tr>
              <td class='label'>In-Charge Name:</td>
              <td class='val'>$contactName ($designation)</td>
            </tr>
            <tr>
              <td class='label'>Contact Mobile:</td>
              <td class='val'>$mobile</td>
            </tr>
            <tr>
              <td class='label'>Registered Email:</td>
              <td class='val'>$toEmail</td>
            </tr>
            <tr>
              <td class='label'>Registration Date:</td>
              <td class='val'>$regDate</td>
            </tr>
            <tr>
              <td class='label'>Status:</td>
              <td class='val' style='color:#16a34a;'>&#10003; CONFIRMED & ACTIVE</td>
            </tr>
          </table>

          <div class='notice-box'>
            <strong>Important Note for Teachers:</strong> Please print or preserve this email slip for your school records. When accessing and downloading EEMT 2027 Prelims Question Papers, you will be prompted to enter your 11-digit UDISE Code and your EEMTSRC code (<code>$refId</code>).
          </div>
        </div>

        <div class='footer'>
          &copy; " . date('Y') . " Educational Epiphany Merit Test (EEMT 2027). All rights reserved.<br>
          For queries or assistance, contact: support@educationalepiphany.org
        </div>

      </div>
    </body>
    </html>
    ";

    // Standard HTML MIME Headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: EEMT 2027 <no-reply@educationalepiphany.org>\r\n";
    $headers .= "Reply-To: support@educationalepiphany.org\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // 1. Save full rendered HTML copy to uploads/sent_emails/ for verification
    $archiveDir = dirname(__DIR__) . '/uploads/sent_emails';
    if (!is_dir($archiveDir)) {
        @mkdir($archiveDir, 0777, true);
    }
    $safeFileName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $refId . '_' . ($data['unique_code'] ?? 'school'));
    @file_put_contents($archiveDir . '/' . $safeFileName . '.html', $htmlBody);

    // 2. Log metadata
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

    // 3. Dispatch via PHP mail()
    $sent = @mail($toEmail, $subject, $htmlBody, $headers);

    return true;
}
