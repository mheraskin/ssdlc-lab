import { sentrySvelteKit } from '@sentry/sveltekit';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
	// sentrySvelteKit() must come before sveltekit(). It enables source-map handling and,
	// when SENTRY_AUTH_TOKEN / org / project are set, uploads source maps on build.
	// Without those it is a no-op, so the build works out of the box.
	plugins: [sentrySvelteKit(), sveltekit()],
	server: {
		// Allow the dev server to be reached via the Docker service name (used by the ZAP
		// DAST scan and when proxied). Dev-only — Vite's anti-DNS-rebinding guard; the
		// production (adapter-node/Cloudflare) server is unaffected.
		allowedHosts: ['frontend', 'localhost', '127.0.0.1']
	}
});
