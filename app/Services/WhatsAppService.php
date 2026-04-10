<?php

namespace App\Services;

use App\Enums\WhatsAppEvent;
use Illuminate\Support\Facades\Http;
use App\Models\ServiceJob;
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
            "مرحبا %s،\n\nشكراً لاختيارك مركز نوزان لإصلاح التلفاز.\nرقم طلبك: %s\nالجهاز: %s\nالفئة: %s\n\nسنبدأ الإصلاح في أقرب وقت.",
            $job->customer_name,
            $job->job_code,
            $job->tv_model,
            $job->category
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_CREATED->templateName());
    }

    public function sendBookingSubmittedMessage(string $customerPhone, ?string $customerName = null): bool
    {
        if (!$this->isConfigured() || !$customerPhone) {
            Log::info('WhatsApp: Skipping booking submitted message - not configured or no customer phone');
            return false;
        }

        $safeName = trim((string) $customerName);
        $greeting = $safeName !== '' ? sprintf('سڵاو %s،\n\n', $safeName) : '';

        $message = $greeting .
            "داواکاریەکەت پێشکەش کرا لە سەنتەری نۆزان. " .
            "بە زووترین کات لەلایەن ستافی سەنتەری نۆزان پەیوەندیت پێوە دەکرێت.\n\n" .
            "تم استلام طلبكم في مركز نوزان، وسيتم التواصل معكم في أقرب وقت من قبل فريق المركز.";

        return $this->sendMessage($customerPhone, $message, 'booking_submitted');
    }

    public function sendRepairStartedMessage(ServiceJob $job): bool
    {
        if (!$this->isConfigured() || !$job->customer_phone) {
            return false;
        }

        $message = sprintf(
            "تنبيه: بدأ إصلاح جهازك\n\nرقم الطلب: %s\nالجهاز: %s\n\nسيتم إخطارك عند انتهاء الإصلاح.",
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

        $price = (float) ($job->final_price_iqd ?? $job->estimated_price_iqd);
        $message = sprintf(
            "أخبار سارة! انتهى إصلاح جهازك\n\nرقم الطلب: %s\nالفئة: %s\nالسعر: %s د.ع\n\nيمكنك استلام جهازك في أي وقت.",
            $job->job_code,
            $job->category,
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

        $message = sprintf(
            "جهازك جاهز للاستلام\n\nرقم الطلب: %s\nالمبلغ المتبقي: %s د.ع\n\nشكراً لتعاملك معنا!",
            $job->job_code,
            number_format($unpaidAmount)
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
            "تم استلام دفعة\n\nرقم الطلب: %s\nالمبلغ: %s د.ع\nالمتبقي: %s د.ع\n\nشكراً!",
            $job->job_code,
            number_format((float) $amountIqd),
            number_format($unpaidAmount)
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::PAYMENT_RECORDED->templateName());
    }

    private function sendMessage(string $phoneNumber, string $messageBody, string $templateName): bool
    {
        try {
            // Format phone number: ensure it starts with country code (964 for Iraq)
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);
            $recipientPhone = ltrim($formattedPhone, '+');

            if (!$this->isConfigured()) {
                Log::warning('WhatsApp not configured, would send:', [
                    'phone' => $formattedPhone,
                    'message' => $messageBody,
                    'template' => $templateName,
                ]);
                return false;
            }

            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->post(sprintf('https://graph.facebook.com/v20.0/%s/messages', $this->phoneNumberId), [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipientPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $messageBody,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('WhatsApp message send failed', [
                    'phone' => $formattedPhone,
                    'template' => $templateName,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return false;
            }

            Log::info('WhatsApp message sent', [
                'phone' => $formattedPhone,
                'template' => $templateName,
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp message send failed', [
                'error' => $e->getMessage(),
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
