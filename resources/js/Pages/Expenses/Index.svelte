<script>
    import { useForm, router, page } from '@inertiajs/svelte';
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
    import ConfirmationDialog from '@/Components/ui/confirmation-dialog/ConfirmationDialog.svelte';
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import { TrendingDown, Plus, Search, Check, X } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        expenses = { data: [], links: [] },
        expense_coas = [],
        cash_bank_accounts = [],
        filters = {},
    } = $props();

    const user = $derived(page.props.auth?.user);

    let createModalOpen = $state(false);
    let approveModalOpen = $state(false);
    let rejectModalOpen = $state(false);
    let selectedExpense = $state(null);
    let approving = $state(false);

    let search = $state(filters.search || '');
    let status = $state(filters.status || '');

    const createForm = useForm({
        date: new Date().toISOString().split('T')[0],
        chart_of_account_id: expense_coas[0]?.id || '',
        cash_bank_account_id: cash_bank_accounts[0]?.id || '',
        amount: '',
        description: '',
        notes: '',
    });

    const rejectForm = useForm({
        rejection_reason: '',
    });

    function handleFilter() {
        router.get('/expenses', { search, status }, { preserveState: true, replace: true });
    }

    function handleCreate(e) {
        e.preventDefault();
        createForm.post('/expenses', {
            onSuccess: () => {
                createModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openApprove(exp) {
        selectedExpense = exp;
        approveModalOpen = true;
    }

    function confirmApprove() {
        if (!selectedExpense || approving) return;

        approving = true;
        router.post(`/expenses/${selectedExpense.id}/approve`, {}, {
            preserveScroll: true,
            onSuccess: () => (approveModalOpen = false),
            onFinish: () => (approving = false),
        });
    }

    function openReject(exp) {
        selectedExpense = exp;
        rejectForm.rejection_reason = '';
        rejectModalOpen = true;
    }

    function handleReject(e) {
        e.preventDefault();
        rejectForm.post(`/expenses/${selectedExpense.id}/reject`, {
            onSuccess: () => (rejectModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Pengeluaran Operasional"
    breadcrumbs={[{ label: 'Beban Operasional' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <TrendingDown class="h-5 w-5 text-rose-700" />
                    Beban & Pengeluaran Operasional
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Alur Draft &rarr; Pending Approval &rarr; Approved/Rejected oleh Owner. Jurnal dan pengurangan kas/bank terbentuk setelah disetujui.
                </p>
            </div>

            <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Ajukan Pengeluaran Baru
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    handleFilter();
                }}
                class="grid grid-cols-1 sm:grid-cols-3 gap-3"
            >
                <div class="sm:col-span-2 relative">
                    <Input
                        bind:value={search}
                        placeholder="Cari No. Pengeluaran atau Keterangan..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={status} onchange={handleFilter}>
                        <option value="">Semua Status</option>
                        <option value="pending">Pending Approval (Menunggu)</option>
                        <option value="approved">Approved (Disetujui)</option>
                        <option value="rejected">Rejected (Ditolak)</option>
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; status = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Pengeluaran</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Akun Beban (COA)</TableHead>
                    <TableHead>Sumber Kas/Bank</TableHead>
                    <TableHead>Keterangan</TableHead>
                    <TableHead class="text-right">Nominal</TableHead>
                    <TableHead class="text-center">Status</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if expenses.data.length === 0}
                    <TableRow>
                        <TableCell colspan="8" class="text-center py-8 text-stone-500">
                            Tidak ada pengeluaran yang ditemukan.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each expenses.data as exp}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-rose-700">
                                {exp.expense_number}
                            </TableCell>

                            <TableCell class="text-stone-500 text-xs">{formatDate(exp.date)}</TableCell>

                            <TableCell class="text-xs">
                                <span class="font-bold text-stone-800">{exp.chart_of_account?.name}</span>
                                <span class="text-[10px] text-stone-500 block font-mono">{exp.chart_of_account?.code}</span>
                            </TableCell>

                            <TableCell class="text-xs text-stone-700">
                                {exp.cash_bank_account?.name}
                            </TableCell>

                            <TableCell class="text-xs text-stone-700 max-w-xs truncate">
                                {exp.description}
                                {#if exp.rejection_reason}
                                    <span class="block text-[10px] text-rose-700 mt-0.5">Alasan Tolak: {exp.rejection_reason}</span>
                                {/if}
                            </TableCell>

                            <TableCell class="text-right font-mono font-bold text-stone-900">{formatRupiah(exp.amount)}</TableCell>

                            <TableCell class="text-center">
                                <Badge
                                    variant={exp.status === 'approved'
                                        ? 'success'
                                        : exp.status === 'pending'
                                        ? 'warning'
                                        : 'danger'}
                                >
                                    {exp.status?.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell class="text-right">
                                {#if exp.status === 'pending' && user?.role === 'owner'}
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="success"
                                            size="sm"
                                            class="h-7 px-2 text-[11px]"
                                            onclick={() => openApprove(exp)}
                                        >
                                            <Check class="h-3 w-3 mr-1" />
                                            Setujui
                                        </Button>

                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            class="h-7 px-2 text-[11px]"
                                            onclick={() => openReject(exp)}
                                        >
                                            <X class="h-3 w-3" />
                                        </Button>
                                    </div>
                                {:else if exp.status === 'pending'}
                                    <span class="text-[11px] text-amber-800">Menunggu Owner</span>
                                {:else}
                                    <span class="text-[11px] text-stone-500">Selesai</span>
                                {/if}
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={expenses.links} />
    </div>

    <!-- CREATE EXPENSE MODAL -->
    <Dialog bind:open={createModalOpen} title="Pengajuan Beban / Pengeluaran Operasional">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="exp_date" class="text-xs font-semibold text-stone-700">Tanggal Pengeluaran</label>
                    <Input id="exp_date" type="date" bind:value={createForm.date} required />
                </div>
                <div class="space-y-1.5">
                    <label for="exp_amount" class="text-xs font-semibold text-stone-700">Nominal (Rp)</label>
                    <Input id="exp_amount" type="number" bind:value={createForm.amount} min="1" placeholder="e.g. 1500000" required />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="exp_coa" class="text-xs font-semibold text-stone-700">Kategori Beban (Akun COA)</label>
                <Select id="exp_coa" bind:value={createForm.chart_of_account_id} required>
                    {#each expense_coas as coa}
                        <option value={coa.id}>{coa.code} - {coa.name}</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="exp_cb" class="text-xs font-semibold text-stone-700">Sumber Rekening Kas / Bank</label>
                <Select id="exp_cb" bind:value={createForm.cash_bank_account_id} required>
                    {#each cash_bank_accounts as cb}
                        <option value={cb.id}>{cb.name} (Saldo: {formatRupiah(cb.current_balance)})</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="exp_desc" class="text-xs font-semibold text-stone-700">Keterangan / Rincian Pengeluaran</label>
                <Input id="exp_desc" bind:value={createForm.description} placeholder="e.g. Pembayaran listrik POP Shelter 1" required />
            </div>

            <div class="space-y-1.5">
                <label for="exp_notes" class="text-xs font-semibold text-stone-700">Catatan untuk Owner</label>
                <Input id="exp_notes" bind:value={createForm.notes} placeholder="Catatan approval..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Mengirim...' : 'Ajukan Pengeluaran'}
                </Button>
            </div>
        </form>
    </Dialog>

    <ConfirmationDialog
        bind:open={approveModalOpen}
        title={`Setujui Pengeluaran ${selectedExpense?.expense_number ?? ''}`}
        confirmLabel="Ya, Setujui & Posting"
        variant="success"
        processing={approving}
        onconfirm={confirmApprove}
    >
        Pengeluaran <strong>{selectedExpense?.expense_number}</strong> sebesar
        <strong>{formatRupiah(selectedExpense?.amount ?? 0)}</strong> akan disetujui.
        Jurnal otomatis akan diposting dan saldo
        <strong>{selectedExpense?.cash_bank_account?.name ?? 'kas/bank'}</strong> akan dikurangi.
    </ConfirmationDialog>

    <!-- REJECT EXPENSE MODAL -->
    <Dialog bind:open={rejectModalOpen} title={`Tolak Pengeluaran ${selectedExpense?.expense_number}`}>
        <form onsubmit={handleReject} class="space-y-4">
            <div class="space-y-1.5">
                <label for="reject_reason" class="text-xs font-semibold text-stone-700">Alasan Penolakan (Wajib)</label>
                <Input id="reject_reason" bind:value={rejectForm.rejection_reason} placeholder="e.g. Bukti nota tidak lengkap / nominal tidak sesuai..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (rejectModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                    {rejectForm.processing ? 'Menolak...' : 'Konfirmasi Tolak'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
