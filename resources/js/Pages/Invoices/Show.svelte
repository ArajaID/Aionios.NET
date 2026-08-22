<script>
    import { Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Receipt, ArrowLeft, Printer, CreditCard, CheckCircle2, AlertCircle, Calendar } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let { invoice = {} } = $props();

    function handlePrint() {
        window.print();
    }
</script>

<AuthenticatedLayout
    title={`Invoice: ${invoice.invoice_number}`}
    breadcrumbs={[
        { label: 'Tagihan', href: '/invoices' },
        { label: invoice.invoice_number },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Top Action Bar (hidden in print) -->
        <div class="flex items-center justify-between no-print">
            <Link href="/invoices">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali ke Daftar
                </Button>
            </Link>

            <div class="flex items-center gap-2">
                {#if invoice.status !== 'paid'}
                    <Link href={`/payments/create?customer_id=${invoice.customer_id}`}>
                        <Button variant="default" size="sm">
                            <CreditCard class="h-3.5 w-3.5 mr-1" />
                            Bayar Tagihan Ini
                        </Button>
                    </Link>
                {/if}

                <Button variant="outline" size="sm" onclick={handlePrint}>
                    <Printer class="h-3.5 w-3.5 mr-1" />
                    Cetak / Simpan PDF
                </Button>
            </div>
        </div>

        <!-- Printable Formal Invoice Document -->
        <div class="rounded-2xl border border-stone-200 bg-white p-8 shadow-2xl backdrop-blur-xl print:border-0 print:bg-white print:text-black print:p-0">
            <!-- Invoice Document Header -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 border-b border-stone-200 pb-8 print:border-stone-300">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-stone-900 font-black text-lg">
                            A
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-stone-900 print:text-black leading-tight">Aionios.NET</h2>
                            <p class="text-[11px] text-stone-500 print:text-stone-600">PT Aionios Solusi Telematika</p>
                        </div>
                    </div>
                    <p class="text-xs text-stone-500 print:text-stone-600 mt-3 max-w-sm leading-relaxed">
                        Cyber Building 2 Lt. 12, Jl. HR Rasuna Said, Jakarta<br />
                        Kontak Billing: (021) 555-0199 / billing@aionios.net
                    </p>
                </div>

                <div class="text-left sm:text-right space-y-1">
                    <h1 class="text-2xl font-black text-stone-900 print:text-black font-mono">TAGIHAN / INVOICE</h1>
                    <p class="mt-1 inline-flex rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 font-mono text-xs font-bold tracking-wide text-blue-800 print:border-stone-300 print:bg-white print:text-black">
                        {invoice.invoice_number}
                    </p>
                    <div class="pt-2">
                        <Badge
                            variant={invoice.status === 'paid' ? 'success' : invoice.status === 'overdue' ? 'danger' : 'warning'}
                            class="text-xs px-3 py-1"
                        >
                            STATUS: {invoice.status?.toUpperCase()}
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Customer & Dates Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-stone-200 print:border-stone-300 text-xs">
                <div>
                    <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider mb-1">Ditujukan Kepada Pelanggan:</p>
                    <h3 class="text-sm font-bold text-stone-900 print:text-black">{invoice.customer?.name}</h3>
                    <p class="font-mono text-stone-500 print:text-stone-600">ID: {invoice.customer?.customer_id}</p>
                    <p class="text-stone-500 print:text-stone-600">Alamat: {invoice.customer?.address}</p>
                    <p class="text-stone-500 print:text-stone-600">No. HP: {invoice.customer?.phone}</p>
                </div>

                <div class="sm:text-right space-y-1">
                    <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider mb-1">Detail Periode & Waktu:</p>
                    <p class="text-stone-700 print:text-stone-800">Periode Tagihan: <span class="font-bold font-mono">{invoice.period}</span></p>
                    <p class="text-stone-700 print:text-stone-800">Tanggal Terbit: <span class="font-semibold">{formatDate(invoice.issue_date)}</span></p>
                    <p class="text-stone-700 print:text-stone-800">Jatuh Tempo: <span class="font-bold text-rose-700 print:text-rose-600">{formatDate(invoice.due_date)}</span></p>
                </div>
            </div>

            <!-- Itemized Breakdown Table -->
            <div class="py-6">
                <table class="w-full text-xs text-left">
                    <thead class="border-b border-stone-200 print:border-stone-300 text-stone-500 print:text-stone-700 uppercase">
                        <tr>
                            <th class="py-3">Deskripsi Layanan</th>
                            <th class="py-3 text-center">Tipe</th>
                            <th class="py-3 text-right">Tarif Normal</th>
                            <th class="py-3 text-right">Potongan Diskon</th>
                            <th class="py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                        <tr>
                            <td class="py-4">
                                <p class="font-semibold text-stone-900 print:text-black">
                                    {invoice.snapshot_data?.package_name || invoice.customer?.package?.name || 'Paket Akses Internet Broadband'}
                                </p>
                                <p class="text-[11px] text-stone-500 print:text-stone-500 mt-0.5">
                                    {#if invoice.is_prorata}
                                        Tagihan Prorata Pasang Baru: {invoice.snapshot_data?.formula || 'Dihitung berdasarkan hari aktif'}
                                    {:else}
                                        Layanan internet broadband periode {invoice.period}
                                    {/if}
                                </p>
                            </td>
                            <td class="py-4 text-center">
                                <Badge variant={invoice.is_prorata ? 'purple' : 'outline'}>
                                    {#if invoice.is_prorata}<Calendar class="h-3 w-3" />{/if}
                                    {invoice.is_prorata ? 'Prorata' : 'Reguler'}
                                </Badge>
                            </td>
                            <td class="py-4 text-right font-mono text-stone-700 print:text-stone-800">{formatRupiah(invoice.subtotal)}</td>
                            <td class="py-4 text-right font-mono text-rose-700 print:text-rose-600">
                                {invoice.discount_amount > 0 ? `-${formatRupiah(invoice.discount_amount)}` : 'Rp 0'}
                            </td>
                            <td class="py-4 text-right font-mono font-bold text-stone-900 print:text-black">{formatRupiah(invoice.total_amount)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Total Calculation Summary -->
            <div class="border-t border-stone-200 print:border-stone-300 pt-4 flex justify-end">
                <div class="w-full sm:w-72 space-y-2 text-xs">
                    <div class="flex justify-between text-stone-500 print:text-stone-600">
                        <span>Subtotal:</span>
                        <span class="font-mono">{formatRupiah(invoice.subtotal)}</span>
                    </div>
                    <div class="flex justify-between text-rose-700 print:text-rose-600">
                        <span>Diskon Promo:</span>
                        <span class="font-mono">-{formatRupiah(invoice.discount_amount)}</span>
                    </div>
                    <div class="flex justify-between text-sm font-black border-t border-stone-200 print:border-stone-300 pt-2 text-stone-900 print:text-black">
                        <span>Total Tagihan:</span>
                        <span class="font-mono text-stone-800 print:text-indigo-700">{formatRupiah(invoice.total_amount)}</span>
                    </div>
                    <div class="flex justify-between text-xs font-semibold text-emerald-700 print:text-emerald-700 pt-1">
                        <span>Jumlah Dibayar:</span>
                        <span class="font-mono">{formatRupiah(invoice.paid_amount)}</span>
                    </div>
                </div>
            </div>

            <!-- Footer Notes & Payment Instructions -->
            <div class="mt-10 border-t border-stone-200 print:border-stone-300 pt-6 text-xs text-stone-500 print:text-stone-600 space-y-2">
                <p class="font-semibold text-stone-700 print:text-stone-800">Petunjuk Pembayaran:</p>
                <p>Pembayaran dapat dilakukan melalui transfer Bank BCA / BRI atau scan QRIS kasir. Harap melunasi tagihan sebelum tanggal 22 setiap bulannya untuk menghindari isolir otomatis pada tanggal 23.</p>
                <p class="text-[10px] text-stone-500 pt-2">Dokumen ini diterbitkan secara elektronik oleh sistem operasional ISP Aionios.NET.</p>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
