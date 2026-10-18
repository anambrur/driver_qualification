@extends('layouts.main-layout')

@section('title', 'Add driver')

@section('content')
    @include('admin.driver.form.page', [
        'action' => route('admin.driver.store'),
        'title' => 'Add driver',
        'description' => 'Step 1 of the application: the driver’s details, license and history.',
    ])
@endsection
