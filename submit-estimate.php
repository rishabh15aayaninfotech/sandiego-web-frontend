<?php

session_start();

header('Content-Type: application/json');

$recipient = "haejean@discountdw.com";
// $recipient = "haejean@yopmail.com";
$allowedHost = "sandiegodoorandwindow.com";

/*
|--------------------------------------------------------------------------
| POST only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        "message"=>"Method Not Allowed"
    ]));
}

/*
|--------------------------------------------------------------------------
| Ajax only
|--------------------------------------------------------------------------
*/

if (
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
){
    http_response_code(403);
    exit(json_encode([
        "message"=>"Invalid Request"
    ]));
}

/*
|--------------------------------------------------------------------------
| Verify Domain
|--------------------------------------------------------------------------
*/

$valid = false;

foreach(['HTTP_ORIGIN','HTTP_REFERER'] as $header){

    if(!empty($_SERVER[$header])){

        $host=parse_url($_SERVER[$header],PHP_URL_HOST);

        if(
            $host==$allowedHost ||
            preg_match('/(^|\.)sandiegodoorandwindow\.com$/i',$host)
        ){
            $valid=true;
            break;
        }

    }

}

if(!$valid){

    http_response_code(403);

    exit(json_encode([
        "message"=>"Invalid Source"
    ]));

}

/*
|--------------------------------------------------------------------------
| Honeypot
|--------------------------------------------------------------------------
*/

if(!empty($_POST['website'])){

    http_response_code(403);

    exit(json_encode([
        "message"=>"Spam detected."
    ]));

}

/*
|--------------------------------------------------------------------------
| Sanitize
|--------------------------------------------------------------------------
*/

$name=trim($_POST['name'] ?? '');
$email=trim($_POST['email'] ?? '');
$subject=trim($_POST['subject'] ?? '');
$message=trim($_POST['message'] ?? '');

if(strlen($name)<2){

    http_response_code(400);

    exit(json_encode([
        "message"=>"Enter your name."
    ]));

}

if(!filter_var($email,FILTER_VALIDATE_EMAIL)){

    http_response_code(400);

    exit(json_encode([
        "message"=>"Invalid email."
    ]));

}

if(strlen($message)<10){

    http_response_code(400);

    exit(json_encode([
        "message"=>"Message is too short."
    ]));

}

/*
|--------------------------------------------------------------------------
| Remove header injection
|--------------------------------------------------------------------------
*/

$name=preg_replace("/[\r\n]+/"," ",$name);
$subject=preg_replace("/[\r\n]+/"," ",$subject);

if(empty($subject)){
    $subject="Website Inquiry";
}

/*
|--------------------------------------------------------------------------
| Rate Limit
|--------------------------------------------------------------------------
*/

$ip=$_SERVER['REMOTE_ADDR'];

$dir=__DIR__.'/ratelimit';

if(!is_dir($dir)){
    mkdir($dir,0755,true);
}

$file=$dir.'/'.md5($ip);

$data=[
    "count"=>0,
    "time"=>time()
];

if(file_exists($file)){

    $data=json_decode(file_get_contents($file),true);

    if(time()-$data['time']>300){

        $data=[
            "count"=>0,
            "time"=>time()
        ];

    }

}

if($data['count']>=5){

    http_response_code(429);

    exit(json_encode([
        "message"=>"Too many requests. Please wait."
    ]));

}

$data['count']++;

file_put_contents($file,json_encode($data));

/*
|--------------------------------------------------------------------------
| Mail
|--------------------------------------------------------------------------
*/

$mailSubject="LEADS: sandiegodoorandwindow.com FREE ESTIMATE INQUIRY";

$mailBody="

Name: {$name}

Email: {$email}

Subject: {$subject}

Message:

{$message}

---------------------------------------

IP:
{$ip}

User Agent:
".($_SERVER['HTTP_USER_AGENT'] ?? '')."

Page:
".($_SERVER['HTTP_REFERER'] ?? '');

$headers=[];

$headers[]="From: Website <noreply@sandiegodoorandwindow.com>";
$headers[]="Reply-To: {$email}";
$headers[]="Content-Type: text/plain; charset=UTF-8";

if(mail(
    $recipient,
    $mailSubject,
    $mailBody,
    implode("\r\n",$headers)
)){

    echo json_encode([
        "success"=>true,
        "message"=>"Thank you for contacting us! Your message has been received. We will get back to you shortly."
    ]);

}else{

    http_response_code(500);

    echo json_encode([
        "message"=>"Unable to send your request."
    ]);

}