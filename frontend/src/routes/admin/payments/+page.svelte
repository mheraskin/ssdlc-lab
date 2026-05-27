<script lang="ts">
	import TransactionTable from '$lib/components/TransactionTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<p class="lead">All payments across all users (admin view).</p>
<TransactionTable transactions={data.payments} showOwner />

<style>
	.lead {
		color: #5a6b80;
	}
</style>
