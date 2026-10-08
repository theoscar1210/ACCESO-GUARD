/**
 * Reduce una foto tomada con el celular (3-5 MB) a un JPEG de ~150-300 KB
 * antes de subirla, para que el registro sea rápido aun con mala conexión.
 */
export async function compressImage(
    file: File,
    { maxSize = 1280, quality = 0.75 } = {},
): Promise<File> {
    if (!file.type.startsWith('image/')) return file;

    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) return file;

    const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );

    if (!blob || blob.size >= file.size) return file;

    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', {
        type: 'image/jpeg',
    });
}
