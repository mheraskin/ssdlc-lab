<script lang="ts">
	import AccountCard from '$lib/components/AccountCard.svelte';
	import TransactionTable from '$lib/components/TransactionTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	// Sum balances per currency (accounts may be in different currencies).
	const byCurrency = $derived(
		data.accounts.reduce<Record<string, number>>((acc, a) => {
			acc[a.currency] = (acc[a.currency] ?? 0) + a.balanceCents;
			return acc;
		}, {})
	);
	const totals = $derived(
		Object.entries(byCurrency).map(([cur, cents]) => `${(cents / 100).toFixed(2)} ${cur}`)
	);
</script>

<h1>Dashboard</h1>

<div class="stats">
	<div class="card stat">
		<span class="k">Total balance</span>
		<span class="v">{totals.join(' · ')}</span>
	</div>
	<div class="card stat">
		<span class="k">Accounts</span>
		<span class="v">{data.accounts.length}</span>
	</div>
	<div class="card stat">
		<span class="k">Recent payments</span>
		<span class="v">{data.transactions.length}</span>
	</div>
</div>

<div class="cards">
	{#each data.accounts as account (account.id)}
		<AccountCard {account} />
	{/each}
</div>

<div class="section-head">
	<h2>Recent activity</h2>
	<a href="/transactions">View all →</a>
</div>
<TransactionTable transactions={data.transactions} />

<div class="cta">
	<a class="btn btn-primary" href="/payments/new">+ New payment</a>
</div>

<style>
	.stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
		gap: 1rem;
		margin: 1rem 0 1.75rem;
	}
	.stat {
		padding: 1rem 1.2rem;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}
	.stat .k {
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--muted);
	}
	.stat .v {
		font-size: 1.35rem;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}
	.cards {
		display: flex;
		gap: 1rem;
		flex-wrap: wrap;
		margin-bottom: 2rem;
	}
	.section-head {
		display: flex;
		justify-content: space-between;
		align-items: baseline;
		margin-bottom: 0.5rem;
	}
	.section-head a {
		font-size: 0.85rem;
		text-decoration: none;
	}
	.cta {
		margin-top: 1.5rem;
	}
</style>
