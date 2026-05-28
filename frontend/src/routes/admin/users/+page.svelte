<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import RoleBadges from '$lib/components/RoleBadges.svelte';
	import type { User } from '$lib/types';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
	let busy = $state<number | null>(null);

	// Calls go through the same-origin BFF proxy (/api/*), which attaches the JWT cookie.
	async function toggle(user: User) {
		const action = user.status === 'active' ? 'block' : 'unblock';
		busy = user.id;
		await fetch(`/api/admin/users/${user.id}/${action}`, { method: 'POST' });
		await invalidateAll();
		busy = null;
	}
</script>

<table class="table">
	<thead>
		<tr>
			<th>ID</th>
			<th>Ім'я</th>
			<th>Електронна пошта</th>
			<th>Ролі</th>
			<th>Статус</th>
			<th class="num">Дія</th>
		</tr>
	</thead>
	<tbody>
		{#each data.users as user (user.id)}
			<tr>
				<td class="muted">{user.id}</td>
				<td class="name">{user.fullName}</td>
				<td class="muted">{user.email}</td>
				<td><RoleBadges roles={user.roles} /></td>
				<td>
					<span class="pill {user.status === 'active' ? 'pill-ok' : 'pill-bad'}">{user.status === 'active' ? 'активний' : 'заблокований'}</span>
				</td>
				<td class="num">
					<button class="btn btn-ghost sm" onclick={() => toggle(user)} disabled={busy === user.id}>
						{user.status === 'active' ? 'Заблокувати' : 'Розблокувати'}
					</button>
				</td>
			</tr>
		{/each}
	</tbody>
</table>
<p class="note">🔒 Паролі та секрети ніколи не доступні в адмін-панелі.</p>

<style>
	.name {
		font-weight: 550;
	}
	.muted {
		color: var(--muted);
	}
	.btn.sm {
		padding: 0.32rem 0.7rem;
		font-size: 0.82rem;
	}
	.note {
		color: var(--muted);
		font-size: 0.82rem;
	}
</style>
