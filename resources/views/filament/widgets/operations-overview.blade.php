<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-6 lg:grid-cols-[1.4fr_.9fr]">
            <div class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-600">Nozan Service Center</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Operations Dashboard</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Central view for daily operations: job intake, repair workflow, customer billing, and inventory visibility.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-gray-500">Platform</p>
                        <p class="mt-3 text-2xl font-bold text-gray-950 dark:text-white">Stable</p>
                        <p class="mt-1 text-sm text-gray-500">Laravel + Filament + Sanctum</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-gray-500">Authentication</p>
                        <p class="mt-3 text-2xl font-bold text-gray-950 dark:text-white">Ready</p>
                        <p class="mt-1 text-sm text-gray-500">/api/auth/login, /api/auth/me, /api/auth/logout</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-gray-500">Live Modules</p>
                        <p class="mt-3 text-2xl font-bold text-gray-950 dark:text-white">4</p>
                        <p class="mt-1 text-sm text-gray-500">Jobs, customers, payments, inventory</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-amber-200/70 bg-amber-50 p-5 dark:border-amber-500/20 dark:bg-amber-500/10">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700 dark:text-amber-300">Operations Notes</p>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-950 dark:text-amber-50">
                    <li>- This repository handles backend APIs and admin panel workflows.</li>
                    <li>- Production updates require a deploy after code merge.</li>
                    <li>- Customer-facing UI is managed from the frontend project.</li>
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
