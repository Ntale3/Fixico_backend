<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
     use HasApiTokens, HasFactory, Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ... existing User model code ...

    // Relationships
    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    public function approvedBlogs()
    {
        return $this->hasMany(Blog::class, 'approved_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    // Helper Methods
    public function isAdmin()
    {
        return $this->role === 'admin' || $this->is_admin; // adjust based on your admin logic
    }

    public function canCreateBlog()
    {
        return true;
    }

    public function hasLiked($likeable)
    {
        return $this->likes()
            ->where('likeable_id', $likeable->id)
            ->where('likeable_type', get_class($likeable))
            ->exists();
    }

    public function toggleLike($likeable)
    {
        $existingLike = $this->likes()
            ->where('likeable_id', $likeable->id)
            ->where('likeable_type', get_class($likeable))
            ->first();

        if ($existingLike) {
            $existingLike->delete();
            return false; // unliked
        } else {
            $this->likes()->create([
                'likeable_id' => $likeable->id,
                'likeable_type' => get_class($likeable)
            ]);
            return true; // liked
        }
    }
}
