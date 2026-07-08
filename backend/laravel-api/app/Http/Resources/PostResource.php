<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ],
            'text' => $this->text,
            'image_url' => $this->image_url,
            'authenticity_score' => $this->authenticity_score,
            'created_at' => $this->created_at->toIso8601String(),
            'time_ago' => $this->created_at->diffForHumans(),
        ];

        if (isset($this->resource->similarity_score)) {
            $data['similarity_score'] = round((float) $this->resource->similarity_score, 4);
        }

        return $data;
    }
}
