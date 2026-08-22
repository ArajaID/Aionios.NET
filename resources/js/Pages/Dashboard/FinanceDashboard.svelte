<script>
    import { Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import MetricCard from '@/Components/ui/metric-card/MetricCard.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import {
        DollarSign,
        Receipt,
        CreditCard,
        AlertCircle,
        CheckCircle2,
        ArrowUpRight,
        PlusCircle,
        TrendingDown,
        TrendingUp,
        Calendar,
        Landmark
    } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        period = '',
        kpis = {},
        cash_bank_accounts = [],
        recent_payments = [],
        recent_invoices = [],
        approvals = {},
    } = $props();
</script>

<AuthenticatedLayout title="Dashboard Admin Keuangan">
    <div class="space-y-8">
        <!-- Top Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2.5">
                    Dashboard Keuangan & Billing
                    <Badge variant="primary">Periode {period}</Badge>
                </h1>
                <p class="text-xs text-stone-500 mt-1">Kelola tagihan pelanggan, konfirmasi pembayaran manual & QRIS, kas/bank, dan pengeluaran.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <Link href="/payments/create">
                    <Button variant="default" size="sm">
                        <CreditCard class="h-3.5 w-3.5 mr-1" />
                        Konfirmasi Pembayaran
                    </Button>
                </Link>

                <Link href="/expenses">
                    <Button variant="outline" size="sm">
                        <PlusCircle class="h-3.5 w-3.5 mr-1" />
                        Ajukan Pengeluaran
                    </Button>
                </Link>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Tagihan Diterbitkan"
                value={formatRupiah(kpis.total_billed)}
                subtitle="Invoice reguler bulan ini"
                color="indigo"
            >
                {#snippet icon()}
                    <Receipt class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Pembayaran Diterima"
                value={formatRupiah(kpis.gross_revenue)}
                subtitle={`Net: ${formatRupiah(kpis.net_revenue)} • MDR: ${formatRupiah(kpis.total_mdr)}`}
                color="emerald"
            >
                {#snippet icon()}
                    <CreditCard class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Piutang Outstanding"
                value={formatRupiah(kpis.total_receivables)}
                subtitle="Belum lunas / jatuh tempo"
                trend={`${kpis.collection_rate}% Terkumpul`}
                trendType="positive"
                color="amber"
            >
                {#snippet icon()}
                    <AlertCircle class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Total Kas & Bank"
                value={formatRupiah(kpis.total_cash_bank)}
                subtitle="Saldo riil seluruh akun"
                color="cyan"
            >
                {#snippet icon()}
                    <Landmark class="h-5 w-5" />
                {/snippet}
            </MetricCard>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Invoices & Payments -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Outstanding Invoices Quick Action Table -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Tagihan Jatuh Tempo & Outstanding</CardTitle>
                            <p class="text-xs text-stone-500 mt-0.5">Daftar tagihan yang belum dilunasi</p>
                        </div>
                        <Link href="/invoices" class="text-xs text-stone-800 hover:underline flex items-center gap-1">
                            Lihat Semua <ArrowUpRight class="h-3 w-3" />
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase">
                                    <tr>
                                        <th class="py-2">No. Tagihan</th>
                                        <th class="py-2">Pelanggan</th>
                                        <th class="py-2">Jatuh Tempo</th>
                                        <th class="py-2 text-right">Nominal</th>
                                        <th class="py-2 text-center">Status</th>
                                        <th class="py-2 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#each recent_invoices as inv}
                                        <tr class="hover:bg-stone-50">
                                            <td class="py-2.5 font-mono text-stone-700">{inv.invoice_number}</td>
                                            <td class="py-2.5 font-medium text-stone-900">{inv.customer?.name}</td>
                                            <td class="py-2.5 text-stone-500">{formatDate(inv.due_date)}</td>
                                            <td class="py-2.5 text-right font-semibold text-stone-900">{formatRupiah(inv.total_amount)}</td>
                                            <td class="py-2.5 text-center">
                                                <Badge variant={inv.status === 'paid' ? 'success' : inv.status === 'overdue' ? 'danger' : 'warning'}>
                                                    {inv.status.toUpperCase()}
                                                </Badge>
                                            </td>
                                            <td class="py-2.5 text-right">
                                                {#if inv.status !== 'paid'}
                                                    <Link href={`/payments/create?customer_id=${inv.customer_id}`}>
                                                        <Button variant="default" size="sm" class="h-7 px-2.5 text-[11px]">
                                                            Bayar
                                                        </Button>
                                                    </Link>
                                                {:else}
                                                    <span class="text-[11px] text-stone-500">Lunas</span>
                                                {/if}
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Col: Cash/Bank Accounts & Pending Approvals -->
            <div class="space-y-6">
                <!-- Cash/Bank Accounts -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle>Rekening Kas & Bank</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        {#each cash_bank_accounts as cb}
                            <div class="flex items-center justify-between p-3 rounded-xl border border-stone-200 bg-stone-50">
                                <div>
                                    <p class="text-xs font-semibold text-stone-800">{cb.name}</p>
                                    <p class="text-[10px] text-stone-500">{cb.bank_name} • {cb.account_number || '-'}</p>
                                </div>
                                <span class="text-xs font-bold text-stone-900 font-mono">{formatRupiah(cb.current_balance)}</span>
                            </div>
                        {/each}
                    </CardContent>
                </Card>

                <!-- Billing Rules Checklist Reminder -->
                <Card class="border-stone-200 bg-white p-4 space-y-3 text-xs">
                    <h4 class="font-semibold text-stone-800 flex items-center gap-1.5">
                        <Calendar class="h-4 w-4 text-stone-800" />
                        Jadwal Siklus Billing ISP
                    </h4>
                    <div class="space-y-2 text-stone-500">
                        <div class="flex items-start gap-2">
                            <span class="font-bold text-stone-800">Tgl 01:</span>
                            <span>Generate otomatis tagihan reguler seluruh pelanggan aktif & isolir.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="font-bold text-amber-800">Tgl 22:</span>
                            <span>Batas akhir jatuh tempo pembayaran tagihan bulanan.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="font-bold text-rose-700">Tgl 23:</span>
                            <span>Eksekusi isolir otomatis (PPP Profile ISOLIR) pukul 01:00 WIB.</span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
