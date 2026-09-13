<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnershipHistoryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'note' => $this->note,
            'changed_at' => $this->changed_at,
            'changed_by_name' => $this->whenLoaded('changedBy', fn () => $this->changedBy?->name),
        ];
    }
}
