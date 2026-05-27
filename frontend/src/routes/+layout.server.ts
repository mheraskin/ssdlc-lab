import { redirect } from '@sveltejs/kit';
import type { LayoutServerLoad } from './$types';

/** Public routes that do not require authentication. */
const PUBLIC_PATHS = new Set(['/login']);

/**
 * Central auth guard for the whole app. Unauthenticated users are sent to /login;
 * authenticated users are kept away from /login. The resolved user (from the BFF) is
 * shared with every page.
 */
export const load: LayoutServerLoad = ({ locals, url }) => {
	const isPublic = PUBLIC_PATHS.has(url.pathname);

	if (!locals.user && !isPublic) {
		throw redirect(303, '/login');
	}
	if (locals.user && url.pathname === '/login') {
		throw redirect(303, '/dashboard');
	}

	return { user: locals.user };
};
