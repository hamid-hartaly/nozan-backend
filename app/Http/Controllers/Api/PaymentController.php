<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceJob;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, ServiceJob $job): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->canRecordPaymentPermission(), 403);

        if (! $request->has('amount_iqd') && $request->has('amount')) {
            $request->merge(['amount_iqd' => $request->input('amount')]);
        }

        $payload = $request->validate([
            'amount_iqd' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = Payment::create([
            'service_job_id' => $job->id,
            'amount_iqd' => $payload['amount_iqd'],
            'method' => $payload['method'] ?? 'cash',
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $job->increment('payment_received_iqd', (int) round((float) $payload['amount_iqd']));

        $freshJob = $job->fresh();
        if ($freshJob && (float) ($freshJob->final_price_iqd ?? 0) <= 0 && (float) ($freshJob->estimated_price_iqd ?? 0) <= 0) {
            $freshJob->final_price_iqd = (float) ($freshJob->payment_received_iqd ?? 0);
            $freshJob->final_price = (float) ($freshJob->payment_received_iqd ?? 0);
            $freshJob->save();
            $freshJob = $freshJob->fresh();
        }

        // Send WhatsApp notification for payment recorded
        $whatsappService = new WhatsAppService();
        $whatsappService->sendPaymentRecordedMessage($job, (int) round((float) $payload['amount_iqd']));

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $payment->fresh(),
            'job' => [
                'id' => (string) ($freshJob?->id ?? $job->id),
                'job_code' => $freshJob?->job_code ?? $job->job_code,
                'payment_received_iqd' => (int) round((float) ($freshJob?->payment_received_iqd ?? 0)),
                'final_price_iqd' => (int) round((float) ($freshJob?->final_price_iqd ?? $freshJob?->estimated_price_iqd ?? 0)),
            ],
            'remaining_balance_iqd' => $freshJob?->remaining_balance ?? 0,
        ]);
    }
}
