<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<div
    class={cn(
        'inline-flex h-10 items-center justify-center rounded-xl bg-stone-200/60 p-1 text-stone-600 border border-stone-200',
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</div>
