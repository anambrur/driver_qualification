@extends('layouts.main-layout')

@section('title', 'Edit Company')

@section('content')
    <div class="p-4 mx-auto max-w-6xl md:p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-foreground dark:text-white/90">Edit Company</h2>
            <p class="text-muted-foreground mt-2">Update company information</p>
        </div>

        @if ($errors->has('system_error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <x-ui.icon name="circle-alert" class="inline-block size-4 align-[-0.125em] text-red-500 mr-3" />
                    <p class="text-red-700 dark:text-red-400">{{ $errors->first('system_error') }}</p>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.settings.company.update', $company->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Company Information Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="building-2" class="inline-block size-4 align-[-0.125em] mr-2" />Company Information
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <!-- Company Name -->
                            <div>
                                <label for="company_name"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Company Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="company_name" name="company_name" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="Enter company name"
                                    value="{{ old('company_name', $company->company_name) }}" />
                                @error('company_name')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required
                                    readonly
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="company@example.com" value="{{ old('email', $company->email) }}" />
                                @error('email')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Phone
                                </label>
                                <input type="text" id="phone" name="phone"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="+1 (555) 123-4567" value="{{ old('phone', $company->phone) }}" />
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Fax -->
                            <div>
                                <label for="fax"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Fax
                                </label>
                                <input type="text" id="fax" name="fax"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="+1 (555) 123-4568" value="{{ old('fax', $company->fax) }}" />
                                @error('fax')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="status"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <div class="relative z-20 bg-transparent">
                                    <select id="status" name="status" required
                                        disabled
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full appearance-none rounded-lg border border-input bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="" class="text-foreground">
                                            Select Status
                                        </option>
                                        <option value="active"
                                            {{ old('status', $company->status) == 'active' ? 'selected' : '' }}
                                            class="text-foreground">
                                            Active
                                        </option>
                                        <option value="inactive"
                                            {{ old('status', $company->status) == 'inactive' ? 'selected' : '' }}
                                            class="text-foreground">
                                            Inactive
                                        </option>
                                    </select>
                                    <span
                                        class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-muted-foreground">
                                        <x-ui.icon name="chevron-down" class="inline-block size-4 align-[-0.125em]" />
                                    </span>
                                </div>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Logo Upload Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="image" class="inline-block size-4 align-[-0.125em] mr-2" />Company Logo
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <!-- Current Logo Preview -->
                            @if ($company->logo_path)
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                                        Current Logo
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <img src="{{ asset('storage/' . $company->logo_path) }}"
                                            alt="{{ $company->company_name }}" class="w-16 h-16 rounded-lg object-cover">
                                        <div>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="remove_logo" value="1"
                                                    class="rounded border-input text-primary focus:ring-primary">
                                                <span class="ml-2 text-sm text-muted-foreground">Remove current
                                                    logo</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- New Logo Upload -->
                            <div>
                                <label for="logo"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    {{ $company->logo_path ? 'Upload New Logo' : 'Upload Logo' }}
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="logo"
                                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-input border-dashed rounded-lg cursor-pointer bg-muted/50 dark:hover:bg-bray-800 hover:bg-muted">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <x-ui.icon name="cloud-upload" class="inline-block size-6 align-[-0.125em] mb-3 text-muted-foreground" />
                                            <p class="mb-2 text-sm text-muted-foreground"><span
                                                    class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-muted-foreground">PNG, JPG, GIF, SVG (MAX.
                                                2MB)</p>
                                        </div>
                                        <input id="logo" name="logo" type="file" class="hidden"
                                            accept="image/*" />
                                    </label>
                                </div>
                                @error('logo')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Address Information Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="map-pin" class="inline-block size-4 align-[-0.125em] mr-2" />Address Information
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <!-- Address -->
                            <div>
                                <label for="address"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Address <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="address" name="address" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="123 Main Street" value="{{ old('address', $company->address) }}" />
                                @error('address')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- City -->
                            <div>
                                <label for="city"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    City <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="city" name="city" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="New York" value="{{ old('city', $company->city) }}" />
                                @error('city')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- State -->
                            <div>
                                <label for="state"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    State <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="state" name="state" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="NY" value="{{ old('state', $company->state_code) }}" />
                                @error('state')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- ZIP Code -->
                            <div>
                                <label for="zip"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    ZIP Code <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="zip" name="zip" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="10001" value="{{ old('zip', $company->zip) }}" />
                                @error('zip')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Description Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="align-left" class="inline-block size-4 align-[-0.125em] mr-2" />Company Description
                            </h3>
                        </div>
                        <div class="border-t border-border p-5 sm:p-6">
                            <div>
                                <label for="description"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Description
                                </label>
                                <textarea id="description" name="description" rows="5"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 w-full rounded-lg border border-input bg-transparent px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="Describe your company...">{{ old('description', $company->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-input bg-card px-4 py-2.5 text-sm font-medium text-foreground shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                    <x-ui.icon name="x" class="inline-block size-4 align-[-0.125em] mr-2" />Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                    <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />Update Company
                </button>
            </div>
        </form>
    </div>

    <script>
        // File upload preview
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You can add a preview functionality here if needed
                    console.log('File selected:', file.name);
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
@endsection
