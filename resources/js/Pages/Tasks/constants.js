export const STATUSES = ['To Do', 'In Progress', 'Review', 'Blocked', 'Done'];
export const KANBAN_EDITABLE_STATUSES = ['To Do', 'In Progress', 'Review', 'Blocked'];
export const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
export const CATEGORIES = ['Operasional', 'Strategis'];

export const ROLE_LABELS = {
    super_admin: 'Super Admin (Owner)',
    approval: 'Manajer / Approval',
    reviewer: 'Reviewer / Supervisor',
    drafter: 'Anggota Tim (Member)',
    external: 'Viewer (Read-only)',
};

export const STATUS_DOT = {
    'To Do': 'bg-warm-300',
    'In Progress': 'bg-notion-blue',
    Review: 'bg-amber-500',
    Blocked: 'bg-red-500',
    Done: 'bg-green-600',
};

export const STATUS_BADGE = {
    'To Do': 'bg-warm-100 text-warm-500',
    'In Progress': 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    Review: 'bg-amber-50 text-amber-700',
    Blocked: 'bg-red-50 text-red-600',
    Done: 'bg-green-50 text-green-700',
};

export const PRIORITY_BADGE = {
    Low: 'bg-warm-100 text-warm-500',
    Medium: 'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    High: 'bg-orange-50 text-orange-700',
    Urgent: 'bg-red-50 text-red-600',
};

export const fmtDate = (d) =>
    d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

export const fmtDateShort = (d) =>
    d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '-';
