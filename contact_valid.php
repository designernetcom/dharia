<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
*/

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// =====================================================
// GET CENTRAL MAIL CONFIG
// =====================================================

$apiUrl = 'https://netcomindia.in/contact-form/mail-api.php';
$apiKey = 'netcom@123';

$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$apiResponse = curl_exec($ch);

if ($apiResponse === false) {
    curl_close($ch);

    http_response_code(500);
    echo "Unable to connect to central mail server.";
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo "Unable to load central mail configuration.";
    exit;
}

$mailConfig = json_decode($apiResponse, true);

if (
    !is_array($mailConfig) ||
    !isset($mailConfig['success']) ||
    $mailConfig['success'] !== true ||
    !isset($mailConfig['gmail_username']) ||
    !isset($mailConfig['gmail_app_password'])
) {
    http_response_code(500);
    echo "Invalid mail configuration received.";
    exit;
}

$gmailUsername = $mailConfig['gmail_username'];
$gmailAppPassword = $mailConfig['gmail_app_password'];


/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| Error Handling
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function response(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Only POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    response(
        false,
        'Invalid request method.',
        405
    );
}


/*
|--------------------------------------------------------------------------
| reCAPTCHA SECRET KEY
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This must be the SECRET KEY corresponding to the SITE KEY
| used in your JavaScript.
|
*/

$RECAPTCHA_SECRET = '6LfFnNMtAAAAAHdHQt0fyUoHLMaV2o9NyQEbisUd';


/*
|--------------------------------------------------------------------------
| Get Form Values
|--------------------------------------------------------------------------
*/

$name = trim(
    (string) ($_POST['name'] ?? '')
);

$email = trim(
    (string) ($_POST['email'] ?? '')
);

$phone = trim(
    (string) ($_POST['phone'] ?? '')
);

$message = trim(
    (string) ($_POST['message'] ?? '')
);

