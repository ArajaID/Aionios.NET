import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Aionios.NET';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
        const page = pages[`./Pages/${name}.svelte`];
        if (!page) {
            console.error(`Page not found: ./Pages/${name}.svelte`);
        }
        return page;
    },
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },
    progress: {
        color: '#6366f1',
        showSpinner: true,
    },
});
