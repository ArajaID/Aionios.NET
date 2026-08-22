<script>
    import { useForm } from '@inertiajs/svelte';
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import { Eye, EyeOff, Lock, Mail } from 'lucide-svelte';

    let { errors = {} } = $props();
    let passwordVisible = $state(false);

    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/login');
    }

</script>

<svelte:head>
    <title>Masuk - Aionios.NET</title>
</svelte:head>

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
                                type={passwordVisible ? 'text' : 'password'}
                                bind:value={form.password}
                                placeholder="••••••••"
                                required
                                class="pl-9 pr-10"
                            />
                            <Lock class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-stone-500 transition-colors hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-stone-400"
                                onclick={() => (passwordVisible = !passwordVisible)}
                                aria-label={passwordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                aria-pressed={passwordVisible}
                                title={passwordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                            >
                                {#if passwordVisible}
                                    <EyeOff class="h-4 w-4" />
                                {:else}
                                    <Eye class="h-4 w-4" />
                                {/if}
                            </button>
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
    </div>
</GuestLayout>
