<script>
    import { Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Receipt, Printer, AlertTriangle, CreditCard } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        total_receivables = 0,
        aging_summary = { current: 0, days_30: 0, days_60: 0, days_90_plus: 0 },
        invoices = [],
    } = $props();
</script>

<AuthenticatedLayout
    title="Laporan Piutang Pelanggan (Aging Receivables)"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/receivables' },
        { label: 'Laporan Piutang' },
    ]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Receipt class="h-5 w-5 text-stone-800" />
                    Laporan Piutang & Umur Piutang (Aging Report)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Analisis keterlambatan pembayaran invoice dan tingkat risiko kredit pelanggan.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Laporan
            </Button>
        </div>

        <!-- Aging Bucket Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl border border-stone-200 bg-white shadow-xs">
                <span class="text-[11px] text-stone-500 uppercase font-semibold block">0 - 30 Hari (Lancar)</span>
                <span class="text-xl font-bold font-mono text-emerald-700 mt-1 block">{formatRupiah(aging_summary.current)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Belum jatuh tempo / baru</span>
            </div>

            <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/80 shadow-xs">
                <span class="text-[11px] text-amber-800 uppercase font-bold block">31 - 60 Hari (Perhatian)</span>
                <span class="text-xl font-bold font-mono text-amber-800 mt-1 block">{formatRupiah(aging_summary.days_30)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Lewat jatuh tempo bulan lalu</span>
            </div>

            <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/80 shadow-xs">
                <span class="text-[11px] text-rose-700 uppercase font-bold block">61 - 90 Hari (Kurang Lancar)</span>
                <span class="text-xl font-bold font-mono text-rose-700 mt-1 block">{formatRupiah(aging_summary.days_60)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Telah terisolir</span>
            </div>

            <div class="p-4 rounded-xl border border-rose-300 bg-rose-100/60 shadow-xs">
                <span class="text-[11px] text-rose-800 uppercase font-bold block">&gt; 90 Hari (Macet)</span>
                <span class="text-xl font-bold font-mono text-rose-800 mt-1 block">{formatRupiah(aging_summary.days_90_plus)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Kandidat terminasi</span>
            </div>
        </div>

        <!-- Receivables Invoices Table -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Rincian Tagihan Belum Lunas</CardTitle>
                    <p class="text-xs text-stone-500 mt-0.5">Total Piutang Berjalan: <span class="font-bold text-rose-700 font-mono">{formatRupiah(total_receivables)}</span></p>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                            <tr>
                                <th class="py-3 px-4">No. Invoice</th>
                                <th class="py-3 px-4">Pelanggan</th>
                                <th class="py-3 px-4">Periode</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Nominal Tagihan</th>
                                <th class="py-3 px-4 text-right no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#if invoices.length === 0}
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-stone-500">
                                        Tidak ada piutang tertunggak. Semua tagihan telah terlunasi.
                                    </td>
                                </tr>
                            {:else}
                                {#each invoices as inv}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-semibold text-stone-800">
                                            <Link href={`/invoices/${inv.id}`} class="hover:underline">{inv.invoice_number}</Link>
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <Link href={`/customers/${inv.customer_id}`} class="font-medium text-stone-900 hover:text-stone-800 hover:underline">
                                                {inv.customer?.name}
                                            </Link>
                                            <span class="text-[10px] text-stone-500 block font-mono">{inv.customer?.phone}</span>
                                        </td>
                                        <td class="py-2.5 px-4 font-mono">{inv.period}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{formatDate(inv.due_date)}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            <Badge variant={inv.status === 'overdue' ? 'danger' : 'warning'}>
                                                {inv.status?.toUpperCase()}
                                            </Badge>
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-700">
                                            {formatRupiah(inv.total_amount)}
                                        </td>
                                        <td class="py-2.5 px-4 text-right no-print">
                                            <Link href={`/payments/create?customer_id=${inv.customer_id}`}>
                                                <Button variant="default" size="sm" class="h-6 px-2 text-[11px]">
                                                    <CreditCard class="h-3 w-3 mr-1" />
                                                    Bayar
                                                </Button>
                                            </Link>
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</AuthenticatedLayout>
