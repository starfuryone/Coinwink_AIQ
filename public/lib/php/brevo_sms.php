<?php

// Plain-PHP Brevo SMS helper used by the standalone cron scripts in
// /public/cron_alerts_sms_*.php. The Laravel application uses
// App\Services\BrevoSmsClient instead.
//
// Brevo Transactional SMS API:
//   POST https://api.brevo.com/v3/transactionalSMS/sms
//   Headers: api-key: <key>, content-type: application/json
//   Body:    { sender, recipient, content, type: "transactional" }
//
// Returns the decoded JSON body on success. On failure throws an Exception so
// the existing cron error-handling paths (admin email, user email, log row)
// continue to work.

if (!function_exists('cw_send_brevo_sms')) {
    function cw_send_brevo_sms(string $recipient, string $content): array
    {
        global $brevo_api_key;
        global $brevo_sms_sender;
        global $brevo_sms_endpoint;

        $endpoint = $brevo_sms_endpoint ?: 'https://api.brevo.com/v3/transactionalSMS/sms';
        $sender = $brevo_sms_sender ?: 'Coinwink';

        if (empty($brevo_api_key)) {
            throw new Exception('Brevo API key is not configured (coinwink_auth_brevo.php).');
        }

        $payload = json_encode([
            'sender' => $sender,
            'recipient' => $recipient,
            'content' => $content,
            'type' => 'transactional',
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $brevo_api_key,
                'content-type: application/json',
                'accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Brevo SMS curl error: ' . $err);
        }
        if ($status < 200 || $status >= 300) {
            throw new Exception('Brevo SMS HTTP ' . $status . ': ' . $body);
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
