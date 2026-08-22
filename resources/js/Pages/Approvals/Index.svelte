<script>
    import { router, useForm } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import ConfirmationDialog from '@/Components/ui/confirmation-dialog/ConfirmationDialog.svelte';
    import Tabs from '@/Components/ui/tabs/Tabs.svelte';
    import TabsList from '@/Components/ui/tabs/TabsList.svelte';
    import TabsTrigger from '@/Components/ui/tabs/TabsTrigger.svelte';
    import TabsContent from '@/Components/ui/tabs/TabsContent.svelte';
    import { CheckSquare, Check, X, TrendingDown, RotateCcw } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        pending_expenses = [],
        pending_reversals = [],
    } = $props();

    let activeTab = $state('expenses');
    let approveExpenseModalOpen = $state(false);
    let approveReversalModalOpen = $state(false);
    let rejectExpenseModalOpen = $state(false);
    let rejectReversalModalOpen = $state(false);
    let selectedExpense = $state(null);
    let selectedReversal = $state(null);
    let approvingExpense = $state(false);
    let approvingReversal = $state(false);

    const rejectExpenseForm = useForm({
        rejection_reason: '',
    });

    const rejectReversalForm = useForm({
        rejection_reason: '',
    });

    // Expenses Actions
    function openApproveExpense(exp) {
        selectedExpense = exp;
        approveExpenseModalOpen = true;
    }

    function confirmApproveExpense() {
        if (!selectedExpense || approvingExpense) return;

        approvingExpense = true;
        router.post(`/expenses/${selectedExpense.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => (approveExpenseModalOpen = false),
            onFinish: () => (approvingExpense = false),
        });
    }

    function openRejectExpense(exp) {
        selectedExpense = exp;
        rejectExpenseForm.rejection_reason = '';
        rejectExpenseModalOpen = true;
    }

    function handleRejectExpense(e) {
        e.preventDefault();
        rejectExpenseForm.post(`/expenses/${selectedExpense.id}/reject`, {
            onSuccess: () => (rejectExpenseModalOpen = false),
        });
    }

    // Reversals Actions
    function openApproveReversal(rev) {
        selectedReversal = rev;
        approveReversalModalOpen = true;
    }

    function confirmApproveReversal() {
        if (!selectedReversal || approvingReversal) return;

        approvingReversal = true;
        router.post(`/approvals/reversal/${selectedReversal.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => (approveReversalModalOpen = false),
            onFinish: () => (approvingReversal = false),
        });
    }

    function openRejectReversal(rev) {
        selectedReversal = rev;
        rejectReversalForm.rejection_reason = '';
        rejectReversalModalOpen = true;
    }

    function handleRejectReversal(e) {
        e.preventDefault();
        rejectReversalForm.post(`/approvals/reversal/${selectedReversal.id}/reject`, {
            onSuccess: () => (rejectReversalModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Persetujuan Owner (Approvals)"
    breadcrumbs={[{ label: 'Persetujuan Owner' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <CheckSquare class="h-5 w-5 text-stone-800" />
                    Pusat Persetujuan Owner (Approval Hub)
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Kontrol otorisasi Owner atas pengeluaran beban kas/bank dan pembatalan (reversal) transaksi keuangan.
                </p>
            </div>
        </div>

        <Tabs value={activeTab}>
            <TabsList>
                <TabsTrigger value="expenses" activeValue={activeTab} onclick={() => (activeTab = 'expenses')}>
                    <TrendingDown class="h-4 w-4" />
                    Pengeluaran Beban ({pending_expenses.length})
                </TabsTrigger>
                <TabsTrigger value="reversals" activeValue={activeTab} onclick={() => (activeTab = 'reversals')}>
                    <RotateCcw class="h-4 w-4" />
                    Reversal Pembayaran ({pending_reversals.length})
                </TabsTrigger>
            </TabsList>

            <!-- TAB 1: PENDING EXPENSES -->
            <TabsContent value="expenses" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Pengajuan Beban Menunggu Persetujuan</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                    <tr>
                                        <th class="py-3 px-4">No. Pengeluaran</th>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Akun Beban (COA)</th>
                                        <th class="py-3 px-4">Kas / Bank</th>
                                        <th class="py-3 px-4">Diajukan Oleh</th>
                                        <th class="py-3 px-4">Keterangan</th>
                                        <th class="py-3 px-4 text-right">Nominal</th>
                                        <th class="py-3 px-4 text-right">Aksi Owner</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if pending_expenses.length === 0}
                                        <tr>
                                            <td colspan="8" class="py-8 text-center text-stone-500">
                                                Tidak ada pengeluaran yang menunggu persetujuan.
                                            </td>
                                        </tr>
                                    {:else}
                                        {#each pending_expenses as exp}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-3 px-4 font-mono font-semibold text-rose-700">{exp.expense_number}</td>
                                                <td class="py-3 px-4 text-stone-500">{formatDate(exp.date)}</td>
                                                <td class="py-3 px-4 font-semibold text-stone-800">
                                                    {exp.chart_of_account?.name} ({exp.chart_of_account?.code})
                                                </td>
                                                <td class="py-3 px-4 text-stone-700">{exp.cash_bank_account?.name}</td>
                                                <td class="py-3 px-4 text-stone-500">{exp.submitter?.name}</td>
                                                <td class="py-3 px-4 text-stone-800">
                                                    {exp.description}
                                                    {#if exp.notes}
                                                        <span class="block text-[10px] text-stone-500 italic mt-0.5">Note: {exp.notes}</span>
                                                    {/if}
                                                </td>
                                                <td class="py-3 px-4 text-right font-mono font-bold text-stone-900">{formatRupiah(exp.amount)}</td>
                                                <td class="py-3 px-4 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="success"
                                                            size="sm"
                                                            class="h-7 px-2.5 text-[11px]"
                                                            onclick={() => openApproveExpense(exp)}
                                                        >
                                                            <Check class="h-3.5 w-3.5 mr-1" />
                                                            Setujui
                                                        </Button>

                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            class="h-7 px-2 text-[11px]"
                                                            onclick={() => openRejectExpense(exp)}
                                                        >
                                                            <X class="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- TAB 2: PENDING REVERSALS -->
            <TabsContent value="reversals" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Pengajuan Reversal Pembayaran Menunggu Persetujuan</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                    <tr>
                                        <th class="py-3 px-4">No. Pembayaran</th>
                                        <th class="py-3 px-4">Pelanggan</th>
                                        <th class="py-3 px-4">Waktu Transaksi</th>
                                        <th class="py-3 px-4">Diajukan Oleh</th>
                                        <th class="py-3 px-4">Alasan Reversal</th>
                                        <th class="py-3 px-4 text-right">Nominal</th>
                                        <th class="py-3 px-4 text-right">Aksi Owner</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if pending_reversals.length === 0}
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-stone-500">
                                                Tidak ada pengajuan reversal pembayaran yang menunggu.
                                            </td>
                                        </tr>
                                    {:else}
                                        {#each pending_reversals as rev}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-3 px-4 font-mono font-semibold text-stone-800">
                                                    {rev.payment?.payment_number}
                                                </td>
                                                <td class="py-3 px-4 font-medium text-stone-800">
                                                    {rev.payment?.customer?.name}
                                                </td>
                                                <td class="py-3 px-4 text-stone-500">{formatDate(rev.payment?.payment_date)}</td>
                                                <td class="py-3 px-4 text-stone-500">{rev.requester?.name}</td>
                                                <td class="py-3 px-4 text-rose-300 font-medium">{rev.reason}</td>
                                                <td class="py-3 px-4 text-right font-mono font-bold text-stone-900">
                                                    {formatRupiah(rev.payment?.gross_amount)}
                                                </td>
                                                <td class="py-3 px-4 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="success"
                                                            size="sm"
                                                            class="h-7 px-2.5 text-[11px]"
                                                            onclick={() => openApproveReversal(rev)}
                                                        >
                                                            <Check class="h-3.5 w-3.5 mr-1" />
                                                            Setujui Reversal
                                                        </Button>

                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            class="h-7 px-2 text-[11px]"
                                                            onclick={() => openRejectReversal(rev)}
                                                        >
                                                            <X class="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>

    <ConfirmationDialog
        bind:open={approveExpenseModalOpen}
        title={`Setujui Pengeluaran ${selectedExpense?.expense_number ?? ''}`}
        confirmLabel="Ya, Setujui & Posting"
        variant="success"
        processing={approvingExpense}
        onconfirm={confirmApproveExpense}
    >
        Pengeluaran <strong>{selectedExpense?.expense_number}</strong> sebesar
        <strong>{formatRupiah(selectedExpense?.amount ?? 0)}</strong> akan disetujui.
        Jurnal otomatis akan langsung diposting dan saldo
        <strong>{selectedExpense?.cash_bank_account?.name ?? 'kas/bank'}</strong> akan dikurangi.
    </ConfirmationDialog>

    <ConfirmationDialog
        bind:open={approveReversalModalOpen}
        title={`Setujui Reversal ${selectedReversal?.payment?.payment_number ?? ''}`}
        confirmLabel="Ya, Setujui Reversal"
        variant="reversal"
        processing={approvingReversal}
        onconfirm={confirmApproveReversal}
    >
        Pembayaran <strong>{selectedReversal?.payment?.payment_number}</strong> sebesar
        <strong>{formatRupiah(selectedReversal?.payment?.gross_amount ?? 0)}</strong> akan dibalik.
        Jurnal pembalik akan diposting dan invoice pelanggan kembali berstatus
        <strong>Belum Lunas</strong>.
    </ConfirmationDialog>

    <!-- REJECT EXPENSE MODAL -->
    <Dialog bind:open={rejectExpenseModalOpen} title={`Tolak Pengeluaran ${selectedExpense?.expense_number}`}>
        <form onsubmit={handleRejectExpense} class="space-y-4">
            <div class="space-y-1.5">
                <label for="rej_exp_reason" class="text-xs font-semibold text-stone-700">Alasan Penolakan Owner (Wajib)</label>
                <Input id="rej_exp_reason" bind:value={rejectExpenseForm.rejection_reason} placeholder="Alasan penolakan..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (rejectExpenseModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={rejectExpenseForm.processing}>
                    {rejectExpenseForm.processing ? 'Menolak...' : 'Konfirmasi Tolak'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- REJECT REVERSAL MODAL -->
    <Dialog bind:open={rejectReversalModalOpen} title="Tolak Pengajuan Reversal">
        <form onsubmit={handleRejectReversal} class="space-y-4">
            <div class="space-y-1.5">
                <label for="rej_rev_reason" class="text-xs font-semibold text-stone-700">Alasan Penolakan Reversal (Wajib)</label>
                <Input id="rej_rev_reason" bind:value={rejectReversalForm.rejection_reason} placeholder="Alasan penolakan..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (rejectReversalModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={rejectReversalForm.processing}>
                    {rejectReversalForm.processing ? 'Menolak...' : 'Konfirmasi Tolak'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
