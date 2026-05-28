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

<h1>Двофакторна автентифікація</h1>
<p class="lead">
	Прив'яжіть до облікового запису застосунок-автентифікатор (Google Authenticator, 1Password, Authy, Bitwarden тощо).
	Після увімкнення ризикові платежі вимагатимуть 6-значний код із вашого автентифікатора —
	це справжній фактор володіння MFA поверх вашого пароля.
</p>

{#if enabled && form?.kind !== 'disabled'}
	<div class="card panel">
		<div class="state ok">✓ MFA увімкнено — платежі понад поріг ризику вимагатимуть код із автентифікатора.</div>
		<h2>Вимкнення</h2>
		<p class="hint">Підтвердьте поточним кодом із автентифікатора, щоб вимкнути MFA.</p>
		{#if form?.kind === 'disable-error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/disable" use:enhance>
			<label class="field">
				Код автентифікатора
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-значний код" required />
			</label>
			<button type="submit" class="btn btn-ghost">Вимкнути MFA</button>
		</form>
	</div>
{:else if form?.kind === 'setup'}
	<div class="card panel">
		<div class="state warn">Реєстрація триває — підтвердьте першим кодом, щоб увімкнути.</div>
		<h2>Скануйте у застосунку-автентифікаторі</h2>
		<p class="hint">
			Або вставте цей секрет вручну:
			<code class="secret">{form.secret}</code>
		</p>
		<div class="qr">
			{#if qrDataUrl}
				<img src={qrDataUrl} alt="QR-код TOTP" />
			{:else}
				<div class="qr-placeholder">Генерація QR…</div>
			{/if}
		</div>
		{#if dev}
			<div class="alert alert-info">
				Dev: телефон не потрібен — виконайте
				<code>docker compose exec backend php bin/console app:totp ВАША_ПОШТА</code>,
				щоб побачити поточний код, і вставте його нижче.
			</div>
		{/if}
		{#if form.error}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/enable" use:enhance>
			<input type="hidden" name="secret" value={form.secret} />
			<input type="hidden" name="provisioningUri" value={form.provisioningUri} />
			<label class="field">
				Код із автентифікатора
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-значний код" required />
			</label>
			<button type="submit" class="btn btn-primary">Підтвердити та увімкнути MFA</button>
		</form>
	</div>
{:else}
	<div class="card panel">
		<div class="state">MFA ще не увімкнено — для ризикових платежів використовується одноразовий код, надісланий на пошту (лише як крок-ап, не справжня MFA).</div>
		{#if form?.kind === 'error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/setup" use:enhance>
			<button type="submit" class="btn btn-primary">Налаштувати застосунок-автентифікатор</button>
		</form>
	</div>
{/if}

<style>
	.panel {
		padding: 1.6rem;
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
