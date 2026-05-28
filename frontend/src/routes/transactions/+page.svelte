<script lang="ts">
	import TransactionTable from '$lib/components/TransactionTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<h1>Історія операцій</h1>
<p class="lead">Усі платежі, створені з ваших рахунків.</p>

<TransactionTable transactions={data.transactions} />

<style>
	.lead {
		color: #5a6b80;
	}
</style>
