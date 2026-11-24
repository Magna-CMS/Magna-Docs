<?php

declare(strict_types=1);

namespace Magna\Docs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Magna\Docs\Support\DocTree;

class DocPageTranslation extends Model
{
    protected $table = 'docs_page_translations';

    protected $fillable = [
        'doc_page_id',
        'locale',
        'title',
        'excerpt',
        'meta_title',
        'meta_description',
        'content',
    ];

    protected static function booted(): void
    {
        // The sidebar shows translated titles; a cached tree must not outlive an edit.
        static::saved(fn () => DocTree::flush());
        static::deleted(fn () => DocTree::flush());
    }

    /** @return BelongsTo<DocPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(DocPage::class, 'doc_page_id');
    }
}
