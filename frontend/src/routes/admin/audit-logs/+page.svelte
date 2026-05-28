<script lang="ts">
	import AuditLogTable from '$lib/components/AuditLogTable.svelte';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<p class="lead">
	Незмінні події безпеки та бізнес-події з режимом лише додавання — база даних блокує будь-який UPDATE або
	DELETE. Вони також виводяться у форматі JSON у stderr для імпорту в SIEM.
</p>
<AuditLogTable logs={data.auditLogs} />

<style>
	.lead {
		color: #5a6b80;
		max-width: 640px;
	}
</style>
