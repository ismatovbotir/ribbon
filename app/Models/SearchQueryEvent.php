<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['query', 'results_count', 'occurred_at'])]
class SearchQueryEvent extends Model
{
    // Eloquent's convention would guess `search_query_events` — the
    // migration names it `search_queries` (matches how it's actually
    // described everywhere else: a log of queries, not "query events").
    protected $table = 'search_queries';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
