<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FishSize;
use Illuminate\Http\JsonResponse;

class FishSizeController extends Controller
{
    public function index(): JsonResponse
    {
        $fishSizes = FishSize::query()
            ->where('is_active', true)
            ->whereHas('commodity', fn ($query) => $query
                ->where('code', 'bandeng')
                ->where('is_active', true))
            ->orderBy('min_weight_gram')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'min_weight_gram', 'max_weight_gram']);

        return response()->json([
            'success' => true,
            'message' => 'Data ukuran bandeng berhasil diambil.',
            'data' => $fishSizes,
        ]);
    }
}
