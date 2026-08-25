<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    /**
     * Send push notification to a single device or list of device tokens.
     */
    public static function sendPushNotification(string|array $tokens, string $title, string $body, array $data = []): bool
    {
        $tokens = is_array($tokens) ? array_filter($tokens) : [$tokens];
        if (empty($tokens)) {
            return false;
        }

        $possiblePaths = [
            config('services.firebase.credentials'),
            env('FIREBASE_CREDENTIALS'),
            storage_path('app/firebase/firebase_credentials.json'),
            base_path('config/firebase/firebase_credentials.json'),
            base_path('config/firebase_credentials.json'),
        ];

        $credentialsPath = null;
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                $credentialsPath = $path;
                break;
            }
        }

        $serverKey = config('services.firebase.server_key') ?? env('FIREBASE_SERVER_KEY');

        // 1. Try Firebase HTTP v1 via Service Account JSON (Recommended modern standard)
        if ($credentialsPath) {
            return self::sendViaHttpV1($credentialsPath, $tokens, $title, $body, $data);
        }

        // 2. Fallback to Legacy Server Key if present
        if ($serverKey) {
            return self::sendViaLegacyKey($serverKey, $tokens, $title, $body, $data);
        }

        // 3. Fallback mock log when credentials are not yet uploaded
        Log::info("Firebase Notification (Mock/No Key Configured): Title='{$title}', Body='{$body}', Tokens=" . count($tokens));
        return true;
    }

    /**
     * Send FCM using modern HTTP v1 API with Google Service Account OAuth2.
     */
    private static function sendViaHttpV1(string $credentialsPath, array $tokens, string $title, string $body, array $data = []): bool
    {
        try {
            $jsonContent = json_decode(file_get_contents($credentialsPath), true);
            if (!$jsonContent || empty($jsonContent['project_id']) || empty($jsonContent['client_email']) || empty($jsonContent['private_key'])) {
                Log::error('FCM Error: Invalid service account credentials JSON file at ' . $credentialsPath);
                return false;
            }

            $projectId = $jsonContent['project_id'];
            $accessToken = self::getGoogleAccessToken($jsonContent);

            if (!$accessToken) {
                Log::error('FCM Error: Failed to generate Google OAuth2 Access Token');
                return false;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            // Convert all data values to strings (FCM requirement)
            $stringData = [];
            foreach ($data as $key => $val) {
                $stringData[(string) $key] = is_array($val) ? json_encode($val) : (string) $val;
            }

            $allSuccess = true;

            foreach ($tokens as $token) {
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => !empty($stringData) ? $stringData : new \stdClass(),
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                                'channel_id' => 'delivery_orders',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'badge' => 1,
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                if ($response->successful()) {
                    Log::info("FCM Notification Sent Successfully! Token: {$token}, Title='{$title}'");
                } else {
                    Log::error("FCM HTTP v1 Error for token {$token}: " . $response->body());
                    $allSuccess = false;
                }
            }

            return $allSuccess;
        } catch (\Exception $e) {
            Log::error('FCM HTTP v1 Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate & Cache Google OAuth2 Access Token from Service Account JSON.
     */
    private static function getGoogleAccessToken(array $serviceAccount): ?string
    {
        $cacheKey = 'fcm_google_access_token_' . md5($serviceAccount['client_email']);

        return Cache::remember($cacheKey, 3300, function () use ($serviceAccount) {
            $now = time();
            $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = self::base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]));

            $signature = '';
            openssl_sign("{$header}.{$claim}", $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$claim}." . self::base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Google OAuth2 Token Error: ' . $response->body());
            return null;
        });
    }

    /**
     * Helper to base64url-encode without padding.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Legacy FCM Send fallback.
     */
    private static function sendViaLegacyKey(string $serverKey, array $tokens, string $title, string $body, array $data = []): bool
    {
        try {
            $payload = [
                'registration_ids' => array_values($tokens),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => 1,
                    'android_channel_id' => 'delivery_orders',
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Legacy FCM Exception: ' . $e->getMessage());
            return false;
        }
    }
}
