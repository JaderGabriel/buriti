import Alpine from 'alpinejs';

document.documentElement.classList.add(
    localStorage.getItem('buriti-theme') === 'light' ? 'light' : 'dark'
);

Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        document.documentElement.classList.toggle('light', !this.dark);
        localStorage.setItem('buriti-theme', this.dark ? 'dark' : 'light');
    },
}));

window.Alpine = Alpine;
Alpine.start();

document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (event) => {
        if (!confirm(el.dataset.confirm || 'Confirmar esta ação?')) {
            event.preventDefault();
        }
    });
});
