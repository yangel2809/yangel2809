export class ApiError extends Error {}

/** fetch JSON con CSRF. Una sesión expirada (419) recarga la página. */
export async function api(url, method, body) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });

    if (res.status === 419 || res.status === 401) {
        window.location.reload();
        throw new ApiError('Sesión expirada');
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new ApiError(data.message || `Error ${res.status}. Revisa tu conexión.`);
    }

    return data;
}
