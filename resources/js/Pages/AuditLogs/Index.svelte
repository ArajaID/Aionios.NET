<script>
    import { router } from '@inertiajs/svelte';
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
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import { History, Search } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let {
        audit_logs = { data: [], links: [] },
        filters = {},
    } = $props();

    let search = $state(filters.search || '');
    let action = $state(filters.action || '');

    function handleFilter() {
        router.get('/audit-logs', { search, action }, { preserveState: true, replace: true });
    }
</script>

<AuthenticatedLayout
    title="Log Audit Trail Sistem"
    breadcrumbs={[{ label: 'Audit Trail' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <History class="h-5 w-5 text-stone-800" />
                    Log Audit Trail Sistem & Keamanan
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Jejak rekam seluruh perubahan data, aksi finansial, dan intervensi jaringan secara detail dan tidak dapat diubah.
                </p>
            </div>
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
                        placeholder="Cari user, tipe entitas, atau ID..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={action} onchange={handleFilter}>
                        <option value="">Semua Aksi</option>
                        <option value="create">CREATE (Tambah)</option>
                        <option value="update">UPDATE (Ubah)</option>
                        <option value="delete">DELETE (Hapus)</option>
                        <option value="isolate">ISOLATE (Isolir)</option>
                        <option value="un-isolate">UN-ISOLATE (Buka Isolir)</option>
                        <option value="approve">APPROVE (Setujui)</option>
                        <option value="reject">REJECT (Tolak)</option>
                        <option value="reversal">REVERSAL (Pembalik)</option>
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; action = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Audit Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Waktu (Timestamp)</TableHead>
                    <TableHead>Pengguna (User)</TableHead>
                    <TableHead>Aksi</TableHead>
                    <TableHead>Entitas Terkait</TableHead>
                    <TableHead>Detail Perubahan (Payload)</TableHead>
                    <TableHead>IP Address</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if audit_logs.data.length === 0}
                    <TableRow>
                        <TableCell colspan="6" class="text-center py-8 text-stone-500">
                            Tidak ada log audit yang sesuai kriteria.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each audit_logs.data as log}
                        <TableRow>
                            <TableCell class="font-mono text-xs text-stone-500">
                                {formatDate(log.created_at, true)}
                            </TableCell>

                            <TableCell>
                                <span class="font-semibold text-stone-800">{log.user?.name || 'Sistem (CLI/Cron)'}</span>
                                <span class="text-[10px] text-stone-500 block capitalize">{log.user?.role || 'Daemon'}</span>
                            </TableCell>

                            <TableCell>
                                <Badge
                                    variant={log.action === 'create'
                                        ? 'success'
                                        : log.action === 'delete' || log.action === 'isolate'
                                        ? 'danger'
                                        : log.action === 'approve'
                                        ? 'primary'
                                        : 'default'}
                                >
                                    {log.action?.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell class="font-mono text-xs text-stone-700">
                                <span class="font-bold">{log.entity_type?.replace('App\\Models\\', '')}</span>
                                <span class="text-stone-500 block">ID: {log.entity_id}</span>
                            </TableCell>

                            <TableCell class="text-xs text-stone-700 max-w-sm">
                                {#if log.new_data}
                                    <pre class="rounded bg-stone-50 p-2 text-[10px] font-mono text-stone-500 overflow-x-auto max-h-24">{JSON.stringify(log.new_data, null, 2)}</pre>
                                {:else}
                                    <span class="text-stone-500">-</span>
                                {/if}
                            </TableCell>

                            <TableCell class="font-mono text-xs text-stone-500">
                                {log.ip_address || '127.0.0.1'}
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={audit_logs.links} />
    </div>
</AuthenticatedLayout>
