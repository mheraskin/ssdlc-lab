import adapter from '@sveltejs/adapter-node';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	compilerOptions: {
		// Force runes mode for the project, except for libraries. Can be removed in svelte 6.
		runes: ({ filename }) => (filename.split(/[/\\]/).includes('node_modules') ? undefined : true)
	},
	kit: {
		// Local container runs on Node (adapter-node). For Cloudflare Pages, swap this for
		// `@sveltejs/adapter-cloudflare` — the BFF server endpoints run as Pages Functions.
		adapter: adapter(),

		// Content-Security-Policy — defense-in-depth against XSS. SvelteKit injects a
		// per-request nonce on its own scripts (mode "auto"), so `script-src` needs no
		// 'unsafe-inline'. Styles allow 'unsafe-inline' (Svelte/inline style attributes);
		// 'ws:'/'wss:' keep the Vite HMR socket working in local dev.
		csp: {
			mode: 'auto',
			directives: {
				'default-src': ['self'],
				'script-src': ['self'],
				'style-src': ['self', 'unsafe-inline'],
				'img-src': ['self', 'data:'],
				'font-src': ['self'],
				'connect-src': ['self', 'ws:', 'wss:'],
				'form-action': ['self'],
				'frame-ancestors': ['none'],
				'base-uri': ['self'],
				'object-src': ['none']
			}
		}
	}
};

export default config;
