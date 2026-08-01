@extends('docs::layout')

@section('content')

{{-- Breadcrumb --}}
@if(!empty($breadcrumb) && count($breadcrumb) > 1)
    <nav class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-zinc-400 mb-5" aria-label="Breadcrumb">
        <a href="{{ route('docs.web.index') }}" class="hover:text-brand transition-colors">Docs</a>
        @foreach($breadcrumb as $crumb)
            <span class="text-slate-300 dark:text-zinc-600">/</span>
            @if($crumb['slug'] !== $page->slug)
                <a href="{{ route('docs.web.show', $crumb['slug']) }}" class="hover:text-brand transition-colors">{{ $crumb['title'] }}</a>
            @else
                <span class="text-slate-700 dark:text-zinc-300">{{ $crumb['title'] }}</span>
            @endif
        @endforeach
    </nav>
@endif

<article class="docs-prose max-w-none">
    <h1 class="text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-4 scroll-mt-20">{{ $displayTitle ?? $page->title }}</h1>

    @if($page->featured_image && ($page->show_featured_image ?? true))
        <img class="docs-featured-image"
             src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($page->featured_image) }}"
             alt="{{ $page->title }}" loading="eager">
    @endif

    @if($page->excerpt)
        <p class="text-lg text-slate-600 dark:text-zinc-300 leading-relaxed mb-6">{{ $page->excerpt }}</p>
    @endif

    {!! $html !!}
</article>

{{-- Prev / Next --}}
@if($prev || $next)
    <div class="mt-12 pt-8 border-t border-slate-200 dark:border-zinc-800 flex justify-between gap-4">
        @if($prev)
            <a href="{{ route('docs.web.show', $prev->slug) }}" class="flex-1 max-w-[48%] rounded-xl border border-slate-200 dark:border-zinc-800 p-4 hover:border-brand transition-colors">
                <span class="block text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-zinc-500">← Previous</span>
                <span class="block text-sm font-semibold text-brand mt-1">{{ $prev->title }}</span>
            </a>
        @else
            <div></div>
        @endif
        @if($next)
            <a href="{{ route('docs.web.show', $next->slug) }}" class="flex-1 max-w-[48%] text-right rounded-xl border border-slate-200 dark:border-zinc-800 p-4 hover:border-brand transition-colors">
                <span class="block text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-zinc-500">Next →</span>
                <span class="block text-sm font-semibold text-brand mt-1">{{ $next->title }}</span>
            </a>
        @endif
    </div>
@endif

{{-- Was this helpful? --}}
<div class="mt-10 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-darkCard p-6 text-center" id="docsFeedback" data-slug="{{ $page->slug }}">
    <p class="text-sm text-slate-600 dark:text-zinc-400 mb-4">Was this page helpful?</p>
    <div class="flex justify-center gap-3">
        <button data-vote="yes" onclick="docsFeedback('yes')" class="feedback-btn inline-flex items-center gap-1.5 rounded-full border border-slate-200 dark:border-zinc-800 px-5 py-2 text-sm text-slate-600 dark:text-zinc-300 hover:border-green-500 hover:text-green-600 transition-colors">👍 Yes</button>
        <button data-vote="no" onclick="docsFeedback('no')" class="feedback-btn inline-flex items-center gap-1.5 rounded-full border border-slate-200 dark:border-zinc-800 px-5 py-2 text-sm text-slate-600 dark:text-zinc-300 hover:border-red-500 hover:text-red-600 transition-colors">👎 No</button>
    </div>
    <p class="hidden text-sm text-slate-500 dark:text-zinc-400 italic mt-3" id="feedbackThanks">Thanks for your feedback!</p>
    <p class="{{ ($helpfulCount ?? 0) > 0 ? '' : 'hidden' }} text-sm text-slate-500 dark:text-zinc-400 mt-3" id="feedbackCount">
        <span id="feedbackCountNumber">{{ $helpfulCount ?? 0 }}</span>
        <span id="feedbackCountWord">{{ Str::plural('person', $helpfulCount ?? 0) }}</span> found this page helpful.
    </p>
</div>

{{-- Footer meta --}}
<div class="mt-8 pt-6 border-t border-slate-200 dark:border-zinc-800 flex justify-between flex-wrap gap-2 text-xs text-slate-400 dark:text-zinc-500">
    <span class="inline-flex items-center gap-1.5">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        {{ $page->readingTimeMinutes() }} min read
    </span>
    <span>Last updated {{ $page->updated_at->toFormattedDateString() }}</span>
</div>

@endsection

@section('toc')
@forelse($toc as $item)
    <li><a href="#{{ $item['id'] }}" class="docs-toc-link {{ $item['level'] >= 3 ? 'sub' : '' }}">{{ $item['text'] }}</a></li>
@empty
    <li class="pl-3 text-xs text-slate-400 dark:text-zinc-500 italic">No sections on this page.</li>
@endforelse
@endsection
