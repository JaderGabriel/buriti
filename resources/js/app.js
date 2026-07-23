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

document.addEventListener('click', toggleTheme, true);

const stored = localStorage.getItem('buriti-theme');
applyTheme(stored ? stored === 'dark' : false);

function initSiteNav() {
    const header = document.querySelector('[data-site-header]');
    if (! header) {
        return;
    }

    const toggle = header.querySelector('[data-nav-toggle]');
    const panel = header.querySelector('[data-nav-panel]');
    const openIcon = header.querySelector('[data-nav-icon="open"]');
    const closeIcon = header.querySelector('[data-nav-icon="close"]');
    if (! toggle || ! panel) {
        return;
    }

    const setOpen = (open) => {
        panel.classList.toggle('hidden', ! open);
        panel.toggleAttribute('hidden', ! open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        openIcon?.classList.toggle('hidden', open);
        closeIcon?.classList.toggle('hidden', ! open);
    };

    setOpen(false);

    toggle.addEventListener('click', () => {
        setOpen(panel.hasAttribute('hidden'));
    });

    header.querySelectorAll('[data-nav-close]').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}

/**
 * Saltos #âncora na mesma página: instantâneos, com offset do header sticky.
 * Evita scroll-behavior:smooth lento e reload desnecessário em links absolutos para a home.
 */
function initInPageAnchors() {
    const closeMobileNav = () => {
        const header = document.querySelector('[data-site-header]');
        const toggle = header?.querySelector('[data-nav-toggle]');
        const panel = header?.querySelector('[data-nav-panel]');
        if (! header || ! toggle || ! panel) {
            return;
        }
        panel.classList.add('hidden');
        panel.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu');
        header.querySelector('[data-nav-icon="open"]')?.classList.remove('hidden');
        header.querySelector('[data-nav-icon="close"]')?.classList.add('hidden');
    };

    const jumpToHash = (hash, { updateHistory = true } = {}) => {
        if (! hash || hash === '#') {
            return false;
        }

        let target = null;
        try {
            target = document.querySelector(hash);
        } catch {
            return false;
        }

        if (! target) {
            return false;
        }

        closeMobileNav();

        if (updateHistory && window.location.hash !== hash) {
            history.pushState(null, '', hash);
        }

        target.scrollIntoView({ behavior: 'auto', block: 'start' });

        return true;
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const rawHref = link.getAttribute('href');
        if (! rawHref || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
            return;
        }

        let url;
        try {
            url = new URL(rawHref, window.location.href);
        } catch {
            return;
        }

        if (url.origin !== window.location.origin || ! url.hash || url.hash === '#') {
            return;
        }

        const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
        const targetPath = url.pathname.replace(/\/$/, '') || '/';
        if (currentPath !== targetPath) {
            return;
        }

        if (! document.querySelector(url.hash)) {
            return;
        }

        event.preventDefault();
        jumpToHash(url.hash);
    });

    if (window.location.hash) {
        requestAnimationFrame(() => {
            jumpToHash(window.location.hash, { updateHistory: false });
        });
    }
}

function initCookieBanner() {
    const banner = document.getElementById('cookie-banner');
    if (! banner) {
        return;
    }

    let show = true;
    try {
        show = ! localStorage.getItem('buriti-cookie-consent');
    } catch {
        show = true;
    }

    const setVisible = (visible) => {
        banner.classList.toggle('hidden', ! visible);
        banner.toggleAttribute('hidden', ! visible);
    };

    setVisible(show);

    banner.querySelector('[data-cookie-accept]')?.addEventListener('click', () => {
        try {
            localStorage.setItem('buriti-cookie-consent', JSON.stringify({
                essential: true,
                at: new Date().toISOString(),
            }));
        } catch {
            // ignore quota / private mode
        }
        setVisible(false);
    });
}

function openCareerDialog(dialog) {
    if (! (dialog instanceof HTMLElement) || ! dialog.classList.contains('career-dialog')) {
        return;
    }

    if (dialog.parentElement !== document.body) {
        document.body.appendChild(dialog);
    }

    dialog.hidden = false;
    dialog.classList.add('is-open');
    dialog.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('overflow-hidden');

    const closeBtn = dialog.querySelector('[data-dialog-close]:not(.career-dialog__backdrop)');
    queueMicrotask(() => closeBtn?.focus?.());
}

function closeCareerDialog(dialog) {
    if (! (dialog instanceof HTMLElement) || ! dialog.classList.contains('career-dialog')) {
        return;
    }

    dialog.classList.remove('is-open');
    dialog.hidden = true;
    dialog.setAttribute('aria-hidden', 'true');

    if (! document.querySelector('.career-dialog.is-open')) {
        document.documentElement.classList.remove('overflow-hidden');
    }
}

