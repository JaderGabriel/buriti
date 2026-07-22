import Alpine from 'alpinejs';

function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', Boolean(dark));
    document.documentElement.classList.toggle('light', ! dark);
    localStorage.setItem('buriti-theme', dark ? 'dark' : 'light');
    syncThemeToggleButtons();
    document.dispatchEvent(new CustomEvent('buriti-theme-changed', { detail: { dark: Boolean(dark) } }));
}

function currentThemeIsDark() {
    return document.documentElement.classList.contains('dark');
}

function syncThemeToggleButtons() {
    const dark = currentThemeIsDark();

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', dark ? 'Ativar modo claro' : 'Ativar modo escuro');
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.setAttribute('title', dark ? 'Modo claro' : 'Modo escuro');
    });
}

function toggleTheme(event) {
    const button = event.target.closest?.('[data-theme-toggle]');
    if (! button) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    applyTheme(! currentThemeIsDark());
}

// Captura na fase de captura para o clique não ser engolido por overlays.
document.addEventListener('click', toggleTheme, true);

const stored = localStorage.getItem('buriti-theme');
applyTheme(stored ? stored === 'dark' : false);

Alpine.data('themeToggle', () => ({
    dark: currentThemeIsDark(),
    init() {
        this.dark = currentThemeIsDark();
        document.addEventListener('buriti-theme-changed', (event) => {
            this.dark = Boolean(event.detail?.dark);
        });
    },
}));

Alpine.data('passwordGenerator', () => ({
    password: '',
    confirmation: '',
    visible: false,
    copied: false,
    generated: false,

    generate() {
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const digits = '23456789';
        const symbols = '!@#$%&*?-_+';
        const all = upper + lower + digits + symbols;
        const pick = (set) => set[Math.floor(Math.random() * set.length)];

        const chars = [pick(upper), pick(lower), pick(digits), pick(symbols)];
        while (chars.length < 16) {
            chars.push(pick(all));
        }

        for (let i = chars.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [chars[i], chars[j]] = [chars[j], chars[i]];
        }

        const value = chars.join('');
        this.password = value;
        this.confirmation = value;
        this.visible = true;
        this.generated = true;
        this.copied = false;
    },

    async copy() {
        if (! this.password) {
            return;
        }

        try {
            await navigator.clipboard.writeText(this.password);
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2000);
        } catch {
            this.copied = false;
        }
    },
}));

Alpine.data('cookieConsent', () => ({
    visible: false,
    init() {
        try {
            this.visible = ! localStorage.getItem('buriti-cookie-consent');
        } catch {
            this.visible = true;
        }
    },
    accept() {
        try {
            localStorage.setItem('buriti-cookie-consent', JSON.stringify({
                essential: true,
                at: new Date().toISOString(),
            }));
        } catch {
            // ignore quota / private mode
        }
        this.visible = false;
    },
}));

Alpine.data('careerModal', () => ({
    open: false,
    init() {
        this.open = false;
    },
    show() {
        this.open = true;
        document.documentElement.classList.add('overflow-hidden');
    },
    hide() {
        this.open = false;
        document.documentElement.classList.remove('overflow-hidden');
    },
}));

Alpine.data('phoneCountryField', (countries, iso) => ({
    countries: Array.isArray(countries) ? countries : [],
    iso: iso || 'BR',
    get selected() {
        return this.countries.find((country) => country.iso === this.iso) || this.countries[0] || null;
    },
    dialLabel() {
        if (! this.selected) {
            return '';
        }

        return `${this.selected.flag || ''} +${this.selected.dial || ''}`.trim();
    },
}));

window.Alpine = Alpine;
Alpine.start();
syncThemeToggleButtons();

document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (event) => {
        if (!confirm(el.dataset.confirm || 'Confirmar esta ação?')) {
            event.preventDefault();
        }
    });
});
