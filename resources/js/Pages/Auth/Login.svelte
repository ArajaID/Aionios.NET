<script>
    import { useForm, router } from '@inertiajs/svelte';
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import { Lock, Mail, Shield, Zap, Sparkles } from 'lucide-svelte';

    let { errors = {} } = $props();

    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/login');
    }

    function quickLogin(role) {
        router.post('/quick-login', { role });
    }
</script>

<GuestLayout>
    <div class="space-y-6">
        <!-- Logo Header -->
        <div class="text-center space-y-2">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-stone-800 via-stone-700 to-stone-800 text-stone-900 font-black text-xl border border-stone-600/70 shadow-lg shadow-stone-950/50">
                A
            </div>
            <h1 class="text-xl font-bold tracking-tight text-stone-900">Aionios.NET</h1>
            <p class="text-xs text-stone-500">Integrated ISP Billing, Network & Accounting Platform</p>
        </div>

        <!-- Login Form Card -->
        <Card class="border-stone-200 bg-white shadow-2xl">
            <CardHeader class="pb-4">
                <CardTitle class="text-stone-900">Masuk ke Sistem</CardTitle>
                <CardDescription class="text-stone-500">Masukkan email dan kata sandi operasional Anda</CardDescription>
            </CardHeader>

            <form onsubmit={handleSubmit}>
                <CardContent class="space-y-4">
                    {#if errors.email}
                        <div class="rounded-lg bg-rose-500/15 p-3 text-xs text-rose-700 border border-rose-500/20">
                            {errors.email}
                        </div>
                    {/if}

                    <div class="space-y-1.5">
                        <label for="email" class="text-xs font-semibold text-stone-700">Email</label>
                        <div class="relative">
                            <Input
                                id="email"
                                type="email"
                                bind:value={form.email}
                                placeholder="name@aionios.net"
                                required
                                class="pl-9"
                            />
                            <Mail class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="password" class="text-xs font-semibold text-stone-700">Kata Sandi</label>
                        <div class="relative">
                            <Input
                                id="password"
                                type="password"
                                bind:value={form.password}
                                placeholder="••••••••"
                                required
                                class="pl-9"
                            />
                            <Lock class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                        </div>
                    </div>
                </CardContent>

                <CardFooter class="flex flex-col gap-3">
                    <Button
                        type="submit"
                        disabled={form.processing}
                        class="w-full h-10 font-semibold"
                    >
                        {#if form.processing}
                            Memproses...
                        {:else}
                            Masuk
                        {/if}
                    </Button>
                </CardFooter>
            </form>
        </Card>

        <!-- Quick Demo Switcher Card -->
        <Card class="border-stone-200 bg-white p-4">
            <div class="flex items-center gap-2 mb-3">
                <Sparkles class="h-4 w-4 text-stone-700" />
                <h4 class="text-xs font-semibold text-stone-800">Masuk Cepat Demo (Quick Switcher)</h4>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <button
                    type="button"
                    onclick={() => quickLogin('owner')}
                    class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-300 bg-stone-800/60 hover:bg-stone-100 hover:border-stone-600 text-stone-800 transition-all text-center group cursor-pointer"
                >
                    <Shield class="h-4 w-4 mb-1 text-stone-700 group-hover:scale-110 transition-transform" />
                    <span class="text-[11px] font-bold">Owner</span>
                    <span class="text-[9px] text-stone-500">Full Access</span>
                </button>

                <button
                    type="button"
                    onclick={() => quickLogin('admin_keuangan')}
                    class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-300 bg-stone-800/60 hover:bg-stone-100 hover:border-stone-600 text-stone-800 transition-all text-center group cursor-pointer"
                >
                    <Zap class="h-4 w-4 mb-1 text-emerald-700 group-hover:scale-110 transition-transform" />
                    <span class="text-[11px] font-bold">Finance</span>
                    <span class="text-[9px] text-stone-500">Billing & COA</span>
                </button>

                <button
                    type="button"
                    onclick={() => quickLogin('admin_jaringan')}
                    class="flex flex-col items-center justify-center p-2.5 rounded-xl border border-stone-300 bg-stone-800/60 hover:bg-stone-100 hover:border-stone-600 text-stone-800 transition-all text-center group cursor-pointer"
                >
                    <Lock class="h-4 w-4 mb-1 text-cyan-800 group-hover:scale-110 transition-transform" />
                    <span class="text-[11px] font-bold">Network</span>
                    <span class="text-[9px] text-stone-500">MikroTik & ONT</span>
                </button>
            </div>
        </Card>
    </div>
</GuestLayout>
