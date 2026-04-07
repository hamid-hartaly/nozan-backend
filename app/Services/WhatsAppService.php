<?php

namespace App\Services;

use App\Enums\WhatsAppEvent;
use App\Models\ServiceJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $phoneNumberId;
    private string $businessAccountId;
    private string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->businessAccountId = config('services.whatsapp.business_account_id', '');
        $this->accessToken = config('services.whatsapp.access_token', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    public function sendJobCreatedMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            Log::info('WhatsApp: Skipping job created message - not configured or no customer phone', [
                'job_code' => $job->job_code,
            ]);
            return false;
        }

        $message = sprintf(
            "سڵاو %s،\n\nسوپاس بۆ دانانی تیڤیەکەت لە ناوەندی نوزان.\n\nژمارەی جۆب: *%s*\nمۆدێل: %s\nجۆری کێشە: %s\n\nبەم زووانەوە دەستی بە چاکردنی دەکەین.",
            $job->customer_name ?? 'خاوەن',
            $job->job_code,
            $job->tv_model,
            $job->category
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_CREATED->templateName());
    }

    public function sendRepairStartedMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        $message = sprintf(
            "ئاگادارکردنەوە ✅\n\nخزمەتگوزاری نوزان: دەستی بە چاکردنی تیڤیەکەت کرا.\n\n🔧 جۆب: *%s*\n📺 مۆدێل: %s\n\nکاتێک تەواو بۆ ئاگادارت دەکەینەوە.",
            $job->job_code,
            $job->tv_model
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_REPAIR_STARTED->templateName());
    }

    public function sendJobFinishedMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        if ($job->resolution === 'NOT_FIXED') {
            return $this->sendJobNotFixedMessage($job);
        }

        // FIXED
        $price = (float) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0);
        $message = sprintf(
            "مژدە! تیڤیەکەت چاک کرا ✅\n\n🔧 جۆب: *%s*\n📺 مۆدێل: %s\n💰 نرخ: *%s* دینار\n\nدەتوانیت بێیت و وەری بگریت. سوپاس بۆ دانانی باوەڕتان لە خزمەتگوزاری نوزان.",
            $job->job_code,
            $job->tv_model,
            number_format($price)
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_FINISHED->templateName());
    }

    public function sendReadyForPickupMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        $unpaidAmount = max(((float) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0)) - ((float) ($job->payment_received_iqd ?? 0)), 0);

        $remainingText = $unpaidAmount > 0
            ? sprintf("\n💵 بڕی ماوە: *%s* دینار", number_format($unpaidAmount))
            : "\n✅ پارەی تەواو وەرگیرا";

        $message = sprintf(
            "تیڤیەکەت ئامادەیە بۆ وەرگرتن 📦\n\n🔧 جۆب: *%s*%s\n\nژووری 8-5 خزمەتگوزاری نوزان. سوپاس!",
            $job->job_code,
            $remainingText
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_READY_FOR_PICKUP->templateName());
    }

    public function sendPaymentRecordedMessage(ServiceJob $job, int $amountIqd): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        $unpaidAmount = max(((float) ($job->final_price_iqd ?? $job->estimated_price_iqd ?? 0)) - ((float) ($job->payment_received_iqd ?? 0)), 0);

        $message = sprintf(
            "پارەی وەرگیرا ✅\n\n🔧 جۆب: *%s*\n💰 بڕی وەرگیراو: *%s* دینار\n💵 بڕی ماوە: *%s* دینار\n\nسوپاس بۆ پێدانتان!",
            $job->job_code,
            number_format((float) $amountIqd),
            number_format($unpaidAmount)
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::PAYMENT_RECORDED->templateName());
    }

    public function sendJobNotFixedMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        $reasonMap = [
            'NOT_REPAIRABLE'      => 'چاک نابێت',
            'MATERIAL_UNAVAILABLE' => 'پارچەی نیە',
            'OWNER_CANCELLED'     => 'خاوەنی کانسڵی کرد',
        ];
        $reason = $reasonMap[$job->not_fixed_reason ?? ''] ?? 'هۆکاری نەدیاری';

        $message = sprintf(
            "ئاگادارکردنەوە — تیڤیەکەت چاک نەکرا ❌\n\n🔧 جۆب: *%s*\n📺 مۆدێل: %s\n⚠️ هۆکار: *%s*\n\nتکایە بێیت و تیڤیەکەت وەربگرەوە. سوپاس بۆ باوەڕتان.",
            $job->job_code,
            $job->tv_model,
            $reason
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_FINISHED->templateName());
    }

    private function sendMessage(string $phoneNumber, string $messageBody, string $templateName): bool
    {
        try {
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);

            if (!$this->isConfigured()) {
                Log::warning('WhatsApp not configured, would send:', [
                    'phone' => $formattedPhone,
                    'message' => $messageBody,
                    'template' => $templateName,
                ]);
                return false;
            }

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $formattedPhone,
                    'type'              => 'text',
                    'text'              => [
                        'preview_url' => false,
                        'body'        => $messageBody,
                    ],
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent', [
                    'phone'    => $formattedPhone,
                    'template' => $templateName,
                    'message_id' => $response->json('messages.0.id'),
                ]);
                return true;
            }

            Log::warning('WhatsApp API rejected message', [
                'phone'    => $formattedPhone,
                'template' => $templateName,
                'status'   => $response->status(),
                'body'     => $response->json(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp message send failed', [
                'error'    => $e->getMessage(),
                'template' => $templateName,
            ]);
            return false;
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove common formatting characters
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // If starts with +, keep it
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // If starts with 964 (Iraq country code), add +
        if (str_starts_with($cleaned, '964')) {
            return '+' . $cleaned;
        }

        // If starts with 07 or 09, replace with +964 7 or +964 9
        if (str_starts_with($cleaned, '07') || str_starts_with($cleaned, '09')) {
            return '+964' . substr($cleaned, 1);
        }

        // Otherwise, assume Iraq and prefix with +964
        return '+964' . $cleaned;
    }
}
