export interface User {
	id: number;
	email: string;
	fullName: string;
	roles: string[];
	status: string;
	createdAt: string;
}

export interface Account {
	id: number;
	accountNumber: string;
	currency: string;
	balanceCents: number;
	balance: string;
	status: string;
	ownerEmail: string;
}

export interface Transaction {
	id: number;
	sourceAccountId: number;
	sourceAccountNumber: string;
	recipientName: string;
	recipientAccount: string;
	amountCents: number;
	amount: string;
	currency: string;
	status: string;
	riskStatus: string;
	riskReason: string | null;
	createdBy: string;
	createdAt: string;
	confirmedAt: string | null;
}

export interface AuditLogEntry {
	id: number;
	eventType: string;
	actorEmail: string | null;
	entityType: string | null;
	entityId: string | null;
	ipAddress: string | null;
	metadata: Record<string, unknown>;
	createdAt: string;
}

export interface Notification {
	id: number;
	type: string;
	recipient: string;
	message: string;
	status: string;
	createdAt: string;
}

export function isAdmin(user: User | null): boolean {
	return !!user && user.roles.includes('ROLE_ADMIN');
}

export type RoleTone = 'admin' | 'employee' | 'client' | 'default';

export interface RoleMeta {
	label: string;
	tone: RoleTone;
}

/** Human-friendly label + colour tone for a raw `ROLE_*` string. */
export function roleMeta(role: string): RoleMeta {
	switch (role) {
		case 'ROLE_ADMIN':
			return { label: 'Admin', tone: 'admin' };
		case 'ROLE_EMPLOYEE':
			return { label: 'Employee', tone: 'employee' };
		case 'ROLE_CLIENT':
			return { label: 'Client', tone: 'client' };
		default:
			return {
				label: role.replace(/^ROLE_/, '').toLowerCase().replace(/^\w/, (c) => c.toUpperCase()),
				tone: 'default'
			};
	}
}

/** The meaningful roles to display (hides the implicit ROLE_USER). */
export function displayRoles(roles: string[]): string[] {
	const meaningful = roles.filter((r) => r !== 'ROLE_USER');
	return meaningful.length ? meaningful : roles;
}
