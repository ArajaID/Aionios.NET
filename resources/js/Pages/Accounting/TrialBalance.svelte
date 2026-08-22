<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Scale, CheckCircle2, AlertTriangle, Printer } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        as_of_date = '',
        trial_balance = [],
        total_debit = 0,
        total_credit = 0,
        is_balanced = true,
    } = $props();

    let asOfDate = $state(as_of_date || '');

    function handleFilter() {
        router.get('/accounting/trial-balance', { as_of_date: asOfDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Neraca Saldo (Trial Balance)"
    breadcrumbs={[{ label: 'Neraca Saldo' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Scale class="h-5 w-5 text-stone-800" />
                    Neraca Saldo (Trial Balance)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Verifikasi matematis keseimbangan total debit dan kredit seluruh akun COA.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Neraca Saldo
            </Button>
        </div>

        <!-- Filter & Balance Status Banner -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-stone-200 bg-white p-4 space-y-1">
                <label for="tb_as_of" class="text-xs font-semibold text-stone-700">Posisi Per Tanggal (Cut-off)</label>
                <Input id="tb_as_of" type="date" bind:value={asOfDate} onchange={handleFilter} />
            </div>

            <div class="p-4 rounded-xl border border-stone-200 bg-white">
                <span class="text-xs text-stone-500 block font-semibold uppercase">Total Debit & Kredit</span>
                <div class="flex items-center gap-3 mt-1 font-mono text-sm font-bold">
                    <span class="text-emerald-700">D: {formatRupiah(total_debit)}</span>
                    <span class="text-stone-800">K: {formatRupiah(total_credit)}</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-stone-200 bg-white flex flex-col justify-center">
                <span class="text-xs text-stone-500 block font-semibold uppercase">Status Keseimbangan</span>
                {#if is_balanced}
                    <span class="text-sm font-bold text-emerald-700 flex items-center gap-1.5 mt-1">
                        <CheckCircle2 class="h-4 w-4" />
                        SEIMBANG (DEBIT = KREDIT)
                    </span>
                {:else}
                    <span class="text-sm font-bold text-rose-700 flex items-center gap-1.5 mt-1">
                        <AlertTriangle class="h-4 w-4" />
                        SELISIH: {formatRupiah(Math.abs(total_debit - total_credit))}
                    </span>
                {/if}
            </div>
        </div>

        <!-- Trial Balance Table -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                            <tr>
                                <th class="py-3 px-4">Kode Akun</th>
                                <th class="py-3 px-4">Nama Akun COA</th>
                                <th class="py-3 px-4">Kelompok</th>
                                <th class="py-3 px-4 text-center">Saldo Normal</th>
                                <th class="py-3 px-4 text-right">Debit (Rp)</th>
                                <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#each trial_balance as row}
                                <tr class="hover:bg-stone-50">
                                    <td class="py-2.5 px-4 font-mono font-bold text-stone-800">{row.code}</td>
                                    <td class="py-2.5 px-4 font-semibold text-stone-900">{row.name}</td>
                                    <td class="py-2.5 px-4 capitalize text-stone-500">{row.type}</td>
                                    <td class="py-2.5 px-4 text-center uppercase text-[10px] text-stone-500">{row.normal_balance}</td>
                                    <td class="py-2.5 px-4 text-right font-mono {row.debit > 0 ? 'text-emerald-700 font-semibold' : 'text-stone-600'}">
                                        {row.debit > 0 ? formatRupiah(row.debit) : '-'}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono {row.credit > 0 ? 'text-stone-800 font-semibold' : 'text-stone-600'}">
                                        {row.credit > 0 ? formatRupiah(row.credit) : '-'}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                        <tfoot class="border-t-2 border-stone-300 bg-stone-50 font-bold text-xs">
                            <tr>
                                <td colspan="4" class="py-3 px-4 text-stone-800">TOTAL NERACA SALDO</td>
                                <td class="py-3 px-4 text-right font-mono text-emerald-700 text-sm">{formatRupiah(total_debit)}</td>
                                <td class="py-3 px-4 text-right font-mono text-stone-800 text-sm">{formatRupiah(total_credit)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</AuthenticatedLayout>
