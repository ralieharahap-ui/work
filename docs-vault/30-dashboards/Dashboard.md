---
title: Dashboard Vault
aliases: [Dashboard]
tags: [moc]
type: dashboard
created: 2026-08-16
updated: 2026-08-16
status: evergreen
related: ["[[Home]]"]
---
# 📊 Dashboard Vault

Ringkasan otomatis seluruh vault. Membutuhkan plugin **Dataview** aktif di Obsidian (Community Plugins → cari "Dataview" → Enable). Semua query di bawah membaca Properties (frontmatter) langsung dari root — bukan bersarang — karena vault ini sengaja disimpan di `docs-vault/` agar kompatibel penuh.

## Catatan terbaru (7 hari terakhir)
```dataview
TABLE updated, tags, type
FROM "docs-vault" OR "10-notes" OR "20-mocs"
WHERE updated >= date(today) - dur(7 days)
SORT updated DESC
```

## Seluruh catatan per Tag
### 📒 Akuntansi
```dataview
LIST
FROM #akuntansi
WHERE type != "moc"
SORT file.name ASC
```

### 📄 Dokumen
```dataview
LIST
FROM #dokumen
WHERE type != "moc"
SORT file.name ASC
```

### 🚚 Supply Chain
```dataview
LIST
FROM #supply-chain
WHERE type != "moc"
SORT file.name ASC
```

### 👤 User & Role
```dataview
LIST
FROM #user-role
WHERE type != "moc"
SORT file.name ASC
```

### 🛠️ Teknis
```dataview
LIST
FROM #teknis
WHERE type != "moc"
SORT file.name ASC
```

## Semua MOC
```dataview
TABLE aliases, updated
WHERE type = "moc"
SORT file.name ASC
```

## Status per catatan (seedling / growing / evergreen)
```dataview
TABLE status, tags
WHERE type = "note"
SORT status ASC, file.name ASC
```

## Catatan berpotensi orphan (tanpa related & tanpa inlink)
```dataview
LIST
WHERE type = "note" AND length(file.inlinks) = 0 AND (!related OR length(related) = 0)
```

## Semua Referensi (type: note dengan tag "referensi")
```dataview
TABLE tags
FROM #referensi
SORT file.name ASC
```

---
*Catatan: query `LIST WHERE length(file.outlinks) = 0` untuk cek link keluar tidak disertakan di sini karena hampir semua catatan sudah bertaut ke MOC induk via bagian `related`; cek manual berkala tetap disarankan lewat Graph View (Ctrl/Cmd+G).*
