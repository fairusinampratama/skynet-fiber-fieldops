<?php

namespace App\Models;

use App\Enums\PortStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdcPort extends Model
{
    protected $fillable = ['odc_asset_id', 'port_number', 'status', 'source_submission_id', 'updated_by'];

    protected function casts(): array
    {
        return ['status' => PortStatus::class];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OdcAsset::class, 'odc_asset_id');
    }
}