$recaptchaToken = trim(
    (string) ($_POST['recaptcha_token'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Validate Name
|--------------------------------------------------------------------------
*/

if ($name === '') {

    response(
        false,
        'Please enter your name.'
    );
}


if (mb_strlen($name) > 100) {

    response(
        false,
        'Name is too long.'
    );
}


if (
    !preg_match(
        "/^[\p{L}\s.'-]+$/u",
        $name
    )
) {

    response(
        false,
        'Please enter a valid name.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

if ($email === '') {

    response(
        false,
        'Please enter your email address.'
    );
}


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    response(
        false,
        'Please enter a valid email address.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Phone
|--------------------------------------------------------------------------
*/

if ($phone === '') {

    response(
        false,
        'Please enter your phone number.'
    );
}


if (
    !preg_match(
        '/^[0-9+\-\s().]{7,20}$/',
        $phone
    )
) {

    response(
        false,
        'Please enter a valid phone number.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Message
|--------------------------------------------------------------------------
*/

if ($message === '') {

    response(
        false,
        'Please enter your message.'
    );
}


if (mb_strlen($message) > 2000) {

    response(
        false,
        'Message is too long.'
    );
}


/*
|--------------------------------------------------------------------------
| Check reCAPTCHA Token
|--------------------------------------------------------------------------
*/

if ($recaptchaToken === '') {

    response(
        false,
        'reCAPTCHA verification is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Verify reCAPTCHA
|--------------------------------------------------------------------------
*/

$ch = curl_init(
    'https://www.google.com/recaptcha/api/siteverify'
);

curl_setopt_array(
    $ch,
    [
        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => http_build_query(
            [
                'secret' => $RECAPTCHA_SECRET,
                'response' => $recaptchaToken,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        ),

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_TIMEOUT => 15,

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]
);


$recaptchaResponse = curl_exec($ch);

$curlError = curl_error($ch);

curl_close($ch);


/*
|--------------------------------------------------------------------------
| cURL Error
|--------------------------------------------------------------------------
*/

if ($recaptchaResponse === false) {

    error_log(
        'reCAPTCHA cURL Error: ' . $curlError
    );

    response(
        false,
        'Unable to verify reCAPTCHA. Please try again.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Decode Response
|--------------------------------------------------------------------------
*/

$recaptchaResult = json_decode(
    $recaptchaResponse,
    true
);


if (!is_array($recaptchaResult)) {

    error_log(
        'Invalid reCAPTCHA response: ' .
        $recaptchaResponse
    );

    response(
        false,
        'Invalid reCAPTCHA response.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Check reCAPTCHA Success
|--------------------------------------------------------------------------
*/

if (
    empty($recaptchaResult['success'])
) {

    error_log(
        'reCAPTCHA failed: ' .
        $recaptchaResponse
    );

    response(
        false,
        'reCAPTCHA verification failed.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Action
|--------------------------------------------------------------------------
*/

$recaptchaAction =
    (string) ($recaptchaResult['action'] ?? '');


if ($recaptchaAction !== 'contact_form') {

    error_log(
        'Invalid reCAPTCHA action: ' .
        $recaptchaAction
    );

    response(
        false,
        'Invalid reCAPTCHA action.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Score
|--------------------------------------------------------------------------
*/

$score = (float) (
    $recaptchaResult['score'] ?? 0
);


if ($score < 0.5) {

    error_log(
        'Low reCAPTCHA score: ' .
        $score
    );

    response(
        false,
        'reCAPTCHA verification failed. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| SMTP SETTINGS
|--------------------------------------------------------------------------
*/

$SMTP_HOST = 'smtp.gmail.com';

$SMTP_PORT = 587;

$SMTP_USER = $gmailUsername;

$SMTP_PASS = $gmailAppPassword;


/*
|--------------------------------------------------------------------------
| Email Settings
|--------------------------------------------------------------------------
*/

$FROM_EMAIL = $SMTP_USER;

$FROM_NAME = 'Dharia Engineers';

$TO_EMAIL = 'designernetcom@gmail.com';

$TO_NAME = 'Dharia Engineers';


/*
|--------------------------------------------------------------------------
| Escape Values For HTML
|--------------------------------------------------------------------------
*/

$safeName = htmlspecialchars(
    $name,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$safeEmail = htmlspecialchars(
    $email,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$safePhone = htmlspecialchars(
    $phone,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$safeMessage = nl2br(
    htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
);


/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
*/

$mail = new PHPMailer(true);


try {

    /*
    |--------------------------------------------------------------------------
    | SMTP
    |--------------------------------------------------------------------------
    */

    $mail->isSMTP();

    $mail->Host = $SMTP_HOST;

    $mail->SMTPAuth = true;

    $mail->Username = $SMTP_USER;

    $mail->Password = $SMTP_PASS;

    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = $SMTP_PORT;

    $mail->Timeout = 30;

    $mail->CharSet = 'UTF-8';


    /*
    |--------------------------------------------------------------------------
    | From
    |--------------------------------------------------------------------------
    */

    $mail->setFrom(
        $FROM_EMAIL,
        $FROM_NAME
    );


    /*
    |--------------------------------------------------------------------------
    | Recipient
    |--------------------------------------------------------------------------
    */

    $mail->addAddress(
        $TO_EMAIL,
        $TO_NAME
    );


    /*
    |--------------------------------------------------------------------------
    | Reply To
    |--------------------------------------------------------------------------
    */

    $mail->addReplyTo(
        $email,
        $name
    );


    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    */

    $mail->isHTML(true);

    $mail->Subject =
        'Dharia Engineers - New Contact Request';


    /*
    |--------------------------------------------------------------------------
    | HTML Body
    |--------------------------------------------------------------------------
    */

    $mail->Body = '
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>New Contact Request</title>

</head>

<body style="
margin:0;
padding:20px;
font-family:Arial,Helvetica,sans-serif;
background:#f5f5f5;
">

<table
width="100%"
cellpadding="0"
cellspacing="0"
style="
max-width:650px;
margin:auto;
background:#ffffff;
border-collapse:collapse;
"
>

<tr>

<td
colspan="2"
style="
padding:20px;
background:#C50B33;
color:#ffffff;
font-size:22px;
font-weight:bold;
"
>
New Website Contact Request
</td>

</tr>

<tr>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
font-weight:bold;
width:180px;
"
>
Name
</td>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
"
>
' . $safeName . '
</td>

</tr>

<tr>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
font-weight:bold;
"
>
Email
</td>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
"
>
' . $safeEmail . '
</td>

</tr>

<tr>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
font-weight:bold;
"
>
Phone
</td>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
"
>
' . $safePhone . '
</td>

</tr>

<tr>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
font-weight:bold;
"
>
Message
</td>

<td
style="
padding:12px;
border-bottom:1px solid #eeeeee;
"
>
' . $safeMessage . '
</td>

</tr>

</table>

</body>

</html>
';


    /*
    |--------------------------------------------------------------------------
    | Plain Text
    |--------------------------------------------------------------------------
    */

    $mail->AltBody =
        "New Website Contact Request\n\n" .
        "Name: " . $name . "\n" .
        "Email: " . $email . "\n" .
        "Phone: " . $phone . "\n\n" .
        "Message:\n" . $message;


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    $mail->send();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    response(
        true,
        'Thank you for contacting Dharia Engineers. We will get back to you shortly.'
    );


} catch (Exception $e) {

    error_log(
        'Dharia Engineers Contact Form Error: ' .
        $mail->ErrorInfo
    );


    response(
        false,
        'Sorry, your message could not be sent. Please try again later.',
        500
    );
}