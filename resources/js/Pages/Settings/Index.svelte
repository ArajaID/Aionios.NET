<script>
    import { useForm } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import { Settings, Save, Calendar, Shield } from 'lucide-svelte';

    let { settings = {} } = $props();

    const form = useForm({
        brand_name: settings.app_brand_name || 'Aionios.NET',
        default_qris_mdr: settings.default_qris_mdr ?? 0.7,
        invoice_due_day: settings.invoice_due_day ?? 22,
        auto_isolate_day: settings.auto_isolate_day ?? 23,
        auto_isolate_time: settings.auto_isolate_time || '01:00',
        auto_isolate_enabled: settings.auto_isolate_enabled ?? true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/settings', {
            preserveScroll: true,
        });
    }
</script>

<AuthenticatedLayout
    title="Pengaturan Sistem"
    breadcrumbs={[{ label: 'Pengaturan Sistem' }]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Settings class="h-5 w-5 text-stone-800" />
                    Pengaturan Sistem ISP Aionios
                </h1>
                <p class="text-xs text-stone-500 mt-1">Konfigurasi identitas aplikasi, parameter billing, MDR QRIS, dan jadwal isolir otomatis.</p>
            </div>
        </div>

        <form onsubmit={handleSubmit} class="space-y-6">
            <!-- Brand & Umum -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-4 w-4 text-stone-800" />
                        Identitas & Branding
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="set_brand" class="text-xs font-semibold text-stone-700">Nama Brand / ISP</label>
                        <Input id="set_brand" bind:value={form.brand_name} required />
                    </div>

                    <div class="space-y-1.5">
                        <label for="set_mdr" class="text-xs font-semibold text-stone-700">Tarif Default MDR QRIS (%)</label>
                        <Input id="set_mdr" type="number" step="0.01" bind:value={form.default_qris_mdr} min="0" max="10" required />
                    </div>
                </CardContent>
            </Card>

            <!-- Jadwal billing dan isolir otomatis -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-amber-800" />
                        Aturan Siklus Billing & Isolir Otomatis
                    </CardTitle>
                    <CardDescription>Jadwal penerbitan tagihan, jatuh tempo, dan isolir otomatis router</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1.5">
                        <label for="set_due" class="text-xs font-semibold text-stone-700">Tanggal Jatuh Tempo Bulanan</label>
                        <Input id="set_due" type="number" bind:value={form.invoice_due_day} min="1" max="28" required />
                        <p class="text-[10px] text-stone-500">Default: Tanggal 22</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="set_iso_day" class="text-xs font-semibold text-stone-700">Tanggal Eksekusi Isolir</label>
                        <Input id="set_iso_day" type="number" bind:value={form.auto_isolate_day} min="1" max="28" required />
                        <p class="text-[10px] text-stone-500">Default: Tanggal 23</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="set_iso_time" class="text-xs font-semibold text-stone-700">Waktu Eksekusi Isolir (WIB)</label>
                        <Input id="set_iso_time" type="time" bind:value={form.auto_isolate_time} required />
                        <p class="text-[10px] text-stone-500">Default: 01:00 WIB</p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-3 pt-2 border-t border-stone-200">
                        <label for="set_iso_enabled" class="text-xs font-semibold text-stone-700">Status Isolir Otomatis</label>
                        <select
                            id="set_iso_enabled"
                            class="flex h-9 w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-1 text-sm text-stone-900"
                            bind:value={form.auto_isolate_enabled}
                        >
                            <option value={true}>Aktif (Jalankan Scheduler Cron Setiap Jam 01:00 Tgl 23)</option>
                            <option value={false}>Non-Aktif (Hanya Isolir Manual)</option>
                        </select>
                    </div>
                </CardContent>
                <CardFooter class="flex items-center justify-end border-t border-stone-200 pt-4">
                    <Button type="submit" disabled={form.processing} class="px-8 font-semibold">
                        <Save class="h-4 w-4 mr-1" />
                        {form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </div>
</AuthenticatedLayout>
