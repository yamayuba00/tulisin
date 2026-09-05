# AI Agents — Tulisin

Folder ini berisi panduan (guide) untuk setiap AI/agent di project **Tulisin**.
Setiap agent punya satu file panduan sendiri. Panduan ini harus dibaca & dipatuhi
oleh agent yang mengerjakan fitur terkait agar konsisten, stabil, dan tidak merusak
fungsi project yang sudah berjalan.

## Daftar Agent

| Agent | File | Tanggung jawab |
| --- | --- | --- |
| Agent AI (pembuat project) | [agent-project.md](agent-project.md) | Membantu user membuat struktur project dokumen dari deskripsi (judul, jenis, bab). |
| Agent AI Canvas (editor dokumen) | [agent-canvas.md](agent-canvas.md) | Modal dari header; memenuhi kebutuhan blok di canvas aktif. Tidak lepas dari canvas & tidak membaca canvas orang lain. |
| AI Academic Co-Pilot | [ai-co-pilot.md](ai-co-pilot.md) | Asisten sidebar kanan, menyusun/menyunting isi blok **per halaman**. |
| AI Optimizer (Turnitin & Plagiarism) | [ai-optimizer.md](ai-optimizer.md) | Menurunkan kemiripan AI & plagiarisme. Turnitin 20 kredit, Plagiarism 1 kredit. |
| Semantic Scholar (riset) | [semantic-scholar.md](semantic-scholar.md) | Mencari paper/jurnal referensi akademik. |

## Pedoman bersama

Semua agent WAJIB membaca [pedoman-umum.md](pedoman-umum.md) sebelum bekerja.
Isinya aturan lintas-fitur: autentikasi, wallet/kredit, penyimpanan project,
struktur blok, dan responsivitas.

## Prompt (landasan canvas)

Setiap agent yang bekerja di canvas punya **system prompt** yang menjadi landasan
perilakunya. Prompt ini membaca `{project_uuid}` (UUID canvas aktif) dan isi canvas
(`{canvas_content}` / `{page_content}` / `{target_text}`), lalu membatasi hal-hal
yang tidak perlu.

| Agent | Prompt | Variabel konteks |
| --- | --- | --- |
| Agent AI Canvas | [agent-canvas-prompt.md](agent-canvas-prompt.md) | `{project_uuid}`, `{canvas_content}` |
| AI Academic Co-Pilot | [ai-co-pilot-prompt.md](ai-co-pilot-prompt.md) | `{project_uuid}`, `{page_content}` |
| Turnitin AI Optimizer | [turnitin-ai-prompt.md](turnitin-ai-prompt.md) | `{project_uuid}`, `{canvas_content}` |
| Plagiarism Optimizer | [plagiarism-prompt.md](plagiarism-prompt.md) | `{project_uuid}`, `{target_text}` |

## Prinsip utama

1. Jangan merusak fitur yang sudah jalan (auth, wallet, builder, project list).
2. Kredit dipotong lewat API `/api/wallet/spend`, bukan langsung dari DB/frontend.
3. Data project memakai `localStorage` dengan indeks ringan `tulisin:projects`.
4. UI harus responsif (mobile & desktop) dan memakai komponen reusable yang ada.
