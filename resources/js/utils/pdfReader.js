// pdfReader.js — Parser PDF minimal (tanpa library eksternal).
// Menyediakan konsep "membaca struktur PDF" untuk Tulisin Workspace:
//   - metadata dari Info dictionary (/Title, /Author, /CreationDate)
//   - teks kasar dari content stream (FlateDecode, pakai DecompressionStream native browser)
//
// Catatan: ini parser minimal, bukan pengganti pdf.js. Cukup untuk mengekstrak
// judul/penulis/tahun + cuplikan teks dari kebanyakan PDF berbasis teks
// (hasil LaTeX/Word). PDF hasil scan tanpa text layer akan menghasilkan teks kosong.

const latin1 = (bytes) => new TextDecoder('latin1').decode(bytes);

function bytesToU8(str) {
    const out = new Uint8Array(str.length);
    for (let i = 0; i < str.length; i++) out[i] = str.charCodeAt(i) & 0xff;
    return out;
}

// Decode string PDF (bytes mentah) → teks. Mendukung BOM UTF-16 dan latin1.
function decodePdfBytes(bytes) {
    if (!bytes || !bytes.length) return '';
    if (bytes[0] === 0xfe && bytes[1] === 0xff) return new TextDecoder('utf-16be').decode(bytes.slice(2));
    if (bytes[0] === 0xff && bytes[1] === 0xfe) return new TextDecoder('utf-16le').decode(bytes.slice(2));
    return latin1(bytes);
}

// Decode string PDF literal dari representasi latin1 (sudah menangani escape).
function unescapePdf(str) {
    let out = '';
    let i = 0;
    while (i < str.length) {
        const c = str[i];
        if (c === '\\' && i + 1 < str.length) {
            const n = str[i + 1];
            if (n === 'n') { out += '\n'; i += 2; continue; }
            if (n === 'r') { out += '\r'; i += 2; continue; }
            if (n === 't') { out += '\t'; i += 2; continue; }
            if (n === '(' || n === ')' || n === '\\') { out += n; i += 2; continue; }
            const oct = str.slice(i + 1, i + 4);
            if (/^[0-7]{3}$/.test(oct)) {
                out += String.fromCharCode(parseInt(oct, 8));
                i += 4;
                continue;
            }
            out += n;
            i += 2;
            continue;
        }
        out += c;
        i++;
    }
    return decodePdfBytes(bytesToU8(out));
}

// Ambil nilai string PDF tepat setelah penanda (mis. "/Title"). Mendukung
// string literal (...) dan hex <...>. `s` adalah representasi latin1 (byte==char).
function extractAfterMarker(s, bytes, marker) {
    let idx = s.indexOf(marker);
    if (idx === -1) return null;
    let i = idx + marker.length;
    while (i < s.length && s[i] !== '(' && s[i] !== '<') i++;
    if (i >= s.length) return null;

    if (s[i] === '<') {
        const end = s.indexOf('>', i);
        if (end === -1) return null;
        const hex = s.slice(i + 1, end).replace(/[^0-9a-fA-F]/g, '');
        if (!hex || hex.length % 2) return null;
        const out = new Uint8Array(hex.length / 2);
        for (let k = 0; k < out.length; k++) out[k] = parseInt(hex.slice(k * 2, k * 2 + 2), 16);
        return decodePdfBytes(out);
    }

    // literal string
    let j = i + 1;
    let raw = '';
    let depth = 1;
    while (j < s.length && depth > 0) {
        const c = s[j];
        if (c === '\\') {
            raw += c + (s[j + 1] || '');
            j += 2;
            continue;
        }
        if (c === '(') { depth++; raw += c; j++; continue; }
        if (c === ')') { depth--; if (depth === 0) break; raw += c; j++; continue; }
        raw += c;
        j++;
    }
    return unescapePdf(raw);
}

