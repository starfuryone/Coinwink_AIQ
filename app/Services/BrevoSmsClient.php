<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Brevo's Transactional SMS API.
 *
 * Brevo replaces Twilio as Coinwink's SMS gateway. The API takes a single
 * recipient per call and returns a JSON envelope with `messageId`,
 * `usedCredits` and `remainingCredits`.
 *
 * Endpoint reference: POST /v3/transactionalSMS/sms
 *   Headers: api-key, content-type: application/json
 *   Body:    { sender, recipient, content, type: "transactional", tag? }
 */
class BrevoSmsClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
        private readonly string $endpoint = 'https://api.brevo.com/v3/transactionalSMS/sms',
    ) {
    }

    public static function fromConfig(): self
    {
        $key = (string) config('services.brevo.api_key');
        if ($key === '') {
            throw new RuntimeException('BREVO_API_KEY is not configured.');
        }
        return new self(
            $key,
            (string) config('services.brevo.sms_sender', 'Coinwink'),
            (string) config('services.brevo.sms_endpoint', 'https://api.brevo.com/v3/transactionalSMS/sms'),
        );
    }

    /**
     * Send a transactional SMS. Returns Brevo's response array on success;
     * throws RuntimeException on failure so callers can fall back to email.
     *
     * @return array{messageId?: int|string, reference?: string, usedCredits?: float, remainingCredits?: float}
     */
    public function send(string $recipient, string $content, ?string $tag = null): array
    {
        $payload = [
            'sender' => $this->sender,
            'recipient' => $recipient,
            'content' => $content,
            'type' => 'transactional',
        ];
        if ($tag !== null) {
            $payload['tag'] = $tag;
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->asJson()->post($this->endpoint, $payload);

        if (!$response->successful()) {
            $body = $response->body();
            Log::warning('Brevo SMS send failed', [
                'status' => $response->status(),
                'body' => $body,
                'recipient_tail' => substr($recipient, -4),
            ]);
            throw new RuntimeException("Brevo SMS failed (HTTP {$response->status()}): {$body}");
        }

        return $response->json() ?? [];
    }
}
