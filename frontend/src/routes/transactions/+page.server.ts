import type { PageServerLoad } from './$types';
import { backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { Transaction } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const data = await backendJson<{ transactions: Transaction[] }>(token, '/api/transactions');
	return { transactions: data.transactions };
};
