@extends('docs::layout')

@section('content')
<article class="docs-prose max-w-none">
    <h1 class="text-3xl lg:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-4">{{ __('docs::docs.documentation', [], $currentLocale ?? 'en') }}</h1>
    <p class="text-lg text-slate-600 dark:text-zinc-300 leading-relaxed">
        {{ __('docs::docs.nothing_published', [], $currentLocale ?? 'en') }}
    </p>
</article>
@endsection
