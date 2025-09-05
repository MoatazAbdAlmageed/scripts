<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get URLs from .env (comma separated)
$urls = array_map('trim', explode(',', $_ENV['URLS']));

$errors = [];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $errors[] = "$url -> CURL Error: $err";
        continue;
    }

    $data = json_decode($response, true);

    if (
        !isset($data["status"], $data["data"]) ||
        $data["status"] !== 200 ||
        $data["data"] === false ||
        $data["data"] === null ||
        $data["data"] === "" ||
        (is_string($data["data"]) && stripos($data["data"], "loading") !== false)
    ) {
        $errors[] = "$url -> Invalid response: $response";
    }
}

// Send email if errors exist
if (!empty($errors)) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['EMAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['EMAIL_USERNAME'];
        $mail->Password   = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $_ENV['EMAIL_PORT'];

        $mail->setFrom($_ENV['EMAIL_FROM'], 'Auth Monitor');
        $mail->addAddress($_ENV['EMAIL_TO']);

        $mail->isHTML(false);
        $mail->Subject = "⚠️ Auth Service Down";
        $mail->Body    = "Some Whatsapp services failed:\n\n" . implode("\n\n", $errors);

        $mail->send();
        echo "Alert email sent!\n";
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}\n";
    }
}
