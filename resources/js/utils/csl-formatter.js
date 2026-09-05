// csl-formatter.js
// Pipeline sitasi bergaya CSL (disederhanakan):
//   Data Mentah (JSON) ➔ [1. Parser/Formatter] ➔ [2. Sorter] ➔ [3. Compiler] ➔ Output Teks.
//
// Gaya yang didukung: IEEE, APA, MLA, Harvard, Chicago.
// Input mengikuti struktur CSL-JSON:
//   {
//     id, type, title,
//     author: [{ family, given }],
//     issued: { 'date-parts': [[tahun]] },
//     'container-title', volume, issue, page, publisher, 'publisher-place', DOI, URL
//   }
// Field sederhana `author` (string) dan `year` juga diterima sebagai fallback.
// Teks miring (mis. nama jurnal) direpresentasikan sebagai <i>...</i> agar
// langsung ter-render miring di canvas (contenteditable HTML).

export const CSL_STYLES = ['IEEE', 'APA', 'MLA', 'Harvard', 'Chicago'];

// ============================================================================
// [1] PARSER / FORMATTER
// ============================================================================

function parseAuthorString(s) {
    const value = (s || '').trim();
    if (!value) return null;
    if (value.includes(',')) {
        const [family, given] = value.split(',').map((p) => p.trim());
        return { family: family || '', given: given || '' };
    }
    const parts = value.split(/\s+/);
    if (parts.length === 1) return { family: parts[0], given: '' };
    return { family: parts[parts.length - 1], given: parts.slice(0, -1).join(' ') };
}

function normalizeAuthors(raw) {
    if (Array.isArray(raw.author)) {
        return raw.author
            .map((a) => (typeof a === 'string' ? parseAuthorString(a) : { family: a.family || '', given: a.given || '' }))
            .filter((a) => a.family);
    }
    if (typeof raw.author === 'string' && raw.author.trim()) {
        return raw.author.split(';').map(parseAuthorString).filter(Boolean);
    }
    return [];
}

function yearOf(raw) {
    const dp = raw.issued?.['date-parts']?.[0];
    if (Array.isArray(dp) && dp.length) return String(dp[0]);
    if (raw.year) return String(raw.year);
    if (raw.issued?.year) return String(raw.issued.year);
    return '';
}

export function parseCSLItem(raw) {
    return {
        id: raw.id || raw.uid || '',
        type: raw.type || 'book',
        title: (raw.title || '').trim(),
        authors: normalizeAuthors(raw),
        year: yearOf(raw),
        containerTitle: (raw['container-title'] || raw.journal || '').trim(),
        volume: raw.volume || '',
        issue: raw.issue || '',
        page: raw.page || '',
        publisher: (raw.publisher || '').trim(),
        publisherPlace: (raw['publisher-place'] || '').trim(),
        doi: raw.DOI || raw.doi || '',
        url: raw.URL || raw.url || '',
    };
}

export function parseCSLItems(rawItems) {
    return (rawItems || []).map(parseCSLItem);
}

