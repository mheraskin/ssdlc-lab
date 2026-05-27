<script lang="ts">
	import type { Account } from '$lib/types';

	let { account }: { account: Account } = $props();
</script>

<div class="acct">
	<div class="top">
		<span class="label">{account.currency} account</span>
		<span class="status" class:frozen={account.status !== 'active'}>{account.status}</span>
	</div>
	<div class="balance">{account.balance} <span>{account.currency}</span></div>
	<div class="number">{account.accountNumber}</div>
</div>

<style>
	.acct {
		background: linear-gradient(135deg, var(--primary), #1f5fa8);
		color: #fff;
		border-radius: var(--radius);
		padding: 1.3rem;
		min-width: 240px;
		flex: 1;
		box-shadow: var(--shadow);
		position: relative;
		overflow: hidden;
	}
	.acct::after {
		content: '';
		position: absolute;
		right: -40px;
		top: -40px;
		width: 130px;
		height: 130px;
		background: rgba(255, 255, 255, 0.08);
		border-radius: 50%;
	}
	.top {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.label {
		font-size: 0.8rem;
		color: #cdd9ec;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}
	.status {
		text-transform: capitalize;
		font-size: 0.7rem;
		font-weight: 600;
		background: rgba(255, 255, 255, 0.18);
		padding: 0.12rem 0.5rem;
		border-radius: 999px;
	}
	.status.frozen {
		background: var(--danger);
	}
	.balance {
		margin-top: 1.1rem;
		font-size: 1.9rem;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}
	.balance span {
		font-size: 0.9rem;
		color: #cdd9ec;
		font-weight: 500;
	}
	.number {
		margin-top: 0.5rem;
		font-size: 0.82rem;
		color: #cdd9ec;
		letter-spacing: 0.05em;
	}
</style>
