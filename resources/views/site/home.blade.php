@extends('layouts.site')

@section('title', $product['name'].' — '.$product['tagline'])

@section('content')
    @include('site.partials.hero')
    @include('site.partials.method')
    @include('site.partials.about')
    @include('site.partials.services')
    @include('site.partials.expertise')
    @include('site.partials.projects')
    @include('site.partials.quotes')
    @include('site.partials.team')
    @foreach(($homeTestimonials ?? collect())->filter(fn ($quote) => $quote->project_id)->groupBy('project_id') as $projectQuotes)
        @include('site.partials.quote-dialog', [
            'project' => $projectQuotes->first()?->project,
            'quotes' => $projectQuotes,
        ])
    @endforeach
    @include('site.partials.contact')
@endsection
