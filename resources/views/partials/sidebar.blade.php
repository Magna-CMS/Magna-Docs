@forelse($nodes as $node)
    @if(empty($node['slug']) && !empty($node['children']))
        {{-- Collection group — collapsible accordion --}}
        <div class="collapsible-wrapper">
            <button class="sidebar-toggle-btn w-full flex items-center justify-between py-1 text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-zinc-200 hover:text-brand dark:hover:text-brand transition-colors">
                <span class="flex items-center gap-1.5">
                    @if(!empty($node['icon']))
                        @php
                            try {
                                echo svg('heroicon-o-'.$node['icon'], 'h-3.5 w-3.5 shrink-0', [
                                    'style' => !empty($node['color']) ? 'color: '.$node['color'] : '',
                                ])->toHtml();
                            } catch (\BladeUI\Icons\Exceptions\SvgNotFound $e) {
                                // Unknown icon name entered by an admin — skip silently rather than break the sidebar.
                            }
                        @endphp
                    @endif
                    <span>{{ $node['title'] }}</span>
                </span>
                <svg class="arrow-icon h-3 w-3 transform transition-transform duration-200 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <div class="collapsible-content show ml-1 mt-1 border-l border-slate-100 dark:border-zinc-800 pl-0.5">
                <ul class="space-y-1 pt-1">
                    @include('docs::partials.sidebar-links', ['items' => $node['children'], 'currentSlug' => $currentSlug])
                </ul>
            </div>
        </div>
    @else
        {{-- Standalone page (uncategorised), optionally with nested children --}}
        <ul class="space-y-1">
            @include('docs::partials.sidebar-links', ['items' => [$node], 'currentSlug' => $currentSlug])
        </ul>
    @endif
@empty
    <p class="text-sm text-slate-400 dark:text-zinc-500 px-1">No pages yet.</p>
@endforelse
