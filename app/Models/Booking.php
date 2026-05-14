<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $device_type
 * @property string $tv_model
 * @property string $description
 * @property string $address
 * @property string|null $image_path
 * @property string $status
 * @property Carbon|null $converted_at
 * @property string|null $converted_job_code
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Booking extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'device_type',
        'tv_model',
        'description',
        'address',
        'image_path',
        'status',
        'converted_at',
        'converted_job_code',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
