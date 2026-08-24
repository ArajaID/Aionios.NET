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
    import { CheckSquare, Check, X, TrendingDown, RotateCcw, FileEdit, Package as PackageIcon, ArrowRight } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        pending_expenses = [],
        pending_reversals = [],
        pending_invoice_adjustments = [],
        pending_package_changes = [],
    } = $props();

    let activeTab = $state('expenses');
    let approveExpenseModalOpen = $state(false);
    let approveReversalModalOpen = $state(false);
    let approveAdjModalOpen = $state(false);
    let approvePkgModalOpen = $state(false);

    let rejectExpenseModalOpen = $state(false);
    let rejectReversalModalOpen = $state(false);
    let rejectAdjModalOpen = $state(false);
    let rejectPkgModalOpen = $state(false);

    let selectedExpense = $state(null);
    let selectedReversal = $state(null);
    let selectedAdj = $state(null);
    let selectedPkg = $state(null);

    let approvingExpense = $state(false);
    let approvingReversal = $state(false);
    let approvingAdj = $state(false);
    let approvingPkg = $state(false);

    const rejectExpenseForm = useForm({
        rejection_reason: '',
    });

    const rejectReversalForm = useForm({
        rejection_reason: '',
    });

    const rejectAdjForm = useForm({
        rejection_reason: '',
    });

    const rejectPkgForm = useForm({
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

    // Invoice Adjustment Actions
    function openApproveAdj(adj) {
        selectedAdj = adj;
        approveAdjModalOpen = true;
    }

    function confirmApproveAdj() {
        if (!selectedAdj || approvingAdj) return;

        approvingAdj = true;
        router.post(`/approvals/invoice-adjustment/${selectedAdj.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => (approveAdjModalOpen = false),
            onFinish: () => (approvingAdj = false),
        });
    }

    function openRejectAdj(adj) {
        selectedAdj = adj;
        rejectAdjForm.rejection_reason = '';
        rejectAdjModalOpen = true;
    }

    function handleRejectAdj(e) {
        e.preventDefault();
        rejectAdjForm.post(`/approvals/invoice-adjustment/${selectedAdj.id}/reject`, {
            onSuccess: () => (rejectAdjModalOpen = false),
        });
    }

    // Package Change Actions
    function openApprovePkg(pkg) {
        selectedPkg = pkg;
        approvePkgModalOpen = true;
    }

    function confirmApprovePkg() {
        if (!selectedPkg || approvingPkg) return;

        approvingPkg = true;
        router.post(`/approvals/package-change/${selectedPkg.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => (approvePkgModalOpen = false),
            onFinish: () => (approvingPkg = false),
        });
    }

    function openRejectPkg(pkg) {
        selectedPkg = pkg;
        rejectPkgForm.rejection_reason = '';
        rejectPkgModalOpen = true;
    }

    function handleRejectPkg(e) {
        e.preventDefault();
        rejectPkgForm.post(`/approvals/package-change/${selectedPkg.id}/reject`, {
            onSuccess: () => (rejectPkgModalOpen = false),
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
                    Kontrol otorisasi Owner atas beban kas/bank, reversal transaksi, penyesuaian invoice, dan pergantian paket pelanggan.
                </p>
            </div>
        </div>

        <Tabs value={activeTab}>
            <TabsList>
                <TabsTrigger value="expenses" activeValue={activeTab} onclick={() => (activeTab = 'expenses')}>
                    <TrendingDown class="h-4 w-4" />
                    Beban Kas ({pending_expenses.length})
                </TabsTrigger>
                <TabsTrigger value="reversals" activeValue={activeTab} onclick={() => (activeTab = 'reversals')}>
                    <RotateCcw class="h-4 w-4" />
                    Reversal ({pending_reversals.length})
                </TabsTrigger>
                <TabsTrigger value="invoice_adjustments" activeValue={activeTab} onclick={() => (activeTab = 'invoice_adjustments')}>
                    <FileEdit class="h-4 w-4" />
                    Penyesuaian Tagihan ({pending_invoice_adjustments.length})
                </TabsTrigger>
                <TabsTrigger value="package_changes" activeValue={activeTab} onclick={() => (activeTab = 'package_changes')}>
                    <PackageIcon class="h-4 w-4" />
                    Ganti Paket ({pending_package_changes.length})
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
                                                <td class="py-3 px-4 text-rose-700 font-medium">{rev.reason}</td>
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

            <!-- TAB 3: PENDING INVOICE ADJUSTMENTS -->
            <TabsContent value="invoice_adjustments" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Pengajuan Penyesuaian Nominal Tagihan</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                    <tr>
                                        <th class="py-3 px-4">No. Tagihan</th>
                                        <th class="py-3 px-4">Pelanggan</th>
                                        <th class="py-3 px-4">Diajukan Oleh</th>
                                        <th class="py-3 px-4">Alasan Penyesuaian</th>
                                        <th class="py-3 px-4 text-right">Nominal Lama</th>
                                        <th class="py-3 px-4 text-right">Nominal Baru</th>
                                        <th class="py-3 px-4 text-right">Aksi Owner</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if pending_invoice_adjustments.length === 0}
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-stone-500">
                                                Tidak ada pengajuan penyesuaian tagihan yang menunggu persetujuan.
                                            </td>
                                        </tr>
                                    {:else}
                                        {#each pending_invoice_adjustments as adj}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-3 px-4 font-mono font-semibold text-stone-800">
                                                    {adj.invoice?.invoice_number}
                                                </td>
                                                <td class="py-3 px-4 font-medium text-stone-800">
                                                    {adj.invoice?.customer?.name} ({adj.invoice?.customer?.customer_id})
                                                </td>
                                                <td class="py-3 px-4 text-stone-500">{adj.requester?.name}</td>
                                                <td class="py-3 px-4 text-stone-700">{adj.reason}</td>
                                                <td class="py-3 px-4 text-right font-mono text-stone-500 line-through">
                                                    {formatRupiah(adj.old_total_amount)}
                                                </td>
                                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                                                    {formatRupiah(adj.new_total_amount)}
                                                    {#if Number(adj.new_total_amount) === 0}
                                                        <Badge variant="success" class="ml-1 text-[10px]">Otomatis Lunas</Badge>
                                                    {/if}
                                                </td>
                                                <td class="py-3 px-4 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="success"
                                                            size="sm"
                                                            class="h-7 px-2.5 text-[11px]"
                                                            onclick={() => openApproveAdj(adj)}
                                                        >
                                                            <Check class="h-3.5 w-3.5 mr-1" />
                                                            Setujui
                                                        </Button>

                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            class="h-7 px-2 text-[11px]"
                                                            onclick={() => openRejectAdj(adj)}
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

            <!-- TAB 4: PENDING PACKAGE CHANGES -->
            <TabsContent value="package_changes" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Pengajuan Perubahan Paket Pelanggan</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                    <tr>
                                        <th class="py-3 px-4">Pelanggan</th>
                                        <th class="py-3 px-4">Diajukan Oleh</th>
                                        <th class="py-3 px-4">Paket Saat Ini</th>
                                        <th class="py-3 px-4"></th>
                                        <th class="py-3 px-4">Paket Baru</th>
                                        <th class="py-3 px-4">Alasan</th>
                                        <th class="py-3 px-4 text-right">Aksi Owner</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if pending_package_changes.length === 0}
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-stone-500">
                                                Tidak ada pengajuan perubahan paket yang menunggu persetujuan.
                                            </td>
                                        </tr>
                                    {:else}
                                        {#each pending_package_changes as pkg}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-3 px-4 font-medium text-stone-800">
                                                    {pkg.customer?.name}
                                                    <span class="block font-mono text-[11px] text-stone-500">{pkg.customer?.customer_id}</span>
                                                </td>
                                                <td class="py-3 px-4 text-stone-500">{pkg.requester?.name}</td>
                                                <td class="py-3 px-4">
                                                    <span class="font-semibold text-stone-800">{pkg.old_package?.name}</span>
                                                    <span class="block text-[11px] text-stone-500">{formatRupiah(pkg.old_package?.price)}/bln</span>
                                                </td>
                                                <td class="py-3 px-2 text-stone-400">
                                                    <ArrowRight class="h-4 w-4" />
                                                </td>
                                                <td class="py-3 px-4">
                                                    <span class="font-bold text-indigo-700">{pkg.new_package?.name}</span>
                                                    <span class="block text-[11px] text-stone-500">{formatRupiah(pkg.new_package?.price)}/bln ({pkg.new_package?.download_speed_mbps} Mbps)</span>
                                                </td>
                                                <td class="py-3 px-4 text-stone-700">{pkg.reason}</td>
                                                <td class="py-3 px-4 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <Button
                                                            variant="success"
                                                            size="sm"
                                                            class="h-7 px-2.5 text-[11px]"
                                                            onclick={() => openApprovePkg(pkg)}
                                                        >
                                                            <Check class="h-3.5 w-3.5 mr-1" />
                                                            Setujui & Sync MT
                                                        </Button>

                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            class="h-7 px-2 text-[11px]"
                                                            onclick={() => openRejectPkg(pkg)}
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

    <!-- CONFIRM APPROVE EXPENSE -->
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

    <!-- CONFIRM APPROVE REVERSAL -->
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

    <!-- CONFIRM APPROVE INVOICE ADJUSTMENT -->
    <ConfirmationDialog
        bind:open={approveAdjModalOpen}
        title={`Setujui Penyesuaian Tagihan ${selectedAdj?.invoice?.invoice_number ?? ''}`}
        confirmLabel="Ya, Setujui Penyesuaian"
        variant="success"
        processing={approvingAdj}
        onconfirm={confirmApproveAdj}
    >
        Nominal tagihan <strong>{selectedAdj?.invoice?.invoice_number}</strong> akan diubah dari
        <span class="line-through">{formatRupiah(selectedAdj?.old_total_amount ?? 0)}</span> menjadi
        <strong>{formatRupiah(selectedAdj?.new_total_amount ?? 0)}</strong>.
        {#if Number(selectedAdj?.new_total_amount ?? 0) === 0}
            <br />Status tagihan akan otomatis menjadi <strong>LUNAS (PAID)</strong>.
        {/if}
    </ConfirmationDialog>

    <!-- CONFIRM APPROVE PACKAGE CHANGE -->
    <ConfirmationDialog
        bind:open={approvePkgModalOpen}
        title={`Setujui Perubahan Paket ${selectedPkg?.customer?.name ?? ''}`}
        confirmLabel="Ya, Setujui & Sync MikroTik"
        variant="success"
        processing={approvingPkg}
        onconfirm={confirmApprovePkg}
    >
        Paket pelanggan <strong>{selectedPkg?.customer?.name}</strong> akan diubah menjadi
        <strong>{selectedPkg?.new_package?.name}</strong>.
        Sistem akan langsung memperbarui profil PPP di router <strong>MikroTik secara real-time</strong>.
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

    <!-- REJECT INVOICE ADJUSTMENT MODAL -->
    <Dialog bind:open={rejectAdjModalOpen} title="Tolak Penyesuaian Tagihan">
        <form onsubmit={handleRejectAdj} class="space-y-4">
            <div class="space-y-1.5">
                <label for="rej_adj_reason" class="text-xs font-semibold text-stone-700">Alasan Penolakan (Wajib)</label>
                <Input id="rej_adj_reason" bind:value={rejectAdjForm.rejection_reason} placeholder="Alasan penolakan..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (rejectAdjModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={rejectAdjForm.processing}>
                    {rejectAdjForm.processing ? 'Menolak...' : 'Konfirmasi Tolak'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- REJECT PACKAGE CHANGE MODAL -->
    <Dialog bind:open={rejectPkgModalOpen} title="Tolak Perubahan Paket">
        <form onsubmit={handleRejectPkg} class="space-y-4">
            <div class="space-y-1.5">
                <label for="rej_pkg_reason" class="text-xs font-semibold text-stone-700">Alasan Penolakan (Wajib)</label>
                <Input id="rej_pkg_reason" bind:value={rejectPkgForm.rejection_reason} placeholder="Alasan penolakan..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (rejectPkgModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={rejectPkgForm.processing}>
                    {rejectPkgForm.processing ? 'Menolak...' : 'Konfirmasi Tolak'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
