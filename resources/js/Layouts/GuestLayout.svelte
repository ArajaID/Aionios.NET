<script>
    import { cn } from '@/lib/utils';
    let { children } = $props();
</script>

<div class="min-h-screen bg-stone-100 flex flex-col items-center justify-center p-4 sm:p-6 antialiased text-stone-900 selection:bg-stone-800 selection:text-white">
    <!-- Background subtle warmth -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden opacity-40">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 h-[500px] w-[700px] rounded-full bg-gradient-to-tr from-stone-200 to-stone-300 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">
        {#if children}
            {@render children()}
        {/if}
    </div>
</div>