function extractInfo(bytes) {
    const s = latin1(bytes);
    const title = extractAfterMarker(s, bytes, '/Title');
    const author = extractAfterMarker(s, bytes, '/Author');
    const rawDate = extractAfterMarker(s, bytes, '/CreationDate');
    let year = '';
    if (rawDate) {
        const m = rawDate.match(/(?:D:)?(\d{4})/);
        if (m) year = m[1];
    }
    return { title: title || '', author: author || '', year };
}

// Dekompresi stream FlateDecode menggunakan DecompressionStream (native).
async function inflate(bytes) {
    for (const format of ['deflate', 'deflate-raw']) {
        try {
            const ds = new DecompressionStream(format);
            const stream = new Blob([bytes]).stream().pipeThrough(ds);
            const buf = await new Response(stream).arrayBuffer();
            return new Uint8Array(buf);
        } catch {
            // coba format berikutnya
        }
    }
    throw new Error('Gagal dekompresi stream');
}

// Ekstrak teks dari content stream dengan hanya mengambil string yang menjadi
// bagian dari operator penampil teks (Tj, TJ, ', "). Ini mencegah sampah biner
// dari stream font/gambar/metadata ikut terbaca.
function extractTextFromContent(bytes) {
    const s = latin1(bytes);
    const parts = [];

    // (teks) Tj  |  (teks) '  |  (teks) "
    const showRe = /\(((?:\\.|[^()\\])*)\)\s*(?:Tj|'|")/g;
    let m;
    while ((m = showRe.exec(s))) {
        const t = unescapePdf(m[1]);
        if (t && t.trim()) parts.push(t.trim());
    }

    // [ (a) (b) ... ] TJ
    const arrayRe = /\[((?:\\.|[^\]\\])*)\]\s*TJ/g;
    while ((m = arrayRe.exec(s))) {
        const inner = m[1];
        const strRe = /\(((?:\\.|[^()\\])*)\)/g;
        let sm;
        while ((sm = strRe.exec(inner))) {
            const t = unescapePdf(sm[1]);
            if (t && t.trim()) parts.push(t.trim());
        }
    }

    return parts.join(' ');
}

async function extractText(bytes) {
    const s = latin1(bytes);
    const streamRe = /stream\r?\n/g;
    const buffers = [];
    let m;
    while ((m = streamRe.exec(s))) {
        const start = m.index + m[0].length;
        const end = s.indexOf('endstream', start);
        if (end === -1) break;
        const raw = bytes.slice(start, end);
        // Hanya coba dekompresi stream yang tampaknya FlateDecode (lihat dict di belakang).
        const head = s.slice(Math.max(0, m.index - 200), m.index);
        let data = raw;
        if (/\/FlateDecode/.test(head)) {
            try {
                data = await inflate(raw);
            } catch {
                data = raw;
            }
        }
        const txt = extractTextFromContent(data);
        if (txt) buffers.push(txt);
    }
    return buffers.join('\n');
}

function guessTitle(text) {
    const lines = text.split('\n').map((l) => l.trim()).filter((l) => l.length >= 8);
    return lines[0] || '';
}

function guessYear(text) {
    const m = text.match(/\b(19|20)\d{2}\b/);
    return m ? m[0] : '';
}

function guessDoi(text) {
    const m = text.match(/\b10\.\d{4,9}\/[-._;()/:A-Za-z0-9]+\b/);
    return m ? m[0] : '';
}

function estimatePages(bytes) {
    const s = latin1(bytes);
    const matches = s.match(/\/Type\s*\/Page[^s]/g);
    return matches ? matches.length : 1;
}

// Entry utama: baca file PDF → metadata + teks + perkiraan.
export async function readPdf(file) {
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    const info = extractInfo(bytes);
    const text = await extractText(bytes);

    const title = info.title || guessTitle(text);
    const author = info.author || '';
    const year = info.year || guessYear(text);
    const doi = guessDoi(text);

    return {
        title,
        author,
        year,
        doi,
        pages: estimatePages(bytes),
        snippet: text.slice(0, 600),
        text, // teks mentah lengkap (dipakai untuk parsing AI)
    };
}
