import * as Sentry from '@sentry/sveltekit';
import { env } from '$env/dynamic/public';

// Client-side Sentry (browser error + performance monitoring). No-op until
// PUBLIC_SENTRY_DSN is set, so the project is fully wired and only needs the key.
Sentry.init({
	dsn: env.PUBLIC_SENTRY_DSN,
	environment: env.PUBLIC_SENTRY_ENVIRONMENT ?? 'development',
	tracesSampleRate: 0.2,
	// Privacy: no Session Replay (it would record the banking UI) and no PII.
	sendDefaultPii: false
});

// Report uncaught client errors to Sentry.
export const handleError = Sentry.handleErrorWithSentry();
