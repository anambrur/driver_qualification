@extends('layouts.main-layout')

@section('title', 'Edit driver')

@section('content')
    @include('admin.driver.form.page', [
        'action' => route('admin.driver.update', $form->driver->id),
        'title' => 'Edit driver',
        'description' => 'Step 1 of the application: the driver’s details, license and history.',
    ])
@endsection
