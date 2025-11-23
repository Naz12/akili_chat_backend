@extends('layouts.admin')

@section('content')
    <h2>Edit Plan</h2>
    @include('admin.plans._form', ['plan' => $plan])
@endsection

@include('admin.plans.partial.estimation_script')
