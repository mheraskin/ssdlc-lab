<script lang="ts">
	import AccountCard from '$lib/components/AccountCard.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<h1>Ваші рахунки</h1>
<p class="lead">Ви бачите лише свої власні рахунки — це гарантується серверною частиною.</p>

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
