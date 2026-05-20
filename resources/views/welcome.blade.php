@php
    use App\Enums\IntervalUnit;
    use App\Enums\ProductPriceBillingKind;
    use App\Enums\ProductType;
    use App\Models\ProductPrice;
    use App\Models\Server;

    $contactEmail = config('billing.company.email') ?? config('mail.from.address');
@endphp

<x-layouts::guest>
    <header class="sticky top-0 z-50 border-b border-zinc-200/80 bg-zinc-50/90 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex min-h-[4.5rem] max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="{{ config('billing.company.name') }}"
                    width="296"
                    height="50"
                    class="min-h-[50px] h-[50px] w-auto dark:invert"
                />
            </a>
            <nav class="flex items-center gap-2 sm:gap-4">
                <a href="#plans" class="hidden text-sm font-medium text-zinc-600 hover:text-zinc-900 sm:inline dark:text-zinc-400 dark:hover:text-white">
                    {{ __('Plans') }}
                </a>
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section class="relative overflow-hidden border-b border-zinc-200 dark:border-zinc-800">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(99,102,241,0.18),transparent)] dark:bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(99,102,241,0.25),transparent)]"></div>
            <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-32">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-indigo-200/80 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200">
                    <span class="size-1.5 rounded-full bg-indigo-500"></span>
                    {{ __('Managed hosting & cloud infrastructure') }}
                </p>
                <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-zinc-900 sm:text-5xl lg:text-6xl dark:text-white">
                    {{ __('Reliable hosting built for your business') }}
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ __('Secure servers, transparent pricing, and expert support. Choose a plan below or talk to us for a tailored setup.') }}
                </p>
                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <a
                        href="#plans"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        {{ __('View plans') }}
                    </a>
                    @if ($contactEmail)
                        <a
                            href="mailto:{{ $contactEmail }}"
                            class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-800 transition hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-600 dark:hover:bg-zinc-800"
                        >
                            {{ __('Contact sales') }}
                        </a>
                    @endif
                </div>
                <dl class="mt-14 grid max-w-3xl grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200/80 bg-white/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Performance') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ __('SSD storage & scalable resources') }}</dd>
                    </div>
                    <div class="rounded-xl border border-zinc-200/80 bg-white/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Security') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ __('Isolated environments & encrypted data') }}</dd>
                    </div>
                    <div class="rounded-xl border border-zinc-200/80 bg-white/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Support') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ __('Direct access to our technical team') }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- Plans --}}
        <section id="plans" class="scroll-mt-20 py-16 sm:py-24">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('Hosting plans') }}
                    </h2>
                    <p class="mt-3 text-zinc-600 dark:text-zinc-400">
                        {{ __('All prices shown are indicative. Final billing may vary by contract and tax settings.') }}
                    </p>
                </div>

                @if ($products->isEmpty())
                    <div class="mt-12 rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="text-zinc-600 dark:text-zinc-400">{{ __('No plans are available at the moment. Please check back soon.') }}</p>
                        @if ($contactEmail)
                            <a href="mailto:{{ $contactEmail }}" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                {{ __('Get in touch') }} →
                            </a>
                        @endif
                    </div>
                @else
                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($products as $product)
                            @php
                                $headline = $product->headlinePrice();
                                $typeLabel = match ($product->type) {
                                    ProductType::Vps => __('VPS'),
                                    ProductType::Dedicated => __('Dedicated'),
                                    ProductType::Hosting => __('Web hosting'),
                                    ProductType::Reseller => __('Reseller'),
                                    ProductType::Service => __('Service'),
                                };
                                $typeColor = match ($product->type) {
                                    ProductType::Vps => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
                                    ProductType::Dedicated => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                                    ProductType::Hosting => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                    ProductType::Reseller => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                    ProductType::Service => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                };
                                $ram = $product->capacityRamGb();
                                $disk = $product->capacityDiskGb();
                            @endphp
                            <article class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold {{ $typeColor }}">
                                        {{ $typeLabel }}
                                    </span>
                                </div>
                                <h3 class="mt-4 text-xl font-semibold text-zinc-900 dark:text-white">
                                    {{ $product->name }}
                                </h3>
                                @if (filled($product->description))
                                    <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                                        {{ $product->description }}
                                    </p>
                                @endif

                                @if ($ram !== null || $disk !== null)
                                    <ul class="mt-4 space-y-1.5 text-sm text-zinc-600 dark:text-zinc-400">
                                        @if (($ram !== null) && ($ram >0))
                                            <li class="flex items-center gap-2">
                                                <flux:icon name="cpu-chip" variant="micro" class="text-zinc-400" />
                                                {{ Server::formatRamGb($ram) }} {{ __('RAM') }}
                                            </li>
                                        @endif
                                        @if ($disk !== null)
                                            <li class="flex items-center gap-2">
                                                <flux:icon name="circle-stack" variant="micro" class="text-zinc-400" />
                                                {{ Server::formatDiskGb($disk) }} {{ __('storage') }}
                                            </li>
                                        @endif
                                    </ul>
                                @endif

                                <div class="mt-6 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                                    @if ($headline)
                                        <p class="flex items-baseline gap-1">
                                            <span class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                                                {{ number_format($headline->amount_minor / 100, 2) }}
                                            </span>
                                            <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                                                {{ $headline->currency }}
                                                @if ($headline->billing_kind === ProductPriceBillingKind::Recurring)
                                                    <span class="text-zinc-400">/</span>
                                                    @if ($headline->interval_count > 1)
                                                        {{ $headline->interval_count }}
                                                    @endif
                                                    {{ $headline->interval_unit === IntervalUnit::Month ? __('mo') : __('yr') }}
                                                @else
                                                    <span class="text-zinc-400">{{ __('one-time') }}</span>
                                                @endif
                                            </span>
                                        </p>
                                        @php
                                            $otherPrices = $product->prices
                                                ->where('is_active', true)
                                                ->reject(fn (ProductPrice $p) => $p->id === $headline->id);
                                        @endphp
                                        @if ($otherPrices->isNotEmpty())
                                            <ul class="mt-2 space-y-0.5 text-xs text-zinc-500 dark:text-zinc-500">
                                                @foreach ($otherPrices->take(3) as $price)
                                                    <li>
                                                        {{ number_format($price->amount_minor / 100, 2) }} {{ $price->currency }}
                                                        @if ($price->billing_kind === ProductPriceBillingKind::Recurring)
                                                            / @if ($price->interval_count > 1){{ $price->interval_count }} @endif{{ $price->interval_unit === IntervalUnit::Month ? __('mo') : __('yr') }}
                                                        @else
                                                            ({{ __('one-time') }})
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @else
                                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                                            {{ __('Custom pricing — contact us') }}
                                        </p>
                                    @endif
                                </div>

                                @if ($contactEmail)
                                    <a
                                        href="mailto:{{ $contactEmail }}?subject={{ rawurlencode(__('Enquiry: :product', ['product' => $product->name])) }}"
                                        class="mt-6 inline-flex w-full items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700"
                                    >
                                        {{ __('Request this plan') }}
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- CTA --}}
        <section class="border-t border-zinc-200 bg-zinc-900 py-16 text-white dark:border-zinc-800">
            <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ __('Need something custom?') }}
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-zinc-400">
                    {{ __('Dedicated clusters, migration assistance, and managed services — we will size a solution for your workload.') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    @if ($contactEmail)
                        <a
                            href="mailto:{{ $contactEmail }}"
                            class="inline-flex items-center rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-100"
                        >
                            {{ __('Email us') }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-200 py-8 dark:border-zinc-800">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 px-4 sm:flex-row sm:px-6 lg:px-8">
            <p class="text-center text-sm text-zinc-500 sm:text-left dark:text-zinc-500">
                © {{ date('Y') }} {{ config('billing.company.name') }}. {{ __('All rights reserved.') }}
            </p>
            @if ($contactEmail)
                {{ $contactEmail }}
            @endif
        </div>
    </footer>
</x-layouts::guest>
