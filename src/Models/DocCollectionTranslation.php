<?php

declare(strict_types=1);

namespace Magna\Docs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Magna\Docs\Support\DocTree;

class DocCollectionTranslation extends Model
{
    protected $table = 'docs_collection_translations';

    protected $fillable = [
        'doc_collection_id',
        'locale',
        'title',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DocTree::flush());
        static::deleted(fn () => DocTree::flush());
    }

    /** @return BelongsTo<DocCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(DocCollection::class, 'doc_collection_id');
    }
}
