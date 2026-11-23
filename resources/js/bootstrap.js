// Frontend bootstrap configuration for KOPDES Inertia + Vue 3 SPA
window.kopdesCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
