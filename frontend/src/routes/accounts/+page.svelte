<script lang="ts">
	import AccountCard from '$lib/components/AccountCard.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<h1>Your accounts</h1>
<p class="lead">You can only ever see accounts you own — enforced by the backend.</p>

<div class="cards">
	{#each data.accounts as account (account.id)}
		<AccountCard {account} />
	{/each}
</div>

<style>
	.lead {
		color: #5a6b80;
	}
	.cards {
		display: flex;
		gap: 1rem;
		flex-wrap: wrap;
		margin-top: 1.25rem;
	}
</style>
