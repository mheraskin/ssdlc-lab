import type { RequestHandler } from './$types';
import { error } from '@sveltejs/kit';
import { backendFetch, SESSION_COOKIE } from '$lib/server/backend';

/**
 * BFF / API-gateway proxy. The browser calls same-origin /api/* here; this server
 * endpoint attaches the JWT from the httpOnly cookie and forwards to the Symfony API.
 *
 * Token-issuing endpoints are deliberately NOT proxied — the raw token must never be
 * returned to the browser (it is set as an httpOnly cookie by the /login action instead).
 */
const BLOCKED_PREFIXES = ['login'];

const handler: RequestHandler = async ({ params, request, cookies, url }) => {
	const path = params.path ?? '';
	if (BLOCKED_PREFIXES.includes(path.split('/')[0])) {
		throw error(404, 'Not found');
	}

	const token = cookies.get(SESSION_COOKIE);
	const init: RequestInit = { method: request.method };
	if (!['GET', 'HEAD'].includes(request.method)) {
		init.body = await request.text();
		init.headers = { 'Content-Type': request.headers.get('Content-Type') ?? 'application/json' };
	}

	const res = await backendFetch(token, `/api/${path}${url.search}`, init);
	const body = await res.text();

	return new Response(body, {
		status: res.status,
		headers: { 'Content-Type': res.headers.get('Content-Type') ?? 'application/json' }
	});
};

export const GET = handler;
export const POST = handler;
export const PUT = handler;
export const PATCH = handler;
export const DELETE = handler;
