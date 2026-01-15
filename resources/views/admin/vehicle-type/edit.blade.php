@extends('layouts.main-layout')

@section('title', 'Edit Vehicle Type')

@section('content')
    <div class="p-4 mx-auto max-w-4xl">
        <!-- Simple Edit Form -->
        <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800">
            <h2 class="mb-6 text-2xl font-bold text-gray-800 dark:text-white">Edit Vehicle Type</h2>

            <form id="quickEditForm" method="POST" action="{{ route('admin.vehicle.type.update', $vehicleType->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-6">
                    <label for="name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Vehicle Type Name
                    </label>
                    <input type="text" id="name" name="name" value="{{ $vehicleType->name }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <div id="name_error" class="mt-1 text-sm text-red-600"></div>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.vehicle.type.index') }}"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#quickEditForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.text();

                // Show loading
                submitBtn.prop('disabled', true).text('Updating...');

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    headers: {
                        'X-HTTP-Method-Override': 'PUT'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            window.location.href = '{{ route('admin.vehicle.type.index') }}';
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).text(originalText);

                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('#' + key + '_error').text(value[0]);
                            });
                        } else {
                            alert('An error occurred. Please try again.');
                        }
                    }
                });
            });
        });
    </script>
@endsection
