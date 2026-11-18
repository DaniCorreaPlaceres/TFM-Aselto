export const acceptedTypes = 'application/pdf,image/jpeg,image/png,image/webp';

export function uploadError(files) {
    if (!files || files.length === 0) return 'Elige un archivo o haz una foto antes de continuar.';
    if (files.length !== 1) return 'Añade un solo documento cada vez.';
    const file = files[0];
    if (file.size === 0) return 'El archivo está vacío. Elige otro archivo.';
    if (file.size > 8_000_000) return 'El archivo supera los 8 MB. Elige uno más pequeño.';
    const type = (file.type || '').toLowerCase();
    if (!acceptedTypes.split(',').includes(type) && !(type === '' && /\.(pdf|jpe?g|png|webp)$/i.test(file.name))) return 'Este formato no está admitido. Usa PDF, JPEG, PNG o WebP.';
    return null;
}

export function uploadSize(bytes) {
    return `${new Intl.NumberFormat('es-ES', { maximumFractionDigits: 1 }).format(bytes / (bytes >= 1_000_000 ? 1_000_000 : 1000))} ${bytes >= 1_000_000 ? 'MB' : 'KB'}`;
}
