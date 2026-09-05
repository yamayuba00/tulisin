// Gabungkan class secara kondisional (mirip clsx sederhana).
export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}

// Format angka menjadi mata uang (default Rupiah Indonesia).
export function formatCurrency(value, { currency = 'IDR', locale = 'id-ID' } = {}) {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
    }).format(value);
}

// Format tanggal/waktu (default Indonesia).
export function formatDate(value, { withTime = false } = {}) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const options = withTime
        ? { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: 'numeric', month: 'short', year: 'numeric' };
    return new Intl.DateTimeFormat('id-ID', options).format(date);
}
