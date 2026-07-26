import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const fmt = (n) => new Intl.NumberFormat('id-ID').format(parseFloat(n));

const MARKER_STYLE = {
    source:    'bg-emerald-500 border-emerald-300',   // sumber cangkang
    unloading: 'bg-red-500 border-red-300',           // titik bongkar (customer)
    jetty:     'bg-blue-500 border-blue-300',          // titik dermaga (kapal)
    pltu:      'bg-amber-500 border-amber-300',        // PLTU
};

const SHIP_SVG = `<svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M20 21c-1.39 0-2.78-.47-4-1.32-2.44 1.71-5.56 1.71-8 0C6.78 20.53 5.39 21 4 21H2v2h2c1.38 0 2.74-.35 4-.99 2.52 1.29 5.48 1.29 8 0 1.26.65 2.62.99 4 .99h2v-2h-2zM3.95 19H4c1.6 0 3.02-.88 4-2 .98 1.12 2.4 2 4 2s3.02-.88 4-2c.98 1.12 2.4 2 4 2h.05l1.89-6.68c.08-.26.06-.54-.06-.78s-.34-.42-.6-.5L20 10.62V6c0-1.1-.9-2-2-2h-3V1H9v3H6c-1.1 0-2 .9-2 2v4.62l-1.29.42c-.26.08-.48.26-.6.5s-.15.52-.06.78L3.95 19zM6 6h12v3.97L12 8 6 9.97V6z"/></svg>`;
const DOT_SVG = `<svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd" /></svg>`;

const createMarkerIcon = (type) => L.divIcon({
    className: 'custom-marker',
    html: `<div class="flex items-center justify-center w-8 h-8 rounded-full shadow-lg ring-2 ring-black/20 border-2 ${MARKER_STYLE[type] || MARKER_STYLE.source}">${type === 'jetty' ? SHIP_SVG : DOT_SVG}</div>`,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16],
});

const coord = (o) => [parseFloat(o.latitude), parseFloat(o.longitude)];
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);

export default function MapViewer({
    sources = [],
    unloadingPoints = [],
    jettyPoints = [],
    pltuLocations = [],
    showRoutes = false,
    center = null,
    zoom = 5,
}) {
    const mapContainer = useRef(null);
    const map = useRef(null);
    const layersRef = useRef([]);

    useEffect(() => {
        if (!mapContainer.current) return;

        if (!map.current) {
            map.current = L.map(mapContainer.current).setView(center || [-0.789275, 113.921327], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
            }).addTo(map.current);
        }

        layersRef.current.forEach((l) => l.remove());
        layersRef.current = [];

        const add = (layer) => { layer.addTo(map.current); layersRef.current.push(layer); };

        // 🔗 Garis rute: tiap sumber → titik bongkar terdekat
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
                        color: '#34d399', weight: 2, opacity: 0.55, dashArray: '6 8',
                    }));
                }
            });
        }

        // 🟢 Sumber
        sources.forEach((s) => {
            const m = L.marker(coord(s), { icon: createMarkerIcon('source') });
            const low = parseFloat(s.stock_volume) <= parseFloat(s.stock_min);
            m.bindPopup(`<div class="text-sm min-w-[150px]">
                <p class="font-bold text-white mb-0.5">${s.name}</p>
                <p class="text-slate-400 text-xs">📍 ${s.city}, ${s.province}</p>
                <p class="${low ? 'text-red-400' : 'text-emerald-400'} font-semibold mt-1">🟢 ${fmt(s.stock_volume)} ${s.unit}</p>
                ${s.supplier_name ? `<p class="text-slate-400 text-xs mt-0.5">${s.supplier_name}</p>` : ''}
            </div>`);
            add(m);
        });

        // 🔴 Titik bongkar
        unloadingPoints.forEach((p) => {
            const m = L.marker(coord(p), { icon: createMarkerIcon('unloading') });
            m.bindPopup(`<div class="text-sm min-w-[150px]">
                <p class="font-bold text-white mb-0.5">🔴 ${p.name}</p>
                ${p.customer_name ? `<p class="text-slate-300 text-xs">${p.customer_name}</p>` : ''}
                <p class="text-slate-400 text-xs">📍 ${p.city}, ${p.province}</p>
                ${p.has_jetty ? `<p class="text-blue-400 text-xs mt-0.5">⚓ Ada dermaga${p.jetty_name ? ': ' + p.jetty_name : ''}</p>` : ''}
                ${p.capacity ? `<p class="text-red-400 text-xs font-medium mt-1">📦 ${fmt(p.capacity)} ton</p>` : ''}
            </div>`);
            add(m);
        });

        // 🔵 Titik dermaga (kapal biru)
        jettyPoints.forEach((j) => {
            const m = L.marker(coord(j), { icon: createMarkerIcon('jetty') });
            m.bindPopup(`<div class="text-sm min-w-[150px]">
                <p class="font-bold text-white mb-0.5">🚢 ${j.name}</p>
                ${j.operator ? `<p class="text-slate-300 text-xs">${j.operator}</p>` : ''}
                <p class="text-slate-400 text-xs">📍 ${j.city}, ${j.province}</p>
                ${j.capacity ? `<p class="text-blue-400 text-xs font-medium mt-1">⚓ Sandar ${fmt(j.capacity)} ton</p>` : ''}
            </div>`);
            add(m);
        });

        // 🟠 PLTU
        pltuLocations.forEach((pltu) => {
            const m = L.marker(coord(pltu), { icon: createMarkerIcon('pltu') });
            m.bindPopup(`<div class="text-sm min-w-[150px]">
                <p class="font-bold text-white mb-0.5">🟠 ${pltu.name}</p>
                <p class="text-slate-400 text-xs">📍 ${pltu.city}, ${pltu.province}</p>
                ${pltu.capacity ? `<p class="text-amber-400 text-xs font-medium mt-1">⚡ ${pltu.capacity} MW</p>` : ''}
            </div>`);
            add(m);
        });

        const markers = layersRef.current.filter((l) => l instanceof L.Marker);
        if (markers.length > 0) {
            map.current.fitBounds(L.featureGroup(markers).getBounds().pad(0.15));
        }
    }, [sources, unloadingPoints, jettyPoints, pltuLocations, showRoutes, center, zoom]);

    return (
        <div className="w-full rounded-xl overflow-hidden border border-slate-700 ring-1 ring-black/20">
            <div ref={mapContainer} className="w-full h-[380px] sm:h-[460px] lg:h-[520px]" />
        </div>
    );
}
