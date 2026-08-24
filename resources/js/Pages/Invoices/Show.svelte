<script>
    import { useForm, router, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Receipt, ArrowLeft, Printer, CreditCard, CheckCircle2, AlertCircle, Calendar, Edit3, Trash2, X, Save } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let { invoice = {}, is_owner = false } = $props();

    let adjustModalOpen = $state(false);

    const adjustForm = useForm({
        subtotal: invoice.subtotal || 0,
        discount_amount: invoice.discount_amount || 0,
        notes: 'Penyesuaian nominal tagihan',
    });

    const calculatedTotal = $derived(
        Math.max(0, Number(adjustForm.subtotal || 0) - Number(adjustForm.discount_amount || 0))
    );

    function handlePrint() {
        window.print();
    }

    function handleAdjustSubmit(e) {
        e.preventDefault();
        adjustForm.put(`/invoices/${invoice.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                adjustModalOpen = false;
            },
        });
    }

    function handleDeleteInvoice() {
        if (confirm(`Apakah Anda yakin ingin menghapus tagihan ${invoice.invoice_number}? Aksi ini tidak dapat dibatalkan.`)) {
            router.delete(`/invoices/${invoice.id}`);
        }
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
                    <Button variant="outline" size="sm" onclick={() => (adjustModalOpen = true)}>
                        <Edit3 class="h-3.5 w-3.5 mr-1" />
                        {is_owner ? 'Sesuaikan Nominal' : 'Ajukan Penyesuaian'}
                    </Button>

                    {#if is_owner}
                        <Button variant="destructive" size="sm" onclick={handleDeleteInvoice}>
                            <Trash2 class="h-3.5 w-3.5 mr-1" />
                            Hapus Tagihan
                        </Button>
                    {/if}

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

        <!-- Pending Adjustment Banner -->
        {#if invoice.pending_adjustment_request}
            <div class="rounded-xl border border-amber-500/30 bg-amber-50 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 no-print">
                <div class="flex items-start gap-3">
                    <AlertCircle class="h-5 w-5 text-amber-700 shrink-0 mt-0.5" />
                    <div>
                        <h4 class="text-xs font-bold text-amber-900">Menunggu Persetujuan Owner (Approval Pending)</h4>
                        <p class="text-xs text-amber-800 mt-0.5">
                            Pengajuan penyesuaian nominal dari <strong>{formatRupiah(invoice.pending_adjustment_request.old_total_amount)}</strong> menjadi <strong>{formatRupiah(invoice.pending_adjustment_request.new_total_amount)}</strong> oleh <strong>{invoice.pending_adjustment_request.requester?.name}</strong> sedang menunggu verifikasi Owner.
                        </p>
                        {#if invoice.pending_adjustment_request.reason}
                            <p class="text-[11px] text-amber-700 italic mt-1">Alasan: {invoice.pending_adjustment_request.reason}</p>
                        {/if}
                    </div>
                </div>
                {#if is_owner}
                    <Link href="/approvals">
                        <Button size="sm" class="shrink-0 bg-amber-600 hover:bg-amber-700 text-stone-900 text-xs">
                            Review di Approvals
                        </Button>
                    </Link>
                {/if}
            </div>
        {/if}

        <!-- Adjustment Modal -->
        {#if adjustModalOpen}
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 sm:p-6 no-print">
                <button
                    type="button"
                    class="fixed inset-0 cursor-default bg-stone-900/60 backdrop-blur-xs"
                    onclick={() => (adjustModalOpen = false)}
                    aria-label="Tutup modal"
                ></button>

                <Card class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto shadow-2xl">
                    <form onsubmit={handleAdjustSubmit}>
                        <CardHeader>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <Edit3 class="h-4 w-4 text-stone-800" />
                                        {is_owner ? 'Sesuaikan Nominal Tagihan' : 'Ajukan Penyesuaian Tagihan'}
                                    </CardTitle>
                                    <CardDescription class="mt-1">
                                        {is_owner
                                            ? `Ubah subtotal atau diskon untuk tagihan ${invoice.invoice_number}. Jika total menjadi Rp 0, tagihan otomatis berstatus Lunas.`
                                            : `Ajukan penyesuaian nominal tagihan ${invoice.invoice_number} kepada Owner untuk disetujui.`}
                                    </CardDescription>
                                </div>
                                <button
                                    type="button"
                                    onclick={() => (adjustModalOpen = false)}
                                    class="rounded-lg p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700"
                                    aria-label="Tutup"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <div class="space-y-1.5">
                                <label for="adj_subtotal" class="text-xs font-semibold text-stone-700">Subtotal Tagihan (Rp)</label>
                                <Input
                                    id="adj_subtotal"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    bind:value={adjustForm.subtotal}
                                    required
                                />
                                {#if adjustForm.errors.subtotal}
                                    <p class="text-xs text-rose-700">{adjustForm.errors.subtotal}</p>
                                {/if}
                            </div>

                            <div class="space-y-1.5">
                                <label for="adj_discount" class="text-xs font-semibold text-stone-700">Potongan Diskon (Rp)</label>
                                <Input
                                    id="adj_discount"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    bind:value={adjustForm.discount_amount}
                                />
                                {#if adjustForm.errors.discount_amount}
                                    <p class="text-xs text-rose-700">{adjustForm.errors.discount_amount}</p>
                                {/if}
                            </div>

                            <div class="rounded-xl border border-stone-200 bg-stone-50 p-3 flex justify-between items-center text-xs">
                                <span class="font-semibold text-stone-600">Total Tagihan Baru:</span>
                                <span class="font-mono font-bold text-base text-stone-900">{formatRupiah(calculatedTotal)}</span>
                            </div>

                            <div class="space-y-1.5">
                                <label for="adj_notes" class="text-xs font-semibold text-stone-700">Alasan / Catatan Penyesuaian</label>
                                <Input
                                    id="adj_notes"
                                    bind:value={adjustForm.notes}
                                    placeholder="e.g. Koreksi paket salah / Peralihan sistem lama"
                                    required={!is_owner}
                                />
                            </div>
                        </CardContent>

                        <CardFooter class="flex items-center justify-end gap-2 border-t border-stone-200 pt-4">
                            <Button type="button" variant="outline" size="sm" onclick={() => (adjustModalOpen = false)}>
                                Batal
                            </Button>
                            <Button type="submit" size="sm" disabled={adjustForm.processing}>
                                <Save class="h-4 w-4 mr-1" />
                                {adjustForm.processing
                                    ? 'Menyimpan...'
                                    : is_owner
                                    ? 'Simpan Perubahan Nominal'
                                    : 'Kirim Pengajuan ke Owner'}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        {/if}

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
