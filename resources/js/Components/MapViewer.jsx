import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(parseFloat(n));

/* ── Ilustrasi mini di dalam pin (area 26×26) ────────────────────────── */

// 🌴 Tandan buah segar + cangkang sawit
const ART_SOURCE = `
  <path d="M13 4C10 1 6 1 4 3c3 0 5 1 7 3z" fill="#16A34A"/>
  <path d="M13 4c3-3 7-3 9-1-3 0-5 1-7 3z" fill="#16A34A"/>
  <path d="M13 4C12 1.5 13 .5 14 0c-.5 1.5-.4 2.6-.2 3.6z" fill="#22C55E"/>
  <circle cx="9" cy="12" r="4.1" fill="#DC2626"/>
  <circle cx="16" cy="11.5" r="4.3" fill="#EA580C"/>
  <circle cx="12.5" cy="16" r="4.1" fill="#F97316"/>
  <circle cx="7.8" cy="10.8" r="1.2" fill="#FCA5A5"/>
  <circle cx="14.8" cy="10.3" r="1.2" fill="#FED7AA"/>
  <circle cx="11.3" cy="14.8" r="1.1" fill="#FFEDD5"/>
  <ellipse cx="6.5" cy="21.5" rx="3" ry="2" fill="#78350F"/>
  <ellipse cx="12.8" cy="22.3" rx="3.5" ry="2.2" fill="#92400E"/>
  <ellipse cx="19" cy="21.5" rx="3" ry="2" fill="#78350F"/>
  <ellipse cx="6" cy="21" rx="1" ry=".6" fill="#A16207" opacity=".8"/>
  <ellipse cx="12.3" cy="21.8" rx="1.1" ry=".7" fill="#A16207" opacity=".8"/>
`;

// 🏭 Fasilitas industri / PLTU
const ART_INDUSTRY = `
  <circle cx="6" cy="3.6" r="2.2" fill="#CBD5E1" opacity=".85"/>
  <circle cx="9.8" cy="1.8" r="1.5" fill="#E2E8F0" opacity=".75"/>
  <circle cx="14.5" cy="3" r="1.7" fill="#CBD5E1" opacity=".6"/>
  <rect x="3.6" y="6.5" width="4.8" height="15.5" fill="#E2E8F0"/>
  <rect x="3.6" y="9" width="4.8" height="2" fill="#EF4444"/>
  <rect x="3.6" y="13" width="4.8" height="2" fill="#EF4444"/>
  <rect x="10.6" y="8.5" width="4.2" height="13.5" fill="#CBD5E1"/>
  <rect x="10.6" y="10.8" width="4.2" height="1.8" fill="#F97316"/>
  <rect x="16.4" y="12.5" width="7" height="9.5" fill="#94A3B8"/>
  <path d="M16.4 12.5l3.5-2.6 3.5 2.6h-7z" fill="#64748B"/>
  <rect x="17.8" y="15" width="2" height="2.2" fill="#FDE68A"/>
  <rect x="20.6" y="15" width="2" height="2.2" fill="#FDE68A"/>
  <rect x="17.8" y="18.4" width="2" height="2.2" fill="#FDE68A"/>
  <path d="M1.5 22h23" stroke="#475569" stroke-width="2.4" stroke-linecap="round"/>
`;

// 🚢 Dermaga / kapal
const ART_JETTY = `
  <path d="M1.5 19.5h23c-1 3-2.2 4.5-4 4.5H5.5c-1.8 0-3-1.5-4-4.5z" fill="#1E40AF"/>
  <path d="M4 19.5l2.2-5.5h12l3 5.5H4z" fill="#DC2626"/>
  <rect x="8" y="7.5" width="8.5" height="6.5" rx="1" fill="#F1F5F9"/>
  <rect x="9.4" y="9.2" width="2.2" height="2.2" fill="#0EA5E9"/>
  <rect x="12.8" y="9.2" width="2.2" height="2.2" fill="#0EA5E9"/>
  <path d="M17.5 7.5v-4" stroke="#94A3B8" stroke-width="1.2" stroke-linecap="round"/>
  <path d="M7.5 17.5c1-2 2.2-2.8 3.2-2.8s2.2.8 3.2 2.8H7.5z" fill="#B45309"/>
  <path d="M1 24c1.6 0 1.6 1.2 3.2 1.2S5.8 24 7.4 24s1.6 1.2 3.2 1.2S12.2 24 13.8 24s1.6 1.2 3.2 1.2S18.6 24 20.2 24s1.6 1.2 3.2 1.2"
        stroke="#38BDF8" stroke-width="1.5" fill="none" stroke-linecap="round" opacity=".9"/>
`;

