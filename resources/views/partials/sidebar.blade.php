<aside :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0">
    <!-- SIDEBAR HEADER -->
    <div :class="sidebarToggle ? 'justify-center' : 'justify-between'"
        class="flex items-center gap-2 pt-8 sidebar-header pb-7">
        <a href="{{ route('admin.dashboard') }}">
            <span class="logo" :class="sidebarToggle ? 'hidden' : ''">
                <img class="dark:hidden" src="{{ asset('images/logo/logo.svg') }}" alt="Logo" />
                <img class="hidden dark:block" src="{{ asset('images/logo/logo-dark.svg') }}" alt="Logo" />
            </span>

            <img class="logo-icon" :class="sidebarToggle ? 'lg:block' : 'hidden'"
                src="{{ asset('images/logo/logo-icon.svg') }}" alt="Logo" />
        </a>
    </div>
    <!-- SIDEBAR HEADER -->

    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <!-- Sidebar Menu -->
        <nav x-data="{ selected: $persist('{{ request()->is('admin/dashboard*') ? 'Dashboard' : '' }}') }">
            <!-- Menu Group -->
            <div>
                <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                    <span class="menu-group-title" :class="sidebarToggle ? 'lg:hidden' : ''">
                        MENU
                    </span>
                </h3>

                <ul class="flex flex-col gap-4 mb-6">

                    <!-- Menu Item Dashboard -->
                    <li>
                        <a href="#" @click.prevent="selected = (selected === 'Dashboard' ? '':'Dashboard')"
                            class="menu-item group"
                            :class="(selected === 'Dashboard') || isCurrentPath('admin/dashboard*') ?
                                'menu-item-active' : 'menu-item-inactive'">
                            <i class="fas fa-th-large"
                                :class="(selected === 'Dashboard') || isCurrentPath('admin/dashboard*') ?
                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                            </i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">
                                Dashboard
                            </span>

                            <i class="fas fa-angle-down menu-item-arrow"
                                :class="[(selected === 'Dashboard') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                    sidebarToggle ? 'lg:hidden' : ''
                                ]">
                            </i>
                        </a>

                        <!-- Dropdown Menu Start -->
                        <div class="overflow-hidden transform translate"
                            :class="(selected === 'Dashboard') ? 'block' : 'hidden'">
                            <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                <li>
                                    <a href="{{ route('admin.dashboard') }}" class="menu-dropdown-item group"
                                        :class="isCurrentPath('admin/dashboard*') ? 'menu-dropdown-item-active' :
                                            'menu-dropdown-item-inactive'">
                                        Dashboard Home
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <!-- Dropdown Menu End -->
                    </li>
                    <!-- Menu Item Dashboard -->

                    <!-- Menu Item Driver -->
                    @can('drivers.view')
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Driver' ? '':'Driver')"
                                class="menu-item group"
                                :class="(selected === 'Driver') || isCurrentPath('admin/drivers*') ?
                                    'menu-item-active' : 'menu-item-inactive'">
                                <i class="fas fa-car"
                                    :class="(selected === 'Driver') || isCurrentPath('admin/drivers*') ?
                                        'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                </i>

                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">
                                    Driver Management
                                </span>

                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Driver') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                        sidebarToggle ? 'lg:hidden' : ''
                                    ]">
                                </i>
                            </a>

                            <div class="overflow-hidden transform translate"
                                :class="(selected === 'Driver') ? 'block' : 'hidden'">
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('drivers.create')
                                        <li>
                                            <a href="{{ route('admin.driver.create') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/drivers/create') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Add New Driver
                                            </a>
                                        </li>
                                    @endcan

                                    @can('drivers.view')
                                        <li>
                                            <a href="{{ route('admin.driver.index') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/drivers') && !isCurrentPath(
                                                        'admin/drivers/create') ?
                                                    'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                All Drivers
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                            <!-- Dropdown Menu End -->
                        </li>
                    @endcan

                    <!-- Menu Item Driver End -->


                    <!-- Menu Item Settings -->
                    @can('settings.view')
                        <li>
                            <a href="#" @click.prevent="selected = (selected === 'Setting' ? '':'Setting')"
                                class="menu-item group"
                                :class="(selected === 'Setting') || isCurrentPath('admin/settings*') ?
                                    'menu-item-active' : 'menu-item-inactive'">
                                <i class="fa-solid fa-gear"
                                    :class="(selected === 'Driver') || isCurrentPath('admin/settings*') ?
                                        'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                </i>

                                <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">
                                    Setting
                                </span>

                                <i class="fas fa-angle-down menu-item-arrow"
                                    :class="[(selected === 'Setting') ? 'menu-item-arrow-active' : 'menu-item-arrow-inactive',
                                        sidebarToggle ? 'lg:hidden' : ''
                                    ]">
                                </i>
                            </a>


                            <div class="overflow-hidden transform translate"
                                :class="(selected === 'Setting') ? 'block' : 'hidden'">
                                <ul :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                                    class="flex flex-col gap-1 mt-2 menu-dropdown pl-9">
                                    @can('companies.create')
                                        <li>
                                            <a href="{{ route('admin.settings.company.create') }}"
                                                class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/company/create') ?
                                                    'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Add New Company
                                            </a>
                                        </li>
                                    @endcan

                                    @can('companies.view')
                                        <li>
                                            <a href="{{ route('admin.settings.company') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/company') && !isCurrentPath(
                                                        'admin/settings/company') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                All Company
                                            </a>
                                        </li>
                                    @endcan


                                    @can('policy-pdf.view')
                                        <li>
                                            <a href="{{ route('admin.settings.policy.pdf') }}" class="menu-dropdown-item group"
                                                :class="isCurrentPath('admin/settings/policy-pdf') && !isCurrentPath(
                                                        'admin/settings/policy-pdf') ? 'menu-dropdown-item-active' :
                                                    'menu-dropdown-item-inactive'">
                                                Policy PDF
                                            </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endcan
                    <!-- Menu Item Settings -->



                    <!-- Menu Item Calendar -->
                    <li>
                        {{-- {{ route('admin.calendar') }} --}}
                        <a href="" @click="selected = (selected === 'Calendar' ? '':'Calendar')"
                            class="menu-item group"
                            :class="(selected === 'Calendar') && isCurrentPath('admin/calendar*') ? 'menu-item-active' :
                                'menu-item-inactive'">
                            <i class="fas fa-calendar-alt"
                                :class="(selected === 'Calendar') && isCurrentPath('admin/calendar*') ?
                                    'menu-item-icon-active' :
                                    'menu-item-icon-inactive'">
                            </i>

                            <span class="menu-item-text" :class="sidebarToggle ? 'lg:hidden' : ''">
                                Calendar
                            </span>
                        </a>
                    </li>
                    <!-- Menu Item Calendar -->


                </ul>
            </div>

        </nav>
        <!-- Sidebar Menu -->
    </div>
</aside>
