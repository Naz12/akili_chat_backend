@extends('layouts.admin')
@section('title', 'Edit Subscription')

@section('content')
    <h2 class="mb-3">Edit Subscription</h2>
    @include('admin.subscriptions._form', ['subscription' => $subscription])
@endsection