const PIN = {
    source:    { color: '#16A34A', glow: 'rgba(34,197,94,.55)',  art: ART_SOURCE },
    unloading: { color: '#DC2626', glow: 'rgba(239,68,68,.55)',  art: ART_INDUSTRY },
    jetty:     { color: '#2563EB', glow: 'rgba(59,130,246,.55)', art: ART_JETTY },
};

const createMarkerIcon = (type, delay = 0) => {
    const p = PIN[type] || PIN.source;
    return L.divIcon({
        className: 'gep-marker-wrap',
        html: `
          <div class="gep-marker" style="--pin-glow:${p.glow}; animation-delay:${delay}ms">
            <svg width="46" height="58" viewBox="0 0 46 58" xmlns="http://www.w3.org/2000/svg">
              <ellipse class="gep-marker-shadow" cx="23" cy="54" rx="8" ry="3" fill="rgba(0,0,0,.35)"/>
              <path d="M23 53S4.5 31.5 4.5 20.5a18.5 18.5 0 1 1 37 0C41.5 31.5 23 53 23 53z"
                    fill="${p.color}" stroke="#fff" stroke-width="2.5" stroke-linejoin="round"/>
              <circle cx="23" cy="20" r="14" fill="#F8FAFC"/>
              <g transform="translate(10,7)">${p.art}</g>
            </svg>
          </div>`,
        iconSize: [46, 58],
        iconAnchor: [23, 53],
        tooltipAnchor: [0, -50],
    });
};

const coord = (o) => [parseFloat(o.latitude), parseFloat(o.longitude)];
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);

const tip = (rows) => `<div class="gep-tip">${rows.filter(Boolean).join('')}</div>`;