function initialsOf(given) {
    return (given || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((p) => `${p[0]}.`)
        .join(' ');
}

// Format satu nama penulis. `inverted` menentukan "Belakang, Depan" vs "Depan Belakang".
function authorName(author, style, inverted = true) {
    const family = author.family || '';
    const given = author.given || '';
    const initials = initialsOf(given);
    switch (style) {
        case 'IEEE':
            return [initials, family].filter(Boolean).join(' ');
        case 'MLA':
        case 'Chicago':
            if (!given) return family;
            return inverted ? `${family}, ${given}` : `${given} ${family}`;
        default: // APA, Harvard
            return initials ? `${family}, ${initials}` : family;
    }
}

function joinNames(names, style) {
    if (names.length === 0) return '';
    if (names.length === 1) return names[0];
    if (names.length === 2) {
        return style === 'APA' ? `${names[0]}, & ${names[1]}` : `${names[0]} and ${names[1]}`;
    }
    const rest = names.slice(0, -1);
    const last = names[names.length - 1];
    if (style === 'APA') return `${rest.join(', ')}, & ${last}`;
    if (style === 'MLA' || style === 'Chicago' || style === 'IEEE') return `${rest.join(', ')}, and ${last}`;
    return `${rest.join(', ')} and ${last}`; // Harvard
}

function bibliographyAuthors(item, style) {
    if (!item.authors.length) return 'Anonim';
    // MLA & Chicago: penulis pertama dibalik, penulis berikutnya "Nama Depan Nama Belakang".
    if (style === 'MLA' || style === 'Chicago') {
        const names = item.authors.map((a, i) => authorName(a, style, i === 0));
        if (names.length === 1) return names[0];
        if (names.length === 2) return `${names[0]}, and ${names[1]}`;
        return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1]}`;
    }
    return joinNames(item.authors.map((a) => authorName(a, style, true)), style);
}

function citationAuthors(item, style) {
    if (!item.authors.length) return 'Anonim';
    const first = item.authors[0].family || '';
    if (item.authors.length === 1) return first;
    if (item.authors.length === 2) {
        const second = item.authors[1].family || '';
        return style === 'APA' ? `${first} & ${second}` : `${first} and ${second}`;
    }
    return `${first} et al.`;
}

function firstPage(page) {
    if (!page) return '';
    return page.split(/[-–—]/)[0].trim();
}

function em(text) {
    if (!text) return '';
    return `<i>${esc(text)}</i>`;
}

// Escape karakter HTML agar data referensi (judul/penulis/dll.) tidak bisa
// menyisipkan tag/script saat dirender lewat v-html / insertHTML.
function esc(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

// ============================================================================
// [2] SORTER
// ============================================================================

export function sortCSLItems(items, style) {
    const sorted = items.slice();
    if (style === 'IEEE') return sorted; // nomor urut (1, 2, 3)
    sorted.sort((a, b) => {
        const fa = (a.authors[0]?.family || '').toLowerCase();
        const fb = (b.authors[0]?.family || '').toLowerCase();
        if (fa !== fb) return fa < fb ? -1 : 1;
        return (a.year || '').localeCompare(b.year || '');
    });
    return sorted;
}

// ============================================================================
// [3] COMPILER
// ============================================================================

export function formatCitation(item, style, index = 1) {
    switch (style) {
        case 'IEEE':
            return `[${index}]`;
        case 'MLA':
            return `(${citationAuthors(item, style)}${item.page ? ` ${firstPage(item.page)}` : ''})`;
        case 'Chicago':
            return chicagoFootnote(item);
        case 'Harvard':
            return `(${citationAuthors(item, style)}, ${item.year})`;
        case 'APA':
        default:
            return `(${citationAuthors(item, style)}, ${item.year})`;
    }
}

function chicagoFootnote(item) {
    const authors = esc(joinNames(item.authors.map((a) => authorName(a, 'Chicago', false)), 'Chicago'));
    let s = authors || 'Anonim';
    if (item.title) s += `, "${esc(item.title)},"`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume) s += ` ${esc(item.volume)}`;
    if (item.issue) s += `, no. ${esc(item.issue)}`;
    if (item.year) s += ` (${esc(item.year)})`;
    if (item.page) s += `: ${esc(firstPage(item.page))}`;
    return `${s}.`;
}

export function formatBibliography(item, style, index = 1) {
    switch (style) {
        case 'IEEE':
            return ieeeBibliography(item, index);
        case 'MLA':
            return mlaBibliography(item);
        case 'Chicago':
            return chicagoBibliography(item);
        case 'Harvard':
            return harvardBibliography(item);
        case 'APA':
        default:
            return apaBibliography(item);
    }
}

function ieeeBibliography(item, index) {
    const authors = esc(bibliographyAuthors(item, 'IEEE'));
    let s = `[${index}] ${authors}`;
    if (item.title) s += `, "${esc(item.title)},"`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume) s += `, vol. ${esc(item.volume)}`;
    if (item.issue) s += `, no. ${esc(item.issue)}`;
    if (item.page) s += `, pp. ${esc(item.page)}`;
    if (item.year) s += `, ${esc(item.year)}`;
    return `${s}.`;
}

function apaBibliography(item) {
    const authors = bibliographyAuthors(item, 'APA');
    let s = `${authors} (${item.year}).`;
    if (item.title) s += ` ${item.title}.`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume) s += `, ${em(item.volume)}`;
    if (item.issue) s += `(${item.issue})`;
    if (item.page) s += `, ${item.page}`;
    return `${s}.`;
}

function mlaBibliography(item) {
    const authors = bibliographyAuthors(item, 'MLA');
    let s = `${authors}.`;
    if (item.title) s += ` "${item.title}."`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume) s += `, vol. ${item.volume}`;
    if (item.issue) s += `, no. ${item.issue}`;
    if (item.year) s += `, ${item.year}`;
    if (item.page) s += `, pp. ${item.page}`;
    return `${s}.`;
}

function harvardBibliography(item) {
    const authors = bibliographyAuthors(item, 'Harvard');
    let s = `${authors}, ${item.year}.`;
    if (item.title) s += ` ${item.title}.`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume || item.issue) s += `, ${item.volume || ''}${item.issue ? `(${item.issue})` : ''}`;
    if (item.page) s += `, pp.${item.page}`;
    return `${s}.`;
}

function chicagoBibliography(item) {
    const authors = esc(bibliographyAuthors(item, 'Chicago'));
    let s = `${authors}.`;
    if (item.title) s += ` "${esc(item.title)}."`;
    if (item.containerTitle) s += ` ${em(item.containerTitle)}`;
    if (item.volume) s += ` ${esc(item.volume)}`;
    if (item.issue) s += `, no. ${esc(item.issue)}`;
    if (item.year) s += ` (${esc(item.year)})`;
    if (item.page) s += `: ${esc(item.page)}`;
    return `${s}.`;
}

// ============================================================================
// PIPELINE UTAMA
// ============================================================================

export function cslFormatter(rawItems, style, { mode = 'citation' } = {}) {
    const parsed = parseCSLItems(rawItems);        // [1] Parser/Formatter
    const sorted = sortCSLItems(parsed, style);     // [2] Sorter
    return sorted.map((item, i) =>                   // [3] Compiler
        mode === 'bibliography' ? formatBibliography(item, style, i + 1) : formatCitation(item, style, i + 1),
    );
}

// Label pendek untuk tampilan UI, mis. "Sugiyono (2019)".
export function authorYearLabel(item) {
    const family = item.author?.[0]?.family || '';
    const year = yearOf(item);
    return year ? `${family} (${year})` : family;
}
