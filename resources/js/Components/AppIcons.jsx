/**
 * Ikon full-color untuk menu & kartu.
 * Dibuat sebagai SVG inline agar tajam di semua ukuran dan tidak butuh aset eksternal.
 */

// 🗺️ Peta / Dashboard — peta terlipat dengan pin lokasi
export function MapDashboardIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 12l12-5v29L4 41V12z" fill="#7DD3FC" />
            <path d="M16 7l16 5v29l-16-5V7z" fill="#BAE6FD" />
            <path d="M32 12l12-5v29l-12 5V12z" fill="#7DD3FC" />
            <path d="M4 12l12-5v29L4 41V12z" stroke="#0284C7" strokeWidth="1.4" strokeLinejoin="round" />
            <path d="M16 7l16 5v29l-16-5V7z" stroke="#0284C7" strokeWidth="1.4" strokeLinejoin="round" />
            <path d="M32 12l12-5v29l-12 5V12z" stroke="#0284C7" strokeWidth="1.4" strokeLinejoin="round" />
            <path d="M7 20c4 2 8 1 11 4s2 7 5 9" stroke="#38BDF8" strokeWidth="1.6" strokeLinecap="round" fill="none" opacity=".8" />
            <path d="M34 17c3 3 6 2 8 6" stroke="#38BDF8" strokeWidth="1.6" strokeLinecap="round" fill="none" opacity=".8" />
            {/* pin merah */}
            <path d="M22 12c2.8 0 5 2.2 5 5 0 3.6-5 9-5 9s-5-5.4-5-9c0-2.8 2.2-5 5-5z" fill="#EF4444" />
            <circle cx="22" cy="17" r="1.9" fill="#FEE2E2" />
            {/* pin hijau */}
            <path d="M37 14c2.2 0 4 1.8 4 4 0 2.9-4 7.2-4 7.2S33 20.9 33 18c0-2.2 1.8-4 4-4z" fill="#22C55E" />
            <circle cx="37" cy="18" r="1.5" fill="#DCFCE7" />
            {/* pin biru */}
            <path d="M10 22c2.2 0 4 1.8 4 4 0 2.9-4 7.2-4 7.2S6 28.9 6 26c0-2.2 1.8-4 4-4z" fill="#3B82F6" />
            <circle cx="10" cy="26" r="1.5" fill="#DBEAFE" />
        </svg>
    );
}

// 🌴 Perkebunan sawit — pohon sawit dengan tandan buah
export function PalmPlantationIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="24" cy="42" rx="18" ry="3.5" fill="#65A30D" opacity=".35" />
            {/* batang */}
            <path d="M22.4 40V22h3.2v18h-3.2z" fill="#92400E" />
            <path d="M22.4 26h3.2M22.4 30h3.2M22.4 34h3.2" stroke="#78350F" strokeWidth="1.1" strokeLinecap="round" />
            {/* pelepah */}
            <path d="M24 21c-6-6-12-6-17-3 5-1 9 1 13 5" fill="#16A34A" />
            <path d="M24 21c6-6 12-6 17-3-5-1-9 1-13 5" fill="#16A34A" />
            <path d="M24 21c-5-7-5-13-2-17-1 5 1 9 4 13" fill="#22C55E" />
            <path d="M24 21c5-7 5-13 2-17 1 5-1 9-4 13" fill="#22C55E" />
            <path d="M24 21c-7-3-12-1-15 3 4-2 8-2 12 0" fill="#15803D" />
            <path d="M24 21c7-3 12-1 15 3-4-2-8-2-12 0" fill="#15803D" />
            {/* tandan buah */}
            <circle cx="20.5" cy="23.5" r="2.6" fill="#DC2626" />
            <circle cx="27.5" cy="23.5" r="2.6" fill="#EA580C" />
            <circle cx="24" cy="26" r="2.2" fill="#F97316" />
            <circle cx="19.8" cy="22.8" r=".7" fill="#FCA5A5" opacity=".9" />
            <circle cx="26.8" cy="22.8" r=".7" fill="#FED7AA" opacity=".9" />
        </svg>
    );
}

// 🏭 Industri / PLTU — pabrik dengan cerobong & asap
export function IndustryIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            {/* asap */}
            <circle cx="14" cy="8" r="3.2" fill="#CBD5E1" opacity=".75" />
            <circle cx="18.5" cy="5.5" r="2.4" fill="#E2E8F0" opacity=".65" />
            <circle cx="27" cy="7" r="2.8" fill="#CBD5E1" opacity=".6" />
            {/* cerobong */}
            <path d="M11 14h6v26h-6V14z" fill="#E2E8F0" />
            <path d="M11 18h6v3h-6v-3zm0 6h6v3h-6v-3z" fill="#EF4444" />
            <path d="M24 16h5v24h-5V16z" fill="#CBD5E1" />
            <path d="M24 20h5v2.6h-5V20z" fill="#F97316" />
            {/* bangunan */}
            <path d="M6 28h9v12H6V28z" fill="#94A3B8" />
            <path d="M31 24l7-4v20h-7V24z" fill="#64748B" />
            <path d="M31 24l7-4v3l-7 4v-3z" fill="#475569" />
            {/* jendela */}
            <path d="M8 31h2.5v2.5H8V31zm4 0h2.5v2.5H12V31zM8 35h2.5v2.5H8V35zm4 0h2.5v2.5H12V35z" fill="#FDE68A" />
            <path d="M33 28h3v2.5h-3V28zm0 5h3v2.5h-3V33z" fill="#FDE68A" />
            {/* tumpukan cangkang */}
            <path d="M17 40c1.5-4 4-6 6-6s4.5 2 6 6h-12z" fill="#B45309" />
            <circle cx="21" cy="37.5" r=".9" fill="#78350F" />
            <circle cx="24.5" cy="36.5" r=".9" fill="#78350F" />
            <path d="M2 40h44" stroke="#334155" strokeWidth="2.2" strokeLinecap="round" />
        </svg>
    );
}

