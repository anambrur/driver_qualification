@extends('layouts.main-layout')

@section('title', 'Create Subscription Plan')

@section('content')
<div class="p-4 sm:p-6">

    <div class="mb-6 flex flex-col justify-between items-start">
        <a href="{{ route('admin.plans.index') }}" class="text-sm text-gray-500 hover:underline mb-2 flex items-center">
            ← Back to Plans
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Plan</h1>
        <p class="text-sm text-gray-500 mt-1">Configure pricing, duration, and features for a new subscription plan.</p>
    </div>

    @if($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 rounded-md">
            <h3 class="font-bold mb-2">Please fix the following errors:</h3>
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.plans.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        @csrf
        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {{-- Basic Details --}}
                <div class="md:col-span-2 border-b border-gray-100 dark:border-gray-700 pb-4 mb-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           oninput="document.getElementById('slug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '')">
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug <span class="text-red-500">*</span></label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug') }}" required 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="2" 
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                </div>

                {{-- Pricing & Cycle --}}
                <div class="md:col-span-2 border-b border-gray-100 dark:border-gray-700 pb-4 mt-4 mb-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing & Billing Cycle</h2>
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price <span class="text-red-500">*</span></label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price', '0.00') }}" required 
                               class="pl-7 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Currency <span class="text-red-500">*</span></label>
                    <input type="text" name="currency" id="currency" value="{{ old('currency', 'USD') }}" required maxlength="3"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 uppercase">
                </div>

                <div>
                    <label for="billing_cycle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Billing Cycle <span class="text-red-500">*</span></label>
                    <select name="billing_cycle" id="billing_cycle" required 
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="monthly" {{ old('billing_cycle') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="yearly" {{ old('billing_cycle') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                        <option value="lifetime" {{ old('billing_cycle') === 'lifetime' ? 'selected' : '' }}>Lifetime</option>
                        <option value="trial" {{ old('billing_cycle') === 'trial' ? 'selected' : '' }}>Trial Only</option>
                    </select>
                </div>

                <div>
                    <label for="duration_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duration (Days) <span class="text-red-500">*</span></label>
                    <input type="number" min="1" name="duration_days" id="duration_days" value="{{ old('duration_days', 30) }}" required 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Total length of the billing cycle (e.g. 30 for monthly, 365 for yearly).</p>
                </div>

                {{-- Limits & Options --}}
                <div class="md:col-span-2 border-b border-gray-100 dark:border-gray-700 pb-4 mt-4 mb-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Limits & Display Options</h2>
                </div>

                <div>
                    <label for="trial_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trial Period (Days)</label>
                    <input type="number" min="0" name="trial_days" id="trial_days" value="{{ old('trial_days', 0) }}" 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Set to 0 if no trial is offered before billing starts.</p>
                </div>

                <div>
                    <label for="max_users" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Users / Employees limit</label>
                    <input type="number" min="1" name="max_users" id="max_users" value="{{ old('max_users') }}" 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for unlimited.</p>
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                    <input type="number" min="0" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" 
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-3 py-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Plan is Active (visible to users)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Highlight as Featured/Popular</span>
                    </label>
                </div>

                {{-- Features Array --}}
                <div class="md:col-span-2 border-b border-gray-100 dark:border-gray-700 pb-4 mt-4 mb-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Plan Features</h2>
                    <p class="text-sm text-gray-500">These will be displayed as bullet points on the pricing page.</p>
                </div>

                <div class="md:col-span-2" x-data="featuresManager()">
                    <div id="features_container" class="space-y-3 mb-4">
                        <template x-for="(feature, index) in features" :key="index">
                            <div class="flex items-center gap-2">
                                <div class="flex-grow">
                                    <input type="text" name="features[]" x-model="features[index]" placeholder="e.g. Access to all basic compliance tools" 
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <button type="button" @click="removeFeature(index)" class="p-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-md transition-colors" title="Remove">
                                   ✕
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addFeature()" class="px-4 py-2 border border-blue-600 text-blue-600 hover:bg-blue-50 rounded-md text-sm font-medium transition-colors">
                        + Add Feature
                    </button>
                </div>

            </div>
        </div>

        <div class="p-5 bg-gray-50 dark:bg-gray-750 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3 rounded-b-xl">
            <a href="{{ route('admin.plans.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900 font-medium">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-md transition-colors shadow-sm">
                Create Plan
            </button>
        </div>
    </form>
</div>

<!-- Include Alpine.js for Features array handling -->
<script src="//unpkg.com/alpinejs" defer></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('featuresManager', () => ({
            features: {!! old('features') ? json_encode(old('features')) : '[""]' !!},
            addFeature() {
                this.features.push('');
            },
            removeFeature(index) {
                this.features.splice(index, 1);
                if (this.features.length === 0) {
                    this.features.push('');
                }
            }
        }));
    });
</script>
@endsection
