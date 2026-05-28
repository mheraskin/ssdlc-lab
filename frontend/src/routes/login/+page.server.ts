import { dev } from '$app/environment';
import { fail, redirect } from '@sveltejs/kit';
import { backendFetch, SESSION_COOKIE } from '$lib/server/backend';
import type { Actions } from './$types';

export const actions: Actions = {
	default: async ({ request, cookies }) => {
		const form = await request.formData();
		const email = String(form.get('email') ?? '').trim();
		const password = String(form.get('password') ?? '');

		if (!email || !password) {
			return fail(400, { error: 'Потрібні електронна пошта та пароль.', email });
		}

		let res: Response;
		try {
			res = await backendFetch(undefined, '/api/login', {
				method: 'POST',
				body: JSON.stringify({ email, password })
			});
		} catch {
			return fail(503, { error: 'Серверна частина недоступна. Чи запущено API?', email });
		}

		const data = (await res.json().catch(() => ({}))) as { token?: string; error?: string };

		if (!res.ok || !data.token) {
			return fail(res.status === 429 ? 429 : 401, {
				error: data.error ?? 'Не вдалося увійти.',
				email
			});
		}

		// Store the JWT in an httpOnly cookie. The browser never sees the token (BFF pattern).
		cookies.set(SESSION_COOKIE, data.token, {
			path: '/',
			httpOnly: true,
			sameSite: 'lax',
			secure: !dev,
			maxAge: 60 * 60
		});

		throw redirect(303, '/dashboard');
	}
};
