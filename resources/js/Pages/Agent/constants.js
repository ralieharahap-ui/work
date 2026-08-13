export const STATUS_BADGE = {
    PENDING:          'bg-warm-100 text-warm-500',
    PLANNING:         'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    EXECUTING:        'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    VERIFYING:        'bg-purple-50 text-purple-700',
    WAITING_APPROVAL: 'bg-amber-50 text-amber-700',
    PAUSED:           'bg-orange-50 text-orange-700',
    COMPLETED:        'bg-green-50 text-green-700',
    FAILED:           'bg-red-50 text-red-600',
    CANCELLED:        'bg-warm-100 text-warm-500',
};

export const STATUS_LABEL = {
    PENDING:          'Menunggu giliran',
    PLANNING:         'Menyusun rencana',
    EXECUTING:        'Sedang dikerjakan',
    VERIFYING:        'Memeriksa hasil',
    WAITING_APPROVAL: 'Menunggu persetujuan',
    PAUSED:           'Tertahan',
    COMPLETED:        'Selesai',
    FAILED:           'Belum tuntas',
    CANCELLED:        'Dibatalkan',
};

export const STEP_BADGE = {
    pending:          'bg-warm-100 text-warm-500',
    running:          'bg-notion-blue-badge-bg text-notion-blue-badge-text',
    waiting_approval: 'bg-amber-50 text-amber-700',
    succeeded:        'bg-green-50 text-green-700',
    failed:           'bg-red-50 text-red-600',
    skipped:          'bg-warm-100 text-warm-300',
};

export const STEP_LABEL = {
    pending: 'Menunggu', running: 'Berjalan', waiting_approval: 'Perlu persetujuan',
    succeeded: 'Berhasil', failed: 'Gagal', skipped: 'Dilewati',
};

export const RISK_BADGE = {
    low:    'bg-green-50 text-green-700',
    medium: 'bg-amber-50 text-amber-700',
    high:   'bg-red-50 text-red-600',
};

export const TASK_TYPE_LABEL = {
    report_generation:    'Penyusunan laporan',
    data_comparison:      'Perbandingan data',
    email_handling:       'Korespondensi email',
    calendar_scheduling:  'Penjadwalan agenda',
    document_preparation: 'Penyusunan dokumen',
    followup:             'Tindak lanjut',
    research:             'Riset',
    general:              'Umum',
};

// Peristiwa dikelompokkan agar linimasa terbaca sebagai cerita, bukan daftar kode.
export const EVENT_STYLE = {
    TASK_CREATED:           { dot: 'bg-notion-blue',  label: 'Pekerjaan diterima' },
    TASK_UNDERSTOOD:        { dot: 'bg-notion-blue',  label: 'Pekerjaan dipahami' },
    MEMORY_RETRIEVED:       { dot: 'bg-purple-500',   label: 'Mengingat pengalaman' },
    TASK_PLANNED:           { dot: 'bg-notion-blue',  label: 'Rencana disusun' },
    PLAN_REVISED:           { dot: 'bg-amber-500',    label: 'Rencana direvisi' },
    LESSON_APPLIED:         { dot: 'bg-purple-500',   label: 'Pelajaran diterapkan' },
    STEP_STARTED:           { dot: 'bg-warm-300',     label: 'Langkah dimulai' },
    TOOL_CALLED:            { dot: 'bg-warm-300',     label: 'Tool dijalankan' },
    TOOL_COMPLETED:         { dot: 'bg-green-600',    label: 'Tool selesai' },
    TOOL_FAILED:            { dot: 'bg-red-500',      label: 'Tool gagal' },
    TOOL_REPLAYED:          { dot: 'bg-warm-300',     label: 'Hasil dipakai ulang' },
    RECOVERY_APPLIED:       { dot: 'bg-amber-500',    label: 'Pemulihan' },
    POLICY_BLOCKED:         { dot: 'bg-red-500',      label: 'Ditahan kebijakan' },
    APPROVAL_REQUESTED:     { dot: 'bg-amber-500',    label: 'Minta persetujuan' },
    APPROVAL_GRANTED:       { dot: 'bg-green-600',    label: 'Disetujui' },
    APPROVAL_REJECTED:      { dot: 'bg-red-500',      label: 'Ditolak' },
    ACCESS_REQUESTED:       { dot: 'bg-amber-500',    label: 'Minta akses tool' },
    VERIFICATION_STARTED:   { dot: 'bg-warm-300',     label: 'Pemeriksaan hasil' },
    VERIFICATION_PASSED:    { dot: 'bg-green-600',    label: 'Pemeriksaan lolos' },
    VERIFICATION_FAILED:    { dot: 'bg-red-500',      label: 'Pemeriksaan gagal' },
    HUMAN_REVIEW_REQUESTED: { dot: 'bg-amber-500',    label: 'Minta pemeriksaan manusia' },
    TASK_PAUSED:            { dot: 'bg-orange-500',   label: 'Dijeda' },
    TASK_RESUMED:           { dot: 'bg-notion-blue',  label: 'Dilanjutkan' },
    TASK_CANCELLED:         { dot: 'bg-warm-300',     label: 'Dibatalkan' },
    TASK_COMPLETED:         { dot: 'bg-green-600',    label: 'Pekerjaan selesai' },
    TASK_FAILED:            { dot: 'bg-red-500',      label: 'Pekerjaan gagal' },
    REFLECTION_CREATED:     { dot: 'bg-purple-500',   label: 'Refleksi' },
    EXPERIENCE_CREATED:     { dot: 'bg-purple-500',   label: 'Pengalaman disimpan' },
    LESSON_CREATED:         { dot: 'bg-purple-500',   label: 'Pelajaran baru' },
    MESSAGE_SENT:           { dot: 'bg-warm-300',     label: 'Kabar dikirim' },
    MESSAGE_RECEIVED:       { dot: 'bg-warm-300',     label: 'Pesan masuk' },
};

export const INTEGRATION_STATUS = {
    connected:       { label: 'Tersambung',  badge: 'bg-green-50 text-green-700' },
    not_configured:  { label: 'Belum diisi', badge: 'bg-warm-100 text-warm-500' },
    error:           { label: 'Gagal',       badge: 'bg-red-50 text-red-600' },
    denied:          { label: 'Ditolak',     badge: 'bg-warm-100 text-warm-300' },
};

export const fmtTime = (value) =>
    value ? new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-';

export const pct = (value) => `${Math.round((Number(value) || 0) * 100)}%`;
