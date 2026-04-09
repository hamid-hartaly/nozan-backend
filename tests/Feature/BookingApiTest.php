<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_submit_booking_without_image(): void
    {
        $response = $this->post('/api/bookings', [
            'name' => 'Ahmed Kareem',
            'phone' => '07502405006',
            'tv_model' => 'LG 55UQ',
            'description' => 'No display but sound works',
            'address' => 'Hawler - 60m Street',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('booking.name', 'Ahmed Kareem')
            ->assertJsonPath('booking.phone', '07502405006')
            ->assertJsonPath('booking.image_path', null)
            ->assertJsonPath('booking.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'name' => 'Ahmed Kareem',
            'phone' => '07502405006',
            'tv_model' => 'LG 55UQ',
            'status' => 'pending',
        ]);
    }

    public function test_public_user_can_submit_booking_with_image(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/bookings', [
            'name' => 'Sara Jamal',
            'phone' => '07701234567',
            'tv_model' => 'Samsung 50Q',
            'description' => 'Screen flickers after a few minutes',
            'address' => 'Erbil - Dream City',
            'image' => UploadedFile::fake()->create('screen.jpg', 256, 'image/jpeg'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('booking.name', 'Sara Jamal')
            ->assertJsonPath('booking.status', 'pending');

        $imagePath = $response->json('booking.image_path');

        $this->assertNotNull($imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_booking_submit_requires_required_fields(): void
    {
        $response = $this->postJson('/api/bookings', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'phone',
                'tv_model',
                'description',
                'address',
            ]);
    }
}
