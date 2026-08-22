<script>
    import { Link, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import Table from '@/Components/ui/table/Table.svelte';
    import TableHeader from '@/Components/ui/table/TableHeader.svelte';
    import TableBody from '@/Components/ui/table/TableBody.svelte';
    import TableRow from '@/Components/ui/table/TableRow.svelte';
    import TableHead from '@/Components/ui/table/TableHead.svelte';
    import TableCell from '@/Components/ui/table/TableCell.svelte';
    import { Users, Plus, Search, Filter, Eye, CreditCard, Edit3 } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let { customers = { data: [], links: [] }, packages = [], filters = {} } = $props();

    let search = $state(filters.search || '');
    let status = $state(filters.status || '');
    let packageId = $state(filters.package_id || '');

    function handleFilter() {
        router.get(
            '/customers',
            { search, status, package_id: packageId },
            { preserveState: true, replace: true }
        );
    }

    function handleReset() {
        search = '';
        status = '';
        packageId = '';
        handleFilter();
    }
</script>

<AuthenticatedLayout
    title="Data Pelanggan"
    breadcrumbs={[{ label: 'Pelanggan' }]}
>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Users class="h-5 w-5 text-stone-800" />
                    Data Pelanggan ISP
                </h1>
                <p class="text-xs text-stone-500 mt-1">Kelola seluruh pelanggan, status PPPoE, ONT terpasang, tagihan, dan riwayat aktivasi.</p>
            </div>

            <Link href="/customers/create">
                <Button variant="default" size="sm">
                    <Plus class="h-4 w-4 mr-1" />
                    Pasang Baru Pelanggan
                </Button>
            </Link>
        </div>

        <!-- Filter & Search Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 backdrop-blur-md">
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
                        placeholder="Cari Customer ID, Nama, No. HP, atau Alamat..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div>
                    <Select bind:value={status} onchange={handleFilter}>
                        <option value="">Semua Status</option>
                        <option value="active">Active (Aktif)</option>
                        <option value="isolated">Isolated (Terisolir)</option>
                        <option value="terminated">Terminated (Berhenti)</option>
                    </Select>
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={packageId} onchange={handleFilter}>
                        <option value="">Semua Paket</option>
                        {#each packages as pkg}
                            <option value={pkg.id}>{pkg.name}</option>
                        {/each}
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={handleReset} class="shrink-0">
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Customer ID</TableHead>
                    <TableHead>Nama Pelanggan</TableHead>
                    <TableHead>Paket Internet</TableHead>
                    <TableHead>Tunggakan</TableHead>
                    <TableHead>Status Layanan</TableHead>
                    <TableHead>PPPoE Profile</TableHead>
                    <TableHead>Perangkat ONT</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if customers.data.length === 0}
                    <TableRow>
                        <TableCell colspan="8" class="text-center py-8 text-stone-500">
                            Tidak ada data pelanggan yang sesuai kriteria pencarian.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each customers.data as customer}
                        <TableRow>
                            <TableCell>
                                <Link
                                    href={`/customers/${customer.id}`}
                                    class="inline-flex rounded-md border border-stone-700 bg-stone-800 px-2.5 py-1 font-mono text-[11px] font-bold tracking-wide text-white shadow-sm transition-colors hover:border-stone-600 hover:bg-stone-700"
                                >
                                    {customer.customer_id}
                                </Link>
                            </TableCell>

                            <TableCell>
                                <div>
                                    <p class="font-medium text-stone-900">{customer.name}</p>
                                    <p class="text-[11px] text-stone-500">{customer.phone}</p>
                                </div>
                            </TableCell>

                            <TableCell>
                                <div class="text-xs">
                                    <span class="font-semibold text-stone-800">{customer.package?.name ?? '-'}</span>
                                    {#if customer.active_promotion}
                                        <Badge variant="primary" class="ml-1 text-[10px] py-0">PROMO</Badge>
                                    {/if}
                                </div>
                            </TableCell>

                            <TableCell>
                                {#if customer.outstanding_amount > 0}
                                    <span class="font-semibold text-rose-700 font-mono">
                                        {formatRupiah(customer.outstanding_amount)}
                                    </span>
                                {:else}
                                    <span class="text-xs text-emerald-700 font-medium">Lunas (Rp 0)</span>
                                {/if}
                            </TableCell>

                            <TableCell>
                                <Badge
                                    variant={customer.status === 'active'
                                        ? 'success'
                                        : customer.status === 'isolated'
                                        ? 'danger'
                                        : 'default'}
                                >
                                    {customer.status.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell class="font-mono text-xs text-stone-700">
                                {customer.ppp_account?.profile ?? '-'}
                            </TableCell>

                            <TableCell>
                                {#if customer.ont}
                                    <span class="font-mono text-xs text-stone-700">
                                        {customer.ont.brand} ({customer.ont.serial_number})
                                    </span>
                                {:else}
                                    <span class="text-xs text-stone-600">Belum ada</span>
                                {/if}
                            </TableCell>

                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {#if customer.outstanding_amount > 0}
                                        <Link href={`/payments/create?customer_id=${customer.id}`}>
                                            <Button variant="default" size="sm" class="h-7 px-2.5 text-[11px]">
                                                <CreditCard class="h-3 w-3 mr-1" />
                                                Bayar
                                            </Button>
                                        </Link>
                                    {/if}

                                    <Link href={`/customers/${customer.id}`}>
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

        <!-- Pagination -->
        <Pagination links={customers.links} />
    </div>
</AuthenticatedLayout>
