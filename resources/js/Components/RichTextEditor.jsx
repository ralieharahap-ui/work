import { useEffect, useRef } from 'react';

/**
 * Editor WYSIWYG ringkas untuk isi dokumen & kertas kerja.
 *
 * Sengaja tanpa pustaka pihak ketiga: memakai contentEditable + execCommand
 * bawaan peramban, cukup untuk kebutuhan dokumen kantor (teks tebal, daftar,
 * tabel, perataan). Markup yang dihasilkan tetap disaring ulang di server.
 */

const BUTTONS = [
    { cmd: 'bold', label: 'B', title: 'Tebal', className: 'font-bold' },
    { cmd: 'italic', label: 'I', title: 'Miring', className: 'italic' },
    { cmd: 'underline', label: 'U', title: 'Garis bawah', className: 'underline' },
    { sep: true },
    { cmd: 'formatBlock', arg: '<h2>', label: 'H2', title: 'Judul bagian' },
    { cmd: 'formatBlock', arg: '<h3>', label: 'H3', title: 'Sub-judul' },
    { cmd: 'formatBlock', arg: '<p>', label: '¶', title: 'Paragraf biasa' },
    { sep: true },
    { cmd: 'insertUnorderedList', label: '•', title: 'Daftar butir' },
    { cmd: 'insertOrderedList', label: '1.', title: 'Daftar bernomor' },
    { sep: true },
    { cmd: 'justifyLeft', label: '⯇', title: 'Rata kiri' },
    { cmd: 'justifyCenter', label: '⯈⯇', title: 'Rata tengah' },
    { cmd: 'justifyFull', label: '☰', title: 'Rata kanan-kiri' },
];

export default function RichTextEditor({ value, onChange, disabled = false, minHeight = 380 }) {
    const ref = useRef(null);
    const lastPropValue = useRef(value);

    // Isi awal & pembaruan dari luar (mis. ganti dokumen) ditulis langsung ke
    // DOM; mengikat innerHTML tiap ketikan akan memindahkan kursor pengguna.
    useEffect(() => {
        if (ref.current && value !== lastPropValue.current) {
            lastPropValue.current = value;
            ref.current.innerHTML = value || '';
        }
    }, [value]);

    useEffect(() => {
        if (ref.current) ref.current.innerHTML = value || '';
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const emit = () => {
        const html = ref.current?.innerHTML ?? '';
        lastPropValue.current = html;
        onChange(html);
    };

    const exec = (cmd, arg) => {
        if (disabled) return;
        ref.current?.focus();
        document.execCommand(cmd, false, arg);
        emit();
    };

    const insertTable = () => {
        const cols = Number.parseInt(window.prompt('Jumlah kolom?', '3') ?? '', 10);
        const rows = Number.parseInt(window.prompt('Jumlah baris (di luar baris judul)?', '3') ?? '', 10);
        if (!cols || !rows || cols < 1 || rows < 1 || cols > 12 || rows > 60) return;

        const head = `<tr>${Array.from({ length: cols }, (_, i) => `<th>Kolom ${i + 1}</th>`).join('')}</tr>`;
        const body = Array.from({ length: rows }, () => `<tr>${'<td>&nbsp;</td>'.repeat(cols)}</tr>`).join('');

        exec(
            'insertHTML',
            `<table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse">${head}${body}</table><p><br></p>`,
        );
    };

    // Tempel sebagai teks biasa supaya markup asing (mis. dari Word) tidak
    // membawa gaya yang merusak tata letak dokumen.
    const handlePaste = (e) => {
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
        emit();
    };

    return (
        <div className="rounded-lg border border-black/10 overflow-hidden bg-white">
            {!disabled && (
                <div className="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-black/10 bg-warm-white sticky top-0 z-10">
                    {BUTTONS.map((b, i) =>
                        b.sep ? (
                            <span key={`s${i}`} className="w-px h-5 bg-black/10 mx-1" />
                        ) : (
                            <button
                                key={`${b.cmd}${b.arg || ''}`}
                                type="button"
                                title={b.title}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => exec(b.cmd, b.arg)}
                                className={`min-w-[30px] h-7 px-1.5 rounded text-xs text-[rgba(0,0,0,0.75)] hover:bg-black/5 transition-colors ${b.className || ''}`}
                            >
                                {b.label}
                            </button>
                        ),
                    )}
                    <span className="w-px h-5 bg-black/10 mx-1" />
                    <button
                        type="button"
                        title="Sisipkan tabel"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={insertTable}
                        className="h-7 px-2 rounded text-xs text-[rgba(0,0,0,0.75)] hover:bg-black/5 transition-colors"
                    >
                        ⊞ Tabel
                    </button>
                    <button
                        type="button"
                        title="Garis pemisah"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => exec('insertHorizontalRule')}
                        className="h-7 px-2 rounded text-xs text-[rgba(0,0,0,0.75)] hover:bg-black/5 transition-colors"
                    >
                        ― Garis
                    </button>
                    <button
                        type="button"
                        title="Hapus format"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => exec('removeFormat')}
                        className="h-7 px-2 rounded text-xs text-[rgba(0,0,0,0.75)] hover:bg-black/5 transition-colors"
                    >
                        ✕ Format
                    </button>
                </div>
            )}

            <div
                ref={ref}
                contentEditable={!disabled}
                suppressContentEditableWarning
                onInput={emit}
                onBlur={emit}
                onPaste={handlePaste}
                style={{ minHeight }}
                className={`evidence-editor p-5 text-[rgba(0,0,0,0.9)] text-sm leading-relaxed outline-none overflow-y-auto max-h-[52vh]
                    ${disabled ? 'bg-warm-white cursor-default' : 'bg-white'}`}
            />
        </div>
    );
}
