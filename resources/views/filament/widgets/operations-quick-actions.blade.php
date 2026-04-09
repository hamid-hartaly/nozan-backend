<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700">Daily Workflow</p>
                <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Quick Actions</h3>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <a href="{{ url('/admin/jobs/create') }}" class="rounded-xl border border-gray-200 bg-white p-4 font-semibold text-gray-950 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:text-white">
                    New Job
                    <p class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">Register a new customer repair request</p>
                </a>
                <a href="{{ url('/admin/jobs') }}" class="rounded-xl border border-gray-200 bg-white p-4 font-semibold text-gray-950 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:text-white">
                    Jobs Board
                    <p class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">Track status, technician assignment, and queue load</p>
                </a>
                <a href="{{ url('/admin/inventory-items') }}" class="rounded-xl border border-gray-200 bg-white p-4 font-semibold text-gray-950 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:text-white">
                    Inventory
                    <p class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">Monitor stock levels and low-stock alerts</p>
                </a>
                <a href="{{ url('/admin/payments/invoices') }}" class="rounded-xl border border-gray-200 bg-white p-4 font-semibold text-gray-950 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:text-white">
                    Invoices
                    <p class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">Open billing and invoice tracking dashboard</p>
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
