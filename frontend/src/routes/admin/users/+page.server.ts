import type { PageServerLoad } from './$types';
import { backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { User } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const data = await backendJson<{ users: User[] }>(token, '/api/admin/users');
	return { users: data.users };
};
