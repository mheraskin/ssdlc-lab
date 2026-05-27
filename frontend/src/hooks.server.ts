import * as Sentry from '@sentry/sveltekit';
import { sequence } from '@sveltejs/kit/hooks';
import type { Handle } from '@sveltejs/kit';
import { env } from '$env/dynamic/public';
import { backendFetch, SESSION_COOKIE } from '$lib/server/backend';
import type { User } from '$lib/types';

// Server-side Sentry. No-op until PUBLIC_SENTRY_DSN is set (SSDLC: maintenance/monitoring).
Sentry.init({
	dsn: env.PUBLIC_SENTRY_DSN,
	environment: env.PUBLIC_SENTRY_ENVIRONMENT ?? 'development',
	tracesSampleRate: 0.2,
	// Privacy: do not attach request bodies / headers by default (banking app).
	sendDefaultPii: false
});

/**
 * Resolves the current user from the session cookie (by asking the backend) and applies
 * baseline security response headers. The backend call keeps the token server-side; the
 * browser only ever receives the resulting user object.
 */
const appHandle: Handle = async ({ event, resolve }) => {
	event.locals.user = null;

	const token = event.cookies.get(SESSION_COOKIE);
	if (token) {
		try {
			const res = await backendFetch(token, '/api/me');
			if (res.ok) {
				const data = (await res.json()) as { user: User };
				event.locals.user = data.user;
			} else if (res.status === 401) {
				// Expired/invalid token: drop the cookie so the user is sent to login.
				event.cookies.delete(SESSION_COOKIE, { path: '/' });
			}
		} catch {
			// Backend unreachable — treat as unauthenticated rather than crashing.
		}
	}

	const response = await resolve(event);

	// Security headers (defence in depth; Cloudflare strengthens these at the edge in prod).
	response.headers.set('X-Content-Type-Options', 'nosniff');
	response.headers.set('X-Frame-Options', 'DENY');
	response.headers.set('Referrer-Policy', 'no-referrer');
	response.headers.set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

	return response;
};

// Sentry's request handler runs first (tracing + error capture), then our app logic.
export const handle = sequence(Sentry.sentryHandle(), appHandle);

// Report uncaught server errors to Sentry (and still log them).
export const handleError = Sentry.handleErrorWithSentry();
