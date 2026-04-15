<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    /**
     * List all bookings (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('tv_model', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $bookings = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($bookings->through(fn (Booking $booking): array => $this->transformBooking($booking)));
    }

    /**
     * Store a new booking (public endpoint - no auth required)
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'device_type' => ['required', 'string', 'in:television,washing_machine,refrigerator,split_ac'],
            'tv_model' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:8192'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bookings', 'public');
        }

        $booking = Booking::create([
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'device_type' => $payload['device_type'],
            'tv_model' => $payload['tv_model'],
            'description' => $payload['description'],
            'address' => $payload['address'],
            'image_path' => $imagePath,
            'status' => 'pending',
        ]);

        $whatsappService = new WhatsAppService();
        $whatsappService->sendBookingSubmittedMessage($booking->phone, $booking->name);

        return response()->json([
            'message' => 'شکریا، داواکاریەکە بە سەرکەوتوویی پێشکەش کرا. بۆ زیانی کپیدا لێنێ.',
            'booking' => $this->transformBooking($booking),
        ], 201);
    }

    /**
     * Get a single booking
     */
    public function show(Booking $booking): JsonResponse
    {
        return response()->json(['booking' => $this->transformBooking($booking)]);
    }

    /**
     * Update booking status (admin only)
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', Rule::in(['pending', 'converted', 'rejected'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($payload['status'] === 'converted') {
            $booking->converted_at = now();
        }

        if (array_key_exists('notes', $payload)) {
            $booking->notes = $payload['notes'];
        }

        $booking->status = $payload['status'];
        $booking->save();

        return response()->json([
            'message' => 'داواکاری نوێ کرایەوە.',
            'booking' => $this->transformBooking($booking),
        ]);
    }

    /**
     * Convert a booking into a real service job (admin only)
     */
    public function convertToJob(Request $request, Booking $booking): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($booking->converted_job_code) {
            $job = ServiceJob::query()->where('job_code', $booking->converted_job_code)->first();

            return response()->json([
                'message' => 'ئەو داواکارییە پێشتر کراوەتە job.',
                'booking' => $this->transformBooking($booking),
                'job' => $job ? $this->transformConvertedJob($job) : null,
            ]);
        }

        $payload = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'estimated_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'assigned_staff_uid' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $job = DB::transaction(function () use ($booking, $payload, $user): ServiceJob {
            $customer = Customer::firstOrCreate(
                ['phone' => $booking->phone],
                ['name' => $booking->name]
            );

            $assignedStaff = isset($payload['assigned_staff_uid'])
                ? User::find($payload['assigned_staff_uid'])
                : null;

            $notes = array_filter([
                $payload['notes'] ?? null,
                $booking->notes ? 'Booking notes: ' . $booking->notes : null,
                'Customer address: ' . $booking->address,
            ]);

            $job = ServiceJob::create([
                'customer_id' => (string) $customer->id,
                'customer_record_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'tv_model' => $booking->tv_model,
                'device_model' => $booking->tv_model,
                'category' => strtoupper((string) ($payload['category'] ?? 'OTHER')),
                'device_type' => strtoupper((string) ($payload['category'] ?? 'OTHER')),
                'priority' => strtolower((string) ($payload['priority'] ?? 'normal')),
                'issue' => $booking->description,
                'problem' => $booking->description,
                'estimated_price_iqd' => $payload['estimated_price_iqd'] ?? 0,
                'estimated_price' => $payload['estimated_price_iqd'] ?? 0,
                'status' => 'PENDING',
                'assigned_staff_uid' => $assignedStaff?->id,
                'notes' => implode("\n\n", $notes),
                'created_by_user_id' => $user->id,
            ]);

            if ($booking->image_path) {
                $job->jobImages()->create([
                    'image_path' => $booking->image_path,
                    'label' => 'Booking image',
                ]);
            }

            $booking->status = 'converted';
            $booking->converted_at = now();
            $booking->converted_job_code = $job->job_code;
            $booking->notes = $payload['notes'] ?? $booking->notes;
            $booking->save();

            $whatsappService = new WhatsAppService();
            if ($whatsappService->sendJobCreatedMessage($job)) {
                $job->update(['whatsapp_created_sent' => true]);
            }

            return $job;
        });

        return response()->json([
            'message' => 'داواکاریەکە بووە job بە سەرکەوتوویی.',
            'booking' => $this->transformBooking($booking->fresh()),
            'job' => $this->transformConvertedJob($job),
        ], 201);
    }

    /**
     * Delete a booking (admin only)
     */
    public function destroy(Booking $booking): JsonResponse
    {
        if ($booking->image_path && Storage::disk('public')->exists($booking->image_path)) {
            Storage::disk('public')->delete($booking->image_path);
        }

        $booking->delete();

        return response()->json(['message' => 'داواکاری سڕایەوە.']);
    }

    /**
     * Transform booking for response
     */
    private function transformBooking(Booking $booking): array
    {
        return [
            'id' => (string) $booking->id,
            'name' => $booking->name,
            'phone' => $booking->phone,
            'device_type' => $booking->device_type,
            'tv_model' => $booking->tv_model,
            'description' => $booking->description,
            'address' => $booking->address,
            'image_path' => $booking->image_path,
            'image_url' => $booking->image_path ? asset('storage/' . ltrim($booking->image_path, '/')) : null,
            'status' => $booking->status,
            'converted_at' => $booking->converted_at?->toIso8601String(),
            'converted_job_code' => $booking->converted_job_code,
            'notes' => $booking->notes,
            'created_at' => $booking->created_at?->toIso8601String(),
            'updated_at' => $booking->updated_at?->toIso8601String(),
        ];
    }

    private function transformConvertedJob(ServiceJob $job): array
    {
        return [
            'id' => (string) $job->id,
            'job_code' => (string) $job->job_code,
            'customer_name' => (string) $job->customer_name,
            'customer_phone' => (string) $job->customer_phone,
            'tv_model' => (string) $job->tv_model,
            'status' => (string) $job->status,
        ];
    }
}

