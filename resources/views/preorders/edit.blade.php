@extends('layouts.app')

@section('title', 'Edit '.$preOrder->pre_order_number)
@section('page-title', 'Edit Pre-Order')

@section('content')
    @include('preorders.form', ['formAction' => route('preorders.update', $preOrder), 'formMethod' => 'PUT'])
@endsection
