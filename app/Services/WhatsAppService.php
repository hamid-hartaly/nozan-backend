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

    private string $supportNumber;

    private bool $verifySsl;

    private ?string $caBundle;

    public function configDiagnostics(): array
    {
        $phoneNumberId = trim($this->phoneNumberId);
        $accessToken = trim($this->accessToken);

        $phoneIdPresent = $phoneNumberId !== '';
        $accessTokenPresent = $accessToken !== '';
        $phoneIdLooksLocalPhone = preg_match('/^0[0-9]{10}$/', $phoneNumberId) === 1;
        $phoneIdLooksNumericId = preg_match('/^[0-9]{6,}$/', $phoneNumberId) === 1;
        $tokenLooksLikePhone = preg_match('/^0[0-9]{10}$/', $accessToken) === 1;
        $tokenLooksPlausible = strlen($accessToken) >= 20 && preg_match('/[A-Za-z]/', $accessToken) === 1;

        $valid = $phoneIdPresent && $accessTokenPresent && $phoneIdLooksNumericId && ! $phoneIdLooksLocalPhone && $tokenLooksPlausible && ! $tokenLooksLikePhone;

        $hint = null;

        if (! $phoneIdPresent || ! $accessTokenPresent) {
            $hint = 'Missing WHATSAPP_PHONE_NUMBER_ID or WHATSAPP_ACCESS_TOKEN.';
        } elseif ($phoneIdLooksLocalPhone) {
            $hint = 'WHATSAPP_PHONE_NUMBER_ID looks like a local phone number (07...). Use Meta Phone Number ID from WhatsApp Cloud API.';
        } elseif (! $phoneIdLooksNumericId) {
            $hint = 'WHATSAPP_PHONE_NUMBER_ID should be a numeric Meta ID.';
        } elseif ($tokenLooksLikePhone || ! $tokenLooksPlausible) {
            $hint = 'WHATSAPP_ACCESS_TOKEN looks invalid. Use a real Meta long-lived access token, not a phone number.';
        }

        return [
            'valid' => $valid,
            'phone_number_id_present' => $phoneIdPresent,
            'access_token_present' => $accessTokenPresent,
            'phone_number_id_looks_local_phone' => $phoneIdLooksLocalPhone,
            'access_token_looks_like_phone' => $tokenLooksLikePhone,
            'hint' => $hint,
        ];
    }

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->businessAccountId = config('services.whatsapp.business_account_id', '');
        $this->accessToken = config('services.whatsapp.access_token', '');
        $this->supportNumber = config('services.whatsapp.support_number', '07704330005');
        $this->verifySsl = (bool) config('services.whatsapp.verify_ssl', true);
        $caBundle = trim((string) config('services.whatsapp.ca_bundle', ''));
        $this->caBundle = $caBundle !== '' ? $caBundle : null;
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->configDiagnostics()['valid'] ?? false);
    }

    public function sendJobCreatedMessage(ServiceJob $job): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp: Skipping job created message - service not configured', [
                'job_code' => $job->job_code,
                'phone_number_id_present' => $this->phoneNumberId !== '',
                'access_token_present' => $this->accessToken !== '',
            ]);

            return false;
        }

        if (! $job->customer_phone) {
            Log::info('WhatsApp: Skipping job created message - missing customer phone', [
                'job_code' => $job->job_code,
            ]);

            return false;
        }

        $trackingUrl = $this->buildTrackingUrl($job);
        $customerName = $this->safeCustomerName($job->customer_name);
        $deviceName = $this->safeDeviceName($job->tv_model);
        $category = $this->safeCategory($job->category);

        $message = sprintf(
            "سڵاو %s،\n\nئامێرەکەتان بە سەرکەوتوویی لە سەنتەری نۆزان بۆ چاککردنەوەی تەلەفزیۆن تۆمار کرا.\n\nژمارەی جۆب: %s\nئامێر: %s\nجۆر: %s\n\nتکایە ئەم ژمارەیە پارێزە بۆ هەر پەیوەندییەکی داهاتوو.\nبۆ بەدواداچوونی دۆخی چاککردن، ئەم بەستەرە بەکاربهێنە:\n%s\n\nژمارەی سەنتەر: %s\n\nسوپاس بۆ متمانەکردنتان بە سەنتەری نۆزان.\n\nمرحبا %s،\n\nتم استلام جهازكم وتسجيله بنجاح في مركز نوزان لصيانة أجهزة التلفزيون.\n\nرقم الطلب: %s\nالجهاز: %s\nالنوع: %s\n\nيرجى الاحتفاظ برقم الطلب لأي متابعة لاحقة.\nيمكنكم متابعة حالة الإصلاح عبر الرابط التالي:\n%s\n\nرقم المركز: %s\n\nشكراً لثقتكم بمركز نوزان.",
            $customerName,
            $job->job_code,
            $deviceName,
            $category,
            $trackingUrl,
            $this->supportNumber,
            $customerName,
            $job->job_code,
            $deviceName,
            $category,
            $trackingUrl,
            $this->supportNumber
        );

        return $this->sendMessage($job->customer_phone, $message, WhatsAppEvent::JOB_CREATED->templateName());
    }

    public function sendBookingSubmittedMessage(string $customerPhone, ?string $customerName = null): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp: Skipping booking submitted message - service not configured', [
                'phone_number_id_present' => $this->phoneNumberId !== '',
                'access_token_present' => $this->accessToken !== '',
            ]);

            return false;
        }

        if (! $customerPhone) {
            Log::info('WhatsApp: Skipping booking submitted message - missing customer phone');

            return false;
        }

        $safeName = $this->safeCustomerName($customerName);

        $message = sprintf(
            "سڵاو %s،\n\nداواکاریەکەتان بە سەرکەوتوویی لە سەنتەری نۆزان بۆ چاککردنەوەی تەلەفزیۆن تۆمار کرا.\nتیمی ئێمە بە زووترین کات پەیوەندیتان پێوە دەکات بۆ تەواوکردنی وردەکارییەکان و دیاریکردنی کات.\n\nژمارەی سەنتەر: %s\n\nسوپاس بۆ هەڵبژاردنی سەنتەری نۆزان.\n\nمرحبا %s،\n\nتم تسجيل طلبكم بنجاح في مركز نوزان لصيانة أجهزة التلفزيون.\nسيقوم فريقنا بالتواصل معكم في أقرب وقت لاستكمال التفاصيل وتحديد الموعد المناسب.\n\nرقم المركز: %s\n\nشكراً لاختياركم مركز نوزان.",
            $safeName,
            $this->supportNumber,
            $safeName,
            $this->supportNumber,
        );

        return $this->sendMessage($customerPhone, $message, 'booking_submitted');
    }

    public function sendManualTestMessage(string $phoneNumber, ?string $customerName = null): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp: Skipping manual test message - service not configured', [
                'phone_number_id_present' => $this->phoneNumberId !== '',
                'access_token_present' => $this->accessToken !== '',
            ]);

            return false;
        }

        if (! trim($phoneNumber)) {
            Log::info('WhatsApp: Skipping manual test message - missing phone number');

            return false;
        }

        $safeName = $this->safeCustomerName($customerName);

        $message = sprintf(
            "سڵاو %s،\n\nئەمە پەیامی تاقیکردنەوەی WhatsApp ـە لە سەنتەری نۆزان بۆ چاککردنەوەی تەلەفزیۆن.\nئەگەر ئەم پەیامە پێگەیشت، واتسئەپ بە دروستی کاردەکات.\n\nژمارەی سەنتەر: %s\n\nمرحبا %s،\n\nهذه رسالة اختبار واتساب من مركز نوزان لصيانة أجهزة التلفزيون.\nإذا وصلتكم هذه الرسالة فهذا يعني أن الإرسال يعمل بشكل صحيح.\n\nرقم المركز: %s",
            $safeName,
            $this->supportNumber,
            $safeName,
            $this->supportNumber,
        );

        return $this->sendMessage($phoneNumber, $message, 'admin_manual_test');
    }

    public function sendRepairStartedMessage(ServiceJob $job): bool
    {
        if (! $this->isConfigured() || ! $job->customer_phone) {
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
        if (! $this->isConfigured() || ! $job->customer_phone) {
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
        if (! $this->isConfigured() || ! $job->customer_phone) {
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
        if (! $this->isConfigured() || ! $job->customer_phone) {
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

            if (! $this->isConfigured()) {
                Log::warning('WhatsApp not configured, would send:', [
                    'phone' => $formattedPhone,
                    'message' => $messageBody,
                    'template' => $templateName,
                ]);

                return false;
            }

            $request = Http::withToken($this->accessToken)
                ->withOptions([
                    'verify' => $this->caBundle ?? $this->verifySsl,
                ])
                ->acceptJson();

            $response = $request->post(sprintf('https://graph.facebook.com/v20.0/%s/messages', $this->phoneNumberId), [
                'messaging_product' => 'whatsapp',
                'to' => $recipientPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $messageBody,
                ],
            ]);

            if (! $response->successful()) {
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
            return '+'.$cleaned;
        }

        // If starts with 07 or 09, replace with +964 7 or +964 9
        if (str_starts_with($cleaned, '07') || str_starts_with($cleaned, '09')) {
            return '+964'.substr($cleaned, 1);
        }

        // Otherwise, assume Iraq and prefix with +964
        return '+964'.$cleaned;
    }

    private function buildTrackingUrl(ServiceJob $job): string
    {
        $baseUrl = rtrim((string) config('services.frontend.url', 'https://www.nozan-service.com'), '/');
        $jobCode = rawurlencode((string) $job->job_code);
        $token = rawurlencode($job->trackingToken());

        return sprintf('%s/track/%s?token=%s', $baseUrl, $jobCode, $token);
    }

    private function safeCustomerName(?string $name): string
    {
        $safeName = trim((string) $name);

        return $safeName !== '' ? $safeName : 'بەڕێز / العميل الكريم';
    }

    private function safeDeviceName(?string $deviceName): string
    {
        $safeName = trim((string) $deviceName);

        return $safeName !== '' ? $safeName : 'TV';
    }

    public function sendPromiseReminderMessage(ServiceJob $job): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp: Skipping promise reminder - service not configured', [
                'job_code' => $job->job_code,
            ]);

            return false;
        }

        if (! $job->customer_phone) {
            Log::info('WhatsApp: Skipping promise reminder - missing customer phone', [
                'job_code' => $job->job_code,
            ]);

            return false;
        }

        $customerName = $this->safeCustomerName($job->customer_name);
        $deviceName = $this->safeDeviceName($job->tv_model);
        $promiseDate = $job->promised_completion_at
            ? $job->promised_completion_at->format('Y-m-d')
            : 'N/A';

        $message = sprintf(
            "سڵاو %s،\n\nیادکردنەوە: ئامێرەکەتان بۆ چاككراوە سبەینێ (%s) دیاری کراوە.\n\nژمارەی جۆب: %s\nئامێر: %s\n\nژمارەی سەنتەر: %s\n\nمرحبا %s،\n\nتذكير: موعد تسليم جهازكم هو %s.\n\nرقم الطلب: %s\nالجهاز: %s\n\nرقم المركز: %s",
            $customerName,
            $promiseDate,
            (string) $job->job_code,
            $deviceName,
            $this->supportNumber,
            $customerName,
            $promiseDate,
            (string) $job->job_code,
            $deviceName,
            $this->supportNumber,
        );

        return $this->sendMessage($job->customer_phone, $message, 'promise_reminder');
    }

    private function safeCategory(?string $category): string
    {
        $safeCategory = trim((string) $category);

        return $safeCategory !== '' ? $safeCategory : 'OTHER';
    }
}
