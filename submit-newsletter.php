<?php
session_start();

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$allowedHost = 'sandiegodoorandwindow.com';
$recipient   = 'haejean@discountdw.com';
// $recipient   = 'haejean@yopmail.com';
$rateLimit   = 5;          // Max requests
$timeWindow  = 300;        // 5 minutes

/*
|--------------------------------------------------------------------------
| Allow POST only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/*
|--------------------------------------------------------------------------
| Validate Origin / Referer
|--------------------------------------------------------------------------
*/

$validSource = false;

foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
    if (!empty($_SERVER[$header])) {
        $host = parse_url($_SERVER[$header], PHP_URL_HOST);

        if (
            $host === $allowedHost ||
            preg_match('/(^|\.)sandiegodoorandwindow\.com$/i', $host)
        ) {
            $validSource = true;
            break;
        }
    }
}

if (!$validSource) {
    http_response_code(403);
    exit('Invalid request source.');
}

/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Invalid email address.');
}

/*
|--------------------------------------------------------------------------
| Sanitize Subject
|--------------------------------------------------------------------------
*/

$userSubject = trim($_POST['subject'] ?? '');
$userSubject = preg_replace('/[\r\n]+/', ' ', $userSubject);

$subject = "Newsletter Opt-In Request from Sandiegodoorandwindow";

if (!empty($userSubject)) {
    $subject .= " - " . substr($userSubject, 0, 100);
}

/*
|--------------------------------------------------------------------------
| Rate Limiting (IP)
|--------------------------------------------------------------------------
*/

$ip = $_SERVER['REMOTE_ADDR'];

$storage = __DIR__ . '/ratelimit';

if (!is_dir($storage)) {
    mkdir($storage, 0755, true);
}

$file = $storage . '/' . md5($ip) . '.json';

$data = [
    'count' => 0,
    'time'  => time()
];

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);

    if (time() - $data['time'] > $timeWindow) {
        $data = [
            'count' => 0,
            'time' => time()
        ];
    }
}

if ($data['count'] >= $rateLimit) {
    http_response_code(429);
    exit('Too many requests. Please try again later.');
}

$data['count']++;

file_put_contents($file, json_encode($data));

/*
|--------------------------------------------------------------------------
| Build Message
|--------------------------------------------------------------------------
*/

$message = "New Newsletter Signup\n\n";
$message .= "Email: {$email}\n";
$message .= "IP: {$ip}\n";
$message .= "Time: " . date('Y-m-d H:i:s') . "\n";
$message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
$message .= "Page: " . ($_SERVER['HTTP_REFERER'] ?? '') . "\n";

/*
|--------------------------------------------------------------------------
| Headers
|--------------------------------------------------------------------------
*/

$headers = [
    "From: Website <noreply@sandiegodoorandwindow.com>",
    "Reply-To: {$email}",
    "Content-Type: text/plain; charset=UTF-8"
];

/*
|--------------------------------------------------------------------------
| Send Mail
|--------------------------------------------------------------------------
*/

if (mail(
    $recipient,
    $subject,
    $message,
    implode("\r\n", $headers)
)) {
    echo "Thank you for signing up for our newsletter.";
} else {
    http_response_code(500);
    echo "Unable to process your request.";
}