<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
class BlogController extends Controller
{

  /**
     * Display a listing of published blogs
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 12);
            $perPage = min($perPage, 50); // Max 50 items per page

            $query = Blog::published()
                ->with(['user:id,name,email'])
                ->withCount(['likes', 'allComments'])
                ->latest('approved_at');

            // Apply filters
            if ($request->filled('location')) {
                $query->byLocation($request->location);
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('tags')) {
                $tags = is_array($request->tags) ? $request->tags : [$request->tags];
                $query->where(function($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }

            // Sort options
            $sortBy = $request->get('sort_by', 'latest');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('views_count', 'desc');
                    break;
                case 'most_liked':
                    $query->withCount('likes as likes_count')->orderBy('likes_count', 'desc');
                    break;
                case 'oldest':
                    $query->oldest('approved_at');
                    break;
                default: // 'latest'
                    $query->latest('approved_at');
            }

            $blogs = $query->paginate($perPage);

            // Transform data for frontend
            $blogs->getCollection()->transform(function ($blog) {
                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                    'excerpt' => $blog->excerpt,
                    'featured_image' => $blog->featured_image ? Storage::url($blog->featured_image) : null,
                    'location' => $blog->location,
                    'tags' => $blog->tags ?? [],
                    'status' => $blog->status,
                    'views_count' => $blog->views_count,
                    'likes_count' => $blog->likes_count,
                    'comments_count' => $blog->all_comments_count,
                    'published_date' => $blog->approved_at?->format('Y-m-d'),
                    'published_at' => $blog->approved_at?->toISOString(),
                    'created_at' => $blog->created_at->toISOString(),
                    'author' => [
                        'id' => $blog->user->id,
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Blogs retrieved successfully',
                'data' => $blogs->items(),
                'meta' => [
                    'current_page' => $blogs->currentPage(),
                    'last_page' => $blogs->lastPage(),
                    'per_page' => $blogs->perPage(),
                    'total' => $blogs->total(),
                    'from' => $blogs->firstItem(),
                    'to' => $blogs->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blogs',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }


    /**
     * Show a specific blog post
     */
    public function show(Request $request, Blog $blog)
    {
        try {
            abort_if(!$blog->isApproved(), 404, 'Blog post not found or not approved');

            // Increment views (with IP-based throttling for SPA)
            $viewKey = 'blog_view_' . $blog->id . '_' . $request->ip();
            if (!cache()->has($viewKey)) {
                $blog->incrementViews();
                cache()->put($viewKey, true, now()->addHours(1)); // 1 hour cooldown
            }

            // Load relationships optimized for API
            $blog->load([
                'user:id,name,email',
                'comments' => function($query) {
                    $query->approved()
                        ->whereNull('parent_id') // Only top-level comments
                        ->with([
                            'user:id,name',
                            'approvedReplies' => function($replyQuery) {
                                $replyQuery->with(['user:id,name'])
                                    ->withCount('likes')
                                    ->latest()
                                    ->limit(5); // Limit replies for performance
                            }
                        ])
                        ->withCount(['likes', 'allReplies'])
                        ->latest()
                        ->limit(20); // Limit comments for initial load
                }
            ])->loadCount(['likes', 'allComments']);

            // Check if current user liked this blog
            $isLiked = false;
            if (auth('sanctum')->check()) {
                $isLiked = $blog->isLikedBy(auth('sanctum')->user());
            }

            // Transform for API response
            $blogData = [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'content' => $blog->content,
                'excerpt' => $blog->excerpt,
                'featured_image' => $blog->featured_image ? Storage::url($blog->featured_image) : null,
                'images' => $blog->images ? array_map(fn($img) => Storage::url($img), $blog->images) : [],
                'location' => $blog->location,
                'latitude' => $blog->latitude,
                'longitude' => $blog->longitude,
                'tags' => $blog->tags ?? [],
                'status' => $blog->status,
                'views_count' => $blog->views_count,
                'likes_count' => $blog->likes_count,
                'comments_count' => $blog->all_comments_count,
                'is_liked' => $isLiked,
                'published_date' => $blog->approved_at?->format('Y-m-d'),
                'published_at' => $blog->approved_at?->toISOString(),
                'created_at' => $blog->created_at->toISOString(),
                'updated_at' => $blog->updated_at->toISOString(),
                'author' => [
                    'id' => $blog->user->id,
                    'name' => $blog->user->name,
                    'email' => $blog->user->email,
                ],
                'comments' => $blog->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'likes_count' => $comment->likes_count,
                        'replies_count' => $comment->all_replies_count,
                        'created_at' => $comment->created_at->toISOString(),
                        'author' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                        ],
                        'replies' => $comment->approvedReplies->map(function ($reply) {
                            return [
                                'id' => $reply->id,
                                'content' => $reply->content,
                                'likes_count' => $reply->likes_count,
                                'created_at' => $reply->created_at->toISOString(),
                                'author' => [
                                    'id' => $reply->user->id,
                                    'name' => $reply->user->name,
                                ],
                            ];
                        }),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Blog retrieved successfully',
                'data' => $blogData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blog',
                'error' => config('APP_DEBUG') ? $e->getMessage() : 'Blog not found'
            ], $e->getCode() ?: 500);
        }
    }


    /**
     * Store a new blog post
     *
     * This method handles both admin and user submissions.
     * Admins can publish immediately, users submit for approval.
     */

    /**
     * Store a new blog post
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:blogs,title',
                'content' => 'required|string|min:100',
                'location' => 'required|string|max:255',
                'excerpt' => 'nullable|string|max:500',
                'featured_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
                'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:2048',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            DB::beginTransaction();

            // Handle featured image upload
            if ($request->hasFile('featured_image')) {
                $validated['featured_image'] = $request->file('featured_image')
                    ->store('blogs/featured', 'public');
            }

            // Handle additional images upload
            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $imagePaths[] = $image->store('blogs/gallery', 'public');
                }
                $validated['images'] = $imagePaths;
            }

            // Set user and approval status
            $validated['user_id'] = auth('sanctum')->id();

            if (auth('sanctum')->user()->isAdmin()) {
                $validated['status'] = 'approved';
                $validated['approved_by'] = auth('sanctum')->id();
                $validated['approved_at'] = now();
            } else {
                $validated['status'] = 'pending';
            }

            $blog = Blog::create($validated);
            $blog->load('user:id,name,email');

            DB::commit();

            // Transform response
            $blogData = [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'content' => $blog->content,
                'excerpt' => $blog->excerpt,
                'featured_image' => $blog->featured_image ? Storage::url($blog->featured_image) : null,
                'images' => $blog->images ? array_map(fn($img) => Storage::url($img), $blog->images) : [],
                'location' => $blog->location,
                'tags' => $blog->tags ?? [],
                'status' => $blog->status,
                'created_at' => $blog->created_at->toISOString(),
                'author' => [
                    'id' => $blog->user->id,
                    'name' => $blog->user->name,
                    'email' => $blog->user->email,
                ],
            ];

            $message = auth('sanctum')->user()->isAdmin()
                ? 'Blog post published successfully!'
                : 'Blog post submitted for approval!';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $blogData
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded files if blog creation fails
            if (isset($validated['featured_image'])) {
                Storage::disk('public')->delete($validated['featured_image']);
            }
            if (isset($validated['images'])) {
                Storage::disk('public')->delete($validated['images']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create blog post',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update an existing blog post
     */

     public function update(Request $request, Blog $blog)
    {
        try {
            abort_if(!$blog->canBeEditedBy(auth('sanctum')->user()), 403, 'Unauthorized action');

            $validated = $request->validate([
                'title' => 'required|string|max:255|unique:blogs,title,' . $blog->id,
                'content' => 'required|string|min:100',
                'location' => 'required|string|max:255',
                'excerpt' => 'nullable|string|max:500',
                'featured_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
                'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:2048',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            DB::beginTransaction();

            // Handle featured image replacement
            if ($request->hasFile('featured_image')) {
                if ($blog->featured_image) {
                    Storage::disk('public')->delete($blog->featured_image);
                }
                $validated['featured_image'] = $request->file('featured_image')
                    ->store('blogs/featured', 'public');
            }

            // If not admin, reset to pending status
            if (!auth('sanctum')->user()->isAdmin() && $blog->isApproved()) {
                $validated['status'] = 'pending';
                $validated['approved_by'] = null;
                $validated['approved_at'] = null;
            }

            $blog->update($validated);
            $blog->load('user:id,name,email');

            DB::commit();

            // Transform response
            $blogData = [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'content' => $blog->content,
                'excerpt' => $blog->excerpt,
                'featured_image' => $blog->featured_image ? Storage::url($blog->featured_image) : null,
                'images' => $blog->images ? array_map(fn($img) => Storage::url($img), $blog->images) : [],
                'location' => $blog->location,
                'tags' => $blog->tags ?? [],
                'status' => $blog->status,
                'updated_at' => $blog->updated_at->toISOString(),
                'author' => [
                    'id' => $blog->user->id,
                    'name' => $blog->user->name,
                    'email' => $blog->user->email,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Blog post updated successfully',
                'data' => $blogData
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update blog post',
                'error' => config('APP_DEBUG') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }


    /**
     * Toggle like status for a blog post
     *
     * This method allows users to like or unlike a blog post.
     * It returns the updated like status and total likes count.
     *
     * @param Blog $blog
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */

    public function toggleLike(Blog $blog)
    {
        try {
            abort_if(!$blog->isApproved(), 404, 'Blog post not found');

            $user = auth('sanctum')->user();

            // Check if already liked
            $existingLike = $blog->likes()->where('user_id', $user->id)->first();

            if ($existingLike) {
                $existingLike->delete();
                $liked = false;
            } else {
                $blog->likes()->create(['user_id' => $user->id]);
                $liked = true;
            }

            // Get fresh count
            $totalLikes = $blog->likes()->count();

            return response()->json([
                'success' => true,
                'message' => $liked ? 'Blog liked successfully' : 'Blog unliked successfully',
                'data' => [
                    'liked' => $liked,
                    'total_likes' => $totalLikes
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle like',
                'error' => config('APP_DEBUG') ? $e->getMessage() : 'Server error'
            ], 500);
        }

    }


    /**
     * Get current user's blogs
     * *
     */
    public function myBlogs(Request $request)
    {
        try {
            $perPage = min($request->get('per_page', 10), 50);

            $blogs = Blog::where('user_id', auth('sanctum')->id())
                ->with(['user:id,name'])
                ->withCount(['likes', 'allComments'])
                ->latest()
                ->paginate($perPage);

            // Transform data
            $blogs->getCollection()->transform(function ($blog) {
                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                    'excerpt' => $blog->excerpt,
                    'featured_image' => $blog->featured_image ? Storage::url($blog->featured_image) : null,
                    'location' => $blog->location,
                    'status' => $blog->status,
                    'views_count' => $blog->views_count,
                    'likes_count' => $blog->likes_count,
                    'comments_count' => $blog->all_comments_count,
                    'created_at' => $blog->created_at->toISOString(),
                    'updated_at' => $blog->updated_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'User blogs retrieved successfully',
                'data' => $blogs->items(),
                'meta' => [
                    'current_page' => $blogs->currentPage(),
                    'last_page' => $blogs->lastPage(),
                    'per_page' => $blogs->perPage(),
                    'total' => $blogs->total(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blogs',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }


    /**
 * Store a new comment for a specific blog post
 *
 * @param Request $request
 * @param Blog $blog
 * @return \Illuminate\Http\JsonResponse
 */
public function storeComment(Request $request, Blog $blog)
{
    try {
        // Ensure the blog is approved
        abort_if(!$blog->isApproved(), 404, 'Blog post not found or not approved');

        // Validate the request
        $validated = $request->validate([
            'content' => 'required|string|min:2|max:1000',
        ]);

        // Begin transaction
        DB::beginTransaction();

        // Create the comment
        $comment = $blog->comments()->create([
            'user_id' => auth('sanctum')->id(),
            'content' => $validated['content']
        ]);

        // Load relationships for response
        $comment->load('user:id,name');

        // Commit transaction
        DB::commit();

        // Transform response
        $commentData = [
            'id' => $comment->id,
            'content' => $comment->content,
            'likes_count' => 0, // New comment starts with 0 likes
            'replies_count' => 0, // New comment starts with 0 replies
            'created_at' => $comment->created_at->toISOString(),
            'author' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ],
            'replies' => [], // No replies for a new comment
        ];

        $message = auth('sanctum')->user()->isAdmin()
            ? 'Comment added successfully!'
            : 'Comment submitted for approval!';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $commentData,
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Failed to add comment',
            'error' => config('app.debug') ? $e->getMessage() : 'Server error',
        ], 500);
    }
}



    /**
     * Delete blog post (soft delete)
     *
     */

    public function destroy(Blog $blog)
    {
        try {
            abort_if(!$blog->canBeEditedBy(auth('sanctum')->user()), 403, 'Unauthorized action');

            $blog->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blog post deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blog post',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }



}
