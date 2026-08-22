<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import { UserPlus, ArrowLeft, Network, HardDrive, Sparkles, CheckCircle2 } from 'lucide-svelte';
    import { formatRupiah } from '@/lib/utils';

    let {
        packages = [],
        available_onts = [],
        promotions = [],
        suggested_customer_id = 'CUST-0001',
        errors = {},
    } = $props();

    const form = useForm({
        customer_id: suggested_customer_id,
        name: '',
        phone: '',
        address: '',
        installed_at: new Date().toISOString().split('T')[0],
        activated_at: new Date().toISOString().split('T')[0],
        package_id: packages[0]?.id || '',
        ont_id: available_onts[0]?.id || '',
        ppp_username: '',
        ppp_password: 'user' + Math.floor(1000 + Math.random() * 9000),
        promotion_id: '',
        notes: '',
    });

    // Auto-fill PPP Username based on name
    function handleNameChange(e) {
        const val = e.target.value;
        const clean = val.toLowerCase().replace(/[^a-z0-9]/g, '');
        if (clean) {
            form.ppp_username = `user_${clean}@aionios`;
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/customers');
    }
</script>

<AuthenticatedLayout
    title="Pasang Baru Pelanggan"
    breadcrumbs={[
        { label: 'Pelanggan', href: '/customers' },
        { label: 'Pasang Baru' },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <UserPlus class="h-5 w-5 text-stone-800" />
                    Pemasangan Baru Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Registrasi data pelanggan, alokasi ONT, konfigurasi PPPoE MikroTik, dan penerbitan tagihan prorata pertama.
                </p>
            </div>

            <Link href="/customers">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali
                </Button>
            </Link>
        </div>

        <Alert variant="info" title="Aturan Billing Pertama (Prorata)">
            Tagihan pertama pelanggan baru dihitung otomatis berdasarkan jumlah hari aktif (Prorata) menggunakan <strong>harga normal paket</strong>, bukan harga promo.
        </Alert>

        <form onsubmit={handleSubmit} class="space-y-6">
            <!-- Data Identitas Pelanggan -->
            <Card>
                <CardHeader>
                    <CardTitle>1. Identitas & Lokasi Pelanggan</CardTitle>
                    <CardDescription>Informasi dasar kontak dan alamat pemasangan dropcore</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="customer_id" class="text-xs font-semibold text-stone-700">Customer ID</label>
                        <Input id="customer_id" bind:value={form.customer_id} required />
                        {#if errors.customer_id}
                            <p class="text-xs text-rose-700">{errors.customer_id}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="name" class="text-xs font-semibold text-stone-700">Nama Lengkap</label>
                        <Input id="name" bind:value={form.name} oninput={handleNameChange} placeholder="e.g. Ahmad Fauzi" required />
                        {#if errors.name}
                            <p class="text-xs text-rose-700">{errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="phone" class="text-xs font-semibold text-stone-700">Nomor Telepon / WhatsApp</label>
                        <Input id="phone" bind:value={form.phone} placeholder="e.g. 081289123401" required />
                        {#if errors.phone}
                            <p class="text-xs text-rose-700">{errors.phone}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="address" class="text-xs font-semibold text-stone-700">Alamat Lengkap Pemasangan</label>
                        <Input id="address" bind:value={form.address} placeholder="Jl. Mawar No. 12, RT 01/RW 03..." required />
                        {#if errors.address}
                            <p class="text-xs text-rose-700">{errors.address}</p>
                        {/if}
                    </div>
                </CardContent>
            </Card>

            <!-- Paket, Promo & Tanggal Aktivasi -->
            <Card>
                <CardHeader>
                    <CardTitle>2. Paket Layanan & Promo</CardTitle>
                    <CardDescription>Pilih profil bandwidth dan diskon promosi jika ada</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="installed_at" class="text-xs font-semibold text-stone-700">Tanggal Pemasangan Fisik</label>
                        <Input id="installed_at" type="date" bind:value={form.installed_at} required />
                    </div>

                    <div class="space-y-1.5">
                        <label for="activated_at" class="text-xs font-semibold text-stone-700">Tanggal Aktivasi Layanan</label>
                        <Input id="activated_at" type="date" bind:value={form.activated_at} required />
                    </div>

                    <div class="space-y-1.5">
                        <label for="package_id" class="text-xs font-semibold text-stone-700">Paket Internet Langganan</label>
                        <Select id="package_id" bind:value={form.package_id} required>
                            {#each packages as pkg}
                                <option value={pkg.id}>
                                    {pkg.name} ({pkg.download_speed_mbps} Mbps) - {formatRupiah(pkg.price)}/bln
                                </option>
                            {/each}
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="promotion_id" class="text-xs font-semibold text-stone-700">Promo Khusus (Opsional)</label>
                        <Select id="promotion_id" bind:value={form.promotion_id}>
                            <option value="">Tanpa Promo (Tarif Normal)</option>
                            {#each promotions as promo}
                                <option value={promo.id}>
                                    {promo.name} ({promo.duration_months} Bulan)
                                </option>
                            {/each}
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <!-- Perangkat ONT & PPPoE MikroTik -->
            <Card>
                <CardHeader>
                    <CardTitle>3. Alokasi ONT & Akun PPPoE MikroTik</CardTitle>
                    <CardDescription>Penetapan hardware ONT dan akun login PPPoE router pelanggan</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="ont_id" class="text-xs font-semibold text-stone-700">Pilih Perangkat ONT (Stok Tersedia)</label>
                        {#if available_onts.length === 0}
                            <p class="text-xs text-amber-800 p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20">
                                Peringatan: Tidak ada stok ONT berstatus Available di inventori. Daftarkan ONT baru di menu Inventori ONT.
                            </p>
                        {:else}
                            <Select id="ont_id" bind:value={form.ont_id}>
                                <option value="">Pilih ONT dari Stok</option>
                                {#each available_onts as ont}
                                    <option value={ont.id}>
                                        {ont.ont_id} - {ont.brand} {ont.model} (SN: {ont.serial_number}) - Kondisi: {ont.condition}
                                    </option>
                                {/each}
                            </Select>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="ppp_username" class="text-xs font-semibold text-stone-700">PPPoE Username</label>
                        <Input id="ppp_username" bind:value={form.ppp_username} required />
                        {#if errors.ppp_username}
                            <p class="text-xs text-rose-700">{errors.ppp_username}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="ppp_password" class="text-xs font-semibold text-stone-700">PPPoE Password</label>
                        <Input id="ppp_password" bind:value={form.ppp_password} required />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label for="notes" class="text-xs font-semibold text-stone-700">Catatan Teknisi / Pemasangan</label>
                        <Input id="notes" bind:value={form.notes} placeholder="Catatan ODP / port splitter..." />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center justify-end gap-3 pt-2">
                <Link href="/customers">
                    <Button type="button" variant="outline">Batal</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="px-8 font-semibold">
                    {form.processing ? 'Menyimpan & Mengaktifkan...' : 'Simpan & Aktifkan Pelanggan'}
                </Button>
            </div>
        </form>
    </div>
</AuthenticatedLayout>
