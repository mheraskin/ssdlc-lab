<script lang="ts">
	import { enhance } from '$app/forms';
	import { dev } from '$app/environment';
	import { page } from '$app/state';
	import type { ActionData, PageData } from './$types';

	let { data, form }: { data: PageData; form: ActionData } = $props();

	// Pulled from layout data — true ⇒ user enrolled an authenticator (real MFA path),
	// false ⇒ confirmation falls back to emailed step-up code.
	const usesTotp = $derived(Boolean(page.data.user?.totpEnabled));
</script>

<h1>Новий платіж</h1>

{#if form?.kind === 'done'}
	<div class="card panel success">
		<div class="check">✓</div>
		<h2>{form.message}</h2>
		<p>
			Сплачено <strong>{form.transaction.amount} {form.transaction.currency}</strong> на користь
			{form.transaction.recipientName}.
		</p>
		<div class="links">
			<a class="btn btn-primary" href="/transactions">Переглянути операції</a>
			<a class="btn btn-ghost" href="/payments/new">Створити ще один</a>
		</div>
	</div>
{:else if form?.kind === 'mfa'}
	<div class="card panel">
		<div class="mfa-icon">🔐</div>
		<h2>{usesTotp ? 'Справжня MFA: введіть код із застосунку-автентифікатора' : 'Підтвердьте свій платіж'}</h2>
		<p class="lead">{form.message}</p>
		{#if dev && usesTotp}
			<div class="alert alert-info">
				Dev: телефон не потрібен — отримайте поточний код командою
				<code>docker compose exec backend php bin/console app:totp {page.data.user?.email}</code>
			</div>
		{:else if dev}
			<div class="alert alert-info">
				Dev: листи перехоплюються Mailpit — відкрийте
				<a href="http://localhost:8025" target="_blank" rel="noreferrer">localhost:8025</a>, щоб прочитати код.
				<br />Увімкніть справжню MFA в
				<a href="/settings/2fa">налаштуваннях безпеки</a>, щоб замість листа вимагати код із автентифікатора.
			</div>
		{/if}
		{#if form.error}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/confirm" use:enhance>
			<input type="hidden" name="transactionId" value={form.transactionId} />
			<label class="field">
				Код підтвердження
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-значний код" required />
			</label>
			<button type="submit" class="btn btn-primary">Підтвердити платіж</button>
		</form>
	</div>
{:else}
	<div class="card panel">
		<p class="lead">
			Серверна частина перевіряє право власності, баланс, ліміти та правила ризику до того, як гроші будуть списані.
		</p>
		{#if form?.kind === 'error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/create" use:enhance>
			<label class="field">
				З рахунку
				<select name="sourceAccountId" required>
					{#each data.accounts as account (account.id)}
						<option value={account.id}>
							{account.accountNumber} — {account.balance} {account.currency}
						</option>
					{/each}
				</select>
			</label>
			<label class="field">
				Ім'я отримувача
				<input name="recipientName" placeholder="напр., ТОВ «Орендодавець»" required />
			</label>
			<label class="field">
				Рахунок отримувача
				<input name="recipientAccount" placeholder="IBAN / номер рахунку" required />
			</label>
			<label class="field">
				Сума
				<input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" required />
			</label>
			<button type="submit" class="btn btn-primary">Надіслати платіж</button>
		</form>
		<p class="tip">
			💡 Спробуйте суму ≥ 10 000, щоб запустити крок підтвердження другим фактором
			({usesTotp ? 'код автентифікатора — справжня MFA' : 'код листом — лише крок-ап'}).
		</p>
	</div>
{/if}

<style>
	.panel {
		padding: 1.6rem;
	}
	.success {
		text-align: center;
	}
	.check {
		width: 52px;
		height: 52px;
		margin: 0 auto 0.5rem;
		border-radius: 50%;
		background: var(--accent-100);
		color: var(--accent);
		display: grid;
		place-items: center;
		font-size: 1.6rem;
		font-weight: 700;
	}
	.mfa-icon {
		font-size: 1.8rem;
	}
	.links {
		display: flex;
		gap: 0.6rem;
		justify-content: center;
		margin-top: 1.2rem;
	}
	.tip {
		font-size: 0.82rem;
		color: var(--muted);
		margin: 1rem 0 0;
	}
</style>