export default function MapViewer({
    sources = [],
    unloadingPoints = [],
    jettyPoints = [],
    showRoutes = false,
    clickable = true,
    center = null,
    zoom = 5,
}) {
    const mapContainer = useRef(null);
    const map = useRef(null);
    const layersRef = useRef([]);

    useEffect(() => {
        if (!mapContainer.current) return;

        if (!map.current) {
            map.current = L.map(mapContainer.current, { zoomAnimation: true, fadeAnimation: true })
                .setView(center || [-0.789275, 113.921327], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
            }).addTo(map.current);
        }

        layersRef.current.forEach((l) => l.remove());
        layersRef.current = [];
        const add = (layer) => { layer.addTo(map.current); layersRef.current.push(layer); };

        /** Marker + tooltip hover + klik → halaman detail.
         *  Jeda animasi dibatasi agar tetap cepat walau titiknya banyak. */
        const addMarker = (item, type, url, rows, order) => {
            const delay = Math.min(order * 45, 800);
            const m = L.marker(coord(item), {
                icon: createMarkerIcon(type, delay),
                riseOnHover: true,
                keyboard: true,
                title: item.name,
            });
            m.bindTooltip(tip(rows), {
                direction: 'top',
                offset: [0, -6],
                opacity: 1,
                className: 'gep-tooltip',
            });
            if (clickable && url) {
                m.on('click', () => router.visit(url));
                m.on('add', () => {
                    const el = m.getElement();
                    if (el) el.style.cursor = 'pointer';
                });
            }
            add(m);
        };

        // ── Garis rute animasi: tiap sumber → titik bongkar terdekat
        if (showRoutes && sources.length && unloadingPoints.length) {
            sources.forEach((s) => {
                const from = coord(s);
                let nearest = null, best = Infinity;
                unloadingPoints.forEach((u) => {
                    const d = dist(from, coord(u));
                    if (d < best) { best = d; nearest = u; }
                });
                if (nearest) {
                    add(L.polyline([from, coord(nearest)], {
                        color: '#34d399', weight: 2.5, opacity: .65,
                        dashArray: '8 10', className: 'gep-route',
                    }));
                }
            });
        }

        // 🟢 Sumber cangkang — tandan buah & cangkang
        sources.forEach((s, i) => {
            const low = parseFloat(s.stock_volume) <= parseFloat(s.stock_min);
            addMarker(s, 'source', `/palm-oil-sources/${s.id}`, [
                `<p class="gep-tip-title">${s.name}</p>`,
                `<p class="gep-tip-sub">📍 ${s.city}, ${s.province}</p>`,
                s.supplier_name ? `<p class="gep-tip-sub">🏢 ${s.supplier_name}</p>` : '',
                `<p class="${low ? 'gep-tip-red' : 'gep-tip-green'}">🌴 Stok ${fmt(s.stock_volume)} ${s.unit}${low ? ' — rendah!' : ''}</p>`,
                `<p class="gep-tip-cta">Klik untuk lihat detail →</p>`,
            ], i * 60);
        });

        // 🔴 Titik bongkar (customer) — fasilitas industri / PLTU
        unloadingPoints.forEach((p, i) => {
            addMarker(p, 'unloading', `/unloading-points/${p.id}`, [
                `<p class="gep-tip-title">${p.name}</p>`,
                p.customer_name ? `<p class="gep-tip-sub">🏭 ${p.customer_name}</p>` : '',
                `<p class="gep-tip-sub">📍 ${p.city}, ${p.province}</p>`,
                p.has_jetty ? `<p class="gep-tip-blue">⚓ Ada dermaga${p.jetty_name ? ': ' + p.jetty_name : ''}</p>` : '',
                p.capacity ? `<p class="gep-tip-red">📦 Kapasitas ${fmt(p.capacity)} ${p.unit || 'ton'}</p>` : '',
                `<p class="gep-tip-cta">Klik untuk lihat detail →</p>`,
            ], (sources.length + i) * 60);
        });

        // 🔵 Titik dermaga
        jettyPoints.forEach((j, i) => {
            addMarker(j, 'jetty', `/jetty-points/${j.id}`, [
                `<p class="gep-tip-title">${j.name}</p>`,
                j.operator ? `<p class="gep-tip-sub">🏢 ${j.operator}</p>` : '',
                `<p class="gep-tip-sub">📍 ${j.city}, ${j.province}</p>`,
                j.capacity ? `<p class="gep-tip-blue">⚓ Sandar ${fmt(j.capacity)} ${j.unit || 'ton'}</p>` : '',
                `<p class="gep-tip-cta">Klik untuk lihat detail →</p>`,
            ], (sources.length + unloadingPoints.length + i) * 60);
        });

        const markers = layersRef.current.filter((l) => l instanceof L.Marker);
        if (markers.length > 0) {
            map.current.fitBounds(L.featureGroup(markers).getBounds().pad(0.18), { animate: true });
        }
    }, [sources, unloadingPoints, jettyPoints, showRoutes, clickable, center, zoom]);

    // Sesuaikan ukuran peta saat wadah berubah (rotasi layar, buka/tutup menu, resize jendela).
    // Tanpa ini Leaflet memakai ukuran lama sehingga ubin & marker meleset.
    useEffect(() => {
        if (!mapContainer.current) return;
        const ro = new ResizeObserver(() => map.current?.invalidateSize({ animate: false }));
        ro.observe(mapContainer.current);
        const onOrient = () => setTimeout(() => map.current?.invalidateSize({ animate: false }), 250);
        window.addEventListener('orientationchange', onOrient);
        return () => { ro.disconnect(); window.removeEventListener('orientationchange', onOrient); };
    }, []);

    return (
        <div className="w-full rounded-xl overflow-hidden border border-slate-700 ring-1 ring-black/20 animate-fade-in">
            <div ref={mapContainer} className="w-full h-[clamp(280px,52vh,620px)]" />
        </div>
    );
}
