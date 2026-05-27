import type { PageServerLoad } from './$types';
import { backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { Account, Transaction } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const [accounts, transactions] = await Promise.all([
		backendJson<{ accounts: Account[] }>(token, '/api/accounts'),
		backendJson<{ transactions: Transaction[] }>(token, '/api/transactions')
	]);
	return { accounts: accounts.accounts, transactions: transactions.transactions.slice(0, 5) };
};
