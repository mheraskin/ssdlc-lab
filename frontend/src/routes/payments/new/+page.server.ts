import { fail } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';
import { backendFetch, backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { Account, Transaction } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const data = await backendJson<{ accounts: Account[] }>(token, '/api/accounts');
	return { accounts: data.accounts };
};

interface PaymentApiResponse {
	status?: string;
	message?: string;
	error?: string;
	transaction?: Transaction;
	fields?: Record<string, string>;
}

export const actions: Actions = {
	// Step 1: create the payment. May return a completed payment, or an MFA challenge.
	create: async ({ request, cookies }) => {
		const token = cookies.get(SESSION_COOKIE);
		const fd = await request.formData();
		const sourceAccountId = Number(fd.get('sourceAccountId'));
		const recipientName = String(fd.get('recipientName') ?? '');
		const recipientAccount = String(fd.get('recipientAccount') ?? '');
		const amount = Number(fd.get('amount'));

		const res = await backendFetch(token, '/api/payments', {
			method: 'POST',
			body: JSON.stringify({ sourceAccountId, recipientName, recipientAccount, amount })
		});
		const body = (await res.json().catch(() => ({}))) as PaymentApiResponse;

		if (body.status === 'mfa_required' && body.transaction) {
			return {
				kind: 'mfa' as const,
				transactionId: body.transaction.id,
				recipientName: body.transaction.recipientName,
				amount: body.transaction.amount,
				currency: body.transaction.currency,
				message: body.message ?? 'A confirmation code has been emailed to you.'
			};
		}
		if (body.status === 'completed' && body.transaction) {
			return { kind: 'done' as const, message: body.message ?? 'Payment completed.', transaction: body.transaction };
		}

		return fail(res.status || 422, {
			kind: 'error' as const,
			error: body.error ?? body.message ?? 'Payment failed.',
			fields: body.fields ?? null
		});
	},

	// Step 2: confirm a risky payment with the emailed MFA code.
	confirm: async ({ request, cookies }) => {
		const token = cookies.get(SESSION_COOKIE);
		const fd = await request.formData();
		const transactionId = Number(fd.get('transactionId'));
		const code = String(fd.get('code') ?? '');

		const res = await backendFetch(token, `/api/payments/${transactionId}/confirm`, {
			method: 'POST',
			body: JSON.stringify({ code })
		});
		const body = (await res.json().catch(() => ({}))) as PaymentApiResponse;

		if (body.status === 'completed' && body.transaction) {
			return { kind: 'done' as const, message: body.message ?? 'Payment confirmed.', transaction: body.transaction };
		}

		return fail(res.status || 422, {
			kind: 'mfa' as const,
			transactionId,
			error: body.message ?? body.error ?? 'Confirmation failed.'
		});
	}
};
