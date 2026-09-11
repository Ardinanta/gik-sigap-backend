<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(['district'])],
        ]);

        $locations = Location::query()
            ->where('is_active', true)
            ->when(
                $validated['type'] ?? null,
                fn ($query, string $type) => $query->where('type', $type),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        return response()->json([
            'success' => true,
            'message' => 'Data lokasi berhasil diambil.',
            'data' => $locations,
        ]);
    }
}
