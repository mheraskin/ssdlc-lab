# Cloudflare deployment (frontend + public security layer)

The SvelteKit frontend is hosted on **Cloudflare Pages**; Cloudflare also provides the
public DMZ controls from the architecture: DNS, CDN, TLS/HTTPS, managed WAF and Anti-DDoS.

## Deploy the frontend to Cloudflare Pages

1. Switch the SvelteKit adapter from Node to Cloudflare:
   ```bash
   cd frontend
   npm i -D @sveltejs/adapter-cloudflare
   ```
   In `svelte.config.js` replace:
   ```js
   import adapter from '@sveltejs/adapter-node';      // local container
   // with:
   import adapter from '@sveltejs/adapter-cloudflare'; // Cloudflare Pages
   ```
   The BFF server endpoints (`/api/*`, `/login`, `/logout`) keep working — they run as
   Cloudflare Pages Functions, so the httpOnly-cookie BFF pattern is preserved at the edge.

2. Set the build env var `API_INTERNAL_URL=https://api.bank-demo.example.com` in the
   Pages project settings (Production + Preview).

3. Build & deploy:
   ```bash
   npm run build
   npx wrangler pages deploy .svelte-kit/cloudflare
   ```

`_headers` is published with the site and applies the edge security headers / CSP.

## Public security layer (no code)

| Control            | How Cloudflare provides it                              |
|--------------------|---------------------------------------------------------|
| DNS                | Domain nameservers on Cloudflare                        |
| CDN                | Pages assets cached at the edge                         |
| TLS / HTTPS        | Automatic certificates, HTTPS-only + HSTS               |
| WAF + Firewall     | Managed WAF ruleset on the zone                         |
| Anti-DDoS          | Always-on L3/L4/L7 DDoS protection                      |
| Load balancer      | Edge load balancing in front of the DO origin           |
| API Gateway        | Optional `worker-gateway.js` on `api.*` (rate limit/CORS)|

## Optional API-gateway Worker

`worker-gateway.js` can be bound to `api.bank-demo.example.com/*` to add edge rate
limiting and CORS validation in front of DigitalOcean. Bind a KV namespace as `RATE_KV`
in `wrangler.toml` and deploy with `npx wrangler deploy`.
