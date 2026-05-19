<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Billing')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('billing.clients.index')" :current="request()->routeIs('billing.clients.*')" wire:navigate>
                        {{ __('Clients') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bolt" :href="route('billing.services.index')" :current="request()->routeIs('billing.services.*')" wire:navigate>
                        {{ __('Services') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="link" :href="route('billing.domains.index')" :current="request()->routeIs('billing.domains.*')" wire:navigate>
                        {{ __('Domains') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('billing.orders.index')" :current="request()->routeIs('billing.orders.*')" wire:navigate>
                        {{ __('Orders') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('billing.transactions.index')" :current="request()->routeIs('billing.transactions.*')" wire:navigate>
                        {{ __('Transactions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('billing.reports.integrated-position')" :current="request()->routeIs('billing.reports.*')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Catalog')" class="grid">
                    <flux:sidebar.item icon="server" :href="route('billing.servers.index')" :current="request()->routeIs('billing.servers.*')" wire:navigate>
                        {{ __('Servers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cube" :href="route('billing.products.index')" :current="request()->routeIs('billing.products.*')" wire:navigate>
                        {{ __('Products') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="globe-alt" :href="route('billing.registrars.index')" :current="request()->routeIs('billing.registrars.*')" wire:navigate>
                        {{ __('Registrars') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('billing.registrar-tlds.index')" :current="request()->routeIs('billing.registrar-tlds.*')" wire:navigate>
                        {{ __('Registrar TLDs') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Setup')" class="grid">
                    <flux:sidebar.item icon="building-office" :href="route('billing.company.edit')" :current="request()->routeIs('billing.company.*')" wire:navigate>
                        {{ __('Company details') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calculator" :href="route('billing.tax-settings.edit')" :current="request()->routeIs('billing.tax-settings.*')" wire:navigate>
                        {{ __('Tax settings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('billing.automation.edit')" :current="request()->routeIs('billing.automation.*')" wire:navigate>
                        {{ __('Automation') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="envelope" :href="route('billing.email-templates.index')" :current="request()->routeIs('billing.email-templates.*')" wire:navigate>
                        {{ __('Email templates') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>


            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
