// Renderer Markdown ringan untuk balasan AI.
// Aman terhadap XSS: seluruh teks di-escape dulu sebelum diubah menjadi tag.

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function renderMarkdown(text) {
    if (!text) return '';
    let html = escapeHtml(String(text));

    // Blok kode ``` ... ```
    html = html.replace(/```([\s\S]*?)```/g, (_, code) => {
        const clean = code.replace(/^\n/, '');
        return `<pre><code>${clean}</code></pre>`;
    });

    // Bold **...**
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    // Italic *...* (yang tersisa setelah bold)
    html = html.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

    // Inline code `...`
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Tautan markdown [label](https://...) — hanya http/https, dibuka di tab baru.
    // Lookbehind (?<![!]) agar sintaks gambar ![...](...) tidak ikut termakan.
    html = html.replace(/(?<![!])\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, (_, label, url) => {
        const safeUrl = String(url).replace(/"/g, '&quot;');
        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="ai-link">${label}</a>`;
    });

    // Heading ### / ## / #
    html = html.replace(/^#{1,3}\s+(.*)$/gm, '<strong>$1</strong>');

    // Bullet list - atau *
    html = html.replace(/^[-*]\s+(.*)$/gm, '• $1');

    return html;
}
