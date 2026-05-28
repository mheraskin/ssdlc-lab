<script lang="ts">
	import { enhance } from '$app/forms';
	import type { ActionData } from './$types';

	let { form }: { form: ActionData } = $props();

	const demoUsers = [
		{ email: 'client@example.com', role: 'Клієнт' },
		{ email: 'admin@example.com', role: 'Адміністратор' },
		{ email: 'employee@example.com', role: 'Співробітник' }
	];
</script>

<div class="screen">
	<div class="hero">
		<div class="brand"><span>🏦</span> Банк SSDLC</div>
		<h2>Безпечний банкінг за замовчуванням.</h2>
		<p>
			Демонстрація багатошарової банківської платформи, побудованої за принципами SSDLC — JWT-автентифікація за BFF,
			рольовий доступ, MFA електронною поштою для ризикових платежів і незмінний журнал аудиту.
		</p>
		<ul class="features">
			<li>🔐 Платежі з підтвердженням MFA</li>
			<li>🛡️ Серверні правила ризику та боротьби з шахрайством</li>
			<li>📜 Незмінний журнал аудиту, готовий до SIEM</li>
		</ul>
	</div>

	<div class="panel">
		<div class="card formcard">
			<h1>Вхід</h1>
			<p class="lead">Скористайтеся демо-обліковим записом нижче, щоб ознайомитись.</p>

			{#if form?.error}
				<div class="alert alert-error">{form.error}</div>
			{/if}

			<form method="POST" use:enhance>
				<label class="field">
					Електронна пошта
					<input name="email" type="email" autocomplete="username" value={form?.email ?? ''} required />
				</label>
				<label class="field">
					Пароль
					<input name="password" type="password" autocomplete="current-password" required />
				</label>
				<button type="submit" class="btn btn-primary block">Увійти</button>
			</form>

			<div class="demo">
				<span>Демо-облікові записи — пароль <code>Password123!</code></span>
				<ul>
					{#each demoUsers as u (u.email)}
						<li><code>{u.email}</code> · {u.role}</li>
					{/each}
				</ul>
			</div>
		</div>
	</div>
</div>

<style>
	.screen {
		min-height: 100vh;
		display: grid;
		grid-template-columns: 1.1fr 1fr;
	}
	.hero {
		background: linear-gradient(160deg, var(--primary), var(--primary-700));
		color: #fff;
		padding: 3.5rem 3rem;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.hero .brand {
		font-size: 1.2rem;
		font-weight: 700;
		margin-bottom: 2rem;
	}
	.hero h2 {
		font-size: 2rem;
		line-height: 1.15;
		margin: 0 0 1rem;
	}
	.hero p {
		color: #c8d6ec;
		max-width: 30rem;
	}
	.features {
		list-style: none;
		padding: 0;
		margin: 2rem 0 0;
		display: grid;
		gap: 0.6rem;
		color: #e3ebf7;
		font-size: 0.95rem;
	}
	.panel {
		display: grid;
		place-items: center;
		padding: 2rem;
	}
	.formcard {
		width: 100%;
		max-width: 380px;
		padding: 2rem;
	}
	.block {
		width: 100%;
	}
	.demo {
		margin-top: 1.5rem;
		font-size: 0.82rem;
		color: var(--muted);
	}
	.demo ul {
		margin: 0.5rem 0 0;
		padding-left: 1rem;
	}
	code {
		background: var(--surface-2);
		padding: 0.05rem 0.35rem;
		border-radius: 5px;
		font-size: 0.85em;
	}
	@media (max-width: 760px) {
		.screen {
			grid-template-columns: 1fr;
		}
		.hero {
			display: none;
		}
	}
</style>
