@extends('layouts.public')

@section('meta_title', __('landing.meta.title'))
@section('meta_description', __('landing.meta.description'))

@section('content')
    @include('landing.partials.hero')
    @include('landing.partials.services')
    @include('landing.partials.how-it-works')
    @include('landing.partials.screenshots')
    @include('landing.partials.become-driver')
    @include('landing.partials.faq')
    @include('landing.partials.contact')
@endsection
