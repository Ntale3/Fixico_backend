<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Blog;
class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::published()
            ->with(['user', 'likes'])
            ->withCount(['likes', 'allComments'])
            ->latest('approved_at')
            ->paginate(12);

        return view('blogs.index', compact('blogs'));
    }

    public function show(Blog $blog)
    {
        abort_if(!$blog->isApproved(), 404);

        $blog->incrementViews();

        // Load comments with their replies in a nested structure
        $blog->load([
            'user',
            'comments' => function($query) {
                $query->approved()->with([
                    'user',
                    'likes',
                    'approvedReplies' => function($replyQuery) {
                        $replyQuery->with(['user', 'likes'])->latest();
                    }
                ])->loadCount(['likes', 'allReplies']);
            }
        ])->loadCount(['likes', 'allComments']);

        return view('blogs.show', compact('blog'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:255',
            'content' => 'required|min:100',
            'location' => 'required|max:255',
            'featured_image' => 'required|image|max:2048',
            'images.*' => 'image|max:2048',
            'tags' => 'array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric'
        ]);

        $blogData = $request->all();
        $blogData['user_id'] = auth()->id();

        // Non-admin posts need approval
        if (!auth()->user()->isAdmin()) {
            $blogData['status'] = 'pending';
        } else {
            $blogData['status'] = 'approved';
            $blogData['approved_by'] = auth()->id();
            $blogData['approved_at'] = now();
        }

        $blog = Blog::create($blogData);

        return redirect()->route('blogs.show', $blog)
            ->with('success', auth()->user()->isAdmin()
                ? 'Blog post published successfully!'
                : 'Blog post submitted for approval!');
    }

    public function toggleLike(Blog $blog)
    {
        abort_if(!auth()->check(), 401);
        abort_if(!$blog->isApproved(), 404);

        $liked = auth()->user()->toggleLike($blog);

        return response()->json([
            'liked' => $liked,
            'total_likes' => $blog->fresh()->total_likes
        ]);
    }
}
