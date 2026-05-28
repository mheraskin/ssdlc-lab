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

<h1>New payment</h1>

{#if form?.kind === 'done'}
	<div class="card panel success">
		<div class="check">✓</div>
		<h2>{form.message}</h2>
		<p>
			Paid <strong>{form.transaction.amount} {form.transaction.currency}</strong> to
			{form.transaction.recipientName}.
		</p>
		<div class="links">
			<a class="btn btn-primary" href="/transactions">View transactions</a>
			<a class="btn btn-ghost" href="/payments/new">Make another</a>
		</div>
	</div>
{:else if form?.kind === 'mfa'}
	<div class="card panel">
		<div class="mfa-icon">🔐</div>
		<h2>{usesTotp ? 'Real MFA: enter the code from your authenticator' : 'Confirm your payment'}</h2>
		<p class="lead">{form.message}</p>
		{#if dev && usesTotp}
			<div class="alert alert-info">
				Dev: no phone needed — get the current code with
				<code>docker compose exec backend php bin/console app:totp {page.data.user?.email}</code>
			</div>
		{:else if dev}
			<div class="alert alert-info">
				Dev: emails are captured by Mailpit — open
				<a href="http://localhost:8025" target="_blank" rel="noreferrer">localhost:8025</a> to read your code.
				<br />Enable real MFA in
				<a href="/settings/2fa">Security settings</a> to require a code from an authenticator instead.
			</div>
		{/if}
		{#if form.error}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/confirm" use:enhance>
			<input type="hidden" name="transactionId" value={form.transactionId} />
			<label class="field">
				Confirmation code
				<input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" required />
			</label>
			<button type="submit" class="btn btn-primary">Confirm payment</button>
		</form>
	</div>
{:else}
	<div class="card panel">
		<p class="lead">
			The backend validates ownership, balance, limits and risk rules before any money moves.
		</p>
		{#if form?.kind === 'error'}<div class="alert alert-error">{form.error}</div>{/if}
		<form method="POST" action="?/create" use:enhance>
			<label class="field">
				From account
				<select name="sourceAccountId" required>
					{#each data.accounts as account (account.id)}
						<option value={account.id}>
							{account.accountNumber} — {account.balance} {account.currency}
						</option>
					{/each}
				</select>
			</label>
			<label class="field">
				Recipient name
				<input name="recipientName" placeholder="e.g. Landlord Properties" required />
			</label>
			<label class="field">
				Recipient account
				<input name="recipientAccount" placeholder="IBAN / account number" required />
			</label>
			<label class="field">
				Amount
				<input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" required />
			</label>
			<button type="submit" class="btn btn-primary">Send payment</button>
		</form>
		<p class="tip">
			💡 Try an amount ≥ 10,000 to trigger the second-factor confirmation step
			({usesTotp ? 'authenticator code — real MFA' : 'emailed code — step-up only'}).
		</p>
	</div>
{/if}

<style>
	.panel {
		padding: 1.6rem;
		max-width: 460px;
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
