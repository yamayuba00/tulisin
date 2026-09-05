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

    // Heading ### / ## / #
    html = html.replace(/^#{1,3}\s+(.*)$/gm, '<strong>$1</strong>');

    // Bullet list - atau *
    html = html.replace(/^[-*]\s+(.*)$/gm, '• $1');

    return html;
}
