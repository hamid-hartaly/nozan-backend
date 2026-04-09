<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceJob;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, ServiceJob $job): JsonResponse
    {
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

        // Send WhatsApp notification for payment recorded
        $whatsappService = new WhatsAppService();
        $whatsappService->sendPaymentRecordedMessage($job, (int) round((float) $payload['amount_iqd']));

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $payment->fresh(),
            'remaining_balance_iqd' => $job->fresh()->remaining_balance,
        ], 201);
    }
}
