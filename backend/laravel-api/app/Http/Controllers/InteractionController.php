<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogInteractionRequest;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\Relationship;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    private const SCORE_WEIGHTS = [
        'view' => 1,
        'reaction' => 2,
        'reply' => 3,
    ];

    public function store(LogInteractionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $post = Post::findOrFail($validated['post_id']);
        $type = $validated['type'];

        if ($type === 'reaction') {
            $existing = Interaction::where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->where('type', 'reaction')
                ->first();

            if ($existing) {
                $existing->delete();

                $relationship = Relationship::where('user_id', $user->id)
                    ->where('target_user_id', $post->user_id)
                    ->first();

                if ($relationship) {
                    $relationship->decrement('score', 2);
                }

                return response()->json([
                    'data' => ['action' => 'removed'],
                ]);
            }
        }

        Interaction::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => $type,
        ]);

        $relationship = Relationship::firstOrCreate(
            [
                'user_id' => $user->id,
                'target_user_id' => $post->user_id,
            ],
            ['score' => 0]
        );

        $relationship->increment('score', self::SCORE_WEIGHTS[$type]);

        if ($post->embedding !== null) {
            $postEmbedding = $post->embedding->toArray();

            if ($user->interest_vector === null) {
                $user->interest_vector = $postEmbedding;
            } else {
                $currentVector = $user->interest_vector->toArray();

                $newVector = [];
                for ($i = 0; $i < count($currentVector); $i++) {
                    $newVector[] = 0.9 * $currentVector[$i] + 0.1 * ($postEmbedding[$i] ?? 0);
                }
                $user->interest_vector = $newVector;
            }

            $user->save();
        }

        return response()->json([
            'data' => [
                'action' => 'created',
                'type' => $type,
            ],
        ], 201);
    }
}
