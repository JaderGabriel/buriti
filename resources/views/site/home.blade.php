@extends('layouts.site')

@section('title', $product['name'].' — '.$product['tagline'])

@section('content')
    @include('site.partials.hero')
    @include('site.partials.method')
    @include('site.partials.about')
    @include('site.partials.services')
    @include('site.partials.expertise')
    @include('site.partials.projects')
    @include('site.partials.team')
    @include('site.partials.contact')
@endsection
