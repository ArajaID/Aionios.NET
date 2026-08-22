<script>
    import { Link, useForm, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Table from '@/Components/ui/table/Table.svelte';
    import TableHeader from '@/Components/ui/table/TableHeader.svelte';
    import TableBody from '@/Components/ui/table/TableBody.svelte';
    import TableRow from '@/Components/ui/table/TableRow.svelte';
    import TableHead from '@/Components/ui/table/TableHead.svelte';
    import TableCell from '@/Components/ui/table/TableCell.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import { CreditCard, Plus, Search, RotateCcw, ShieldAlert, CheckCircle2 } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        payments = { data: [], links: [] },
        filters = {},
    } = $props();

    let search = $state(filters.search || '');
    let method = $state(filters.method || '');
    let status = $state(filters.status || '');

    let reversalModalOpen = $state(false);
    let selectedPayment = $state(null);

    const reversalForm = useForm({
        reason: '',
    });

    function handleFilter() {
        router.get('/payments', { search, method, status }, { preserveState: true, replace: true });
    }

    function openReversal(payment) {
        selectedPayment = payment;
        reversalForm.reason = '';
        reversalModalOpen = true;
    }

    function handleReversal(e) {
        e.preventDefault();
        reversalForm.post(`/payments/${selectedPayment.id}/reversal`, {
            onSuccess: () => (reversalModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Riwayat Pembayaran"
    breadcrumbs={[{ label: 'Pembayaran' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <CreditCard class="h-5 w-5 text-stone-800" />
                    Penerimaan Pembayaran Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">Konfirmasi pelunasan invoice, perhitungan MDR QRIS, pencatatan kas/bank, dan pengajuan reversal.</p>
            </div>

            <Link href="/payments/create">
                <Button variant="default" size="sm">
                    <Plus class="h-4 w-4 mr-1" />
                    Konfirmasi Pembayaran Baru
                </Button>
            </Link>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    handleFilter();
                }}
                class="grid grid-cols-1 sm:grid-cols-4 gap-3"
            >
                <div class="sm:col-span-2 relative">
                    <Input
                        bind:value={search}
                        placeholder="Cari No. Pembayaran atau Nama Pelanggan..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div>
                    <Select bind:value={method} onchange={handleFilter}>
                        <option value="">Semua Metode</option>
                        <option value="manual">Manual (Bank / Kas)</option>
                        <option value="qris">QRIS Manual</option>
                    </Select>
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={status} onchange={handleFilter}>
                        <option value="">Semua Status</option>
                        <option value="posted">Posted (Sah)</option>
                        <option value="reversed">Reversed (Dibatalkan)</option>
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; method = ''; status = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Pembayaran</TableHead>
                    <TableHead>Pelanggan</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Metode</TableHead>
                    <TableHead>Kas / Bank</TableHead>
                    <TableHead class="text-right">Gross (Invoice)</TableHead>
                    <TableHead class="text-right">MDR QRIS</TableHead>
                    <TableHead class="text-right">Net Settlement</TableHead>
                    <TableHead class="text-center">Status</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if payments.data.length === 0}
                    <TableRow>
                        <TableCell colspan="10" class="text-center py-8 text-stone-500">
                            Tidak ada data transaksi pembayaran.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each payments.data as pay}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-stone-800">
                                {pay.payment_number}
                            </TableCell>

                            <TableCell>
                                <Link href={`/customers/${pay.customer_id}`} class="font-medium text-stone-900 hover:text-stone-800 hover:underline">
                                    {pay.customer?.name}
                                </Link>
                                <span class="text-[11px] text-stone-500 block font-mono">{pay.customer?.customer_id}</span>
                            </TableCell>

                            <TableCell class="text-stone-500 text-xs">{formatDate(pay.payment_date)}</TableCell>

                            <TableCell>
                                <Badge variant={pay.payment_method === 'qris' ? 'purple' : 'default'}>
                                    {pay.payment_method?.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell class="text-stone-700 text-xs">
                                {pay.cash_bank_account?.name ?? '-'}
                            </TableCell>

                            <TableCell class="text-right font-mono text-stone-800">{formatRupiah(pay.gross_amount)}</TableCell>
                            <TableCell class="text-right font-mono text-rose-700">
                                {pay.mdr_fee > 0 ? `-${formatRupiah(pay.mdr_fee)} (${pay.mdr_percentage}%)` : '-'}
                            </TableCell>
                            <TableCell class="text-right font-mono font-bold text-emerald-700">{formatRupiah(pay.net_amount)}</TableCell>

                            <TableCell class="text-center">
                                <Badge variant={pay.status === 'posted' ? 'success' : 'danger'}>
                                    {pay.status?.toUpperCase()}
                                </Badge>
                                {#if pay.reversal_request && pay.reversal_request.status === 'pending'}
                                    <span class="block text-[9px] text-amber-800 mt-0.5">Rev Pending</span>
                                {/if}
                            </TableCell>

                            <TableCell class="text-right">
                                {#if pay.status === 'posted'}
                                    {#if pay.reversal_request && pay.reversal_request.status === 'pending'}
                                        <span class="text-[11px] text-amber-800">Menunggu Review</span>
                                    {:else}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-[11px] text-rose-700 hover:bg-rose-950/30"
                                            onclick={() => openReversal(pay)}
                                        >
                                            <RotateCcw class="h-3 w-3 mr-1" />
                                            Ajukan Reversal
                                        </Button>
                                    {/if}
                                {:else}
                                    <span class="text-[11px] text-stone-500">Reversed</span>
                                {/if}
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={payments.links} />
    </div>

    <!-- Reversal request modal -->
    <Dialog bind:open={reversalModalOpen} title={`Ajukan Reversal Pembayaran ${selectedPayment?.payment_number}`}>
        <form onsubmit={handleReversal} class="space-y-4">
            <Alert variant="warning" title="Integritas Transaksi">
                Transaksi finansial yang telah diposting bersifat <strong>immutable</strong> (tidak dapat diedit/dihapus langsung). Pengajuan reversal memerlukan <strong>persetujuan Owner</strong> untuk membuat jurnal pembalik dan mengembalikan invoice ke status belum lunas.
            </Alert>

            <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 text-xs space-y-1">
                <div class="flex justify-between text-stone-500">
                    <span>Pelanggan:</span>
                    <span class="font-bold text-stone-800">{selectedPayment?.customer?.name}</span>
                </div>
                <div class="flex justify-between text-stone-500">
                    <span>Nominal Transaksi:</span>
                    <span class="font-bold text-emerald-700 font-mono">{formatRupiah(selectedPayment?.gross_amount)}</span>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="reversal_reason" class="text-xs font-semibold text-stone-700">Alasan Reversal (Wajib Diisi)</label>
                <Input
                    id="reversal_reason"
                    bind:value={reversalForm.reason}
                    placeholder="e.g. Salah input rekening kas / bukti transfer fiktif..."
                    required
                />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (reversalModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={reversalForm.processing}>
                    {reversalForm.processing ? 'Mengirim...' : 'Kirim Pengajuan ke Owner'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
