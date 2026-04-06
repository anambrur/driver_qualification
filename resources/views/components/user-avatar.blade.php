@props(['size' => 'h-10 w-10', 'showName' => true])

@if(Auth::check())
    @php
        $user = Auth::user();
        $avatarUrl = null;
        
        if (isset($user->profile_image) && $user->profile_image) {
            $avatarUrl = asset('storage/' . $user->profile_image);
        } elseif (isset($user->avatar) && $user->avatar) {
            $avatarUrl = asset('storage/' . $user->avatar);
        } elseif (isset($user->company) && $user->company && $user->company->logo) {
            $avatarUrl = asset('storage/' . $user->company->logo);
        }
    @endphp

    <div class="flex items-center gap-3 group">
        <div class="{{ $size }} relative flex shrink-0 items-center justify-center overflow-hidden rounded-full ring-2 ring-transparent transition-all duration-300 group-hover:ring-brand-500 group-hover:shadow-md">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110" />
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 font-semibold text-white shadow-[inset_0_-2px_4px_rgba(0,0,0,0.2)]">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
            @endif
        </div>

        @if($showName)
            <div class="flex flex-col text-left">
                <span class="text-sm font-semibold text-gray-800 transition-colors duration-200 group-hover:text-brand-600 dark:text-white/90 dark:group-hover:text-brand-400">
                    {{ $user->name }}
                </span>
                @if(isset($user->company) && $user->company)
                    <span class="text-xs text-gray-500 dark:text-gray-400 block truncate max-w-[120px]">
                        {{ $user->company->name ?? 'Admin' }}
                    </span>
                @endif
            </div>
        @endif
    </div>
@else
    {{ $slot ?? '' }}
@endif
