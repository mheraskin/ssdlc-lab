<script lang="ts">
	import AuditLogTable from '$lib/components/AuditLogTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<p class="lead">
	Immutable, append-only security &amp; business events — the database blocks any UPDATE or
	DELETE. These are also emitted as JSON to stderr for SIEM ingestion.
</p>
<AuditLogTable logs={data.auditLogs} />

<style>
	.lead {
		color: #5a6b80;
		max-width: 640px;
	}
</style>
