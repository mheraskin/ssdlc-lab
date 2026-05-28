<script lang="ts">
	import type { Transaction } from '$lib/types';

	let { transactions, showOwner = false }: { transactions: Transaction[]; showOwner?: boolean } =
		$props();

	function statusPill(status: string): string {
		if (status === 'completed') return 'pill-ok';
		if (status === 'rejected') return 'pill-bad';
		return 'pill-warn';
	}

	function riskPill(risk: string): string {
		if (risk === 'blocked') return 'pill-bad';
		if (risk === 'review') return 'pill-warn';
		return 'pill-muted';
	}

	const statusLabels: Record<string, string> = {
		completed: 'виконано',
		rejected: 'відхилено',
		pending_mfa: 'очікує MFA'
	};

	const riskLabels: Record<string, string> = {
		blocked: 'заблоковано',
		review: 'на перевірці',
		ok: 'ок'
	};
</script>

{#if transactions.length === 0}
	<p class="empty">Поки що немає операцій.</p>
{:else}
	<table class="table">
		<thead>
			<tr>
				<th>Дата</th>
				{#if showOwner}<th>Ким</th>{/if}
				<th>Отримувач</th>
				<th>З рахунку</th>
				<th class="num">Сума</th>
				<th>Статус</th>
				<th>Ризик</th>
			</tr>
		</thead>
		<tbody>
			{#each transactions as t (t.id)}
				<tr>
					<td class="muted">{new Date(t.createdAt).toLocaleDateString()}</td>
					{#if showOwner}<td>{t.createdBy}</td>{/if}
					<td>
						<div class="recipient">{t.recipientName}</div>
						<small>{t.recipientAccount}</small>
					</td>
					<td class="muted">{t.sourceAccountNumber}</td>
					<td class="num">{t.amount} {t.currency}</td>
					<td><span class="pill {statusPill(t.status)}">{statusLabels[t.status] ?? t.status.replace('_', ' ')}</span></td>
					<td><span class="pill {riskPill(t.riskStatus)}" title={t.riskReason ?? ''}>{riskLabels[t.riskStatus] ?? t.riskStatus}</span></td>
				</tr>
			{/each}
		</tbody>
	</table>
{/if}

<style>
	.recipient {
		font-weight: 550;
	}
	td small {
		color: var(--muted);
		font-size: 0.76rem;
	}
	.muted {
		color: var(--muted);
	}
	.empty {
		color: var(--muted);
	}
</style>
