<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Blog;

class CommentController extends Controller
{
     public function store(Request $request, Blog $blog)
    {
        abort_if(!auth()->check(), 401);
        abort_if(!$blog->isApproved(), 404);

        $request->validate([
            'content' => 'required|min:3|max:1000',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        // If replying to a comment, verify it belongs to this blog
        if ($request->parent_id) {
            $parentComment = Comment::findOrFail($request->parent_id);
            abort_if($parentComment->blog_id !== $blog->id, 422, 'Invalid parent comment');
            abort_if(!$parentComment->canBeRepliedTo(), 422, 'Cannot reply to this comment');
        }

        $comment = Comment::create([
            'content' => $request->input('content'),
            'user_id' => auth()->id(),
            'blog_id' => $blog->id,
            'parent_id' => $request->parent_id,
            'is_approved' => true // or implement approval logic
        ]);

        $comment->load(['user', 'likes'])->loadCount(['likes', 'allReplies']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $comment,
                'message' => $comment->isReply() ? 'Reply added successfully!' : 'Comment added successfully!'
            ]);
        }

        return back()->with('success', 'Comment added successfully!');
    }

    public function toggleLike(Comment $comment)
    {
        abort_if(!auth()->check(), 401);
        abort_if(!$comment->blog->isApproved(), 404);

        $liked = auth()->user()->toggleLike($comment);

        return response()->json([
            'liked' => $liked,
            'total_likes' => $comment->fresh()->total_likes
        ]);
    }

    public function reply(Request $request, Comment $comment)
    {
        abort_if(!auth()->check(), 401);
        abort_if(!$comment->blog->isApproved(), 404);
        abort_if(!$comment->canBeRepliedTo(), 422, 'Cannot reply to this comment');

        $request->validate([
            'content' => 'required|min:3|max:1000'
        ]);

        $reply = $comment->addReply($request->input('content'), auth()->user());
        $reply->load(['user', 'likes'])->loadCount('likes');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'reply' => $reply,
                'message' => 'Reply added successfully!'
            ]);
        }

        return back()->with('success', 'Reply added successfully!');
    }

    public function destroy(Comment $comment)
    {
        abort_if(!$comment->canBeEditedBy(auth()->user()), 403);

        // If deleting a parent comment, also delete all replies
        if ($comment->isParentComment()) {
            $comment->replies()->delete();
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully!'
        ]);
    }
}
