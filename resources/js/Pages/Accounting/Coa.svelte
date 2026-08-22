<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import { BookOpen, Plus, FileSpreadsheet, Lock } from 'lucide-svelte';

    let { coas = [] } = $props();

    let createModalOpen = $state(false);

    const form = useForm({
        code: '',
        name: '',
        type: 'asset',
        category: 'Kas & Setara Kas',
        normal_balance: 'debit',
    });

    const groups = $derived({
        asset: coas.filter((c) => c.type === 'asset'),
        liability: coas.filter((c) => c.type === 'liability'),
        equity: coas.filter((c) => c.type === 'equity'),
        revenue: coas.filter((c) => c.type === 'revenue'),
        expense: coas.filter((c) => c.type === 'expense'),
    });

    function handleCreate(e) {
        e.preventDefault();
        form.post('/accounting/coa', {
            onSuccess: () => {
                createModalOpen = false;
                form.reset();
            },
        });
    }
</script>

<AuthenticatedLayout
    title="Chart of Accounts (COA)"
    breadcrumbs={[{ label: 'Chart of Accounts' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <BookOpen class="h-5 w-5 text-stone-800" />
                    Bagan Akun Standar ISP (Chart of Accounts)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Struktur akun akuntansi terintegrasi untuk pencatatan otomatis & penyusunan laporan keuangan.</p>
            </div>

            <div class="flex items-center gap-2.5">
                <Link href="/accounting/opening-balance">
                    <Button variant="outline" size="sm">
                        <FileSpreadsheet class="h-4 w-4 mr-1 text-emerald-700" />
                        Posting Saldo Awal (Opening Balance)
                    </Button>
                </Link>

                <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                    <Plus class="h-4 w-4 mr-1" />
                    Tambah Akun COA
                </Button>
            </div>
        </div>

        <!-- Grouped COA Tables -->
        <div class="space-y-6">
            <!-- 1. ASSETS -->
            <Card>
                <CardHeader class="pb-3 border-b border-stone-200 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Badge variant="primary">1000 - ASET (ASSETS)</Badge>
                        <span class="text-xs text-stone-500">Saldo Normal: DEBIT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-semibold">{groups.asset.length} Akun</span>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Kode Akun</th>
                                    <th class="py-2.5 px-4">Nama Akun</th>
                                    <th class="py-2.5 px-4">Kategori Sub-Kelompok</th>
                                    <th class="py-2.5 px-4 text-center">Status Sistem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each groups.asset as coa}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-bold text-stone-800">{coa.code}</td>
                                        <td class="py-2.5 px-4 font-semibold text-stone-900">{coa.name}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{coa.category}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            {#if coa.is_system}
                                                <Badge variant="outline" class="text-[10px] py-0">SISTEM ISP</Badge>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">Kustom</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. LIABILITIES -->
            <Card>
                <CardHeader class="pb-3 border-b border-stone-200 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Badge variant="warning">2000 - KEWAJIBAN (LIABILITIES)</Badge>
                        <span class="text-xs text-stone-500">Saldo Normal: KREDIT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-semibold">{groups.liability.length} Akun</span>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Kode Akun</th>
                                    <th class="py-2.5 px-4">Nama Akun</th>
                                    <th class="py-2.5 px-4">Kategori Sub-Kelompok</th>
                                    <th class="py-2.5 px-4 text-center">Status Sistem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each groups.liability as coa}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-bold text-amber-800">{coa.code}</td>
                                        <td class="py-2.5 px-4 font-semibold text-stone-900">{coa.name}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{coa.category}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            {#if coa.is_system}
                                                <Badge variant="outline" class="text-[10px] py-0">SISTEM ISP</Badge>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">Kustom</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. EQUITY -->
            <Card>
                <CardHeader class="pb-3 border-b border-stone-200 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Badge variant="purple">3000 - EKUITAS (EQUITY)</Badge>
                        <span class="text-xs text-stone-500">Saldo Normal: KREDIT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-semibold">{groups.equity.length} Akun</span>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Kode Akun</th>
                                    <th class="py-2.5 px-4">Nama Akun</th>
                                    <th class="py-2.5 px-4">Kategori Sub-Kelompok</th>
                                    <th class="py-2.5 px-4 text-center">Status Sistem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each groups.equity as coa}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-bold text-purple-700">{coa.code}</td>
                                        <td class="py-2.5 px-4 font-semibold text-stone-900">{coa.name}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{coa.category}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            {#if coa.is_system}
                                                <Badge variant="outline" class="text-[10px] py-0">SISTEM ISP</Badge>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">Kustom</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. REVENUE -->
            <Card>
                <CardHeader class="pb-3 border-b border-stone-200 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Badge variant="success">4000 - PENDAPATAN (REVENUE)</Badge>
                        <span class="text-xs text-stone-500">Saldo Normal: KREDIT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-semibold">{groups.revenue.length} Akun</span>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Kode Akun</th>
                                    <th class="py-2.5 px-4">Nama Akun</th>
                                    <th class="py-2.5 px-4">Kategori Sub-Kelompok</th>
                                    <th class="py-2.5 px-4 text-center">Status Sistem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each groups.revenue as coa}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-bold text-emerald-700">{coa.code}</td>
                                        <td class="py-2.5 px-4 font-semibold text-stone-900">{coa.name}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{coa.category}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            {#if coa.is_system}
                                                <Badge variant="outline" class="text-[10px] py-0">SISTEM ISP</Badge>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">Kustom</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- 5. EXPENSES -->
            <Card>
                <CardHeader class="pb-3 border-b border-stone-200 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Badge variant="danger">5000 - BEBAN / PENGELUARAN (EXPENSES)</Badge>
                        <span class="text-xs text-stone-500">Saldo Normal: DEBIT</span>
                    </div>
                    <span class="text-xs text-stone-500 font-semibold">{groups.expense.length} Akun</span>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Kode Akun</th>
                                    <th class="py-2.5 px-4">Nama Akun</th>
                                    <th class="py-2.5 px-4">Kategori Sub-Kelompok</th>
                                    <th class="py-2.5 px-4 text-center">Status Sistem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each groups.expense as coa}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-bold text-rose-700">{coa.code}</td>
                                        <td class="py-2.5 px-4 font-semibold text-stone-900">{coa.name}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{coa.category}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            {#if coa.is_system}
                                                <Badge variant="outline" class="text-[10px] py-0">SISTEM ISP</Badge>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">Kustom</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- CREATE COA MODAL -->
    <Dialog bind:open={createModalOpen} title="Tambah Akun COA Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="coa_code" class="text-xs font-semibold text-stone-700">Kode Akun</label>
                    <Input id="coa_code" bind:value={form.code} placeholder="5190" required />
                </div>
                <div class="space-y-1.5">
                    <label for="coa_type" class="text-xs font-semibold text-stone-700">Kelompok Akun</label>
                    <Select id="coa_type" bind:value={form.type} required>
                        <option value="asset">Aset (1xxx)</option>
                        <option value="liability">Kewajiban (2xxx)</option>
                        <option value="equity">Ekuitas (3xxx)</option>
                        <option value="revenue">Pendapatan (4xxx)</option>
                        <option value="expense">Beban (5xxx)</option>
                    </Select>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="coa_name" class="text-xs font-semibold text-stone-700">Nama Akun</label>
                <Input id="coa_name" bind:value={form.name} placeholder="e.g. Beban Konsumsi & Rapat" required />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="coa_cat" class="text-xs font-semibold text-stone-700">Kategori Sub-Kelompok</label>
                    <Input id="coa_cat" bind:value={form.category} placeholder="Beban Operasional" required />
                </div>
                <div class="space-y-1.5">
                    <label for="coa_bal" class="text-xs font-semibold text-stone-700">Saldo Normal</label>
                    <Select id="coa_bal" bind:value={form.normal_balance} required>
                        <option value="debit">Debit</option>
                        <option value="credit">Kredit</option>
                    </Select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan Akun COA'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
