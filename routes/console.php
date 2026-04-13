<?php

use App\Services\WhatsAppService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:test {phone?} {name?}', function (?string $phone = null, ?string $name = null) {
    $service = new WhatsAppService();
    $diagnostics = $service->configDiagnostics();

    if (! $service->isConfigured()) {
        $this->error('WhatsApp is not configured correctly.');
        if (! empty($diagnostics['hint'])) {
            $this->warn((string) $diagnostics['hint']);
        }
        return 1;
    }

    $targetPhone = trim((string) ($phone ?: config('services.whatsapp.support_number', '07704330005')));
    $targetName = trim((string) ($name ?: 'Nozan Customer'));

    if ($targetPhone === '') {
        $this->error('No target phone number provided.');
        return 1;
    }

    $sent = $service->sendManualTestMessage($targetPhone, $targetName);

    if (! $sent) {
        $this->error('WhatsApp test message failed. Check storage/logs/laravel.log for details.');
        return 1;
    }

    $this->info("WhatsApp test message sent to {$targetPhone}.");
    return 0;
})->purpose('Send a WhatsApp test message using current backend credentials');
