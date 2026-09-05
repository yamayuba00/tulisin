// Pewarnaan sintaks sederhana (tanpa library eksternal) untuk blok kode.
// Menghasilkan HTML aman (teks asli di-escape) dengan <span> berkelas untuk
// keyword, string, komentar, dan angka. Pewarnaan umum, tidak terikat bahasa.

const KEYWORDS = new Set([
    'const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while',
    'do', 'switch', 'case', 'break', 'continue', 'new', 'class', 'extends',
    'super', 'this', 'import', 'export', 'from', 'default', 'try', 'catch',
    'finally', 'throw', 'async', 'await', 'typeof', 'instanceof', 'in', 'of',
    'delete', 'void', 'yield', 'static', 'get', 'set', 'null', 'true', 'false',
    'undefined', 'NaN', 'def', 'elif', 'lambda', 'print', 'pass', 'raise',
    'global', 'nonlocal', 'and', 'or', 'not', 'is', 'None', 'True', 'False',
    'self', 'public', 'private', 'protected', 'int', 'float', 'double',
    'char', 'boolean', 'string', 'namespace', 'using', 'include', 'define',
]);

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Token: komentar (//, /* */, #), string (' " `), angka, identifier.
const TOKEN_RE = /(\/\/[^\n]*|\/\*[\s\S]*?\*\/|#[^\n]*|"(?:[^"\\\n]|\\.)*"|'(?:[^'\\\n]|\\.)*'|`(?:[^`\\]|\\.)*`|\b\d[\d_]*(?:\.\d+)?(?:[eE][+-]?\d+)?\b|[A-Za-z_$][A-Za-z0-9_$]*)/g;

function tokenHtml(tok) {
    const c = tok[0];
    if (c === '/' || c === '#') return `<span class="tok-comment">${tok}</span>`;
    if (c === '"' || c === "'" || c === '`') return `<span class="tok-string">${tok}</span>`;
    if (c >= '0' && c <= '9') return `<span class="tok-number">${tok}</span>`;
    if (KEYWORDS.has(tok)) return `<span class="tok-keyword">${tok}</span>`;
    return tok;
}

export function highlightCode(code) {
    const src = escapeHtml(code);
    let out = '';
    let last = 0;
    TOKEN_RE.lastIndex = 0;
    let m;
    while ((m = TOKEN_RE.exec(src))) {
        out += src.slice(last, m.index);
        out += tokenHtml(m[0]);
        last = m.index + m[0].length;
    }
    out += src.slice(last);
    return out;
}
