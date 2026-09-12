// Nama brand aplikasi — diambil dari env APP_NAME (di-inject lewat window.__APP_NAME__
// di resources/views/app.blade.php). Fallback ke "Tulissin" bila belum tersedia.
export const appName = (typeof window !== 'undefined' && window.__APP_NAME__) || 'Tulissin';

export default appName;