function initDialogs() {
    if (document.documentElement.dataset.careerDialogsBound === '1') {
        return;
    }
    document.documentElement.dataset.careerDialogsBound = '1';

    document.querySelectorAll('.career-dialog').forEach((dialog) => {
        if (dialog.parentElement !== document.body) {
            document.body.appendChild(dialog);
        }
        closeCareerDialog(dialog);
    });

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest?.('[data-dialog-open]');
        if (openBtn) {
            event.preventDefault();
            event.stopPropagation();
            const id = openBtn.getAttribute('data-dialog-open');
            const dialog = id ? document.getElementById(id) : null;
            openCareerDialog(dialog);
            return;
        }

        const closeBtn = event.target.closest?.('[data-dialog-close]');
        if (closeBtn) {
            const dialog = closeBtn.closest('.career-dialog');
            if (! dialog) {
                return;
            }

            if (closeBtn.tagName === 'A' && closeBtn.getAttribute('href')?.startsWith('#')) {
                closeCareerDialog(dialog);
                return;
            }

            event.preventDefault();
            closeCareerDialog(dialog);
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.career-dialog.is-open').forEach((dialog) => {
            closeCareerDialog(dialog);
        });
    });
}

function buritiPhoneCountryField(countries, iso) {
    return {
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
    };
}

