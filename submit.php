<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) { $input = $_POST ?: []; }

$name    = trim((string)($input['name']    ?? ''));
$phone   = trim((string)($input['phone']   ?? ''));
$subject = trim((string)($input['subject'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$honey   = trim((string)($input['website'] ?? ''));

// Honeypot — bots fill hidden field
if ($honey !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_required']);
    exit;
}

if (mb_strlen($name) > 120 || mb_strlen($phone) > 40 || mb_strlen($subject) > 200 || mb_strlen($message) > 4000) {
    http_response_code(400);
    echo json_encode(['error' => 'too_long']);
    exit;
}

$to = 'Zahra.dabbah@gmail.com';

$displaySubject = 'פנייה חדשה מהאתר — ' . $name;
$encodedSubject = '=?UTF-8?B?' . base64_encode($displaySubject) . '?=';

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$ts = date('Y-m-d H:i:s');

$body  = "פנייה חדשה התקבלה דרך אתר זהרה אסדי\r\n";
$body .= "----------------------------------------\r\n\r\n";
$body .= "שם: $name\r\n";
$body .= "טלפון: $phone\r\n";
$body .= "נושא: " . ($subject !== '' ? $subject : '(לא מולא)') . "\r\n\r\n";
$body .= "הודעה:\r\n" . ($message !== '' ? $message : '(לא מולא)') . "\r\n\r\n";
$body .= "----------------------------------------\r\n";
$body .= "זמן: $ts\r\n";
$body .= "IP: $ip\r\n";

$host = $_SERVER['HTTP_HOST'] ?? 'zahraassadi.co.il';
$fromAddr = 'noreply@' . preg_replace('/^www\./', '', $host);

$headers  = "From: =?UTF-8?B?" . base64_encode('אתר זהרה אסדי') . "?= <$fromAddr>\r\n";
$headers .= "Reply-To: $fromAddr\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "X-Mailer: zahraassadi-form\r\n";

$ok = @mail($to, $encodedSubject, $body, $headers, "-f$fromAddr");

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'mail_failed']);
}
