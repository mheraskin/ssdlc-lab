/**
 * Optional Cloudflare Worker that fronts the API subdomain (api.bank-demo.example.com)
 * and adds explicit "API Gateway" behaviour at the edge, in front of DigitalOcean:
 *   - simple per-IP rate limiting (in addition to the backend's login limiter)
 *   - CORS allow-list validation
 *   - request filtering / method allow-list
 *
 * This complements Cloudflare's managed WAF, DNS, CDN and Anti-DDoS. It is a deployment
 * artifact — not used by the local lab — and is bound to the route in wrangler config.
 */

const ALLOWED_ORIGINS = ['https://bank-demo.example.com'];
const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
const RATE_LIMIT = 120; // requests per minute per IP
const WINDOW_SECONDS = 60;

export default {
	async fetch(request, env, ctx) {
		const origin = request.headers.get('Origin');

		// CORS preflight
		if (request.method === 'OPTIONS') {
			return new Response(null, { headers: corsHeaders(origin) });
		}

		if (!ALLOWED_METHODS.includes(request.method)) {
			return new Response('Method Not Allowed', { status: 405 });
		}

		// Per-IP rate limiting backed by a KV namespace (bind as RATE_KV in wrangler).
		const ip = request.headers.get('CF-Connecting-IP') ?? 'unknown';
		if (env.RATE_KV) {
			const key = `rl:${ip}:${Math.floor(Date.now() / 1000 / WINDOW_SECONDS)}`;
			const count = parseInt((await env.RATE_KV.get(key)) ?? '0', 10) + 1;
			ctx.waitUntil(env.RATE_KV.put(key, String(count), { expirationTtl: WINDOW_SECONDS }));
			if (count > RATE_LIMIT) {
				return new Response('Too Many Requests', { status: 429 });
			}
		}

		// Forward to the origin (DigitalOcean App Platform).
		const response = await fetch(request);
		const headers = new Headers(response.headers);
		for (const [k, v] of Object.entries(corsHeaders(origin))) headers.set(k, v);
		return new Response(response.body, { status: response.status, headers });
	}
};

function corsHeaders(origin) {
	const allowed = origin && ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0];
	return {
		'Access-Control-Allow-Origin': allowed,
		'Access-Control-Allow-Methods': ALLOWED_METHODS.join(', '),
		'Access-Control-Allow-Headers': 'Content-Type, Authorization',
		'Access-Control-Allow-Credentials': 'true',
		Vary: 'Origin'
	};
}
