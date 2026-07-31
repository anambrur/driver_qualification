<aside :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0">
    <!-- SIDEBAR HEADER -->
    <div :class="sidebarToggle ? 'justify-center' : 'justify-between'"
        class="flex items-center gap-2 pt-8 sidebar-header pb-7">
        <a href="{{ route('admin.dashboard') }}">
            <span class="logo" :class="sidebarToggle ? 'hidden' : ''">
                <x-user-avatar>
                    <img class="dark:hidden" src="{{ asset('images/logo/logo.svg') }}" alt="Logo" />
                    <img class="hidden dark:block" src="{{ asset('images/logo/logo-dark.svg') }}" alt="Logo" />
                </x-user-avatar>
            </span>
            <img class="logo-icon" :class="sidebarToggle ? 'lg:block' : 'hidden'"
                src="{{ asset('images/logo/logo-icon.svg') }}" alt="Logo" />
        </a>
    </div>
    <!-- SIDEBAR HEADER -->

    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <!-- Sidebar Menu -->
        <nav x-data="{ selected: $persist('{{ request()->is('admin/dashboard*') ? 'Dashboard' : '' }}') }">

            <!-- GROUP: GENERAL -->
            <div>
                <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                    <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">GENERAL</span>
                </h3>
                <ul class="flex flex-col gap-2 mb-6">
                    <!-- Dashboard -->
                    <li>
                        <a href="{{ route('dashboard') }}"
                            @click="selected = (selected === 'Dashboard' ? '':'Dashboard')" class="menu-item group"
                            :class="(selected === 'Dashboard') && isCurrentPath('dashboard*') ? 'menu-item-active' :
                                'menu-item-inactive'">
                            <i class="fas fa-th-large"
                                :class="(selected === 'Dashboard') && isCurrentPath('dashboard*') ? 'menu-item-icon-active' :
                                    'menu-item-icon-inactive'"></i>
                            <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Dashboard</span>
                        </a>
                    </li>


                </ul>
            </div>

            <!-- GROUP: OPERATIONS -->
            <div>
                <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                    <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">OPERATIONS</span>
                </h3>
                <ul class="flex flex-col gap-2 mb-6">

                    <!-- Driver Management -->
                    @can('drivers.view')
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Driver' ? '':'Driver')"
                                class="menu-item group"
                                :class="(selected === 'Driver') || isCurrentPath('admin/compliance/drivers*') ||
                                    isCurrentPath('admin/driver*') ? 'menu-item-active' : 'menu-item-inactive'">
                                <i class="fas fa-id-card"
                                    :class="(selected === 'Driver') || isCurrentPath('admin/compliance/drivers*') ||
                                        isCurrentPath('admin/driver*') ? 'menu-item-icon-active' :
                                        'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Driver
                                    Management</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Driver') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                        sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'Driver'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('drivers.dashboard')
                                        <li>
                                            <a href="{{ route('admin.compliance.drivers') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/compliance/drivers') ?
                                                    'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                Compliance Dash
                                            </a>
                                        </li>
                                    @endcan
                                    @can('drivers.create')
                                        <li>
                                            <a href="{{ route('admin.driver.create') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/driver/create') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Add New Driver
                                            </a>
                                        </li>
                                    @endcan
                                    @can('drivers.view')
                                        <li>
                                            <a href="{{ route('admin.driver.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/driver') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                All Drivers
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endcan

                    <!-- Fleet Management -->
                    @can('fleets.view')
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Fleet' ? '':'Fleet')"
                                class="menu-item group"
                                :class="(selected === 'Fleet') || isCurrentPath('admin/compliance/fleet-compliance') ||
                                    isCurrentPath(
                                        'admin/vehicle*') || isCurrentPath('admin/trailer*') ? 'menu-item-active' :
                                    'menu-item-inactive'">
                                <i class="fas fa-truck"
                                    :class="(selected === 'Fleet') || isCurrentPath('admin/compliance/fleet-compliance') ||
                                        isCurrentPath('admin/vehicle*') || isCurrentPath('admin/trailer*') ?
                                        'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Fleet
                                    Management</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Fleet') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                        sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'Fleet'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('fleets.dashboard')
                                        <li>
                                            <a href="{{ route('admin.compliance.fleet') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/compliance/fleet-compliance') ?
                                                    'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Compliance Dash
                                            </a>
                                        </li>
                                    @endcan
                                    @can('vehicles.view')
                                        <li>
                                            <a href="{{ route('admin.vehicle.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/vehicle*') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Vehicles
                                            </a>
                                        </li>
                                    @endcan
                                    @can('trailers.view')
                                        <li>
                                            <a href="{{ route('admin.trailer.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/trailer*') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Trailers
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endcan

                    <!-- Maintenance -->
                    @can('maintenance.view')
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Maintenance' ? '':'Maintenance')"
                                class="menu-item group"
                                :class="(selected === 'Maintenance') || isCurrentPath('admin/service-log*') || isCurrentPath(
                                    'admin/maintenance-schedule*') ? 'menu-item-active' : 'menu-item-inactive'">
                                <i class="fa-solid fa-wrench"
                                    :class="(selected === 'Maintenance') || isCurrentPath('admin/service-log*') ||
                                        isCurrentPath('admin/maintenance-schedule*') ? 'menu-item-icon-active' :
                                        'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Maintenance</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Maintenance') ? 'menu-item-arrow-active' :
                                        'menu-item-arrow-inactive', sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'Maintenance'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('maintenance.view')
                                        <li>
                                            <a href="{{ route('admin.service-log.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/service-log') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Service Log
                                            </a>
                                        </li>
                                    @endcan
                                    @can('scheduled.view')
                                        <li>
                                            <a href="{{ route('admin.maintenance-schedule.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/maintenance-schedule') ?
                                                    'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                Scheduled
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endcan

                </ul>
            </div>

            

            <!-- GROUP: SETTINGS -->
            @can('settings.view')
                <div>
                    <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                        <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">SETTINGS</span>
                    </h3>
                    <ul class="flex flex-col gap-2 mb-6">

                        <!-- General Settings -->
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Settings' ? '':'Settings')"
                                class="menu-item group"
                                :class="(selected === 'Settings') || isCurrentPath('admin/settings/site') || isCurrentPath(
                                        'admin/settings/tawk') || isCurrentPath('admin/settings/company') ||
                                    isCurrentPath('admin/settings/policy-pdf') ||
                                    isCurrentPath('admin/settings/document-types') || isCurrentPath('admin/roles') ?
                                    'menu-item-active' : 'menu-item-inactive'">
                                <i class="fa-solid fa-gear"
                                    :class="(selected === 'Settings') || isCurrentPath('admin/settings/site') || isCurrentPath(
                                            'admin/settings/tawk') || isCurrentPath('admin/settings/company') ||
                                        isCurrentPath('admin/settings/policy-pdf') ||
                                        isCurrentPath('admin/settings/document-types') || isCurrentPath('admin/roles') ?
                                        'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">System
                                    Config</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Settings') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                        sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'Settings'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    <li>
                                        <a href="{{ route('admin.settings.site.index') }}"
                                            class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/settings/site') ? 'menu-dropdown-item-active' :
                                                'menu-dropdown-item-inactive'">
                                            Site Configuration
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.settings.tawk.index') }}"
                                            class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/settings/tawk') ? 'menu-dropdown-item-active' :
                                                'menu-dropdown-item-inactive'">
                                            Tawk.to Chat
                                        </a>
                                    </li>
                                    @can('companies.view')
                                        <li>
                                            <a href="{{ route('admin.settings.company') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/company') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Companies List
                                            </a>
                                        </li>
                                    @endcan
                                    @can('policy-pdf.view')
                                        <li>
                                            <a href="{{ route('admin.settings.policy.pdf') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/policy-pdf') ?
                                                    'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                Policy Configs (PDF)
                                            </a>
                                        </li>
                                    @endcan
                                    @can('document-types.view')
                                        <li>
                                            <a href="{{ route('admin.settings.document-types.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/document-types') ?
                                                    'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                Document Types
                                            </a>
                                        </li>
                                    @endcan
                                    @can('roles.view')
                                        <li>
                                            <a href="{{ route('admin.roles.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/roles') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Roles & Access
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>

                        <!-- Fleet Configurations -->
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'FleetConfig' ? '':'FleetConfig')"
                                class="menu-item group"
                                :class="(selected === 'FleetConfig') || isCurrentPath('admin/vehicle-type') || isCurrentPath(
                                        'admin/vehicle-group') || isCurrentPath('admin/fuel-type') || isCurrentPath(
                                        'admin/equipment-type') || isCurrentPath('admin/maintenance-category') ||
                                    isCurrentPath('admin/asset-group') ? 'menu-item-active' : 'menu-item-inactive'">
                                <i class="fa-solid fa-sliders"
                                    :class="(selected === 'FleetConfig') || isCurrentPath('admin/vehicle-type') ||
                                        isCurrentPath('admin/vehicle-group') || isCurrentPath('admin/fuel-type') ||
                                        isCurrentPath('admin/equipment-type') || isCurrentPath(
                                            'admin/maintenance-category') || isCurrentPath('admin/asset-group') ?
                                        'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Fleet
                                    Options</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'FleetConfig') ? 'menu-item-arrow-active' :
                                        'menu-item-arrow-inactive', sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'FleetConfig'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('vehicle-types.view')
                                        <li>
                                            <a href="{{ route('admin.vehicle.type.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/vehicle-type') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Vehicle Types
                                            </a>
                                        </li>
                                    @endcan
                                    @can('vehicle-groups.view')
                                        <li>
                                            <a href="{{ route('admin.vehicle.group.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/vehicle-group') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Vehicle Groups
                                            </a>
                                        </li>
                                    @endcan
                                    @can('fuel-types.view')
                                        <li>
                                            <a href="{{ route('admin.fuel.type.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/fuel-type') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Fuel Types
                                            </a>
                                        </li>
                                    @endcan
                                    @can('equipment-types.view')
                                        <li>
                                            <a href="{{ route('admin.equipment.type.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/equipment-type') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Equipment Types
                                            </a>
                                        </li>
                                    @endcan
                                    @can('maintenance-categories.view')
                                        <li>
                                            <a href="{{ route('admin.maintenance.category.index') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/maintenance-category') ?
                                                    'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                Maintenance Categories
                                            </a>
                                        </li>
                                    @endcan
                                    @can('asset-groups.view')
                                        <li>
                                            <a href="{{ route('admin.asset-group.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/asset-group') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Asset Groups
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>

                    </ul>
                </div>
            @endcan


            <!-- GROUP: ADMINISTRATION (Super Admin Only) -->
            @hasrole('super-admin')
                <div>
                    <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                        <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">ADMINISTRATION</span>
                    </h3>
                    <ul class="flex flex-col gap-2 mb-6">

                        <!-- System Subscriptions -->
                        <li>
                            <a href="#"
                                @click.prevent="selected = (selected === 'AdminSubscriptions' ? '':'AdminSubscriptions')"
                                class="menu-item group"
                                :class="(selected === 'AdminSubscriptions') || isCurrentPath('admin/subscriptions*') ||
                                    isCurrentPath('admin/plans*') ? 'menu-item-active' : 'menu-item-inactive'">
                                <i class="fas fa-chart-pie"
                                    :class="(selected === 'AdminSubscriptions') || isCurrentPath('admin/subscriptions*') ||
                                        isCurrentPath('admin/plans*') ? 'menu-item-icon-active' :
                                        'menu-item-icon-inactive'"></i>
                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">Module
                                    Subscriptions</span>
                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'AdminSubscriptions') ? 'menu-item-arrow-active' :
                                        'menu-item-arrow-inactive', sidebarToggle ? 'lg:hidden' : ''
                                    ]"></i>
                            </a>

                            <div class="overflow-hidden transform translate" x-show="selected === 'AdminSubscriptions'"
                                style="display:none;" x-collapse>
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    <li>
                                        <a href="{{ route('admin.subscriptions.dashboard') }}"
                                            class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/subscriptions') ? 'menu-dropdown-item-active' :
                                                'menu-dropdown-item-inactive'">
                                            Revenue Dashboard
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.plans.index') }}" class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/plans') ? 'menu-dropdown-item-active' :
                                                'menu-dropdown-item-inactive'">
                                            Manage Plans
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('admin.subscriptions.index') }}"
                                            class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/subscriptions/all') ? 'menu-dropdown-item-active' :
                                                'menu-dropdown-item-inactive'">
                                            All Subscriptions
                                        </a>
                                    </li>
                                    {{-- <li>
                                        <a href="{{ route('admin.subscriptions.payments') }}"
                                            class="menu-dropdown-item group"
                                            :class="isCurrentPath('admin/subscriptions/payments') ?
                                                'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                            Payments & Invoices
                                        </a>
                                    </li> --}}
                                </ul>
                            </div>
                        </li>

                        <!-- User Management -->
                        @can('users.view')
                            <li>
                                <a href="{{ route('users.index') }}" @click="selected = (selected === 'Users' ? '':'Users')"
                                    class="menu-item group"
                                    :class="(selected === 'Users') && isCurrentPath('users*') ? 'menu-item-active' :
                                        'menu-item-inactive'">
                                    <i class="fas fa-users"
                                        :class="(selected === 'Users') && isCurrentPath('users*') ?
                                            'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                                    <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">All Users</span>
                                </a>
                            </li>
                        @endcan

                    </ul>
                </div>
            @endhasrole

            <!-- GROUP: ACCOUNT -->
            <div>
                <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                    <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">ACCOUNT</span>
                </h3>
                <ul class="flex flex-col gap-2 mb-6">
                    @can('companies.edit')
                        @php
                            $companyId = Auth::user()->company?->id;
                        @endphp
                        @if ($companyId)
                            <li>
                                <a href="{{ route('admin.settings.company.edit', $companyId) }}"
                                    @click="selected = (selected === 'Account' ? '':'Account')" class="menu-item group"
                                    :class="(selected === 'Account') || isCurrentPath(
                                            'admin/settings/company/{{ $companyId }}') ?
                                        'menu-item-active' : 'menu-item-inactive'">
                                    <i class="fa-solid fa-user"
                                        :class="(selected === 'Account') || isCurrentPath(
                                                'admin/settings/company/{{ $companyId }}') ?
                                            'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                                    <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">My
                                        Account</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    <!-- Subscription (User) -->
                    <li>
                        <a href="#"
                            @click.prevent="selected = (selected === 'MySubscription' ? '' : 'MySubscription')"
                            class="menu-item group"
                            :class="(selected === 'MySubscription') || isCurrentPath('pricing/plans') || isCurrentPath(
                                'billing') ? 'menu-item-active' : 'menu-item-inactive'">
                            <i class="fas fa-credit-card"
                                :class="(selected === 'MySubscription') || isCurrentPath('pricing/plans') || isCurrentPath(
                                    'billing') ? 'menu-item-icon-active' : 'menu-item-icon-inactive'"></i>
                            <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">My
                                Subscription</span>
                            <i class="fas fa-angle-down menu-item-arrow"
                                :class="[(selected === 'MySubscription') ? 'menu-item-arrow-active' :
                                    'menu-item-arrow-inactive', sidebarToggle ? 'lg:hidden' : ''
                                ]"></i>
                        </a>

                        <div class="overflow-hidden transform translate" x-show="selected === 'MySubscription'"
                            style="display:none;" x-collapse>
                            <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                <li>
                                    <a href="{{ route('pricing.plans') }}" class="menu-dropdown-item group"
                                        :class="isCurrentPath('pricing/plans') ? 'menu-dropdown-item-active' :
                                            'menu-dropdown-item-inactive'">
                                        Plans & Pricing
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('billing.index') }}" class="menu-dropdown-item group"
                                        :class="isCurrentPath('billing') ? 'menu-dropdown-item-active' :
                                            'menu-dropdown-item-inactive'">
                                        Billing & Invoices
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>

        </nav>
        <!-- Sidebar Menu -->
    </div>
</aside>
