import type { PageServerLoad } from './$types';
import { backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { Account } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const data = await backendJson<{ accounts: Account[] }>(token, '/api/accounts');
	return { accounts: data.accounts };
};
