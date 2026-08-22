<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { BookMarked, Filter, Printer } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        coas = [],
        selected_coa_id = null,
        selected_coa = null,
        start_date = '',
        end_date = '',
        opening_balance = 0,
        running_ledger = [],
        ending_balance = 0,
    } = $props();

    let coaId = $state(selected_coa_id || coas[0]?.id || '');
    let startDate = $state(start_date || '');
    let endDate = $state(end_date || '');

    function handleFilter() {
        router.get(
            '/accounting/ledger',
            { coa_id: coaId, start_date: startDate, end_date: endDate },
            { preserveState: true }
        );
    }
</script>

<AuthenticatedLayout
    title="Buku Besar (General Ledger)"
    breadcrumbs={[{ label: 'Buku Besar' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <BookMarked class="h-5 w-5 text-stone-800" />
                    Buku Besar (General Ledger)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Mutasi transaksi dan saldo berjalan (running balance) per akun COA.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Laporan
            </Button>
        </div>

        <!-- Filter Controls -->
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2 space-y-1">
                    <label for="ledger_coa" class="text-xs font-semibold text-stone-700">Pilih Akun COA</label>
                    <Select id="ledger_coa" bind:value={coaId} onchange={handleFilter}>
                        {#each coas as c}
                            <option value={c.id}>{c.code} - {c.name} ({c.type.toUpperCase()})</option>
                        {/each}
                    </Select>
                </div>

                <div class="space-y-1">
                    <label for="ledger_start" class="text-xs font-semibold text-stone-700">Dari Tanggal</label>
                    <Input id="ledger_start" type="date" bind:value={startDate} onchange={handleFilter} />
                </div>

                <div class="space-y-1">
                    <label for="ledger_end" class="text-xs font-semibold text-stone-700">Sampai Tanggal</label>
                    <Input id="ledger_end" type="date" bind:value={endDate} onchange={handleFilter} />
                </div>
            </div>
        </div>

        <!-- Ledger Table Card -->
        {#if selected_coa}
            <Card>
                <CardHeader class="border-b border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-base font-bold text-stone-800">{selected_coa.code}</span>
                            <CardTitle class="text-base">{selected_coa.name}</CardTitle>
                        </div>
                        <p class="text-xs text-stone-500 mt-1">
                            Kelompok: {selected_coa.type?.toUpperCase()} • Saldo Normal: {selected_coa.normal_balance?.toUpperCase()}
                        </p>
                    </div>

                    <div class="flex items-center gap-4 text-xs">
                        <div class="p-2.5 rounded-lg bg-stone-50 border border-stone-200">
                            <span class="text-stone-500 block text-[10px] uppercase">Saldo Awal</span>
                            <span class="font-bold font-mono text-stone-800">{formatRupiah(opening_balance)}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-stone-50 border border-stone-200">
                            <span class="text-stone-500 block text-[10px] uppercase">Saldo Akhir Berjalan</span>
                            <span class="font-bold font-mono text-emerald-700">{formatRupiah(ending_balance)}</span>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Tanggal</th>
                                    <th class="py-2.5 px-4">No. Jurnal</th>
                                    <th class="py-2.5 px-4">Keterangan / Transaksi</th>
                                    <th class="py-2.5 px-4 text-right">Debit (Rp)</th>
                                    <th class="py-2.5 px-4 text-right">Kredit (Rp)</th>
                                    <th class="py-2.5 px-4 text-right">Saldo Berjalan (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <!-- Opening Balance Line -->
                                <tr class="bg-stone-50 font-semibold">
                                    <td class="py-2 px-4 text-stone-500" colspan="3">SALDO AWAL PERIODE</td>
                                    <td class="py-2 px-4 text-right">-</td>
                                    <td class="py-2 px-4 text-right">-</td>
                                    <td class="py-2 px-4 text-right font-mono text-stone-800">{formatRupiah(opening_balance)}</td>
                                </tr>

                                {#if running_ledger.length === 0}
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-stone-500">
                                            Tidak ada mutasi transaksi pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                {:else}
                                    {#each running_ledger as row}
                                        <tr class="hover:bg-stone-50">
                                            <td class="py-2 px-4 text-stone-500">{formatDate(row.date)}</td>
                                            <td class="py-2 px-4 font-mono text-stone-800">{row.entry_number}</td>
                                            <td class="py-2 px-4 text-stone-800">
                                                {row.description}
                                                {#if row.memo}
                                                    <span class="block text-[10px] text-stone-500">{row.memo}</span>
                                                {/if}
                                            </td>
                                            <td class="py-2 px-4 text-right font-mono {row.debit > 0 ? 'text-emerald-700 font-medium' : 'text-stone-600'}">
                                                {row.debit > 0 ? formatRupiah(row.debit) : '-'}
                                            </td>
                                            <td class="py-2 px-4 text-right font-mono {row.credit > 0 ? 'text-rose-700 font-medium' : 'text-stone-600'}">
                                                {row.credit > 0 ? formatRupiah(row.credit) : '-'}
                                            </td>
                                            <td class="py-2 px-4 text-right font-mono font-bold text-stone-900">
                                                {formatRupiah(row.running_balance)}
                                            </td>
                                        </tr>
                                    {/each}
                                {/if}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        {/if}
    </div>
</AuthenticatedLayout>
