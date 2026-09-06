<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected ?string $accountSid;
    protected ?string $authToken;
    protected ?string $fromNumber;
    protected ?string $forwardToNumber;
    protected bool $recordCalls;

    public function __construct()
    {
        $this->accountSid = config('services.twilio.sid');
        $this->authToken = config('services.twilio.token');
        $this->fromNumber = config('services.twilio.from');
        $this->forwardToNumber = config('services.twilio.forward_to');
        $this->recordCalls = (bool) config('services.twilio.record_calls', true);
    }

    /**
     * Check if Twilio service is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromNumber);
    }

    /**
     * Generate TwiML for an incoming call to forward to a mobile/restaurant number.
     */
    public function generateForwardTwiML(string $statusCallbackUrl, ?string $forwardTo = null): string
    {
        $targetNumber = $forwardTo ?: $this->forwardToNumber;
        $recordAttribute = $this->recordCalls ? 'record="record-from-answer-dual" recordingStatusCallback="' . htmlspecialchars($statusCallbackUrl, ENT_QUOTES, 'UTF-8') . '"' : '';

        if (empty($targetNumber)) {
            return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Amy">Thank you for calling. Please leave a message after the tone or our staff will call you back shortly.</Say>
    <Record maxLength="120" action="' . htmlspecialchars($statusCallbackUrl, ENT_QUOTES, 'UTF-8') . '" />
</Response>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Amy">Connecting your call, please hold.</Say>
    <Dial timeout="30" callerId="' . htmlspecialchars($this->fromNumber ?? '', ENT_QUOTES, 'UTF-8') . '" action="' . htmlspecialchars($statusCallbackUrl, ENT_QUOTES, 'UTF-8') . '" ' . $recordAttribute . '>
        <Number>' . htmlspecialchars($targetNumber, ENT_QUOTES, 'UTF-8') . '</Number>
    </Dial>
</Response>';
    }

    /**
     * Initiate an Outgoing Call (Click to Call) via Twilio REST API.
     */
    public function makeCall(string $toNumber, string $twimlOrUrl, ?string $fromNumber = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Twilio is not configured. Please add TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_PHONE_NUMBER in your .env file.',
            ];
        }

        $from = $fromNumber ?: $this->fromNumber;
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls.json";

        $payload = [
            'To' => $toNumber,
            'From' => $from,
        ];

        if (filter_var($twimlOrUrl, FILTER_VALIDATE_URL)) {
            $payload['Url'] = $twimlOrUrl;
        } else {
            $payload['Twiml'] = $twimlOrUrl;
        }

        if ($this->recordCalls) {
            $payload['Record'] = 'true';
        }

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'call_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? 'queued',
                    'data' => $data,
                ];
            }

            Log::error('Twilio Make Call API Error', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to initiate Twilio call.',
                'error' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio Make Call Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send SMS Message via Twilio REST API.
     */
    public function sendSms(string $toNumber, string $message, ?string $fromNumber = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Twilio is not configured. Please add TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_PHONE_NUMBER in your .env file.',
            ];
        }

        $from = $fromNumber ?: $this->fromNumber;
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($url, [
                    'To' => $toNumber,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? 'queued',
                    'data' => $data,
                ];
            }

            Log::error('Twilio Send SMS Error', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to send SMS via Twilio.',
                'error' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio Send SMS Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
