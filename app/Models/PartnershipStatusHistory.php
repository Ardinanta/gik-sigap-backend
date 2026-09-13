<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partnership_id', 'changed_by_user_id', 'from_status', 'to_status', 'note', 'changed_at'])]
class PartnershipStatusHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
