<?php

namespace App\Http\Controllers;

use App\Models\Post;

class PostController extends FrontendController
{
    /**
     * @inheritDoc
     */
    protected function getService()
    {
        // TODO: Implement getService() method.
    }

    public function postDetail($slug)
    {
        $post = services()->postService()->where([
            'active' => Post::ACTIVE,
            'slug' => $slug
        ])->with('creator')->select('id', 'title', 'slug', 'description', 'avatar', 'created_at', 'author_id', 'content')->first();
        $tag_ids = $post->tags->pluck('id')->toArray();
        $postsRelated  = services()->postService()
            ->where(['active' => Post::ACTIVE])
            ->whereKeyNot($post->id)
            ->whereHas('tags', function ($query) use ($tag_ids) {
                $query->whereIn('tag_id', $tag_ids);
            })
            ->with('creator')->select('id', 'title', 'slug', 'description', 'avatar', 'created_at', 'author_id', 'content')->limit(3)->get();

        $viewData = [
            'post' => $post,
            'postsRelated' => $postsRelated
        ];
        return view('post.detail', $viewData);
    }

    public function listPost()
    {
        $posts = services()->postService()->where([
            'active' => Post::ACTIVE
        ])->with('creator')->select('title', 'slug', 'description', 'avatar', 'created_at', 'author_id')->orderByDesc('id')->paginate(12);
        $viewData = [
            'posts' => $posts
        ];
        return view('post.list', $viewData);
    }
}