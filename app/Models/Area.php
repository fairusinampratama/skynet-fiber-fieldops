<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    protected $fillable = ['project_id', 'name', 'code', 'description'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
