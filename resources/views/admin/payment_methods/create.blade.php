@extends('layouts.admin')

@section('title', 'Add Payment Method')

@section('content')
    <h2 class="mb-3">Add New Payment Method</h2>
    @include('admin.payment_methods._form')
@endsection
