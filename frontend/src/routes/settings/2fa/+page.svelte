<script lang="ts">
	import { enhance } from '$app/forms';
	import { invalidateAll } from '$app/navigation';
	import { dev } from '$app/environment';
	import QRCode from 'qrcode';
	import type { ActionData, PageData } from './$types';

	let { data, form }: { data: PageData; form: ActionData } = $props();

	// Re-derive each time form changes so the QR repaints if the user retries setup.
	let qrDataUrl = $state<string | null>(null);
	$effect(() => {
		const uri = form?.kind === 'setup' ? form.provisioningUri : null;
		if (!uri) {
			qrDataUrl = null;
			return;
		}
		QRCode.toDataURL(uri, { width: 220, margin: 1 }).then((url) => (qrDataUrl = url));
	});

	// When `enable` succeeds, refresh layout data so locals.user.totpEnabled flips on.
	$effect(() => {
		if (form?.kind === 'enabled' || form?.kind === 'disabled') {
			invalidateAll();
		}
	});

	const enabled = $derived(data.user?.totpEnabled ?? false);
</script>

<h1>Two-factor authentication</h1>
<p class="lead">
	Bind an authenticator app (Google Authenticator, 1Password, Authy, Bitwarden, …) to your
	account. Once enabled, risky payments will require a 6-digit code from your authenticator —
	this is the real MFA possession factor on top of your password.
</p>

{#if enabled && form?.kind !== 'disabled'}
	<div class="card panel">
		<div class="state ok">✓ MFA is enabled — payments above the risk threshold ask for a code from your authenticator.</div>
		<h2>Disable</h2>
		<p class="hint">Confirm with a current code from your authenticator to turn MFA off.</p>
		{#if form?.kind === 'disable-error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/disable" use:enhance>
			<label class="field">
				Authenticator code
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" required />
			</label>
			<button type="submit" class="btn btn-ghost">Disable MFA</button>
		</form>
	</div>
{:else if form?.kind === 'setup'}
	<div class="card panel">
		<div class="state warn">Enrollment in progress — confirm your first code to enable.</div>
		<h2>Scan with your authenticator app</h2>
		<p class="hint">
			Or paste this secret manually:
			<code class="secret">{form.secret}</code>
		</p>
		<div class="qr">
			{#if qrDataUrl}
				<img src={qrDataUrl} alt="TOTP QR code" />
			{:else}
				<div class="qr-placeholder">Generating QR…</div>
			{/if}
		</div>
		{#if dev}
			<div class="alert alert-info">
				Dev: no phone needed — run
				<code>docker compose exec backend php bin/console app:totp YOUR_EMAIL</code>
				to print the current code, then paste it below.
			</div>
		{/if}
		{#if form.error}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/enable" use:enhance>
			<input type="hidden" name="secret" value={form.secret} />
			<input type="hidden" name="provisioningUri" value={form.provisioningUri} />
			<label class="field">
				Code from authenticator
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" required />
			</label>
			<button type="submit" class="btn btn-primary">Confirm &amp; enable MFA</button>
		</form>
	</div>
{:else}
	<div class="card panel">
		<div class="state">MFA is not enabled yet — risky payments fall back to an emailed one-time code (step-up only, not real MFA).</div>
		{#if form?.kind === 'error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/setup" use:enhance>
			<button type="submit" class="btn btn-primary">Set up authenticator app</button>
		</form>
	</div>
{/if}

<style>
	.panel {
		padding: 1.6rem;
		max-width: 460px;
	}
	h2 {
		margin: 0.4rem 0 0.8rem;
		font-size: 1.05rem;
	}
	.state {
		font-weight: 600;
		margin-bottom: 1rem;
	}
	.state.ok { color: var(--accent); }
	.state.warn { color: var(--warn); }
	.hint {
		color: var(--muted);
		font-size: 0.9rem;
	}
	.secret {
		display: block;
		margin-top: 0.4rem;
		font-family: ui-monospace, 'SF Mono', monospace;
		font-size: 0.88rem;
		padding: 0.45rem 0.6rem;
		background: var(--surface-2);
		border-radius: var(--radius-sm);
		word-break: break-all;
	}
	.qr {
		display: grid;
		place-items: center;
		padding: 0.5rem;
		background: #fff;
		border: 1px solid var(--border);
		border-radius: var(--radius-sm);
		margin: 1rem 0;
	}
	.qr img { display: block; }
	.qr-placeholder {
		color: var(--muted);
		padding: 2rem;
	}
</style>
