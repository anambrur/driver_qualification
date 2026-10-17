@extends('layouts.main-layout')

@section('title', 'Edit User')

@section('content')
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header Card -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Edit User</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ $user->email }}</p>
                    </div>
                    <a href="{{ route('users.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Back to Users
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <form action="{{ route('users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="p-6 space-y-6">
                        <!-- Name -->
                        <div class="max-w-md">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('name') border-red-500 ring-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Roles -->
                        <div class="border-t border-gray-200 pt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Roles</label>

                            @php($heldIds = old('roles', $user->roles->pluck('id')->all()))
                            @php($fixedRoles = $user->roles->whereNotIn('id', $roles->modelKeys()))

                            @if ($fixedRoles->isNotEmpty())
                                <p class="mb-3 text-sm text-gray-600">
                                    Also holds (you can't change these):
                                    @foreach ($fixedRoles as $locked)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-700">{{ $locked->name }}</span>
                                    @endforeach
                                </p>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @forelse ($roles as $role)
                                    <label
                                        class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors duration-200">
                                        <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                            class="mt-1 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                            @checked(in_array($role->id, $heldIds))>
                                        <span class="ml-3 block text-sm font-medium text-gray-700">{{ $role->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500">There are no roles you can assign.</p>
                                @endforelse
                            </div>
                            @error('roles')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex space-x-3">
                        <button type="submit"
                            class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                            Update User
                        </button>
                        <a href="{{ route('users.index') }}"
                            class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
