@extends('layouts.admin')

@section('title', 'Create Plan')

@section('content')
    <h2 class="mb-4">Create New Plan</h2>
    @include('admin.plans._form', ['plan' => null, 'engines' => $engines])
@endsection

@include('admin.plans.partial.estimation_script')
