<script>
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    let visible = $state(true);
    let status = $state('Memuat data aplikasi…');

    let showTimer;
    let hideTimer;
    let statusTimer;
    let shownAt = Date.now();

    const clearTimer = (timer) => {
        if (timer) clearTimeout(timer);
    };

    function beginLoading() {
        clearTimer(showTimer);
        clearTimer(hideTimer);
        clearTimer(statusTimer);

        status = 'Memuat halaman…';

        if (visible) {
            shownAt = Date.now();
        } else {
            showTimer = setTimeout(() => {
                visible = true;
                shownAt = Date.now();
            }, 100);
        }

        statusTimer = setTimeout(() => {
            status = 'Menyinkronkan data layanan…';
        }, 900);
    }

    function finishLoading() {
        clearTimer(showTimer);
        clearTimer(statusTimer);
        showTimer = null;

        if (!visible) return;

        status = 'Hampir selesai…';
        const remainingDuration = Math.max(0, 500 - (Date.now() - shownAt));

        hideTimer = setTimeout(() => {
            visible = false;
        }, remainingDuration);
    }

    onMount(() => {
        hideTimer = setTimeout(() => {
            status = 'Hampir selesai…';
            hideTimer = setTimeout(() => {
                visible = false;
            }, 220);
        }, 650);

        const removeStartListener = router.on('start', beginLoading);
        const removeFinishListener = router.on('finish', finishLoading);

        return () => {
            clearTimer(showTimer);
            clearTimer(hideTimer);
            clearTimer(statusTimer);
            removeStartListener();
            removeFinishListener();
        };
    });
</script>

<div
    class:loader-visible={visible}
    class="app-loader-overlay"
    aria-hidden={!visible}
>
    <div class="loader-content" role="status" aria-live="polite" aria-label={status}>
        <div class="loader-mark" aria-hidden="true">
            <span class="loader-ring"></span>
            <span class="loader-orbit"></span>
            <span class="loader-core">A</span>
        </div>

        <div class="text-center">
            <p class="text-sm font-bold text-stone-900">Menyiapkan Aionios.NET</p>
            <p class="mt-1 min-w-52 text-[11px] font-medium text-stone-500">{status}</p>
        </div>

        <div class="loader-progress" aria-hidden="true">
            <span class="loader-progress-bar"></span>
        </div>
    </div>
</div>

<style>
    .app-loader-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: grid;
        place-items: center;
        padding: 1.5rem;
        visibility: hidden;
        pointer-events: none;
        opacity: 0;
        background: rgb(245 245 244 / 0.36);
        backdrop-filter: blur(0) saturate(1);
        transition:
            opacity 260ms ease,
            backdrop-filter 360ms ease,
            visibility 0s linear 420ms;
    }

    .app-loader-overlay.loader-visible {
        visibility: visible;
        pointer-events: auto;
        opacity: 1;
        backdrop-filter: blur(9px) saturate(0.72);
        transition-delay: 0s;
    }

    .loader-content {
        display: grid;
        justify-items: center;
        gap: 0.9rem;
        opacity: 0;
        transform: translateY(0.75rem) scale(0.9);
        transition:
            transform 420ms cubic-bezier(0.16, 1, 0.3, 1),
            opacity 220ms ease;
    }

    .loader-visible .loader-content {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    .loader-mark {
        position: relative;
        display: grid;
        width: 5.125rem;
        height: 5.125rem;
        place-items: center;
    }

    .loader-core {
        position: relative;
        z-index: 2;
        display: grid;
        width: 3.125rem;
        height: 3.125rem;
        place-items: center;
        border-radius: 0.95rem;
        background: #1c1917;
        color: #fafaf9;
        font-size: 1.2rem;
        font-weight: 800;
        box-shadow: 0 12px 30px rgb(28 25 23 / 0.2);
        animation: loader-core-pulse 1.35s ease-in-out infinite;
    }

    .loader-ring {
        position: absolute;
        inset: 0.5rem;
        border: 1px solid rgb(87 83 78 / 0.2);
        border-radius: 9999px;
    }

    .loader-orbit {
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        animation: loader-orbit-spin 1.25s linear infinite;
    }

    .loader-orbit::before,
    .loader-orbit::after {
        position: absolute;
        border-radius: 9999px;
        background: #57534e;
        content: '';
    }

    .loader-orbit::before {
        top: 0.125rem;
        left: 50%;
        width: 0.45rem;
        height: 0.45rem;
        transform: translateX(-50%);
    }

    .loader-orbit::after {
        right: 0.3rem;
        bottom: 0.75rem;
        width: 0.25rem;
        height: 0.25rem;
        opacity: 0.45;
    }

    .loader-progress {
        width: 11.875rem;
        height: 0.2rem;
        overflow: hidden;
        border-radius: 9999px;
        background: rgb(120 113 108 / 0.18);
    }

    .loader-progress-bar {
        display: block;
        width: 38%;
        height: 100%;
        border-radius: inherit;
        background: #292524;
        transform: translateX(-120%);
        animation: loader-progress-slide 1.15s cubic-bezier(0.65, 0, 0.35, 1) infinite;
    }

    @keyframes loader-orbit-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes loader-core-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(0.94); }
    }

    @keyframes loader-progress-slide {
        0% { transform: translateX(-120%); }
        55% { transform: translateX(110%); }
        100% { transform: translateX(300%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .app-loader-overlay,
        .loader-content {
            transition: none;
        }

        .loader-core,
        .loader-orbit,
        .loader-progress-bar {
            animation: none;
        }

        .loader-progress-bar {
            width: 65%;
            transform: none;
        }
    }
</style>
