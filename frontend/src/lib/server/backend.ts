import { env } from '$env/dynamic/private';

/**
 * Server-only helper for talking to the Symfony API.
 *
 * This is the heart of the BFF pattern: the JWT lives in an httpOnly cookie that the
 * browser cannot read. Only this server-side code attaches it as a Bearer token when
 * calling the backend. The backend address is internal (Docker network / private VPC)
 * and is never exposed to the browser.
 */
export const SESSION_COOKIE = 'session';

function apiBase(): string {
	return env.API_INTERNAL_URL ?? 'http://localhost:8000';
}

export async function backendFetch(
	token: string | undefined,
	path: string,
	init: RequestInit = {}
): Promise<Response> {
	const headers = new Headers(init.headers);
	headers.set('Accept', 'application/json');
	if (token) {
		headers.set('Authorization', `Bearer ${token}`);
	}
	if (init.body && !headers.has('Content-Type')) {
		headers.set('Content-Type', 'application/json');
	}
	return fetch(`${apiBase()}${path}`, { ...init, headers });
}

/** Convenience: fetch + parse JSON, returning a typed payload or throwing on non-2xx. */
export async function backendJson<T>(
	token: string | undefined,
	path: string,
	init: RequestInit = {}
): Promise<T> {
	const res = await backendFetch(token, path, init);
	if (!res.ok) {
		throw new Error(`Backend ${path} responded ${res.status}`);
	}
	return (await res.json()) as T;
}
