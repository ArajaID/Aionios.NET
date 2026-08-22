import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';
import GlobalAppLoader from '@/Components/GlobalAppLoader.svelte';

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

        let loaderTarget = document.getElementById('global-app-loader');
        if (!loaderTarget) {
            loaderTarget = document.createElement('div');
            loaderTarget.id = 'global-app-loader';
            document.body.appendChild(loaderTarget);
            mount(GlobalAppLoader, { target: loaderTarget });
        }
    },
    progress: false,
});
