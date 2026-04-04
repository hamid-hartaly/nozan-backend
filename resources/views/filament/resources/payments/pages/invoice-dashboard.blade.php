<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700 dark:text-amber-300">Finance</p>
            <h2 class="mt-2 text-xl font-bold text-gray-950 dark:text-white">Invoices Dashboard</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Review customer invoices, payment status, and outstanding balances from one place.
                Use "Preview / Edit" to adjust invoice items, pricing, and payment records.
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
