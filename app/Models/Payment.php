<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference', 'booking_id', 'amount', 'currency', 'payment_method',
        'status', 'transaction_id', 'gateway_response', 'gateway_data', 'paid_at',
    ];

    protected $casts = [
        'gateway_data' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->payment_reference = 'PAY-'.strtoupper(uniqid());
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
