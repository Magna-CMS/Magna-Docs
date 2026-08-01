<?php

declare(strict_types=1);

namespace Magna\Docs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DocCollection extends Model
{
    protected $table = 'doc_collections';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'icon',
        'color',
        'order',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Transient (non-persisted) — the collection's order position before the
     * current save, captured so `saved()` knows how far it moved and which
     * siblings need to shift. Not a DB column; declared as a real property so
     * Eloquent's attribute magic doesn't intercept it.
     */
    public ?int $orderBeforeSave = null;

    protected static function booted(): void
    {
        // New record: treat it as inserting at the end, so if its typed
        // `order` is actually earlier than that, saved() shifts siblings down.
        static::creating(function (DocCollection $collection): void {
            $collection->orderBeforeSave = static::query()->count();
        });

        // Existing record: only track a move when `order` itself changed.
        static::updating(function (DocCollection $collection): void {
            if ($collection->isDirty('order')) {
                $collection->orderBeforeSave = (int) $collection->getOriginal('order');
            }
        });

        static::saved(function (DocCollection $collection): void {
            static::applyReorder($collection);
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(DocPage::class, 'collection_id')->orderBy('order');
    }

    /**
     * Move every other collection out of the way of this one's new position,
     * like reordering a list: shift the range between the old and new spot by
     * one, then normalize everything to gapless unique integers. Uses raw DB
     * updates — not Eloquent saves — so this can't re-fire `saved` itself.
     */
    private static function applyReorder(DocCollection $collection): void
    {
        $old = $collection->orderBeforeSave;
        $new = (int) $collection->order;

        if ($old !== null && $old !== $new) {
            $shift = DB::table('doc_collections')->where('id', '!=', $collection->id);

            if ($new > $old) {
                $shift->whereBetween('order', [$old, $new])->decrement('order');
            } else {
                $shift->whereBetween('order', [$new, $old])->increment('order');
            }
        }

        static::resequence();
    }

    /**
     * Collapse `order` down to gapless, unique sequential integers (0, 1, 2, ...)
     * ordered by (order, id), so two collections can never tie on the same value
     * and produce a non-deterministic frontend sort.
     */
    public static function resequence(): void
    {
        static::query()
            ->orderBy('order')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id, int $position): void {
                DB::table('doc_collections')->where('id', $id)->update(['order' => $position]);
            });
    }
}
