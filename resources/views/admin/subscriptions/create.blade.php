@extends('layouts.admin')
@section('title', 'Assign New Subscription')

@section('content')
    <h2 class="mb-3">Assign New Subscription</h2>
    @include('admin.subscriptions._form', ['subscription' => null])
@endsection
