<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class SmsService
{
    protected $provider;
    protected $apiKey;
    protected $apiSecret;
    protected $senderId;
    protected $accountSid;
    protected $enabled;
    protected $customUrl;
    protected $customUsername;
    protected $customPassword;

    public function __construct()
    {
        $this->enabled = Setting::get('enable_sms_notifications', false);
        $this->provider = Setting::get('sms_provider', 'custom');
        $this->apiKey = Setting::get('sms_api_key', '');
        $this->apiSecret = Setting::get('sms_api_secret', '');
        $this->senderId = Setting::get('sms_sender_id', '');
        $this->accountSid = Setting::get('sms_account_sid', '');
        $this->customUrl = Setting::get('sms_custom_url', 'https://www.textit.biz/sendmsg/index.php');
        $this->customUsername = Setting::get('sms_custom_username', '');
        $this->customPassword = Setting::get('sms_custom_password', '');
    }

    /**
     * Send SMS to multiple numbers
     */
    public function sendBulk(array $numbers, string $message): int
    {
        if (!$this->enabled) {
            Log::info('SMS notifications disabled');
            return 0;
        }

        // Validate provider configuration
        if ($this->provider === 'custom') {
            if (empty($this->customUrl) || empty($this->customUsername) || empty($this->customPassword)) {
                Log::warning('Custom SMS provider not properly configured');
                return 0;
            }
        } else {
            if (empty($this->apiKey)) {
                Log::warning('SMS API key not configured');
                return 0;
            }
        }

        $successCount = 0;
        foreach ($numbers as $number) {
            if ($this->send($number, $message)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }

    /**
     * Send SMS to a single number
     */
    public function send(string $number, string $message): bool
    {
        if (!$this->enabled) {
            Log::info('SMS not sent - service disabled or not configured', ['number' => $number]);
            return false;
        }

        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->sendViaTwilio($number, $message);
                case 'nexmo':
                    return $this->sendViaNexmo($number, $message);
                case 'msg91':
                    return $this->sendViaMsg91($number, $message);
                case 'custom':
                    return $this->sendViaCustom($number, $message);
                default:
                    Log::warning('Unknown SMS provider: ' . $this->provider);
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'number' => $number,
                'provider' => $this->provider,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send via Twilio
     */
    protected function sendViaTwilio(string $number, string $message): bool
    {
        $response = Http::withBasicAuth($this->accountSid, $this->apiKey)
            ->asForm()
            ->post("https://api.textit.biz/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'From' => $this->senderId,
                'To' => $number,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            Log::info('SMS sent via Twilio', ['number' => $number]);
            return true;
        }

        Log::error('Twilio SMS failed', ['response' => $response->body()]);
        return false;
    }

    /**
     * Send via Nexmo (Vonage)
     */
    protected function sendViaNexmo(string $number, string $message): bool
    {
        $response = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'from' => $this->senderId,
            'to' => $number,
            'text' => $message,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['messages'][0]['status']) && $data['messages'][0]['status'] == '0') {
                Log::info('SMS sent via Nexmo', ['number' => $number]);
                return true;
            }
        }

        Log::error('Nexmo SMS failed', ['response' => $response->body()]);
        return false;
    }

    /**
     * Send via MSG91
     */
    protected function sendViaMsg91(string $number, string $message): bool
    {
        $response = Http::get('https://api.msg91.com/api/sendhttp.php', [
            'authkey' => $this->apiKey,
            'mobiles' => $number,
            'message' => $message,
            'sender' => $this->senderId,
            'route' => '4',
            'country' => '91',
        ]);

        if ($response->successful()) {
            Log::info('SMS sent via MSG91', ['number' => $number]);
            return true;
        }

        Log::error('MSG91 SMS failed', ['response' => $response->body()]);
        return false;
    }

    /**
     * Send via Custom Provider (TextIt.biz)
     */
    protected function sendViaCustom(string $number, string $message): bool
    {
        if (empty($this->customUrl)) {
            Log::error('Custom SMS URL not configured');
            return false;
        }

        // Determine if using REST API or HTTP GET based on URL
        $isRestApi = strpos($this->customUrl, 'api.textit.biz') !== false;

        if ($isRestApi) {
            // REST API with API Key (Bearer token)
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            
            // TextIt.biz REST API uses the API key as Bearer token
            if (!empty($this->customPassword)) {
                $headers['Authorization'] = 'Bearer ' . $this->customPassword;
            }

            $payload = [
                'to' => $number,
                'text' => $message,
            ];

            if (!empty($this->senderId)) {
                $payload['from'] = $this->senderId;
            }

            Log::info('Sending SMS via TextIt.biz REST API', [
                'url' => $this->customUrl,
                'to' => $number,
                'has_auth' => !empty($this->customPassword)
            ]);

            $response = Http::withHeaders($headers)
                ->post($this->customUrl, $payload);

        } else {
            // HTTP GET method (BASIC HTTP API)
            $params = [
                'id' => $this->customUsername,
                'pw' => $this->customPassword,
                'to' => $number,
                'text' => $message,
            ];

            if (!empty($this->senderId)) {
                $params['from'] = $this->senderId;
            }

            Log::info('Sending SMS via TextIt.biz HTTP API', [
                'url' => $this->customUrl,
                'to' => $number
            ]);

            $response = Http::get($this->customUrl, $params);
        }

        if ($response->successful()) {
            Log::info('SMS sent via Custom provider (TextIt.biz)', [
                'number' => $number,
                'method' => $isRestApi ? 'REST' : 'HTTP',
                'response' => $response->body()
            ]);
            return true;
        }

        Log::error('Custom SMS provider failed', [
            'url' => $this->customUrl,
            'method' => $isRestApi ? 'REST' : 'HTTP',
            'response' => $response->body(),
            'status' => $response->status()
        ]);
        return false;
    }

    /**
     * Test SMS configuration
     */
    public function testConnection(string $testNumber = null): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SMS notifications are disabled'];
        }

        // Validate configuration based on provider
        if ($this->provider === 'custom') {
            if (empty($this->customUrl) || empty($this->customUsername) || empty($this->customPassword)) {
                return ['success' => false, 'message' => 'Custom provider URL, username, and password are required'];
            }
        } else {
            if (empty($this->apiKey)) {
                return ['success' => false, 'message' => 'API key not configured'];
            }
        }

        if (empty($testNumber)) {
            return ['success' => false, 'message' => 'Test number required'];
        }

        $testMessage = 'This is a test message from Vehicle POS System';
        $result = $this->send($testNumber, $testMessage);

        return [
            'success' => $result,
            'message' => $result ? 'Test SMS sent successfully' : 'Failed to send test SMS',
            'provider' => $this->provider,
        ];
    }
}
