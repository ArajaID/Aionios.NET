<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
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
    import { Receipt, Plus, Search, Eye, CreditCard, RefreshCw, Calendar } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        invoices = { data: [], links: [] },
        periods = [],
        filters = {},
    } = $props();

    let search = $state(filters.search || '');
    let status = $state(filters.status || '');
    let period = $state(filters.period || '');

    let generateModalOpen = $state(false);

    const generateForm = useForm({
        period: new Date().toISOString().slice(0, 7),
    });

    function handleFilter() {
        router.get('/invoices', { search, status, period }, { preserveState: true, replace: true });
    }

    function handleGenerate(e) {
        e.preventDefault();
        generateForm.post('/invoices/generate', {
            onSuccess: () => (generateModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Tagihan & Invoice"
    breadcrumbs={[{ label: 'Tagihan & Invoice' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Receipt class="h-5 w-5 text-stone-800" />
                    Manajemen Tagihan & Invoice ISP
                </h1>
                <p class="text-xs text-stone-500 mt-1">Daftar invoice bulanan reguler (tgl 1) dan prorata pasang baru.</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (generateModalOpen = true)}>
                <RefreshCw class="h-4 w-4 mr-1" />
                Generate Tagihan Periode Ini
            </Button>
        </div>

        <!-- Filters -->
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
                        placeholder="Cari No. Invoice atau Nama Pelanggan..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div>
                    <Select bind:value={status} onchange={handleFilter}>
                        <option value="">Semua Status</option>
                        <option value="unpaid">Unpaid (Belum Lunas)</option>
                        <option value="overdue">Overdue (Jatuh Tempo)</option>
                        <option value="paid">Paid (Lunas)</option>
                    </Select>
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={period} onchange={handleFilter}>
                        <option value="">Semua Periode</option>
                        {#each periods as p}
                            <option value={p}>{p}</option>
                        {/each}
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; status = ''; period = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Invoice</TableHead>
                    <TableHead>Pelanggan</TableHead>
                    <TableHead>Periode</TableHead>
                    <TableHead>Tgl Terbit</TableHead>
                    <TableHead>Jatuh Tempo</TableHead>
                    <TableHead class="text-right">Total Tagihan</TableHead>
                    <TableHead class="text-center">Status</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if invoices.data.length === 0}
                    <TableRow>
                        <TableCell colspan="8" class="text-center py-8 text-stone-500">
                            Tidak ada invoice yang ditemukan.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each invoices.data as inv}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-stone-800">
                                <Link href={`/invoices/${inv.id}`} class="hover:underline">
                                    {inv.invoice_number}
                                </Link>
                                {#if inv.is_prorata}
                                    <span class="ml-1 text-[9px] bg-stone-800 text-stone-700 px-1.5 py-0.5 rounded">Prorata</span>
                                {/if}
                            </TableCell>

                            <TableCell>
                                <Link href={`/customers/${inv.customer_id}`} class="font-medium text-stone-900 hover:text-stone-800 hover:underline">
                                    {inv.customer?.name}
                                </Link>
                                <span class="text-[11px] text-stone-500 block font-mono">{inv.customer?.customer_id}</span>
                            </TableCell>

                            <TableCell class="font-mono text-xs">{inv.period}</TableCell>
                            <TableCell class="text-stone-500 text-xs">{formatDate(inv.issue_date)}</TableCell>
                            <TableCell class="text-stone-500 text-xs">{formatDate(inv.due_date)}</TableCell>
                            <TableCell class="text-right font-mono font-bold text-stone-900">{formatRupiah(inv.total_amount)}</TableCell>

                            <TableCell class="text-center">
                                <Badge
                                    variant={inv.status === 'paid'
                                        ? 'success'
                                        : inv.status === 'overdue'
                                        ? 'danger'
                                        : 'warning'}
                                >
                                    {inv.status?.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {#if inv.status !== 'paid'}
                                        <Link href={`/payments/create?customer_id=${inv.customer_id}`}>
                                            <Button variant="default" size="sm" class="h-7 px-2 text-[11px]">
                                                <CreditCard class="h-3 w-3 mr-1" />
                                                Bayar
                                            </Button>
                                        </Link>
                                    {/if}

                                    <Link href={`/invoices/${inv.id}`}>
                                        <Button variant="outline" size="sm" class="h-7 px-2 text-[11px]">
                                            <Eye class="h-3 w-3" />
                                        </Button>
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={invoices.links} />
    </div>

    <!-- GENERATE INVOICES MODAL -->
    <Dialog bind:open={generateModalOpen} title="Generate Tagihan Bulanan (Billing Engine)">
        <form onsubmit={handleGenerate} class="space-y-4">
            <p class="text-xs text-stone-500 leading-relaxed">
                Billing engine akan menerbitkan tagihan reguler untuk seluruh pelanggan berstatus <strong>Active</strong> dan <strong>Isolated</strong> pada periode yang dipilih. Sistem bersifat <strong>idempotent</strong> (tidak akan membuat tagihan ganda).
            </p>

            <div class="space-y-1.5">
                <label for="generate_period" class="text-xs font-semibold text-stone-700">Pilih Periode Tagihan (YYYY-MM)</label>
                <Input id="generate_period" bind:value={generateForm.period} placeholder="2026-08" required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (generateModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={generateForm.processing}>
                    {generateForm.processing ? 'Menerbitkan...' : 'Jalankan Billing Engine'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
