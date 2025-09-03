<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference', 'user_id', 'destination_id', 'number_of_travelers',
        'total_amount', 'tax_amount', 'discount_amount', 'travel_date',
        'status', 'traveler_details', 'special_requests',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'traveler_details' => 'array',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            $booking->booking_reference = 'TRV-'.strtoupper(uniqid());
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function getLatestPaymentAttribute()
    {
        return $this->payments()->latest()->first();
    }
}
