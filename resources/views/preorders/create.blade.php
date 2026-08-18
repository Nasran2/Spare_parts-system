@extends('layouts.app')

@section('title', 'Create Pre-Order')
@section('page-title', 'Create Pre-Order')

@section('content')
    @include('preorders.form', ['formAction' => route('preorders.store'), 'formMethod' => 'POST'])
@endsection