// 🚢 Dermaga / Jetty — kapal & pelabuhan
export function JettyIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            {/* air */}
            <path d="M2 36h44v8H2v-8z" fill="#38BDF8" opacity=".55" />
            <path d="M2 38c3 0 3 1.6 6 1.6s3-1.6 6-1.6 3 1.6 6 1.6 3-1.6 6-1.6 3 1.6 6 1.6 3-1.6 6-1.6 3 1.6 6 1.6"
                  stroke="#0EA5E9" strokeWidth="1.4" fill="none" strokeLinecap="round" />
            {/* dermaga */}
            <path d="M2 30h16v4H2v-4z" fill="#A16207" />
            <path d="M5 34h2v6H5v-6zm8 0h2v6h-2v-6z" fill="#78350F" />
            {/* crane */}
            <path d="M9 30V14h2v16H9z" fill="#F59E0B" />
            <path d="M10 15h13v2H10v-2z" fill="#F59E0B" />
            <path d="M21 17v5" stroke="#B45309" strokeWidth="1.3" strokeLinecap="round" />
            <path d="M19.4 22h3.4l-.6 3.4h-2.2L19.4 22z" fill="#B45309" />
            {/* kapal */}
            <path d="M23 34l2-7h15l3 7H23z" fill="#DC2626" />
            <path d="M27 27v-5h9v5h-9z" fill="#F1F5F9" />
            <path d="M28.5 23.5h2v2h-2v-2zm3.5 0h2v2h-2v-2z" fill="#0EA5E9" />
            <path d="M22 34h22l-2.5 3.5H24.5L22 34z" fill="#991B1B" />
            {/* muatan cangkang */}
            <path d="M25.5 29.5c1-2.2 2.4-3.2 3.6-3.2s2.6 1 3.6 3.2h-7.2z" fill="#B45309" opacity=".9" />
        </svg>
    );
}

// 📊 Kalkulator / analitik
export function CalculatorColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="9" y="5" width="30" height="38" rx="4" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <rect x="13" y="9" width="22" height="9" rx="2" fill="#38BDF8" opacity=".85" />
            <path d="M16 15h10" stroke="#0C4A6E" strokeWidth="1.6" strokeLinecap="round" />
            <rect x="13" y="22" width="6" height="5" rx="1.4" fill="#64748B" />
            <rect x="21" y="22" width="6" height="5" rx="1.4" fill="#64748B" />
            <rect x="29" y="22" width="6" height="5" rx="1.4" fill="#F97316" />
            <rect x="13" y="30" width="6" height="5" rx="1.4" fill="#64748B" />
            <rect x="21" y="30" width="6" height="5" rx="1.4" fill="#64748B" />
            <rect x="29" y="30" width="6" height="12" rx="1.4" fill="#22C55E" />
            <rect x="13" y="37" width="14" height="5" rx="1.4" fill="#64748B" />
        </svg>
    );
}

// 👥 Manajemen user
export function UsersColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="18" cy="17" r="7" fill="#38BDF8" />
            <path d="M6 39c0-6.6 5.4-11 12-11s12 4.4 12 11v3H6v-3z" fill="#0EA5E9" />
            <circle cx="33" cy="19" r="5.5" fill="#A78BFA" />
            <path d="M25 42v-3c0-4 2-7.4 5.2-9.2 1-.5 2-.8 3.1-.8 5.4 0 9.7 3.8 9.7 9.4V42H25z" fill="#8B5CF6" />
        </svg>
    );
}

// ✅ Manajemen Tugas — papan kanban dengan kartu tercentang
export function TaskBoardIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="5" y="8" width="38" height="34" rx="4" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <rect x="9" y="13" width="10" height="24" rx="2" fill="#0EA5E9" opacity=".18" />
            <rect x="19" y="13" width="10" height="16" rx="2" fill="#22C55E" opacity=".18" />
            <rect x="29" y="13" width="10" height="10" rx="2" fill="#F59E0B" opacity=".18" />
            <rect x="10.5" y="16" width="7" height="4" rx="1.2" fill="#38BDF8" />
            <rect x="10.5" y="22" width="7" height="4" rx="1.2" fill="#38BDF8" />
            <rect x="20.5" y="16" width="7" height="4" rx="1.2" fill="#4ADE80" />
            <path d="M22 25.2l1.6 1.6 3-3.2" stroke="#16A34A" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
            <rect x="30.5" y="16" width="7" height="4" rx="1.2" fill="#FBBF24" />
        </svg>
    );
}

