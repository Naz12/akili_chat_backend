@extends('layouts.admin')

@section('title', 'Edit Payment Method')

@section('content')
    <h2 class="mb-3">Edit Payment Method</h2>
    @include('admin.payment_methods._form')
@endsection
