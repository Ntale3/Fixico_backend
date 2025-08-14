<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'location',
        'country',
        'packages',
        'price_per_person',
        'max_capacity',
        'start_date',
        'end_date',
        'duration_days',
        'images',
        'amenities',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'packages' => 'array',
        'images' => 'array',
        'amenities' => 'array',
        'price_per_person' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * A destination can have many bookings
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }



    public function reviews()
    {
        return $this->hasMany(Review::class)->with('user');
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    public function reviewsCount()
    {
        return $this->reviews()->count();
    }

    /**
     * Calculate available spots for the destination
     */
    public function getAvailableSpotsAttribute()
    {
        $bookedSpots = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->sum('number_of_travelers');

        return $this->max_capacity - $bookedSpots;
    }
}
