<x-filament-panels::page>
    @php($counts = $this->siteCounts())

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            'Collections' => $counts['collections'],
            'Pages' => $counts['pages'],
            'Translations' => $counts['translations'],
        ] as $label => $value)
            <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">What the archive contains</x-slot>

        <div class="prose prose-sm max-w-none dark:prose-invert">
            <p>
                An export is a single <code>.zip</code>: collections, pages (Markdown and metadata),
                every translation, and the images pages reference. Records point at each other by
                <strong>slug</strong>, never by database id, so an archive imports cleanly into a
                different install.
            </p>
            <p>
                Import matches on slug too. Choose <em>Update existing</em> to refresh a site from a
                newer export, <em>Skip existing</em> to only fill in what is missing, or
                <em>Keep both</em> to import conflicting pages under a suffixed slug. Run it with
                <em>Preview only</em> first to see the exact effect before anything is written.
            </p>
            <p class="text-gray-500 dark:text-gray-400">
                Very large sites can also use the CLI, which has no request time limit:
                <code>php artisan magna:docs:export</code> and
                <code>php artisan magna:docs:import path/to/archive.zip --dry-run</code>.
            </p>
        </div>
    </x-filament::section>

    @if ($lastReport)
        <x-filament::section>
            <x-slot name="heading">
                {{ $lastReport['dry_run'] ? 'Last preview (nothing was written)' : 'Last import' }}
            </x-slot>

            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($lastReport['counts'] as $bucket => $value)
                    <div class="flex items-baseline justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', $bucket)) }}</span>
                        <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($value) }}</span>
                    </div>
                @endforeach
            </div>

            @if (! empty($lastReport['errors']))
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                        {{ count($lastReport['errors']) }} item(s) needed attention
                    </h4>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($lastReport['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
