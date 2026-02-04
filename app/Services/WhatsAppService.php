<?php

namespace App\Services;

class WhatsAppService
{
    public function sendBulk(array $numbers, string $message): array
    {
        // Return WhatsApp web links for each number
        $links = [];
        foreach ($numbers as $n) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $n);
            $encodedMessage = urlencode($message);
            $links[] = "https://web.whatsapp.com/send?phone={$cleanNumber}&text={$encodedMessage}";
        }
        return $links;
    }

    public function generateLink(string $phone, string $message): string
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $phone);
        $encodedMessage = urlencode($message);
        return "https://web.whatsapp.com/send?phone={$cleanNumber}&text={$encodedMessage}";
    }
}
