<script lang="ts">
	import '../app.css';
	import { page } from '$app/state';
	import { isAdmin } from '$lib/types';
	import RoleBadges from '$lib/components/RoleBadges.svelte';
	import favicon from '$lib/assets/favicon.svg';
	import type { LayoutData } from './$types';

	let { data, children }: { data: LayoutData; children: import('svelte').Snippet } = $props();

	const user = $derived(data.user);

	const navLinks = [
		{ href: '/dashboard', label: 'Dashboard' },
		{ href: '/accounts', label: 'Accounts' },
		{ href: '/transactions', label: 'Transactions' },
		{ href: '/payments/new', label: 'New payment' },
		{ href: '/settings/2fa', label: 'Security' }
	];

	const initials = $derived(
		(user?.fullName ?? '')
			.split(' ')
			.map((p) => p[0])
			.join('')
			.slice(0, 2)
			.toUpperCase()
	);
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<title>SSDLC Bank</title>
</svelte:head>

<div class="app">
	{#if user}
		<header>
			<div class="bar">
				<div class="brand"><span class="mark">🏦</span> SSDLC <span>Bank</span></div>
				<nav>
					{#each navLinks as link (link.href)}
						<a href={link.href} class:active={page.url.pathname === link.href}>{link.label}</a>
					{/each}
					{#if isAdmin(user)}
						<a href="/admin/audit-logs" class:active={page.url.pathname.startsWith('/admin')}>Admin</a>
					{/if}
				</nav>
				<div class="account">
					<div class="who">
						<span class="name">{user.fullName}</span>
						<RoleBadges roles={user.roles} size="sm" />
					</div>
					<div class="avatar">{initials}</div>
					<form method="POST" action="/logout">
						<button type="submit" class="logout" title="Log out" aria-label="Log out">⎋</button>
					</form>
				</div>
			</div>
		</header>
	{/if}

	<main class:full={!user}>
		{@render children()}
	</main>
</div>

<style>
	.app {
		min-height: 100vh;
	}
	header {
		background: linear-gradient(180deg, var(--primary), var(--primary-700));
		color: #fff;
		box-shadow: var(--shadow-sm);
	}
	.bar {
		max-width: 1040px;
		margin: 0 auto;
		display: flex;
		align-items: center;
		gap: 1.5rem;
		padding: 0.7rem 1.5rem;
	}
	.brand {
		font-weight: 700;
		font-size: 1.05rem;
		white-space: nowrap;
		letter-spacing: -0.01em;
	}
	.brand span {
		font-weight: 400;
		opacity: 0.85;
	}
	.brand .mark {
		margin-right: 0.15rem;
	}
	nav {
		display: flex;
		gap: 0.2rem;
		flex: 1;
	}
	nav a {
		color: #c8d6ec;
		text-decoration: none;
		padding: 0.4rem 0.8rem;
		border-radius: var(--radius-sm);
		font-size: 0.92rem;
		font-weight: 500;
		transition: background 0.15s ease;
	}
	nav a:hover {
		background: rgba(255, 255, 255, 0.12);
		color: #fff;
	}
	nav a.active {
		background: rgba(255, 255, 255, 0.16);
		color: #fff;
	}
	.account {
		display: flex;
		align-items: center;
		gap: 0.7rem;
	}
	.who {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 0.15rem;
	}
	.who .name {
		font-size: 0.9rem;
		font-weight: 600;
	}
	.avatar {
		width: 34px;
		height: 34px;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.18);
		display: grid;
		place-items: center;
		font-size: 0.78rem;
		font-weight: 700;
	}
	.logout {
		background: transparent;
		border: 1px solid rgba(255, 255, 255, 0.25);
		color: #c8d6ec;
		width: 34px;
		height: 34px;
		border-radius: var(--radius-sm);
		cursor: pointer;
		font-size: 1rem;
	}
	.logout:hover {
		background: rgba(255, 255, 255, 0.12);
		color: #fff;
	}
	main {
		max-width: 1040px;
		margin: 0 auto;
		padding: 1.9rem 1.5rem 3rem;
	}
	main.full {
		max-width: none;
		padding: 0;
	}
</style>
