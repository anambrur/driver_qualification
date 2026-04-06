@extends('layouts.main-layout')

@section('title', 'Site Settings')

@section('content')
    <div class="p-4 mx-auto max-w-6xl md:p-6">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Site Settings</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your global application settings</p>
            </div>
            
            <nav x-data="{}" class="hidden sm:block">
                <ol class="flex items-center gap-2">
                    <li>
                        <a class="font-medium text-gray-600 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400" href="{{ route('admin.dashboard') }}">Dashboard /</a>
                    </li>
                    <li class="font-medium text-brand-500 dark:text-brand-400">Site Settings</li>
                </ol>
            </nav>
        </div>

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        @endif
        
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <div class="text-red-700 dark:text-red-400">
                        Please check the form below for errors.
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.settings.site.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column -->
                <div class="space-y-6">
                    
                    <!-- General Information Card -->
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-info-circle mr-2"></i>General Information
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <!-- Site Name -->
                            <div>
                                <label for="site_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Site Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="site_name" name="site_name" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="Enter site name" value="{{ old('site_name', $setting->site_name) }}" />
                                @error('site_name')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email Address -->
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Contact Email
                                </label>
                                <input type="email" id="email" name="email"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="contact@yoursite.com" value="{{ old('email', $setting->email) }}" />
                                @error('email')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Contact Phone
                                </label>
                                <input type="text" id="phone" name="phone"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="+1 (555) 123-4567" value="{{ old('phone', $setting->phone) }}" />
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Address -->
                            <div>
                                <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Physical Address
                                </label>
                                <input type="text" id="address" name="address"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="123 Main Street, City, State..." value="{{ old('address', $setting->address) }}" />
                                @error('address')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Social Links Card -->
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-share-alt mr-2"></i>Social Links
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <!-- Facebook -->
                            <div>
                                <label for="facebook_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Facebook URL
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="fab fa-facebook-f"></i>
                                    </span>
                                    <input type="url" id="facebook_url" name="facebook_url"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        placeholder="https://facebook.com/yoursite" value="{{ old('facebook_url', $setting->facebook_url) }}" />
                                </div>
                                @error('facebook_url')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Twitter -->
                            <div>
                                <label for="twitter_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Twitter URL
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="fab fa-twitter"></i>
                                    </span>
                                    <input type="url" id="twitter_url" name="twitter_url"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        placeholder="https://twitter.com/yoursite" value="{{ old('twitter_url', $setting->twitter_url) }}" />
                                </div>
                                @error('twitter_url')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- LinkedIn -->
                            <div>
                                <label for="linkedin_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    LinkedIn URL
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="fab fa-linkedin-in"></i>
                                    </span>
                                    <input type="url" id="linkedin_url" name="linkedin_url"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        placeholder="https://linkedin.com/company/yoursite" value="{{ old('linkedin_url', $setting->linkedin_url) }}" />
                                </div>
                                @error('linkedin_url')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    
                    <!-- Media Upload Card -->
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-image mr-2"></i>Media & Branding
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <!-- Logo Upload -->
                            <div>
                                <label for="logo" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Site Logo
                                </label>
                                @if($setting->logo)
                                    <div class="mb-4 p-4 rounded-lg border border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700 flex justify-center">
                                        <img src="{{ asset('storage/' . $setting->logo) }}" alt="Site Logo" class="h-16 w-auto object-contain" />
                                    </div>
                                @endif
                                <div class="flex items-center justify-center w-full">
                                    <label for="logo"
                                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:border-gray-500">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-cloud-upload-alt mb-3 text-2xl text-gray-500 dark:text-gray-400"></i>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, SVG (MAX. 2MB)</p>
                                        </div>
                                        <input id="logo" name="logo" type="file" class="hidden" accept="image/*" />
                                    </label>
                                </div>
                                @error('logo')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Favicon Upload -->
                            <div>
                                <label for="favicon" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Site Favicon
                                </label>
                                @if($setting->favicon)
                                    <div class="mb-4 p-4 rounded-lg border border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700 flex justify-center">
                                        <img src="{{ asset('storage/' . $setting->favicon) }}" alt="Site Favicon" class="h-10 w-10 object-contain" />
                                    </div>
                                @endif
                                <div class="flex items-center justify-center w-full">
                                    <label for="favicon"
                                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:border-gray-500">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-cloud-upload-alt mb-3 text-2xl text-gray-500 dark:text-gray-400"></i>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">PNG, ICO (MAX. 1MB)</p>
                                        </div>
                                        <input id="favicon" name="favicon" type="file" class="hidden" accept="image/png, image/x-icon, image/ico" />
                                    </label>
                                </div>
                                @error('favicon')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- SEO Meta Card -->
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-search mr-2"></i>SEO Settings
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <!-- Meta Title -->
                            <div>
                                <label for="meta_title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Meta Title
                                </label>
                                <input type="text" id="meta_title" name="meta_title"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="Enter meta title" value="{{ old('meta_title', $setting->meta_title) }}" />
                                @error('meta_title')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Meta Keywords -->
                            <div>
                                <label for="meta_keywords" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Meta Keywords
                                </label>
                                <input type="text" id="meta_keywords" name="meta_keywords"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="keyword1, keyword2, keyword3" value="{{ old('meta_keywords', $setting->meta_keywords) }}" />
                                @error('meta_keywords')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Meta Description -->
                            <div>
                                <label for="meta_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Meta Description
                                </label>
                                <textarea id="meta_description" name="meta_description" rows="4"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="Describe your site for search engines...">{{ old('meta_description', $setting->meta_description) }}</textarea>
                                @error('meta_description')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Google Analytics ID -->
                            <div>
                                <label for="google_analytics_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Google Analytics ID
                                </label>
                                <input type="text" id="google_analytics_id" name="google_analytics_id"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="G-XXXXXXXXXX" value="{{ old('google_analytics_id', $setting->google_analytics_id) }}" />
                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Leave blank to use environment default (.env) if present.</p>
                                @error('google_analytics_id')
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
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                    <i class="fas fa-save mr-2"></i>Save Configuration
                </button>
            </div>
        </form>
    </div>

    <script>
        // File upload previews via simple console logging (or you can expand this to update preview images)
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('Logo selected:', file.name);
                // Optionally visually indicate selection
            }
        });
        
        document.getElementById('favicon').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('Favicon selected:', file.name);
            }
        });
    </script>
@endsection
