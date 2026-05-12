<?php

namespace App\Http\Resources\AI;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AIConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->resource->relationLoaded('messages')
            ? $this->messages->sortByDesc('created_at')->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'latest_message_preview' => $latest ? Str::limit((string) $latest->content, 120) : null,
            'message_count' => $this->whenCounted('messages'),
            'messages' => AIMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
