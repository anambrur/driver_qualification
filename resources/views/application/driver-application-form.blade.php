@extends('layouts.application-form-layout')

@section('title', 'Driver\'s Application')
@section('themed', '1')

@section('content')
    <x-application.entry :company="$company" heading="Driver's Application" intro="New applicant or return to an existing application?" width="max-w-2xl">
        <div class="grid gap-4 sm:grid-cols-2" x-data="resumeApplication">
            <button type="button" id="startNewBtn" x-on:click="startNew"
                class="group grid justify-items-center gap-2 rounded-xl border bg-primary p-6 text-center text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <x-ui.icon name="file-plus" class="size-8" />
                <span class="text-lg font-semibold">Start New Application</span>
                <span class="text-sm opacity-90">Begin a new driver application</span>
            </button>

            <button type="button" id="returnToBtn" x-on:click="open"
                class="group grid justify-items-center gap-2 rounded-xl border bg-card p-6 text-center shadow-sm transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <x-ui.icon name="rotate-ccw" class="size-8 text-primary" />
                <span class="text-lg font-semibold">Return to Application</span>
                <span class="text-sm text-muted-foreground">Continue your saved application</span>
            </button>

            {{-- Resume: phone → SMS code → redirect. Same endpoints and messages as before batch 4c. --}}
            <x-ui.dialog id="resume-dialog" size="sm" icon="rotate-ccw" :close-on-overlay="false" title="Return to Application">
                <form x-show="step === 'phone'" x-on:submit.prevent="checkPhone" id="resume-phone-form" class="grid gap-4" novalidate>
                    <x-ui.field label="Phone Number" for="resume-phone" hint="Enter the phone number associated with your uncompleted application to pick up where you left off.">
                        <x-ui.input type="tel" id="resume-phone" x-bind:value="phone" x-on:input="formatPhone" maxlength="14" autocomplete="tel" placeholder="(555) 555-5555"
                            x-bind:aria-invalid="error ? 'true' : null" />
                    </x-ui.field>
                </form>

                <form x-show="step === 'otp'" x-cloak x-on:submit.prevent="checkOtp" id="resume-otp-form" class="grid gap-4" novalidate>
                    <x-ui.field label="6-Digit SMS Code" for="resume-otp">
                        <x-ui.input id="resume-otp" x-model="otp" x-on:input="otp = otp.replace(/[^0-9]/g, '')" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="••••••"
                            class="text-center text-xl tracking-[0.5em]" x-bind:aria-invalid="error ? 'true' : null" />
                    </x-ui.field>
                    <p class="text-sm text-muted-foreground">We've just sent a secure verification code to <span class="font-medium text-foreground" x-text="phone"></span></p>
                </form>

                <div x-show="step === 'done'" x-cloak>
                    <x-ui.alert tone="success" title="Identity Verified!">Resuming your application...</x-ui.alert>
                </div>

                <p x-show="error" x-cloak role="alert" class="mt-4 text-sm text-destructive" x-text="error"></p>

                <x-slot:footer class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end" x-show="step !== 'done'">
                    <x-ui.button variant="outline" x-on:click="close" x-bind:disabled="busy">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" x-bind:form="step === 'otp' ? 'resume-otp-form' : 'resume-phone-form'" x-bind:aria-busy="busy" x-bind:disabled="busy">
                        <span x-text="step === 'otp' ? 'Verify Code' : 'Retrieve Application'">Retrieve Application</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.dialog>
        </div>
    </x-application.entry>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            const slug = @json(request()->route('slug') ?? '');
            const startUrl = @json(route('public.application.start', ['slug' => ':slug']));
            const csrf = @json(csrf_token());

            const post = (url, body) => fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            }).then((response) => {
                if (!response.ok) throw new Error(response.statusText);
                return response.json();
            });

            window.Alpine.data('resumeApplication', () => ({
                step: 'phone',
                phone: '',
                verifiedPhone: '',
                otp: '',
                error: '',
                busy: false,

                async startNew() {
                    if (!slug) {
                        showAppAlert('Unable to determine company. Please contact support.', 'error', 'Error');
                        return;
                    }
                    const result = await showAppConfirm('You are about to start a new driver application. All required documents will be needed.', {
                        title: 'Start New Application',
                        icon: 'info',
                        confirmButtonText: 'Continue',
                    });
                    if (result.isConfirmed) window.location.href = startUrl.replace(':slug', slug);
                },

                open() {
                    Object.assign(this, { step: 'phone', phone: '', otp: '', error: '', busy: false });
                    document.getElementById('resume-dialog').showModal();
                },

                close() {
                    if (!this.busy) document.getElementById('resume-dialog').close();
                },

                formatPhone(e) {
                    const x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
                    this.phone = e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
                },

                async checkPhone() {
                    this.error = '';
                    if (!this.phone || this.phone.length < 14) {
                        this.error = 'Please enter a valid complete US phone number';
                        return;
                    }
                    this.busy = true;
                    try {
                        const data = await post(`/${slug}/application/check-resume`, { phone: this.phone });
                        if (!data.success) {
                            this.error = data.message || 'No application found with this phone number.';
                        } else if (data.requires_otp) {
                            this.verifiedPhone = data.phone;
                            this.step = 'otp';
                            this.$nextTick(() => document.getElementById('resume-otp').focus());
                        } else {
                            this.busy = false;
                            this.close();
                        }
                    } catch (error) {
                        this.error = `Request failed: ${error}`;
                    } finally {
                        this.busy = false;
                    }
                },

                async checkOtp() {
                    this.error = '';
                    if (!this.otp || this.otp.length !== 6) {
                        this.error = 'Please enter the full 6-digit verification code sent to your phone';
                        return;
                    }
                    this.busy = true;
                    try {
                        const data = await post(`/${slug}/application/check-resume-otp`, { phone: this.verifiedPhone, otp: this.otp });
                        if (!data.success) {
                            this.error = data.message || 'Invalid verification code';
                            this.busy = false;
                            return;
                        }
                        this.step = 'done';
                        setTimeout(() => { window.location.href = data.redirect; }, 1500);
                    } catch (error) {
                        this.error = `Request failed: ${error}`;
                        this.busy = false;
                    }
                },
            }));
        });
    </script>
@endpush
