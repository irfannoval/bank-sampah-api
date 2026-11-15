<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kartu extends Model
{
    public $timestamps = false;

    protected $table = 'kartu';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['dicetak_pada' => 'datetime'];
    }

    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class);
    }
}
