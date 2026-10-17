<?php
/**
 * EEMT 2027 - Mail Notification Service
 * Sends registration confirmation with unique Reference ID.
 */

function sendRegistrationEmail(string $toEmail, array $data): bool {
    $refId       = $data['reference_id'] ?? '';
    $schoolName  = $data['school_name'] ?? '';
    $contactName = $data['contact_person'] ?? '';
    $designation = $data['designation'] ?? '';
    $district    = $data['district'] ?? '';
    $mandal      = $data['mandal'] ?? '';
    $uniqueCode  = $data['unique_code'] ?? '';

    $subject = "EEMT 2027 Registration Confirmation - Ref ID: $refId";

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #1e40af; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 30px 24px; color: #374151; line-height: 1.6; }
        .ref-box { background: #eff6ff; border: 2px dashed #3b82f6; border-radius: 8px; padding: 18px; text-align: center; margin: 20px 0; }
        .ref-label { font-size: 13px; color: #1e40af; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .ref-id { font-size: 26px; font-weight: 800; color: #1e3a8a; letter-spacing: 0.1em; margin-top: 6px; }
        .info-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .info-table td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .info-table td.label { font-weight: 600; color: #6b7280; width: 40%; }
        .footer { background: #f9fafb; padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>
          <h1>Educational Epiphany Merit Test 2027</h1>
          <p style='margin:4px 0 0; font-size:14px; opacity:0.9;'>Government Schools Prelims Registration</p>
        </div>
        <div class='content'>
          <p>Dear <strong>$contactName ($designation)</strong>,</p>
          <p>Your school registration for <strong>EEMT 2027</strong> has been successfully submitted and recorded in the state merit test registry.</p>
          
          <div class='ref-box'>
            <div class='ref-label'>EEMT School Registration Code (EEMTSRC)</div>
            <div class='ref-id'>$refId</div>
            <p style='margin: 8px 0 0; font-size: 13px; color: #1e3a8a;'><strong>Validation check is mandatory for Prelims QP.</strong><br>Teacher needs to enter:<br>1. <strong>UDISE Code:</strong> $uniqueCode<br>2. <strong>EEMTSRC Code:</strong> $refId (Format: 26275XXXXXX)</p>
          </div>

          <table class='info-table'>
            <tr><td class='label'>School Name:</td><td><strong>$schoolName</strong></td></tr>
            <tr><td class='label'>District:</td><td>$district</td></tr>
            <tr><td class='label'>Mandal:</td><td>$mandal</td></tr>
            <tr><td class='label'>School UDISE Code:</td><td><code>$uniqueCode</code></td></tr>
            <tr><td class='label'>EEMT SR Code (EEMTSRC):</td><td><strong>$refId</strong></td></tr>
            <tr><td class='label'>Contact Person:</td><td>$contactName ($designation)</td></tr>
          </table>

          <p style='margin-top: 25px;'>You can access the Prelims QP Portal or School Dashboard at any time to validate credentials and download examination question papers.</p>
        </div>
        <div class='footer'>
          &copy; " . date('Y') . " Educational Epiphany Merit Test (EEMT). All rights reserved.
        </div>
      </div>
    </body>
    </html>
    ";

    // Headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: EEMT 2027 <no-reply@educationalepiphany.org>\r\n";
    $headers .= "Reply-To: support@educationalepiphany.org\r\n";

    // 1. Log to file for local tracking
    $logDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf(
        "[%s] Reference ID: %s | To: %s | School: %s\n",
        date('Y-m-d H:i:s'),
        $refId,
        $toEmail,
        $schoolName
    );
    file_put_contents($logDir . '/mail_logs.txt', $logEntry, FILE_APPEND);

    // 2. Try standard PHP mail() if server configured and not local Windows without SMTP
    $isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    if (!$isWin || ini_get('sendmail_path') || ini_get('SMTP') !== 'localhost') {
        @mail($toEmail, $subject, $htmlBody, $headers);
    }

    return true;
}
