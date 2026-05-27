import { error } from '@sveltejs/kit';
import { isAdmin } from '$lib/types';
import type { LayoutServerLoad } from './$types';

/** RBAC: the entire /admin area is restricted to ROLE_ADMIN. */
export const load: LayoutServerLoad = ({ locals }) => {
	if (!isAdmin(locals.user)) {
		throw error(403, 'Administrator access required.');
	}
	return {};
};
