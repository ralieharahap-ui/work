import { useEffect, useRef, useState, forwardRef, useImperativeHandle } from 'react';
import { ArrowUturnLeftIcon, TrashIcon } from '@heroicons/react/24/outline';

/**
 * Kanvas tanda tangan: mendukung mouse, stylus, dan sentuhan layar.
 *
 * Hasilnya diambil lewat ref: `ref.current.toDataURL()` → PNG data URL, atau
 * null bila belum ada goresan sama sekali.
 */
const SignaturePad = forwardRef(function SignaturePad({ height = 190, onChange }, ref) {
    const canvasRef = useRef(null);
    const strokesRef = useRef([]);     // [[{x,y}, …], …] disimpan dalam satuan CSS pixel
    const drawingRef = useRef(false);
    const [isEmpty, setIsEmpty] = useState(true);

    // Gambar ulang seluruh goresan — dipakai saat resize maupun undo.
    const redraw = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ratio = window.devicePixelRatio || 1;
        const ctx = canvas.getContext('2d');

        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.scale(ratio, ratio);

        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';

        strokesRef.current.forEach((stroke) => {
            if (stroke.length === 0) return;
            ctx.beginPath();
            ctx.moveTo(stroke[0].x, stroke[0].y);
            stroke.forEach((p) => ctx.lineTo(p.x, p.y));
            // Titik tunggal tetap terlihat sebagai noktah.
            if (stroke.length === 1) ctx.lineTo(stroke[0].x + 0.1, stroke[0].y + 0.1);
            ctx.stroke();
        });
    };

    // Samakan resolusi kanvas dengan ukuran tampilannya agar goresan tidak buram.
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const resize = () => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.max(1, Math.floor(rect.width * ratio));
            canvas.height = Math.max(1, Math.floor(rect.height * ratio));
            redraw();
        };

        resize();
        const observer = new ResizeObserver(resize);
        observer.observe(canvas);
        return () => observer.disconnect();
    }, []);

    const pointFrom = (e) => {
        const rect = canvasRef.current.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    };

    const start = (e) => {
        e.preventDefault();
        canvasRef.current.setPointerCapture?.(e.pointerId);
        drawingRef.current = true;
        strokesRef.current.push([pointFrom(e)]);
        redraw();
    };

    const move = (e) => {
        if (!drawingRef.current) return;
        e.preventDefault();
        strokesRef.current[strokesRef.current.length - 1].push(pointFrom(e));
        redraw();
    };

    const end = () => {
        if (!drawingRef.current) return;
        drawingRef.current = false;
        const empty = strokesRef.current.length === 0;
        setIsEmpty(empty);
        onChange?.(!empty);
    };

    const clear = () => {
        strokesRef.current = [];
        redraw();
        setIsEmpty(true);
        onChange?.(false);
    };

    const undo = () => {
        strokesRef.current.pop();
        redraw();
        const empty = strokesRef.current.length === 0;
        setIsEmpty(empty);
        onChange?.(!empty);
    };

    useImperativeHandle(ref, () => ({
        isEmpty: () => strokesRef.current.length === 0,
        clear,
        toDataURL: () => (strokesRef.current.length === 0 ? null : canvasRef.current.toDataURL('image/png')),
    }));

    return (
        <div>
            <div className="relative rounded-lg border-2 border-dashed border-black/15 bg-white overflow-hidden">
                <canvas
                    ref={canvasRef}
                    style={{ height, touchAction: 'none' }}
                    className="w-full block cursor-crosshair"
                    onPointerDown={start}
                    onPointerMove={move}
                    onPointerUp={end}
                    onPointerLeave={end}
                    onPointerCancel={end}
                />
                {isEmpty && (
                    <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-warm-300">
                        Bubuhkan tanda tangan di sini
                    </span>
                )}
                <span className="pointer-events-none absolute left-6 right-6 bottom-8 border-b border-black/15" />
            </div>

            <div className="flex justify-end gap-2 mt-2">
                <button
                    type="button"
                    onClick={undo}
                    disabled={isEmpty}
                    className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded border border-black/10 text-[rgba(0,0,0,0.7)] hover:bg-warm-white disabled:opacity-40"
                >
                    <ArrowUturnLeftIcon className="w-3.5 h-3.5" /> Batalkan goresan
                </button>
                <button
                    type="button"
                    onClick={clear}
                    disabled={isEmpty}
                    className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded border border-black/10 text-red-600 hover:bg-red-50 disabled:opacity-40"
                >
                    <TrashIcon className="w-3.5 h-3.5" /> Bersihkan
                </button>
            </div>
        </div>
    );
});

export default SignaturePad;
