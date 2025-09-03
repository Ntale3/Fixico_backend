<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number', 'payment_id', 'booking_id', 'file_path',
        'receipt_data', 'generated_at',
    ];

    protected $casts = [
        'receipt_data' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            $receipt->receipt_number = 'REC-'.date('Y').'-'.str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $receipt->generated_at = now();
        });
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
