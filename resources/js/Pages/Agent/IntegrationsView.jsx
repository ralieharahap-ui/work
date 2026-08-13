import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import {
    CheckBadgeIcon, ExclamationTriangleIcon, LinkIcon, ArrowPathIcon, TrashIcon,
} from '@heroicons/react/24/outline';
import { INTEGRATION_STATUS, fmtTime } from './constants';

/**
 * Layar "minta akses": agent menjelaskan apa yang dibutuhkannya, untuk apa,
 * dan bagaimana cara memberikannya. Nilai kredensial hanya dikirim ke server —
 * tidak pernah dikirim balik ke layar ini.
 */
export default function IntegrationsView({ integrations, onboarding, canManage, agentName }) {
    const [open, setOpen] = useState(null);

    return (
        <div className="space-y-5">
            <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4">
                <h3 className="font-bold text-[rgba(0,0,0,0.9)]">Akses Tools untuk {agentName}</h3>
                <pre className="text-xs text-warm-500 mt-2 whitespace-pre-wrap font-sans max-w-4xl">{onboarding.script}</pre>

                {canManage && (
                    <div className="flex flex-wrap gap-2 mt-3 pt-3 border-t border-black/5">
                        <button type="button"
                            onClick={() => router.post(route('agent.integrations.telegram-link'), {}, { preserveScroll: true })}
                            className="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                            <LinkIcon className="w-3.5 h-3.5" /> Hubungkan Telegram saya
                        </button>
                        <button type="button"
                            onClick={() => router.post(route('agent.integrations.telegram-webhook'), {}, { preserveScroll: true })}
                            className="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                            <ArrowPathIcon className="w-3.5 h-3.5" /> Daftarkan webhook bot
                        </button>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {integrations.map((integration) => (
                    <IntegrationCard
                        key={integration.key}
                        integration={integration}
                        canManage={canManage}
                        expanded={open === integration.key}
                        onToggle={() => setOpen(open === integration.key ? null : integration.key)}
                    />
                ))}
            </div>
        </div>
    );
}

function IntegrationCard({ integration, canManage, expanded, onToggle }) {
    const status = INTEGRATION_STATUS[integration.status] || INTEGRATION_STATUS.not_configured;

    const form = useForm({
        credentials: Object.fromEntries(integration.fields.map((field) => [field.key, ''])),
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(route('agent.integrations.update', integration.key), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div className="bg-white rounded-xl border border-black/10 shadow-notion p-4 flex flex-col">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-semibold text-sm text-[rgba(0,0,0,0.9)]">
                        {integration.label}
                        {integration.essential && (
                            <span className="ml-2 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-red-50 text-red-600">
                                wajib
                            </span>
                        )}
                    </p>
                    <p className="text-xs text-warm-500 mt-1">{integration.purpose}</p>
                </div>
                <span className={`shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${status.badge}`}>
                    {status.label}
                </span>
            </div>

            {integration.scopes?.length > 0 && (
                <p className="text-[11px] text-warm-300 mt-2">Yang diminta: {integration.scopes.join('; ')}</p>
            )}

            {integration.status === 'connected' && integration.meta && (
                <p className="text-[11px] text-green-700 mt-1 flex items-center gap-1">
                    <CheckBadgeIcon className="w-3.5 h-3.5" />
                    {Object.entries(integration.meta).map(([key, value]) => `${key}: ${value}`).join(' · ')}
                    {integration.last_verified_at && ` · diuji ${fmtTime(integration.last_verified_at)}`}
                </p>
            )}

            {integration.last_error && (
                <p className="text-[11px] text-red-600 mt-1 flex items-start gap-1">
                    <ExclamationTriangleIcon className="w-3.5 h-3.5 shrink-0 mt-px" /> {integration.last_error}
                </p>
            )}

            <button type="button" onClick={onToggle} className="text-xs text-notion-blue hover:underline mt-3 text-left">
                {expanded ? 'Sembunyikan panduan' : 'Lihat panduan & beri akses'}
            </button>

            {expanded && (
                <div className="mt-3 pt-3 border-t border-black/5">
                    <ol className="space-y-1 list-decimal list-inside">
                        {integration.guidance.map((step, i) => (
                            <li key={i} className="text-xs text-warm-500">{step}</li>
                        ))}
                    </ol>

                    {canManage ? (
                        <form onSubmit={submit} className="mt-3 space-y-2">
                            {integration.fields.map((field) => (
                                <div key={field.key}>
                                    <label className="block text-[11px] font-medium text-warm-500 mb-0.5">
                                        {field.label}
                                        {field.required && <span className="text-red-500"> *</span>}
                                        {field.filled && <span className="text-green-700"> · sudah terisi</span>}
                                    </label>
                                    <input
                                        type={field.secret ? 'password' : 'text'}
                                        value={form.data.credentials[field.key]}
                                        onChange={(event) => form.setData('credentials', {
                                            ...form.data.credentials, [field.key]: event.target.value,
                                        })}
                                        placeholder={field.filled ? '•••••• (kosongkan bila tidak diubah)' : (field.hint || '')}
                                        autoComplete="off"
                                        className="w-full text-xs rounded-lg border-black/10 text-[rgba(0,0,0,0.8)] focus:border-notion-blue focus:ring-notion-blue"
                                    />
                                </div>
                            ))}

                            {integration.fields.length === 0 && (
                                <p className="text-xs text-warm-500">Akses ini tidak memerlukan kredensial tambahan.</p>
                            )}

                            <div className="flex flex-wrap gap-2 pt-1">
                                <button type="submit" disabled={form.processing}
                                    className="text-xs px-3 py-1.5 rounded-lg bg-notion-blue text-white hover:opacity-90 disabled:opacity-50">
                                    Berikan akses
                                </button>
                                {integration.status === 'connected' && (
                                    <>
                                        <button type="button"
                                            onClick={() => router.post(route('agent.integrations.verify', integration.key), {}, { preserveScroll: true })}
                                            className="flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg border border-black/10 text-[rgba(0,0,0,0.8)] hover:bg-warm-50">
                                            <ArrowPathIcon className="w-3.5 h-3.5" /> Uji koneksi
                                        </button>
                                        <button type="button"
                                            onClick={() => window.confirm('Cabut akses dan hapus kredensialnya?')
                                                && router.delete(route('agent.integrations.revoke', integration.key), { preserveScroll: true })}
                                            className="flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">
                                            <TrashIcon className="w-3.5 h-3.5" /> Cabut
                                        </button>
                                    </>
                                )}
                                {['not_configured', 'error'].includes(integration.status) && (
                                    <button type="button"
                                        onClick={() => router.post(route('agent.integrations.deny', integration.key), {}, { preserveScroll: true })}
                                        className="text-xs px-3 py-1.5 rounded-lg border border-black/10 text-warm-500 hover:bg-warm-50">
                                        Tolak akses ini
                                    </button>
                                )}
                            </div>

                            <p className="text-[11px] text-warm-300 pt-1">{integration.revoke}</p>
                        </form>
                    ) : (
                        <p className="text-xs text-warm-500 mt-3">
                            Hubungi administrator aplikasi untuk memberikan akses ini.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
