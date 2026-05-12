<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'format' => $this->format,
            'isbn' => $this->isbn,
            'description' => $this->when($request->routeIs('api.books.show'), $this->description),
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'published_at' => optional($this->published_at)->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'user_name' => $review->relationLoaded('user') ? $review->user?->name : null,
                'created_at' => optional($review->created_at)->toIso8601String(),
            ])),
        ];
    }
}
