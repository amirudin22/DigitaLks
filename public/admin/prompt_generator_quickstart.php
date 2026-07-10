<?php

/**
 * QUICK START - JSON Prompt Generator
 * 
 * Short guide untuk mulai gunakan JSON Prompt Generator
 */

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigitLks - Prompt Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .step-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .step {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            line-height: 32px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .step h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
        }

        .step p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .flow-diagram {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .btn {
            display: inline-block;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
            font-size: 15px;
            color: #333;
        }

        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .comparison-table th,
        .comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .comparison-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }

        .comparison-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-new {
            background: #ffc107;
            color: #333;
        }

        .badge-recommended {
            background: #28a745;
            color: white;
        }

        @media (max-width: 768px) {
            .step-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🤖 JSON Prompt Generator</h1>
            <p>Generate deterministic prompt untuk AI dalam 5 menit</p>
        </div>

        <!-- Quick Start -->
        <div class="card">
            <h2>⚡ Quick Start (5 Menit)</h2>

            <div class="step-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Klik Tombol Generate</h3>
                    <p>Buka form di halaman generator, isi kelas, mapel, dan materi</p>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Generate Prompt</h3>
                    <p>Klik tombol "🚀 Generate Prompt", sistem akan create instruction untuk AI</p>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Copy ke AI</h3>
                    <p>Copy prompt yang sudah di-generate dan paste ke QWEN/Claude/ChatGPT</p>
                </div>

                <div class="step">
                    <div class="step-number">4</div>
                    <h3>AI Generate Soal</h3>
                    <p>Tunggu AI generate soal sesuai format instruction yang diberikan</p>
                </div>

                <div class="step">
                    <div class="step-number">5</div>
                    <h3>Copy Hasil</h3>
                    <p>Copy output soal dari AI (biasanya sudah formatted dengan benar)</p>
                </div>

                <div class="step">
                    <div class="step-number">6</div>
                    <h3>Paste ke Platform</h3>
                    <p>Paste hasil soal di "Paste Text Mode" → auto-parse → done!</p>
                </div>
            </div>

            <div class="info-box">
                <strong>💡 Pro Tip:</strong> Rata-rata waktu per step adalah 1 menit. Total workflow kurang dari 10
                menit!
            </div>
        </div>

        <!-- Alur Lengkap -->
        <div class="card">
            <h2>📊 Alur Kerja Lengkap</h2>

            <div class="flow-diagram">
                ┌─────────────────────────────────────┐
                │ 1. PROMPT GENERATOR │
                │ ├─ Input: Kelas, Mapel, Materi │
                │ ├─ Output: JSON Prompt │
                │ └─ Export: Copy/Download │
                └────────────┬────────────────────────┘
                │
                ↓
                ┌──────────────────────────────────────────┐
                │ 2. AI GENERATION (QWEN/Claude/ChatGPT) │
                │ ├─ Input: Paste prompt │
                │ ├─ Processing: AI generate soal │
                │ └─ Output: Soal formatted │
                └────────────┬─────────────────────────────┘
                │
                ↓
                ┌─────────────────────────────────────┐
                │ 3. PASTE TEXT MODE │
                │ ├─ Input: Paste output AI │
                │ ├─ Processing: Auto-parse │
                │ └─ Output: Ready untuk ujian │
                └────────────┬────────────────────────┘
                │
                ↓
                ┌─────────────────────────────────────┐
                │ 4. QUIZ SYSTEM │
                │ ├─ Setup: Timer, Pin, Rules │
                │ ├─ Deploy: Live untuk siswa │
                │ └─ Analytics: Track hasil │
                └─────────────────────────────────────┘
            </div>
        </div>

        <!-- Fitur Utama -->
        <div class="card">
            <h2>✨ Fitur Utama</h2>

            <ul class="feature-list">
                <li><strong>Deterministic Output</strong> - Prompt konsisten dan reproducible</li>
                <li><strong>Subject-Specific Templates</strong> - Template unik untuk setiap mata pelajaran</li>
                <li><strong>Automatic Distribution</strong> - Hitung distribusi kesulitan otomatis</li>
                <li><strong>Quality Control Checklist</strong> - 8 item checklist untuk QA</li>
                <li><strong>Format Guidance</strong> - Instruksi EXACT untuk AI follow</li>
                <li><strong>Multi-Export</strong> - Export JSON atau Text untuk berbagai kebutuhan</li>
                <li><strong>Zero Setup Time</strong> - Langsung pakai, tidak perlu konfigurasi rumit</li>
            </ul>
        </div>

        <!-- Perbandingan Mode -->
        <div class="card">
            <h2>🔄 Perbandingan dengan Paste Text Mode</h2>

            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Aspek</th>
                        <th>Prompt Generator <span class="badge badge-new">NEW</span></th>
                        <th>Paste Text Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Input Method</strong></td>
                        <td>Form (Kelas, Mapel, Materi, dll)</td>
                        <td>Paste text soal langsung</td>
                    </tr>
                    <tr>
                        <td><strong>AI Integration</strong></td>
                        <td>✅ Integrate dengan Claude/ChatGPT</td>
                        <td>❌ Manual input</td>
                    </tr>
                    <tr>
                        <td><strong>Best For</strong></td>
                        <td>Generate soal baru berkualitas</td>
                        <td>Import soal yang sudah ada</td>
                    </tr>
                    <tr>
                        <td><strong>Setup Time</strong></td>
                        <td>5 menit</td>
                        <td>2 menit</td>
                    </tr>
                    <tr>
                        <td><strong>QA/QC</strong></td>
                        <td>✅ Comprehensive Checklist</td>
                        <td>✅ Format Validation</td>
                    </tr>
                    <tr>
                        <td><strong>Template</strong></td>
                        <td>✅ Provided</td>
                        <td>❌ Self-create</td>
                    </tr>
                </tbody>
            </table>

            <div class="info-box">
                <strong>📌 Rekomendasi:</strong> Gunakan keduanya! Prompt Generator untuk generate soal baru, Paste Text
                Mode untuk import soal existing.
            </div>
        </div>

        <!-- Parameter Guide -->
        <div class="card">
            <h2>📋 Parameter Reference</h2>

            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Opsi Tersedia</th>
                        <th>Rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Kelas</strong></td>
                        <td>SD 1-6, SMP 7-9, SMA 10-12</td>
                        <td>Sesuaikan dengan level siswa</td>
                    </tr>
                    <tr>
                        <td><strong>Mata Pelajaran</strong></td>
                        <td>6 mapel (Matematika, IPA, IPS, dll)</td>
                        <td>Pilih mapel utama</td>
                    </tr>
                    <tr>
                        <td><strong>Kurikulum</strong></td>
                        <td>K13, Merdeka, AADC, Custom</td>
                        <td>K13 atau Merdeka</td>
                    </tr>
                    <tr>
                        <td><strong>Tingkat Kesulitan</strong></td>
                        <td>Mudah, Sedang, Sulit, Campuran</td>
                        <td>Sedang untuk latihan umum</td>
                    </tr>
                    <tr>
                        <td><strong>Mode Ujian</strong></td>
                        <td>5 mode (Latihan, Try Out, Ujian, dll)</td>
                        <td>Pilih sesuai kebutuhan</td>
                    </tr>
                    <tr>
                        <td><strong>Jumlah Soal</strong></td>
                        <td>1-100</td>
                        <td>10-20 untuk latihan optimal</td>
                    </tr>
                    <tr>
                        <td><strong>Durasi</strong></td>
                        <td>5-60 menit</td>
                        <td>3-5 menit per soal</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Call to Action -->
        <div class="card" style="text-align: center;">
            <h2>🚀 Siap Dimulai?</h2>

            <div class="button-group">
                <a href="/admin/prompt_generator.php" class="btn btn-primary" target="_blank">
                    ▶ Buka Prompt Generator
                </a>
                <a href="PROMPT_GENERATOR_DOCS.md" class="btn btn-secondary" target="_blank">
                    📖 Baca Dokumentasi Lengkap
                </a>
            </div>

            <div class="info-box">
                <strong>❓ Pertanyaan?</strong> Baca dokumentasi lengkap di link di atas atau hubungi tim support.
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; color: white; margin-top: 40px; opacity: 0.8;">
            <p>JSON Prompt Generator v1.0 | Production Ready ✅</p>
            <p>Created: March 23, 2026 | Last Updated: March 23, 2026</p>
        </div>
    </div>
</body>

</html>