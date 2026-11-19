<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends one WhatsApp message through the sender service of the platform owner
 * (Settings: its address and token). The service receives a POST with "to" and "message".
 * With no address saved, nothing is sent and false comes back.
 */
class WhatsApp
{
    public static function ready(): bool
    {
        return filled(platform('whatsapp_url'));
    }

    public static function send(string $to, string $message): bool
    {
        $url = (string) platform('whatsapp_url');
        // digits only, with the country code: "+93 70 000 0000" becomes "93700000000"
        $number = preg_replace('/\D+/', '', $to);
        if ($url === '' || strlen($number) < 8) {
            return false;
        }

        try {
            $request = Http::timeout(15)->acceptJson();
            if ($token = (string) platform('whatsapp_token')) {
                $request = $request->withToken($token);
            }

            return $request->post($url, ['to' => $number, 'message' => $message])->successful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
