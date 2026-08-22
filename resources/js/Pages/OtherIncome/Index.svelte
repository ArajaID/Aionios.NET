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
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import { TrendingUp, Plus } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        incomes = { data: [], links: [] },
        revenue_coas = [],
        cash_bank_accounts = [],
    } = $props();

    let createModalOpen = $state(false);

    const form = useForm({
        date: new Date().toISOString().split('T')[0],
        chart_of_account_id: revenue_coas[0]?.id || '',
        cash_bank_account_id: cash_bank_accounts[0]?.id || '',
        amount: '',
        description: '',
        reference: '',
    });

    function handleCreate(e) {
        e.preventDefault();
        form.post('/other-income', {
            onSuccess: () => {
                createModalOpen = false;
                form.reset();
            },
        });
    }
</script>

<AuthenticatedLayout
    title="Pemasukan Lain-Lain"
    breadcrumbs={[{ label: 'Pemasukan Lain' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-emerald-700" />
                    Pemasukan Non-Langganan Internet
                </h1>
                <p class="text-xs text-stone-500 mt-1">Pencatatan pendapatan instalasi, jasa teknis, penjualan perangkat, atau sewa tiang.</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Catat Pemasukan Baru
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Pemasukan</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Kategori Pendapatan (COA)</TableHead>
                    <TableHead>Kas / Bank Penerima</TableHead>
                    <TableHead>Keterangan</TableHead>
                    <TableHead>No. Referensi</TableHead>
                    <TableHead class="text-right">Nominal</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if incomes.data.length === 0}
                    <TableRow>
                        <TableCell colspan="7" class="text-center py-8 text-stone-500">
                            Belum ada catatan pemasukan lain.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each incomes.data as inc}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-emerald-700">
                                {inc.income_number}
                            </TableCell>
                            <TableCell class="text-xs text-stone-500">{formatDate(inc.date)}</TableCell>
                            <TableCell class="text-xs font-semibold text-stone-800">
                                {inc.chart_of_account?.name} ({inc.chart_of_account?.code})
                            </TableCell>
                            <TableCell class="text-xs text-stone-700">{inc.cash_bank_account?.name}</TableCell>
                            <TableCell class="text-xs text-stone-800">{inc.description}</TableCell>
                            <TableCell class="text-xs font-mono text-stone-500">{inc.reference || '-'}</TableCell>
                            <TableCell class="text-right font-mono font-bold text-emerald-700">{formatRupiah(inc.amount)}</TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={incomes.links} />
    </div>

    <!-- CREATE MODAL -->
    <Dialog bind:open={createModalOpen} title="Catat Pemasukan Non-Internet Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="inc_date" class="text-xs font-semibold text-stone-700">Tanggal Penerimaan</label>
                    <Input id="inc_date" type="date" bind:value={form.date} required />
                </div>
                <div class="space-y-1.5">
                    <label for="inc_amount" class="text-xs font-semibold text-stone-700">Nominal (Rp)</label>
                    <Input id="inc_amount" type="number" bind:value={form.amount} min="1" placeholder="e.g. 500000" required />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="inc_coa" class="text-xs font-semibold text-stone-700">Akun Pendapatan (COA)</label>
                <Select id="inc_coa" bind:value={form.chart_of_account_id} required>
                    {#each revenue_coas as coa}
                        <option value={coa.id}>{coa.code} - {coa.name}</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="inc_cb" class="text-xs font-semibold text-stone-700">Kas / Bank Tujuan Penerimaan</label>
                <Select id="inc_cb" bind:value={form.cash_bank_account_id} required>
                    {#each cash_bank_accounts as cb}
                        <option value={cb.id}>{cb.name} (Saldo: {formatRupiah(cb.current_balance)})</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="inc_desc" class="text-xs font-semibold text-stone-700">Keterangan Pemasukan</label>
                <Input id="inc_desc" bind:value={form.description} placeholder="e.g. Biaya instalasi & material dropcore pelanggan baru" required />
            </div>

            <div class="space-y-1.5">
                <label for="inc_ref" class="text-xs font-semibold text-stone-700">No. Referensi / Kwitansi (Opsional)</label>
                <Input id="inc_ref" bind:value={form.reference} placeholder="KW-001" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan Pemasukan'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
