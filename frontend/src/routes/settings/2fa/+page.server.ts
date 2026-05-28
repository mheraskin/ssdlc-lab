import { fail } from '@sveltejs/kit';
import type { Actions, PageServerLoad } from './$types';
import { backendFetch, SESSION_COOKIE } from '$lib/server/backend';

interface TotpSetupResponse {
	secret: string;
	provisioningUri: string;
	enabled: boolean;
}

interface TotpToggleResponse {
	enabled?: boolean;
	error?: string;
}

export const load: PageServerLoad = ({ locals }) => ({ user: locals.user });

export const actions: Actions = {
	// Begin enrollment: ask the backend for a fresh secret + otpauth:// URI.
	setup: async ({ cookies }) => {
		const token = cookies.get(SESSION_COOKIE);
		const res = await backendFetch(token, '/api/totp/setup', { method: 'POST' });
		if (!res.ok) {
			return fail(res.status, { kind: 'error' as const, error: 'Не вдалося розпочати налаштування TOTP.' });
		}
		const data = (await res.json()) as TotpSetupResponse;
		return { kind: 'setup' as const, secret: data.secret, provisioningUri: data.provisioningUri };
	},

	// Confirm enrollment with the first code from the user's authenticator app.
	enable: async ({ request, cookies }) => {
		const token = cookies.get(SESSION_COOKIE);
		const fd = await request.formData();
		const code = String(fd.get('code') ?? '').trim();
		const secret = String(fd.get('secret') ?? '');
		const provisioningUri = String(fd.get('provisioningUri') ?? '');

		const res = await backendFetch(token, '/api/totp/enable', {
			method: 'POST',
			body: JSON.stringify({ code })
		});
		const body = (await res.json().catch(() => ({}))) as TotpToggleResponse;

		if (!res.ok || body.enabled !== true) {
			// Keep the same secret + URI on screen so the user can retry without re-scanning.
			return fail(res.status || 422, {
				kind: 'setup' as const,
				secret,
				provisioningUri,
				error: body.error ?? 'Невірний код.'
			});
		}
		return { kind: 'enabled' as const };
	},

	// Disable — backend requires a fresh code from the authenticator (re-auth via the factor).
	disable: async ({ request, cookies }) => {
		const token = cookies.get(SESSION_COOKIE);
		const fd = await request.formData();
		const code = String(fd.get('code') ?? '').trim();

		const res = await backendFetch(token, '/api/totp/disable', {
			method: 'POST',
			body: JSON.stringify({ code })
		});
		const body = (await res.json().catch(() => ({}))) as TotpToggleResponse;

		if (!res.ok || body.enabled !== false) {
			return fail(res.status || 422, {
				kind: 'disable-error' as const,
				error: body.error ?? 'Не вдалося вимкнути.'
			});
		}
		return { kind: 'disabled' as const };
	}
};
