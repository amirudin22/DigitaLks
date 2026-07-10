# DigitLKS

Platform ujian berbasis web untuk sekolah — **aman, mobile-first, hybrid storage (JSON + MySQL), AI-powered question generator.**

## Fitur Utama

- 👥 **RBAC** — Guru, Administrator, Kepala Sekolah (bcrypt auth)
- 📝 **Buat Soal** — Upload file, import dari URL, atau generate otomatis via AI (Groq API)
- 📊 **Laporan Siswa** — Detail per siswa, statistik, CSV export, pagination
- 👨‍🎓 **Data Siswa** — CRUD siswa per guru, NIS-based
- 🔐 **Keamanan** — CSRF protection, rate limiting, prepared statements
- 📱 **Mobile-first** — Responsive sidebar, hamburger menu, scrollable tables
- 💬 **WhatsApp** — Kontak per-guru untuk notifikasi
- ⚡ **Hybrid Storage** — JSON sebagai primary, MySQL sebagai secondary (graceful degradation)

## Persyaratan

- PHP 8.0+
- MySQL 5.7+ (opsional — fallback ke JSON)
- Web server (Apache / Nginx)
- ekstensi: `curl`, `json`, `mbstring`, `pdo_mysql` (opsional)

## Instalasi

1. Clone repo ke webroot:
   ```bash
   git clone https://github.com/amirudin22/digitlks.git
   cd digitlks
   ```

2. Copy `.env.example` ke `.env` dan sesuaikan:
   ```bash
   cp .env.example .env
   nano .env
   ```

3. Buka browser, akses halaman utama → otomatis redirect ke **Install Wizard** untuk membuat akun Kepala Sekolah.

4. Selesai. Login dan mulai gunakan.

## Struktur Direktori

```
├── config/              # Konfigurasi aplikasi
├── database/            # Schema SQL
├── public/              # Web root
│   ├── admin/           # Panel admin
│   ├── api/             # API endpoint (import questions)
│   ├── s/               # Siswa interface (ujian)
│   ├── index.php        # Login
│   └── install.php      # Install wizard
├── src/Core/            # Core classes
│   ├── Auth.php         # RBAC authentication
│   ├── Security.php     # CSRF, rate limiting, PIN
│   ├── FileManager.php  # Hybrid JSON + MySQL storage
│   ├── Database.php     # PDO wrapper
│   ├── Scraper.php      # URL fetching
│   ├── Parser.php       # Question parser (HTML/text)
│   └── PromptGenerator.php  # AI prompt builder
├── storage/             # User data (JSON, config)
└── LICENSE              # MIT
```

## Konfigurasi `.env`

| Variable | Deskripsi | Default |
|----------|-----------|---------|
| `APP_NAME` | Nama aplikasi | DigitLKS |
| `APP_TIMEZONE` | Zona waktu | Asia/Jakarta |
| `AUTH_USERS` | User awal (format: `user:pass,user:pass`) | — |
| `DB_HOST` | Host MySQL | localhost |
| `DB_NAME` | Nama database | — |
| `DB_USER` | User MySQL | — |
| `DB_PASS` | Password MySQL | — |
| `GROQ_API_KEY` | API key Groq (AI generator) | — |
| `GROQ_MODEL` | Model Groq | mixtral-8x7b-32768 |
| `PIN_MAX_ATTEMPTS` | Maks percobaan PIN | 3 |
| `PIN_LOCK_MINUTES` | Lock time (menit) | 10 |

## License

MIT — silakan gunakan, modifikasi, dan distribusikan.
