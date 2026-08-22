<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', onclick, children, ...rest } = $props();
</script>

<tr
    {onclick}
    class={cn(
        'border-b border-stone-100 transition-colors hover:bg-stone-50/80 data-[state=selected]:bg-stone-100',
        onclick ? 'cursor-pointer' : '',
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</tr>
