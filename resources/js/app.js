import Alpine from 'alpinejs';
import '../css/app.css';

const THEME_KEY = 'activitylog-ui.theme';
const FILTERS_FORM = 'activitylog-filters';

const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(theme) {
    const dark = theme === 'dark' || (theme === 'auto' && darkQuery.matches);
    document.documentElement.classList.toggle('dark', dark);
}

const ActivitylogUi = {
    /**
     * Navigates to the filtered URL, omitting empty and default values so the
     * query string stays short and shareable.
     */
    submitFilters() {
        const form = document.getElementById(FILTERS_FORM);

        if (!form) {
            return;
        }

        const data = new FormData(form);
        const date = data.get('date');
        const params = new URLSearchParams();

        for (const [key, value] of data.entries()) {
            if (value === '') continue;
            if (key === 'date' && value === 'all') continue;
            if (key === 'relation' && value === 'self') continue;
            if ((key === 'from' || key === 'to') && date !== 'custom') continue;

            params.append(key, value);
        }

        const query = params.toString();

        document.documentElement.classList.add('alu-loading');
        window.location.href = form.action + (query ? `?${query}` : '');
    },
};

window.ActivitylogUi = ActivitylogUi;

window.addEventListener('pageshow', () => {
    document.documentElement.classList.remove('alu-loading');
});

document.addEventListener('alpine:init', () => {
    Alpine.data('themeSwitcher', () => ({
        theme: localStorage.getItem(THEME_KEY) || 'auto',

        init() {
            darkQuery.addEventListener('change', () => {
                if (this.theme === 'auto') applyTheme('auto');
            });
        },

        set(theme) {
            this.theme = theme;
            localStorage.setItem(THEME_KEY, theme);
            applyTheme(theme);
        },
    }));

    Alpine.data('userSelect', ({ options, selected }) => ({
        options,
        selected: selected ?? '',
        query: '',
        open: false,
        limit: 200,

        get selectedLabel() {
            const option = this.options.find((item) => item.value === this.selected);

            return option ? option.label : `#${this.selected}`;
        },

        get filtered() {
            const term = this.normalize(this.query.trim());
            const items = term === ''
                ? this.options
                : this.options.filter((item) => this.normalize(item.label).includes(term));

            return items.slice(0, this.limit);
        },

        normalize(value) {
            return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.search.focus());
            }
        },

        choose(value) {
            this.open = false;
            this.query = '';

            if (value === this.selected) return;

            this.selected = value;
            this.$nextTick(() => ActivitylogUi.submitFilters());
        },

        chooseFirst() {
            if (this.filtered.length > 0) {
                this.choose(this.filtered[0].value);
            }
        },
    }));
});

window.Alpine = Alpine;
Alpine.start();
