<script lang="ts">
	import TransactionTable from '$lib/components/TransactionTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<h1>Transaction history</h1>
<p class="lead">All payments created from your accounts.</p>

<TransactionTable transactions={data.transactions} />

<style>
	.lead {
		color: #5a6b80;
	}
</style>
