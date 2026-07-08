<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Services\FeedRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(
        private FeedRankingService $feedRankingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $result = $this->feedRankingService->getFeed($request->user(), $page, $perPage);

        return response()->json([
            'data' => PostResource::collection($result['posts']),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }
}
