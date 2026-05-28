<script lang="ts">
	import TransactionTable from '$lib/components/TransactionTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<p class="lead">Усі платежі всіх користувачів (адміністративний перегляд).</p>
<TransactionTable transactions={data.payments} showOwner />

<style>
	.lead {
		color: #5a6b80;
	}
</style>
