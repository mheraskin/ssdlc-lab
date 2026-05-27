import type { PageServerLoad } from './$types';
import { backendJson, SESSION_COOKIE } from '$lib/server/backend';
import type { AuditLogEntry } from '$lib/types';

export const load: PageServerLoad = async ({ cookies }) => {
	const token = cookies.get(SESSION_COOKIE);
	const data = await backendJson<{ auditLogs: AuditLogEntry[] }>(token, '/api/admin/audit-logs');
	return { auditLogs: data.auditLogs };
};
