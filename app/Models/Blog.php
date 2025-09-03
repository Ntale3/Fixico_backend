<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    //
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'featured_image',
        'images',
        'location',
        'slug',
        'excerpt',
        'tags',
        'status',
        'admin_notes',
        'user_id',
        'approved_by',
        'approved_at',
        'views_count',
    ];

    protected $casts = [
        'images' => 'array',
        'tags' => 'array',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', "%{$location}%");
    }

    public function scopePublished($query)
    {
        return $query->approved()->where('approved_at', '<=', now());
    }

    // Accessors & Mutators
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value).'-'.time();
    }

    public function getExcerptAttribute($value)
    {
        return $value ?: Str::limit(strip_tags($this->content), 150);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    // Helper Methods
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function canBeEditedBy(User $user)
    {
        return $this->user_id === $user->id || $user->isAdmin();
    }

    public function needsApproval()
    {
        return ! $this->user->isAdmin() && $this->status === 'pending';
    }

    public function approve(User $admin)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $admin, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'admin_notes' => $notes,
        ]);
    }

    public function getTotalLikesAttribute()
    {
        return $this->likes()->count();
    }

    public function getTotalCommentsAttribute()
    {
        return $this->allComments()->count();
    }

    public function isLikedBy(?User $user = null)
    {
        if (! $user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }
}
