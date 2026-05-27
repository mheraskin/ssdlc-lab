import { redirect } from '@sveltejs/kit';
import type { RequestHandler } from './$types';
import { backendFetch, SESSION_COOKIE } from '$lib/server/backend';

/** Clears the session cookie and records the logout event in the backend audit log. */
export const POST: RequestHandler = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	if (token) {
		try {
			await backendFetch(token, '/api/logout', { method: 'POST' });
		} catch {
			// Best effort — clearing the cookie below is what actually logs the user out.
		}
	}
	cookies.delete(SESSION_COOKIE, { path: '/' });
	throw redirect(303, '/login');
};
