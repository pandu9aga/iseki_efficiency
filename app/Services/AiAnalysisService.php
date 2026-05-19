<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Cost;
use App\Models\Power;
use App\Models\DailyJob;
use App\Models\Scan;
use App\Models\Penanganan;
use App\Models\Report;
use App\Models\Replacement;
use App\Models\Assistance;
use App\Models\ReplacementDuration;
use App\Models\AssistanceDuration;
use App\Models\Area;

class AiAnalysisService
{
    /**
     * =====================================================================
     * OTAK KIRI: Mengumpulkan & Menghitung Semua Data Operasional
     * =====================================================================
     * Fungsi ini menghitung semua metrik produksi untuk tanggal tertentu,
     * lalu membandingkannya dengan rata-rata 7 hari dan 30 hari terakhir.
     */
    public function gatherMetrics($date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();
        $dateString = $targetDate->format('Y-m-d');
        $productionDateYmd = $targetDate->format('Ymd');

        // ─── DATA HARI INI ───────────────────────────────────
        $todayData = $this->getDailyData($targetDate);

        // ─── DATA HISTORIS 7 HARI TERAKHIR ───────────────────
        $hist7 = $this->getHistoricalAverage($targetDate, 7);

        // ─── DATA HISTORIS 30 HARI TERAKHIR ──────────────────
        $hist30 = $this->getHistoricalAverage($targetDate, 30);

        // ─── DETEKSI ANOMALI OTOMATIS (Rule-Based) ───────────
        $anomalies = $this->detectAnomalies($todayData, $hist7, $hist30);

        // ─── TOP NON-OP CATEGORIES HARI INI ──────────────────
        $topNonOpCategories = Cost::whereDate('Start_Cost', $dateString)
            ->select('Keterangan_Cost', DB::raw('SUM(Non_Operational_Cost) as total'))
            ->groupBy('Keterangan_Cost')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($c) => $c->Keterangan_Cost . ': ' . round($c->total, 2) . ' jam')
            ->toArray();

