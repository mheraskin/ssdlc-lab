<script lang="ts">
	import { displayRoles, roleMeta } from '$lib/types';

	let { roles, size = 'md' }: { roles: string[]; size?: 'sm' | 'md' } = $props();
</script>

<span class="badges {size}">
	{#each displayRoles(roles) as role (role)}
		{@const meta = roleMeta(role)}
		<span class="role {meta.tone}">{meta.label}</span>
	{/each}
</span>

<style>
	.badges {
		display: inline-flex;
		flex-wrap: wrap;
		gap: 0.3rem;
	}
	.role {
		font-size: 0.72rem;
		font-weight: 650;
		padding: 0.13rem 0.55rem;
		border-radius: 999px;
		line-height: 1.4;
		white-space: nowrap;
	}
	.sm .role {
		font-size: 0.66rem;
		padding: 0.08rem 0.45rem;
	}
	.admin {
		background: #ece4fb;
		color: #6b35c9;
	}
	.employee {
		background: #dff3ef;
		color: #0f7a63;
	}
	.client {
		background: #e3edfd;
		color: #1f5fc0;
	}
	.default {
		background: #eef1f6;
		color: #5a6b80;
	}
</style>