// 📚 Daftar Akun (COA)
export function AccountsColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="7" width="26" height="34" rx="3" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <path d="M13 15h16M13 22h16M13 29h11" stroke="#38BDF8" strokeWidth="2" strokeLinecap="round" />
            <rect x="30" y="18" width="10" height="23" rx="2" fill="#22C55E" />
            <path d="M35 23v13M31.5 29.5h7" stroke="#0F3D22" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}

// 📝 Jurnal
export function JournalColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="9" y="6" width="30" height="36" rx="3" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <rect x="9" y="6" width="7" height="36" rx="2" fill="#F97316" />
            <path d="M21 15h13M21 22h13M21 29h9" stroke="#38BDF8" strokeWidth="2" strokeLinecap="round" />
            <path d="M30 34l3-3 4 4-3 3h-4v-4z" fill="#FBBF24" />
        </svg>
    );
}

// 📒 Buku Besar
export function LedgerColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 8c0-1.7 1.3-3 3-3h24a3 3 0 013 3v32a3 3 0 01-3 3H13a3 3 0 01-3-3V8z" fill="#0EA5E9" />
            <rect x="16" y="9" width="21" height="30" rx="2" fill="#1E293B" />
            <path d="M20 16h13M20 23h13M20 30h8" stroke="#38BDF8" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

// 🏢 Aset Tetap
export function AssetColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="12" width="18" height="30" rx="2" fill="#38BDF8" />
            <rect x="26" y="20" width="14" height="22" rx="2" fill="#0EA5E9" />
            <path d="M12 18h4M18 18h4M12 24h4M18 24h4M12 30h4M18 30h4M30 26h6M30 32h6" stroke="#1E293B" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}

// 📊 Laporan Keuangan
export function ReportColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="6" width="32" height="36" rx="3" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <rect x="14" y="26" width="5" height="10" rx="1" fill="#38BDF8" />
            <rect x="21.5" y="20" width="5" height="16" rx="1" fill="#22C55E" />
            <rect x="29" y="14" width="5" height="22" rx="1" fill="#F97316" />
            <path d="M14 12h10" stroke="#64748B" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

// 🏭 Master Vendor
export function VendorColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 42V22l10 6V22l10 6V12l16 10v20H6z" fill="#0EA5E9" />
            <rect x="10" y="32" width="5" height="10" fill="#1E293B" />
            <rect x="20" y="32" width="5" height="10" fill="#1E293B" />
            <rect x="30" y="30" width="8" height="12" fill="#1E293B" />
            <circle cx="34" cy="10" r="3" fill="#22C55E" />
        </svg>
    );
}

// 🗂️ Dokumentasi (arsip terbit)
export function ArchiveColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="7" y="9" width="34" height="9" rx="2" fill="#0EA5E9" />
            <rect x="9" y="18" width="30" height="22" rx="2" fill="#1E293B" stroke="#475569" strokeWidth="1.5" />
            <rect x="18" y="24" width="12" height="4" rx="2" fill="#38BDF8" />
            <path d="M14 33h20" stroke="#64748B" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

// 📄 Dokumen Template
export function DocumentColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 5h16l7 7v31a2 2 0 01-2 2H13a2 2 0 01-2-2V7a2 2 0 012-2z" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <path d="M29 5v7h7" fill="#0EA5E9" opacity=".9" />
            <path d="M16 20h16M16 26h16M16 32h11" stroke="#38BDF8" strokeWidth="2" strokeLinecap="round" />
            <circle cx="33" cy="36" r="7" fill="#22C55E" />
            <path d="M30 36l2.2 2.2L37 33.5" stroke="#0F3D22" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

// 🧾 Pajak / Peredaran Bruto
export function TaxColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11 6h26v33l-4.3-3-4.3 3-4.4-3-4.3 3-4.4-3V6z" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <path d="M17 14h14M17 21h14" stroke="#38BDF8" strokeWidth="2" strokeLinecap="round" />
            <circle cx="20" cy="30" r="2.4" fill="#A78BFA" />
            <circle cx="28" cy="30" r="2.4" fill="#A78BFA" />
            <path d="M30 27l-12 8" stroke="#8B5CF6" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

// ✅ Manajemen Tugas (papan kanban + centang)
export function TaskBoardColorIcon({ className = 'w-6 h-6' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="6" y="8" width="36" height="32" rx="3" fill="#1E293B" stroke="#475569" strokeWidth="1.6" />
            <rect x="10" y="13" width="8" height="16" rx="1.5" fill="#38BDF8" opacity=".85" />
            <rect x="20" y="13" width="8" height="11" rx="1.5" fill="#A78BFA" opacity=".85" />
            <rect x="30" y="13" width="8" height="20" rx="1.5" fill="#F59E0B" opacity=".8" />
            <circle cx="34" cy="36" r="7" fill="#22C55E" />
            <path d="M31 36l2.2 2.2L38 33.5" stroke="#0F3D22" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
