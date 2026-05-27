<script lang="ts">
	import type { AuditLogEntry } from '$lib/types';

	let { logs }: { logs: AuditLogEntry[] } = $props();

	function tone(eventType: string): string {
		if (eventType.includes('failed') || eventType.includes('rejected') || eventType.includes('rate'))
			return 'pill-bad';
		if (eventType.includes('mfa') || eventType.includes('admin')) return 'pill-warn';
		return 'pill-ok';
	}
</script>

<table class="table">
	<thead>
		<tr>
			<th>Time</th>
			<th>Event</th>
			<th>Actor</th>
			<th>Entity</th>
			<th>IP</th>
			<th>Metadata</th>
		</tr>
	</thead>
	<tbody>
		{#each logs as log (log.id)}
			<tr>
				<td class="muted nowrap">{new Date(log.createdAt).toLocaleString()}</td>
				<td><span class="pill {tone(log.eventType)}">{log.eventType.replace(/_/g, ' ')}</span></td>
				<td>{log.actorEmail ?? '—'}</td>
				<td class="muted">{log.entityType ? `${log.entityType}#${log.entityId}` : '—'}</td>
				<td class="muted">{log.ipAddress ?? '—'}</td>
				<td class="meta">{Object.keys(log.metadata).length ? JSON.stringify(log.metadata) : '—'}</td>
			</tr>
		{/each}
	</tbody>
</table>

<style>
	.muted {
		color: var(--muted);
	}
	.nowrap {
		white-space: nowrap;
	}
	.meta {
		font-family: ui-monospace, 'SF Mono', monospace;
		font-size: 0.74rem;
		color: var(--muted);
		max-width: 300px;
		word-break: break-all;
	}
</style>
