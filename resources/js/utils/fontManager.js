// ---- Font kustom (TTF/OTF/WOFF) ----
// Mendukung upload font bawaan kampus. Font disimpan sebagai data URL dan
// didaftarkan lewat @font-face secara dinamis agar bisa dipakai di canvas.

const FONTS_KEY = 'tulisin.customFonts';

const FORMAT_BY_EXT = {
    ttf: 'truetype',
    otf: 'opentype',
    woff: 'woff',
    woff2: 'woff2',
};

function readJson(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function writeJson(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // localStorage penuh/tidak tersedia — abaikan.
    }
}

function readFileAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error || new Error('Gagal membaca file font.'));
        reader.readAsDataURL(file);
    });
}

function fontFormat(file) {
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    return FORMAT_BY_EXT[ext] || 'truetype';
}

function fontFamilyFromName(name) {
    // Nama font = nama file tanpa ekstensi (dibersihkan agar valid sebagai family).
    const base = name.replace(/\.[^.]+$/, '').trim() || 'Font Kustom';
    return base.replace(/[^\w\s-]/g, '').trim() || 'Font Kustom';
}

export function listCustomFonts() {
    return readJson(FONTS_KEY, []);
}

export function registerFontFace(font) {
    const id = `custom-font-${font.family}`;
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent =
        `@font-face { font-family: '${font.family.replace(/'/g, '')}'; ` +
        `src: url('${font.dataUrl}') format('${font.format}'); ` +
        'font-weight: normal; font-style: normal; }';
    document.head.appendChild(style);
}

export function registerAllCustomFonts() {
    listCustomFonts().forEach(registerFontFace);
}

export async function addCustomFont(file) {
    const dataUrl = await readFileAsDataURL(file);
    const family = fontFamilyFromName(file.name);
    const font = {
        family,
        name: family,
        format: fontFormat(file),
        dataUrl,
        createdAt: Date.now(),
    };
    // Ganti font lama dengan nama sama, lalu simpan.
    const fonts = listCustomFonts().filter((f) => f.family !== family);
    fonts.push(font);
    writeJson(FONTS_KEY, fonts);
    registerFontFace(font);
    return font;
}

export function removeCustomFont(family) {
    writeJson(FONTS_KEY, listCustomFonts().filter((f) => f.family !== family));
    const el = document.getElementById(`custom-font-${family}`);
    if (el) el.remove();
}
