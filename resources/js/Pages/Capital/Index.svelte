<script>
    import { useForm } from '@inertiajs/svelte';
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
    import { Landmark, Plus } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        transactions = { data: [], links: [] },
        equity_coas = [],
        cash_bank_accounts = [],
    } = $props();

    let createModalOpen = $state(false);

    const form = useForm({
        date: new Date().toISOString().split('T')[0],
        type: 'additional',
        chart_of_account_id: equity_coas[0]?.id || '',
        cash_bank_account_id: cash_bank_accounts[0]?.id || '',
        amount: '',
        description: 'Penambahan modal kerja kas/bank',
    });

    function handleCreate(e) {
        e.preventDefault();
        form.post('/capital', {
            onSuccess: () => {
                createModalOpen = false;
                form.reset();
            },
        });
    }
</script>

<AuthenticatedLayout
    title="Modal & Ekuitas"
    breadcrumbs={[{ label: 'Modal & Ekuitas' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Landmark class="h-5 w-5 text-stone-800" />
                    Manajemen Modal & Ekuitas Pemilik
                </h1>
                <p class="text-xs text-stone-500 mt-1">Pencatatan setoran modal, penambahan modal investasi, dan prive (penarikan pribadi pemilik).</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Catat Transaksi Modal
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Transaksi</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Jenis Transaksi</TableHead>
                    <TableHead>Akun Ekuitas (COA)</TableHead>
                    <TableHead>Kas / Bank</TableHead>
                    <TableHead>Keterangan</TableHead>
                    <TableHead class="text-right">Nominal</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if transactions.data.length === 0}
                    <TableRow>
                        <TableCell colspan="7" class="text-center py-8 text-stone-500">
                            Belum ada transaksi modal tercatat.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each transactions.data as tx}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-stone-800">
                                {tx.transaction_number}
                            </TableCell>
                            <TableCell class="text-xs text-stone-500">{formatDate(tx.date)}</TableCell>
                            <TableCell>
                                <Badge variant={tx.type === 'drawing' ? 'danger' : 'success'}>
                                    {tx.type === 'deposit' ? 'SETORAN AWAL' : tx.type === 'additional' ? 'PENAMBAHAN MODAL' : 'PRIVE (PENARIKAN)'}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-xs text-stone-800">
                                {tx.chart_of_account?.name} ({tx.chart_of_account?.code})
                            </TableCell>
                            <TableCell class="text-xs text-stone-700">{tx.cash_bank_account?.name}</TableCell>
                            <TableCell class="text-xs text-stone-700">{tx.description}</TableCell>
                            <TableCell class="text-right font-mono font-bold {tx.type === 'drawing' ? 'text-rose-700' : 'text-emerald-700'}">
                                {tx.type === 'drawing' ? `-${formatRupiah(tx.amount)}` : formatRupiah(tx.amount)}
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={transactions.links} />
    </div>

    <!-- CREATE MODAL -->
    <Dialog bind:open={createModalOpen} title="Catat Transaksi Modal / Prive">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="cap_date" class="text-xs font-semibold text-stone-700">Tanggal</label>
                    <Input id="cap_date" type="date" bind:value={form.date} required />
                </div>
                <div class="space-y-1.5">
                    <label for="cap_type" class="text-xs font-semibold text-stone-700">Jenis Transaksi</label>
                    <Select id="cap_type" bind:value={form.type} required>
                        <option value="additional">Penambahan Modal Pemilik</option>
                        <option value="deposit">Setoran Modal Baru</option>
                        <option value="drawing">Prive (Penarikan Pribadi Pemilik)</option>
                    </Select>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="cap_amount" class="text-xs font-semibold text-stone-700">Nominal (Rp)</label>
                <Input id="cap_amount" type="number" bind:value={form.amount} min="1" placeholder="e.g. 10000000" required />
            </div>

            <div class="space-y-1.5">
                <label for="cap_coa" class="text-xs font-semibold text-stone-700">Akun Ekuitas (COA)</label>
                <Select id="cap_coa" bind:value={form.chart_of_account_id} required>
                    {#each equity_coas as coa}
                        <option value={coa.id}>{coa.code} - {coa.name}</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="cap_cb" class="text-xs font-semibold text-stone-700">Akun Kas / Bank Terkait</label>
                <Select id="cap_cb" bind:value={form.cash_bank_account_id} required>
                    {#each cash_bank_accounts as cb}
                        <option value={cb.id}>{cb.name} (Saldo: {formatRupiah(cb.current_balance)})</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="cap_desc" class="text-xs font-semibold text-stone-700">Keterangan Transaksi</label>
                <Input id="cap_desc" bind:value={form.description} placeholder="e.g. Suntikan modal operasional ekspansi jaringan" required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan Transaksi Modal'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
