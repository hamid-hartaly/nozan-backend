<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'tv_model' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'address' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:8192'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bookings', 'public');
        }

        $booking = Booking::create([
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'tv_model' => $payload['tv_model'],
            'description' => $payload['description'],
            'address' => $payload['address'],
            'image_path' => $imagePath,
            'status' => 'pending',
        ]);

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
            'tv_model' => $booking->tv_model,
            'description' => $booking->description,
            'address' => $booking->address,
            'image_path' => $booking->image_path,
            'image_url' => $booking->image_path ? asset('storage/' . ltrim($booking->image_path, '/')) : null,
            'status' => $booking->status,
            'converted_at' => $booking->converted_at?->toIso8601String(),
            'notes' => $booking->notes,
            'created_at' => $booking->created_at?->toIso8601String(),
            'updated_at' => $booking->updated_at?->toIso8601String(),
        ];
    }
}