        // ─── TOP PENANGANAN CATEGORIES HARI INI ──────────────
        $topHandlingCategories = Penanganan::whereDate('Start_Penanganan', $dateString)
            ->select('Keterangan_Penanganan', DB::raw('SUM(Hour_Penanganan) as total'))
            ->groupBy('Keterangan_Penanganan')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($p) => $p->Keterangan_Penanganan . ': ' . round($p->total, 2) . ' jam')
            ->toArray();

        // ─── DATA PER AREA HARI INI ─────────────────────────
        $areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
        $perAreaData = [];
        foreach ($areas as $area) {
            $areaMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->where('Id_Area', $area->Id_Area)
                ->distinct('Nik_Daily_Job')
                ->count();
            $areaScanHours = Scan::whereDate('Time_Scan', $dateString)
                ->where('Id_Area', $area->Id_Area)
                ->sum('Assigned_Hour_Scan');
            $areaNonOp = Cost::whereDate('Start_Cost', $dateString)
                ->where('Id_Area', $area->Id_Area)
                ->sum('Non_Operational_Cost');

            if ($areaMembers > 0 || $areaScanHours > 0 || $areaNonOp > 0) {
                $perAreaData[] = $area->Name_Area
                    . ': ' . $areaMembers . ' orang, '
                    . round($areaScanHours, 2) . ' jam traktor, '
                    . round($areaNonOp, 2) . ' jam non-op';
            }
        }

        return [
            'date' => $dateString,
            'dayOfWeek' => $targetDate->translatedFormat('l'),
            'today' => $todayData,
            'avg7' => $hist7,
            'avg30' => $hist30,
            'anomalies' => $anomalies,
            'topNonOpCategories' => $topNonOpCategories,
            'topHandlingCategories' => $topHandlingCategories,
            'perAreaData' => $perAreaData,
        ];
    }

    /**
     * Mengambil data operasional untuk satu hari tertentu
     */
    private function getDailyData(Carbon $date): array
    {
        $dateString = $date->format('Y-m-d');
        $productionDateYmd = $date->format('Ymd');

        // Manpower
        $totalMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
            ->distinct('Nik_Daily_Job')
            ->count();

        // Member Hours (dari Report jika ada, fallback ke member * 8)
        $areas = Area::all();
        $allReports = Report::where('Day_Report', $dateString)->get()->keyBy('Id_Area');
        $memberHours = 0;
        foreach ($areas as $area) {
            $report = $allReports->get($area->Id_Area);
            if ($report) {
                $memberHours += (float) $report->Total_Hours_Report;
            } else {
                $areaCount = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $area->Id_Area)
                    ->distinct('Nik_Daily_Job')
                    ->count();
                $memberHours += ($areaCount * 8.0);
            }
        }

        // Non-Op Total
        $totalNonOp = Cost::whereDate('Start_Cost', $dateString)->sum('Non_Operational_Cost');

        // Ijin/Absen Total
        $totalIjin = Power::whereDate('Start_Power', $dateString)->sum('Leave_Hour_Power');

        // Scan Traktor (Jam & Jumlah)
        $totalScanHours = Scan::whereDate('Time_Scan', $dateString)->sum('Assigned_Hour_Scan');
        $totalTractorCount = Scan::whereDate('Time_Scan', $dateString)->count();

        // Penanganan (Handling) Total
        $totalHandling = Penanganan::whereDate('Start_Penanganan', $dateString)->sum('Hour_Penanganan');

        // Jam Pergantian (Replacement Duration)
        $totalReplacementMinutes = ReplacementDuration::whereHas('dailyJob', function ($q) use ($productionDateYmd) {
            // Filter by Production_Date_Plan if relation exists
        })->sum('Total_Minutes');
        // Fallback: just get today's data based on created_at
        $totalReplacementMinutes = ReplacementDuration::whereDate('created_at', $dateString)->sum('Total_Minutes');
        $totalReplacementHours = round($totalReplacementMinutes / 60, 2);

        // Jam Perbantuan (Assistance Duration)
        $totalAssistanceMinutes = AssistanceDuration::whereDate('created_at', $dateString)->sum('Total_Minutes');
        $totalAssistanceHours = round($totalAssistanceMinutes / 60, 2);

        // Jumlah record pergantian & perbantuan
        $replacementCount = Replacement::where('Production_Date_Plan', $productionDateYmd)->count();
        $assistanceCount = Assistance::where('Production_Date_Plan', $productionDateYmd)->count();

        // Hitung Efisiensi (sama seperti di dashboard)
        $powerNet = $memberHours - $totalIjin;
        $kategori1 = $powerNet + $totalHandling; // Man Power Net + Handling
        $kategori2 = $totalScanHours + $totalNonOp; // Tractor + Non-Op
        $selisihJam = $kategori2 - $kategori1;
        $efisiensiPersen = $kategori2 != 0
            ? (($kategori2 - $kategori1) / $kategori2) * 100
            : 0;

        return [
            'manpower' => $totalMembers,
            'memberHours' => round($memberHours, 2),
            'nonOp' => round($totalNonOp, 2),
            'ijin' => round($totalIjin, 2),
            'scanHours' => round($totalScanHours, 2),
            'tractorCount' => $totalTractorCount,
            'handling' => round($totalHandling, 2),
            'replacementHours' => $totalReplacementHours,
            'replacementCount' => $replacementCount,
            'assistanceHours' => $totalAssistanceHours,
            'assistanceCount' => $assistanceCount,
            'powerNet' => round($powerNet, 2),
            'selisihJam' => round($selisihJam, 2),
            'efisiensi' => round($efisiensiPersen, 2),
        ];
    }

    /**
     * Menghitung rata-rata N hari terakhir (tidak termasuk hari target)
     */
    private function getHistoricalAverage(Carbon $targetDate, int $days): array
    {
        $results = [];
        $validDays = 0;

        for ($i = 1; $i <= $days; $i++) {
            $d = $targetDate->copy()->subDays($i);
            $data = $this->getDailyData($d);

            // Hanya hitung hari yang ada aktivitas (bukan weekend/libur kosong)
            if ($data['manpower'] > 0 || $data['tractorCount'] > 0) {
                $validDays++;
                foreach ($data as $key => $value) {
                    if (!isset($results[$key])) {
                        $results[$key] = 0;
                    }
                    $results[$key] += $value;
                }
            }
        }

        if ($validDays === 0) {
            return [
                'manpower' => 0, 'memberHours' => 0, 'nonOp' => 0, 'ijin' => 0,
                'scanHours' => 0, 'tractorCount' => 0, 'handling' => 0,
                'replacementHours' => 0, 'replacementCount' => 0,
                'assistanceHours' => 0, 'assistanceCount' => 0,
                'powerNet' => 0, 'selisihJam' => 0, 'efisiensi' => 0,
                'validDays' => 0,
            ];
        }

        // Bagi semua akumulasi dengan jumlah hari valid
        foreach ($results as $key => $value) {
            $results[$key] = round($value / $validDays, 2);
        }
        $results['validDays'] = $validDays;

        return $results;
    }

    /**
     * =====================================================================
     * DETEKSI ANOMALI OTOMATIS (Rule-Based pre-AI)
     * =====================================================================
     * Memberikan flag awal kepada AI agar lebih fokus.
     */
    private function detectAnomalies(array $today, array $avg7, array $avg30): array
    {
        $anomalies = [];

        // 1. Manpower = 0 tapi ada scan traktor (Leader belum assign)
        if ($today['manpower'] == 0 && $today['scanHours'] > 0) {
            $anomalies[] = '🚨 KRITIS: Manpower tercatat 0 tetapi Jam Traktor > 0. Kemungkinan Leader BELUM melakukan Assign Member Daily. Efisiensi yang tercatat TIDAK VALID.';
        }

        // 2. Non-Op naik > 50% dari rata-rata 7 hari
        if ($avg7['nonOp'] > 0 && $today['nonOp'] > $avg7['nonOp'] * 1.5) {
            $persen = round((($today['nonOp'] - $avg7['nonOp']) / $avg7['nonOp']) * 100);
            $anomalies[] = "⚠️ Non-Op hari ini NAIK {$persen}% dibanding rata-rata 7 hari terakhir ({$today['nonOp']} vs {$avg7['nonOp']} jam).";
        }

        // 3. Jam traktor hari ini jauh lebih tinggi/rendah dari rata-rata
        if ($avg7['scanHours'] > 0) {
            $diff = $today['scanHours'] - $avg7['scanHours'];
            $persen = round(abs($diff / $avg7['scanHours']) * 100);
            if ($persen > 30) {
                $direction = $diff > 0 ? 'NAIK' : 'TURUN';
                $anomalies[] = "📊 Jam Traktor hari ini {$direction} {$persen}% dibanding rata-rata 7 hari ({$today['scanHours']} vs {$avg7['scanHours']} jam).";
            }
        }

        // 4. Ijin/Absen tinggi (> 2x rata-rata)
        if ($avg7['ijin'] > 0 && $today['ijin'] > $avg7['ijin'] * 2) {
            $anomalies[] = "⚠️ Jam Ijin/Absen hari ini sangat tinggi ({$today['ijin']} jam), lebih dari 2x rata-rata 7 hari ({$avg7['ijin']} jam).";
        }

        // 5. Efisiensi minus
        if ($today['efisiensi'] < 0) {
            $anomalies[] = "📉 Efisiensi hari ini MINUS ({$today['efisiensi']}%). Total beban produksi lebih rendah dibanding kapasitas yang tersedia.";
        }

        // 6. Efisiensi sangat tinggi (> 50%) — kemungkinan data tidak lengkap
        if ($today['efisiensi'] > 50 && $today['manpower'] > 0) {
            $anomalies[] = "⚠️ Efisiensi tercatat sangat tinggi ({$today['efisiensi']}%). Mohon verifikasi apakah data Man Power sudah lengkap.";
        }

        // 7. Tren perbantuan meningkat
        if ($avg7['assistanceHours'] > 0 && $today['assistanceHours'] > $avg7['assistanceHours'] * 1.5) {
            $anomalies[] = "📊 Jam Perbantuan meningkat signifikan dari rata-rata ({$today['assistanceHours']} vs {$avg7['assistanceHours']} jam).";
        }

        // 8. Jumlah traktor turun tapi manpower sama
        if ($avg7['manpower'] > 0 && $avg7['tractorCount'] > 0) {
            $mpDiff = abs($today['manpower'] - $avg7['manpower']) / $avg7['manpower'];
            $trDiff = ($avg7['tractorCount'] - $today['tractorCount']) / $avg7['tractorCount'];
            if ($mpDiff < 0.1 && $trDiff > 0.2) {
                $anomalies[] = "🔍 Manpower relatif stabil tapi jumlah traktor TURUN " . round($trDiff * 100) . "%. Kemungkinan ada kendala teknis di lapangan.";
            }
        }

        return $anomalies;
    }

    /**
     * =====================================================================
     * OTAK KANAN: Merakit Prompt & Mengirim ke Groq AI
     * =====================================================================
     */
    public function generateDailyInsight($date = null): string
    {
        $metrics = $this->gatherMetrics($date);
        $t = $metrics['today'];
        $a7 = $metrics['avg7'];
        $a30 = $metrics['avg30'];

        // ─── RAKIT PROMPT ─────────────────────────────────────
        $prompt = "Kamu adalah Data Analyst Senior di pabrik perakitan traktor ISEKI Indonesia.
Tugasmu adalah menganalisis data produksi harian, mendeteksi masalah, dan memberikan rekomendasi.
Gunakan bahasa Indonesia profesional yang mudah dipahami manajemen. Maksimal 4 paragraf.

ATURAN PENTING:
- Jika Manpower = 0 dan Jam Traktor > 0, itu artinya Leader BELUM assign member di sistem, bukan berarti tidak ada pekerja.
- Jangan halusinasi data di luar yang diberikan.
- Bandingkan data hari ini dengan rata-rata 7 dan 30 hari terakhir.
- Gunakan Chain of Thought: (1) Validasi data, (2) Cari anomali terbesar, (3) Analisis sebab-akibat, (4) Kesimpulan & Rekomendasi.

═══════════════════════════════════════════════
DATA OPERASIONAL TANGGAL: {$metrics['date']} ({$metrics['dayOfWeek']})
═══════════════════════════════════════════════

📊 DATA HARI INI:
- Manpower (Pekerja Hadir): {$t['manpower']} orang
- Total Jam Kerja Tersedia: {$t['memberHours']} jam
- Jam Kerja Bersih (Net Power): {$t['powerNet']} jam
- Total Jam Traktor (Scan): {$t['scanHours']} jam ({$t['tractorCount']} unit)
- Total Jam Non-Operasional: {$t['nonOp']} jam
- Total Jam Ijin/Absen: {$t['ijin']} jam
- Total Jam Penanganan (Handling): {$t['handling']} jam
- Jam Pergantian (Replacement): {$t['replacementHours']} jam ({$t['replacementCount']} kali)
- Jam Perbantuan (Assistance): {$t['assistanceHours']} jam ({$t['assistanceCount']} kali)
- Selisih Jam (Beban vs Kapasitas): {$t['selisihJam']} jam
- Efisiensi: {$t['efisiensi']}%

📈 RATA-RATA 7 HARI TERAKHIR ({$a7['validDays']} hari kerja):
- Manpower: {$a7['manpower']} orang
- Jam Traktor: {$a7['scanHours']} jam ({$a7['tractorCount']} unit)
- Non-Op: {$a7['nonOp']} jam
- Ijin: {$a7['ijin']} jam
- Handling: {$a7['handling']} jam
- Efisiensi: {$a7['efisiensi']}%

📉 RATA-RATA 30 HARI TERAKHIR ({$a30['validDays']} hari kerja):
- Manpower: {$a30['manpower']} orang
- Jam Traktor: {$a30['scanHours']} jam ({$a30['tractorCount']} unit)
- Non-Op: {$a30['nonOp']} jam
- Efisiensi: {$a30['efisiensi']}%";

        // Tambahkan anomali yang terdeteksi
        if (!empty($metrics['anomalies'])) {
            $prompt .= "\n\n🚩 ANOMALI TERDETEKSI OLEH SISTEM:\n";
            foreach ($metrics['anomalies'] as $anomaly) {
                $prompt .= "- {$anomaly}\n";
            }
        }

        // Tambahkan detail non-op kategori
        if (!empty($metrics['topNonOpCategories'])) {
            $prompt .= "\n📋 TOP KATEGORI NON-OPERASIONAL HARI INI:\n";
            foreach ($metrics['topNonOpCategories'] as $cat) {
                $prompt .= "- {$cat}\n";
            }
        }

        // Tambahkan detail penanganan kategori
        if (!empty($metrics['topHandlingCategories'])) {
            $prompt .= "\n📋 TOP KATEGORI PENANGANAN HARI INI:\n";
            foreach ($metrics['topHandlingCategories'] as $cat) {
                $prompt .= "- {$cat}\n";
            }
        }

        // Tambahkan data per area
        if (!empty($metrics['perAreaData'])) {
            $prompt .= "\n🏭 DATA PER AREA HARI INI:\n";
            foreach ($metrics['perAreaData'] as $areaInfo) {
                $prompt .= "- {$areaInfo}\n";
            }
        }

        $prompt .= "\n\nBerdasarkan semua data di atas, buatkan analisis diagnostik yang mencakup:
1. Ringkasan kondisi produksi hari ini (baik/normal/bermasalah).
2. Temuan utama: apa yang menyimpang dari biasanya dan mengapa.
3. Root cause analysis: apa kemungkinan akar masalahnya.
4. Rekomendasi tindakan untuk manajemen.";

        // ─── KIRIM KE GROQ API ───────────────────────────────
        return $this->askGroq($prompt);
    }

    /**
     * Mengirim prompt ke Groq API (Llama-3)
     */
    private function askGroq(string $prompt): string
    {
        $apiKey = config('services.groq.api_key', env('GROQ_API_KEY'));

        if (!$apiKey) {
            return '❌ GROQ_API_KEY belum diatur. Silakan tambahkan GROQ_API_KEY di file .env Anda. Dapatkan API Key gratis di https://console.groq.com';
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah Data Analyst Senior yang ahli dalam analisis produksi pabrik. Kamu selalu memberikan analisis berdasarkan data yang diberikan, tidak pernah mengarang data. Jawab dalam bahasa Indonesia profesional.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 1500,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content')
                    ?? '⚠️ AI tidak memberikan respons.';
            }

            $error = $response->json('error.message') ?? $response->body();
            return '❌ Groq API Error: ' . $error;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return '❌ Tidak dapat terhubung ke Groq API. Pastikan server memiliki akses internet. Error: ' . $e->getMessage();
        } catch (\Exception $e) {
            return '❌ Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
