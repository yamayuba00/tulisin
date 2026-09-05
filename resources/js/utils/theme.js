import { ref } from 'vue';

const STORAGE_KEY = 'theme';

function getInitialTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === 'dark' ? 'dark' : 'light';
}

function applyTheme(value) {
    document.documentElement.classList.toggle('dark', value === 'dark');
}

// State tema bersifat global (singleton) agar dipakai konsisten di seluruh app.
const theme = ref(getInitialTheme());
applyTheme(theme.value);

export function useTheme() {
    function setTheme(value) {
        theme.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        applyTheme(value);
    }

    function toggle() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return { theme, setTheme, toggle };
}
