@extends('layouts.main-layout')

@section('title', 'Edit Company')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Edit Company" description="Update company information" />

        @if ($errors->has('system_error'))
            <x-ui.alert tone="danger" class="mb-6">{{ $errors->first('system_error') }}</x-ui.alert>
        @endif

        <form action="{{ route('admin.settings.company.update', $company->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <x-ui.card title="Company Information">
                        <div class="grid gap-4">
                            <x-ui.field label="Company Name" for="company_name" name="company_name" required>
                                <x-ui.input name="company_name" id="company_name" required placeholder="Enter company name" :value="$company->company_name" />
                            </x-ui.field>
                            <x-ui.field label="Email Address" for="email" name="email" required>
                                <x-ui.input type="email" name="email" id="email" required readonly placeholder="company@example.com" :value="$company->email" />
                            </x-ui.field>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.field label="Phone" for="phone" name="phone">
                                    <x-ui.input name="phone" id="phone" placeholder="+1 (555) 123-4567" :value="$company->phone" />
                                </x-ui.field>
                                <x-ui.field label="Fax" for="fax" name="fax">
                                    <x-ui.input name="fax" id="fax" placeholder="+1 (555) 123-4568" :value="$company->fax" />
                                </x-ui.field>
                            </div>
                            <x-ui.field label="Status" for="status" name="status" required>
                                <x-ui.select name="status" id="status" required disabled placeholder="Select Status"
                                    :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$company->status" />
                            </x-ui.field>
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Company Logo">
                        <div class="grid gap-4">
                            @if ($company->logo_path)
                                <div class="flex items-center gap-4">
                                    <img src="{{ asset('storage/' . $company->logo_path) }}" alt="{{ $company->company_name }}" class="size-16 rounded-lg border object-cover">
                                    <label class="label inline-flex items-center gap-2 font-normal">
                                        <input type="checkbox" name="remove_logo" value="1" class="input">
                                        <span class="text-muted-foreground">Remove current logo</span>
                                    </label>
                                </div>
                            @endif

                            <div class="grid gap-2">
                                <p class="label">{{ $company->logo_path ? 'Upload New Logo' : 'Upload Logo' }}</p>
                                <label for="logo" @class([
                                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed bg-muted/40 p-6 text-center transition-colors hover:bg-muted has-[input:focus-visible]:border-ring',
                                    'border-destructive' => $errors->has('logo'),
                                ])>
                                    <x-ui.icon name="cloud-upload" class="size-8 text-muted-foreground" />
                                    <span class="text-sm text-muted-foreground"><span class="font-medium text-primary">Click to upload</span> or drag and drop</span>
                                    <span class="text-xs text-muted-foreground">PNG, JPG, GIF, SVG (MAX. 2MB)</span>
                                    <span id="logo_filename" class="hidden text-sm font-medium text-foreground"></span>
                                    <input id="logo" name="logo" type="file" class="sr-only" accept="image/*"
                                        @error('logo') aria-invalid="true" aria-describedby="logo-error" @enderror />
                                </label>
                                @error('logo')
                                    <p id="logo-error" role="alert" class="text-sm text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="space-y-6">
                    <x-ui.card title="Address Information">
                        <div class="grid gap-4">
                            <x-ui.field label="Address" for="address" name="address" required>
                                <x-ui.input name="address" id="address" required placeholder="123 Main Street" :value="$company->address" />
                            </x-ui.field>
                            <x-ui.field label="City" for="city" name="city" required>
                                <x-ui.input name="city" id="city" required placeholder="New York" :value="$company->city" />
                            </x-ui.field>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.field label="State" for="state" name="state" required>
                                    <x-ui.input name="state" id="state" required placeholder="NY" :value="$company->state_code" />
                                </x-ui.field>
                                <x-ui.field label="ZIP Code" for="zip" name="zip" required>
                                    <x-ui.input name="zip" id="zip" required placeholder="10001" :value="$company->zip" />
                                </x-ui.field>
                            </div>
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Company Description">
                        <x-ui.field label="Description" for="description" name="description">
                            <x-ui.textarea name="description" id="description" rows="5" placeholder="Describe your company..." :value="$company->description" />
                        </x-ui.field>
                    </x-ui.card>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="outline" :href="route('admin.dashboard')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">Update Company</x-ui.button>
            </div>
        </form>

        <div class="mt-6 max-w-2xl">
            @include('settings.partials.reply-email-card', [
                'owner' => $company,
                'description' => 'Emails to your drivers come from your company name. When a driver replies, the reply goes to this inbox.',
                'fallback' => 'Until then, replies go to the company owner’s login email.',
                'storeUrl' => route('admin.settings.company.reply-email.store'),
                'destroyUrl' => route('admin.settings.company.reply-email.destroy'),
            ])
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('logo').addEventListener('change', function () {
            const name = document.getElementById('logo_filename');
            const file = this.files[0];
            name.textContent = file ? `Selected: ${file.name}` : '';
            name.classList.toggle('hidden', !file);
        });
    </script>
@endpush
