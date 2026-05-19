<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>

                <flux:dropdown position="bottom" align="start">
                    <flux:navbar.item
                        icon="currency-dollar"
                        icon-trailing="chevron-down"
                        :current="request()->routeIs(
                            'billing.clients.*',
                            'billing.services.*',
                            'billing.domains.*',
                            'billing.orders.*',
                            'billing.transactions.*',
                        )"
                    >
                        {{ __('Billing') }}
                    </flux:navbar.item>
                    <flux:menu>
                        <flux:menu.item :href="route('billing.clients.index')" icon="users" wire:navigate>{{ __('Clients') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.services.index')" icon="bolt" wire:navigate>{{ __('Services') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.domains.index')" icon="link" wire:navigate>{{ __('Domains') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.orders.index')" icon="document-text" wire:navigate>{{ __('Orders') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.transactions.index')" icon="banknotes" wire:navigate>{{ __('Transactions') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:navbar.item
                        icon="cube"
                        icon-trailing="chevron-down"
                        :current="request()->routeIs(
                            'billing.servers.*',
                            'billing.products.*',
                            'billing.registrars.*',
                            'billing.registrar-tlds.*',
                        )"
                    >
                        {{ __('Catalog') }}
                    </flux:navbar.item>
                    <flux:menu>
                        <flux:menu.item :href="route('billing.servers.index')" icon="server" wire:navigate>{{ __('Servers') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.products.index')" icon="cube" wire:navigate>{{ __('Products') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.registrars.index')" icon="globe-alt" wire:navigate>{{ __('Registrars') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.registrar-tlds.index')" icon="tag" wire:navigate>{{ __('Registrar TLDs') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:navbar.item
                        icon="cog-6-tooth"
                        icon-trailing="chevron-down"
                        :current="request()->routeIs(
                            'billing.company.*',
                            'billing.tax-settings.*',
                            'billing.automation.*',
                            'billing.email-templates.*',
                        )"
                    >
                        {{ __('Setup') }}
                    </flux:navbar.item>
                    <flux:menu>
                        <flux:menu.item :href="route('billing.company.edit')" icon="building-office" wire:navigate>{{ __('Company details') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.tax-settings.edit')" icon="calculator" wire:navigate>{{ __('Tax settings') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.automation.edit')" icon="clock" wire:navigate>{{ __('Automation') }}</flux:menu.item>
                        <flux:menu.item :href="route('billing.email-templates.index')" icon="envelope" wire:navigate>{{ __('Email templates') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                <flux:tooltip :content="__('Repository')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/laravel/livewire-starter-kit"
                        target="_blank"
                        :label="__('Repository')"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Documentation')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://laravel.com/docs/starter-kits#livewire"
                        target="_blank"
                        :label="__('Documentation')"
                    />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
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

        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
