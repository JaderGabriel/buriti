@extends('layouts.site')

@section('title', 'BURI-TI — Tecnologia para Pessoas')

@section('content')
    @include('site.partials.hero')
    @include('site.partials.about')
    @include('site.partials.services')
    @include('site.partials.expertise')
    @include('site.partials.projects')
    @include('site.partials.cta')
    @include('site.partials.contact')
@endsection
