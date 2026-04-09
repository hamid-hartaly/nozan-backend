<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-600">System Checks</p>
                <h3 class="mt-2 text-xl font-bold text-gray-950 dark:text-white">Operational Links</h3>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <a href="{{ url('/') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Public landing page</p>
                    <p class="mt-2 text-sm text-gray-500">Review the public site entry page.</p>
                </a>
                <a href="{{ url('/admin') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Admin dashboard</p>
                    <p class="mt-2 text-sm text-gray-500">Open the operations control center.</p>
                </a>
                <a href="{{ url('/api/auth/me') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Auth endpoint</p>
                    <p class="mt-2 text-sm text-gray-500">Check the authenticated user payload.</p>
                </a>
                <a href="{{ url('/admin/jobs') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Jobs board</p>
                    <p class="mt-2 text-sm text-gray-500">Open live queue and technician assignments.</p>
                </a>
                <a href="{{ rtrim(env('FRONTEND_URL', 'http://127.0.0.1:3001'), '/') . '/admin/management' }}" target="_blank" rel="noopener" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Frontend management</p>
                    <p class="mt-2 text-sm text-gray-500">Open Next.js admin management page.</p>
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
