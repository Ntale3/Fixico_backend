<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    //
     use HasFactory;

    protected $fillable = [
        'content',
        'user_id',
        'blog_id',
        'parent_id',
        'is_approved'
    ];

    protected $casts = [
        'is_approved' => 'boolean'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }

    public function allReplies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function approvedReplies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->approved();
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeParentComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    // Helper Methods
    public function isReply()
    {
        return !is_null($this->parent_id);
    }

    public function isParentComment()
    {
        return is_null($this->parent_id);
    }

    public function canBeRepliedTo()
    {
        return $this->isParentComment(); // Only allow replies to parent comments (no nested replies)
    }

    public function canBeEditedBy(User $user)
    {
        return $this->user_id === $user->id;
    }

    public function getTotalLikesAttribute()
    {
        return $this->likes()->count();
    }

    public function getTotalRepliesAttribute()
    {
        return $this->allReplies()->count();
    }

    public function isLikedBy(User $user = null)
    {
        if (!$user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function addReply($content, User $user)
    {
        if (!$this->canBeRepliedTo()) {
            throw new \Exception('Cannot reply to this comment');
        }

        return $this->replies()->create([
            'content' => $content,
            'user_id' => $user->id,
            'blog_id' => $this->blog_id,
            'is_approved' => true // or set approval logic
        ]);
    }

    public function getThreadAttribute()
    {
        // Get the full comment thread (parent + all replies)
        if ($this->isReply()) {
            return $this->parent->replies()->approved()->with('user')->get();
        }

        return $this->replies()->approved()->with('user')->get();
    }
}
