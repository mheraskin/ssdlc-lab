<script lang="ts">
	import { page } from '$app/state';

	let { children }: { children: import('svelte').Snippet } = $props();

	const tabs = [
		{ href: '/admin/users', label: 'Користувачі' },
		{ href: '/admin/payments', label: 'Платежі' },
		{ href: '/admin/audit-logs', label: 'Журнали аудиту' }
	];
</script>

<div class="admin-head">
	<h1>Адмін-панель</h1>
	<nav>
		{#each tabs as tab (tab.href)}
			<a href={tab.href} class:active={page.url.pathname === tab.href}>{tab.label}</a>
		{/each}
	</nav>
</div>

{@render children()}

<style>
	.admin-head {
		margin-bottom: 1.25rem;
	}
	h1 {
		margin-bottom: 0.5rem;
	}
	nav {
		display: flex;
		gap: 0.4rem;
		border-bottom: 1px solid #dde5f0;
	}
	nav a {
		padding: 0.5rem 0.9rem;
		text-decoration: none;
		color: #5a6b80;
		border-bottom: 2px solid transparent;
		font-size: 0.92rem;
	}
	nav a.active {
		color: #0f2d52;
		border-bottom-color: #0f2d52;
		font-weight: 600;
	}
</style>
