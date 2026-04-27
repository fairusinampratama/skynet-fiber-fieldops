<?php

namespace App\Models;

use App\Enums\PortStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdpPort extends Model
{
    protected $fillable = ['odp_asset_id', 'port_number', 'status', 'source_submission_id', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => PortStatus::class];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OdpAsset::class, 'odp_asset_id');
    }
}