function createRandomPassword(length = 16) {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnopqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*?-_+';
    const all = upper + lower + digits + symbols;
    const pick = (set) => set[Math.floor(Math.random() * set.length)];

    const chars = [pick(upper), pick(lower), pick(digits), pick(symbols)];
    while (chars.length < length) {
        chars.push(pick(all));
    }

    for (let i = chars.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}

function setPasswordVisibility(field, visible) {
    const input = field.querySelector('[data-password-input]');
    const toggle = field.querySelector('[data-password-toggle]');
    const showIcon = field.querySelector('[data-password-icon="show"]');
    const hideIcon = field.querySelector('[data-password-icon="hide"]');

    if (! input) {
        return;
    }

    input.type = visible ? 'text' : 'password';

    if (toggle) {
        toggle.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
        toggle.setAttribute('title', visible ? 'Ocultar senha' : 'Mostrar senha');
    }

    showIcon?.classList.toggle('hidden', visible);
    hideIcon?.classList.toggle('hidden', ! visible);
}

function initPasswordFields(root = document) {
    root.querySelectorAll('[data-password-field]').forEach((field) => {
        if (field.dataset.bound === '1') {
            return;
        }
        field.dataset.bound = '1';

        const toggle = field.querySelector('[data-password-toggle]');
        toggle?.addEventListener('click', () => {
            const input = field.querySelector('[data-password-input]');
            setPasswordVisibility(field, input?.type === 'password');
        });
    });
}

function initPasswordGenerators() {
    document.querySelectorAll('[data-password-generator]').forEach((root) => {
        if (root.dataset.bound === '1') {
            return;
        }
        root.dataset.bound = '1';

        const valueInput = root.querySelector('[data-password-value]');
        const confirmInput = root.querySelector('[data-password-confirm]');
        const generateBtn = root.querySelector('[data-password-generate]');
        const copyBtn = root.querySelector('[data-password-copy]');
        const generatedBox = root.querySelector('[data-password-generated]');
        const display = root.querySelector('[data-password-display]');

        const syncCopyState = () => {
            if (! copyBtn) {
                return;
            }
            copyBtn.disabled = ! (valueInput?.value || '');
        };

        valueInput?.addEventListener('input', syncCopyState);
        syncCopyState();

        generateBtn?.addEventListener('click', () => {
            const password = createRandomPassword(16);

            if (valueInput) {
                valueInput.value = password;
                valueInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (confirmInput) {
                confirmInput.value = password;
                confirmInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            root.querySelectorAll('[data-password-field]').forEach((field) => {
                setPasswordVisibility(field, true);
            });

            if (display) {
                display.textContent = password;
            }
            if (generatedBox) {
                generatedBox.hidden = false;
                generatedBox.classList.remove('hidden');
            }

            syncCopyState();
            valueInput?.focus();
            valueInput?.select?.();
        });

        copyBtn?.addEventListener('click', async () => {
            const password = valueInput?.value || '';
            if (! password) {
                return;
            }

            try {
                await navigator.clipboard.writeText(password);
                const original = copyBtn.textContent;
                copyBtn.textContent = 'Copiada!';
                setTimeout(() => {
                    copyBtn.textContent = original || 'Copiar';
                }, 2000);
            } catch {
                // ignore clipboard failures
            }
        });
    });
}

function initAdminShell() {
    const shell = document.querySelector('[data-admin-shell]');
    if (! shell) {
        return;
    }

    const sidebar = shell.querySelector('[data-admin-sidebar]');
    const overlay = shell.querySelector('[data-admin-overlay]');
    const openBtn = shell.querySelector('[data-admin-open]');
    if (! sidebar || ! overlay) {
        return;
    }

    const setOpen = (open) => {
        sidebar.dataset.open = open ? 'true' : 'false';
        overlay.classList.toggle('hidden', ! open);
        overlay.toggleAttribute('hidden', ! open);
        document.documentElement.classList.toggle(
            'overflow-hidden',
            open && window.matchMedia('(max-width: 1023px)').matches
        );
    };

    setOpen(false);

    openBtn?.addEventListener('click', () => setOpen(true));
    overlay.addEventListener('click', () => setOpen(false));
    shell.querySelectorAll('[data-admin-close]').forEach((el) => {
        el.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}

function initAvatarPreviews() {
    document.querySelectorAll('[data-avatar-preview]').forEach((root) => {
        const input = root.querySelector('[data-avatar-preview-input]');
        const image = root.querySelector('[data-avatar-preview-image]');
        const fallback = root.querySelector('[data-avatar-preview-fallback]');
        const nameEl = root.querySelector('[data-avatar-preview-name]');

        if (! input || ! image) {
            return;
        }

        let objectUrl = null;
        const originalSrc = image.getAttribute('data-original-src') || '';

        const showImage = (src) => {
            image.src = src;
            image.hidden = false;
            image.classList.remove('hidden');
            if (fallback) {
                fallback.hidden = true;
                fallback.classList.add('hidden');
            }
        };

        const showFallback = () => {
            image.removeAttribute('src');
            image.hidden = true;
            image.classList.add('hidden');
            if (fallback) {
                fallback.hidden = false;
                fallback.classList.remove('hidden');
            }
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0] || null;

            if (nameEl) {
                nameEl.textContent = file?.name || 'Escolher arquivo';
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            if (! file) {
                if (originalSrc) {
                    showImage(originalSrc);
                } else {
                    showFallback();
                }
                return;
            }

            objectUrl = URL.createObjectURL(file);
            showImage(objectUrl);
        });
    });
}

function initTelegramLogin() {
    const root = document.querySelector('[data-telegram-login]');
    const startBtn = root?.querySelector('[data-telegram-start]');
    if (! root || ! startBtn) {
        return;
    }

    const waitBox = root.querySelector('[data-telegram-wait]');
    const openLink = root.querySelector('[data-telegram-open]');
    const label = root.querySelector('[data-telegram-label]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';

    let pollTimer = null;

    const setWaiting = (waiting, deepLink = '#') => {
        startBtn.disabled = waiting;
        if (label) {
            label.textContent = waiting ? 'Aguardando Telegram…' : 'Continuar com Telegram';
        }
        if (waitBox) {
            waitBox.classList.toggle('hidden', ! waiting);
            waitBox.toggleAttribute('hidden', ! waiting);
        }
        if (openLink && deepLink) {
            openLink.setAttribute('href', deepLink);
        }
    };

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const pollStatus = (statusUrl, completeUrl) => {
        stopPolling();
        pollTimer = window.setInterval(async () => {
            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (! response.ok) {
                    return;
                }
                const data = await response.json();
                if (data.status === 'ready') {
                    stopPolling();
                    window.location.href = data.complete_url || completeUrl;
                    return;
                }
                if (data.status === 'expired' || data.status === 'unlinked' || data.status === 'denied') {
                    stopPolling();
                    setWaiting(false);
                    if (data.status === 'unlinked') {
                        window.alert('Vincule a conta no bot com /login email | senha e tente de novo.');
                    } else {
                        window.alert('Pedido de login Telegram expirou ou foi recusado. Tente novamente.');
                    }
                }
            } catch {
                // ignore transient network errors while polling
            }
        }, 1800);
    };

    startBtn.addEventListener('click', async () => {
        const startUrl = startBtn.getAttribute('data-start-url');
        if (! startUrl) {
            return;
        }

        setWaiting(true);
        try {
            const response = await fetch(startUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: '{}',
            });
            const data = await response.json();
            if (! response.ok || ! data.ok) {
                throw new Error(data.message || 'Falha ao iniciar login Telegram');
            }

            setWaiting(true, data.deep_link);
            window.open(data.deep_link, '_blank', 'noopener');
            pollStatus(data.status_url, data.complete_url);
        } catch (error) {
            setWaiting(false);
            window.alert(error instanceof Error ? error.message : 'Não foi possível iniciar o login Telegram.');
        }
    });
}

function initBackToTop() {
    const button = document.querySelector('[data-back-to-top]');
    if (! (button instanceof HTMLElement)) {
        return;
    }

    const threshold = 420;

    const sync = () => {
        const visible = window.scrollY > threshold;
        button.classList.toggle('is-visible', visible);
        button.toggleAttribute('hidden', ! visible);
    };

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'auto' });
    });

    window.addEventListener('scroll', sync, { passive: true });
    sync();
}

window.buritiPhoneCountryField = buritiPhoneCountryField;

document.addEventListener('alpine:init', () => {
    Alpine.data('buritiPhoneCountryField', buritiPhoneCountryField);
});

window.Alpine = Alpine;

// Modal / nav / cookies / avatar / password must not depend on Alpine succeeding.
initDialogs();
syncThemeToggleButtons();
initSiteNav();
initInPageAnchors();
initCookieBanner();
initBackToTop();
initAdminShell();
initAvatarPreviews();
initPasswordFields();
initPasswordGenerators();
initTelegramLogin();

try {
    Alpine.start();
} catch (error) {
    console.error('Alpine failed to start', error);
}

document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (event) => {
        if (!confirm(el.dataset.confirm || 'Confirmar esta ação?')) {
            event.preventDefault();
        }
    });
});
