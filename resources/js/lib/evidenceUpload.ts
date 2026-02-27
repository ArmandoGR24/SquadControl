export const MAX_EVIDENCE_SIZE_BYTES = 20 * 1024 * 1024;

export const ALLOWED_EVIDENCE_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'video/mp4',
    'video/quicktime',
    'video/x-m4v',
] as const;

const MAX_IMAGE_DIMENSION = 1920;
const IMAGE_QUALITY = 0.82;

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export const validateEvidenceFile = (file: File): string | null => {
    if (!ALLOWED_EVIDENCE_MIME_TYPES.includes(file.type as (typeof ALLOWED_EVIDENCE_MIME_TYPES)[number])) {
        return 'Tipo de archivo no permitido. Usa imagen (jpg/png/webp/gif) o video (mp4/mov).';
    }

    if (file.size > MAX_EVIDENCE_SIZE_BYTES) {
        return `El archivo excede ${formatBytes(MAX_EVIDENCE_SIZE_BYTES)}.`;
    }

    return null;
};

const loadImage = (file: File): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No se pudo leer la imagen.'));
        };

        image.src = url;
    });

const canvasToBlob = (canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob | null> =>
    new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), type, quality);
    });

export const optimizeEvidenceFile = async (file: File): Promise<File> => {
    const isImage = file.type.startsWith('image/');
    const isGif = file.type === 'image/gif';

    if (!isImage || isGif) {
        return file;
    }

    try {
        const image = await loadImage(file);
        const ratio = Math.min(1, MAX_IMAGE_DIMENSION / Math.max(image.width, image.height));

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * ratio));
        canvas.height = Math.max(1, Math.round(image.height * ratio));

        const context = canvas.getContext('2d');
        if (!context) {
            return file;
        }

        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const optimizedBlob = await canvasToBlob(canvas, 'image/webp', IMAGE_QUALITY);
        if (!optimizedBlob) {
            return file;
        }

        if (optimizedBlob.size >= file.size) {
            return file;
        }

        const optimizedName = file.name.replace(/\.[^.]+$/, '') + '.webp';
        return new File([optimizedBlob], optimizedName, { type: 'image/webp' });
    } catch {
        return file;
    }
};
