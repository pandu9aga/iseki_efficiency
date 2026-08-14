<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin jobdesc/prosedur dari report member yang digantikan
 * ke report member pengganti di database iseki_aspro.
 *
 * Dipicu otomatis setelah Replacement tersimpan (ReplacementController@storeScan).
 * Logika meniru ReportController@copyJobdescReplacement pada project iseki_aspro.
 */
class AsproJobCopyService
{
    protected string $storageRoot;

    public function __construct()
    {
        $this->storageRoot = env('ASPRO_STORAGE_ROOT', 'C:\xampp\htdocs\iseki_aspro\public\storage');
    }

    /**
     * Rule mapping Type_Plan -> Name_Tractor (sama dengan iseki_aspro).
     */
    protected function mapTypePlanToTractors($typePlan): array
    {
        $typePlan = trim((string) $typePlan);
        $map = [
            'GC' => ['MF1GC'],
            'GNT' => ['GNT 1640'],
            'GNTDAI' => ['MF 1650'],
            'MF' => ['MF1E25', 'MF1E35,40'],
            'MFDAI' => ['MF2E'],
            'MFE' => ['MF 1741'],
            'MFEDAI' => ['MF 1756'],
            'NT' => ['NT'],
            'NTDAI' => ['NT DAI'],
            'SF2' => ['SF 2'],
            'SF2CL' => ['SF 2'],
            'SF2MW' => ['SF 2'],
            'SF2日本' => ['SF 2'],
            'SF2CL日本' => ['SF 2'],
            'SF2MW日本' => ['SF 2'],
            'SF5' => ['SF 2'],
            'SUSXG2' => ['SUSXG2'],
            'SXG2' => ['SXG 2'],
            'SXG2CL' => ['SXG 2'],
            'SXG2MW' => ['SXG 2'],
            'SXG2日本' => ['SXG 2'],
            'SXG2CL日本' => ['SF 2'],
            'SXG2MW日本' => ['SXG 2'],
            'SXG3' => ['SXG3'],
            'SXG3CL' => ['SXG3'],
            'SXG3MW' => ['SXG3'],
            'SXG3日本' => ['SXG3'],
            'SXG3CL日本' => ['SXG3'],
            'SXG3MW日本' => ['SXG3'],
            'TLE' => ['TLE'],
            'TLEDAI' => ['TLE DAI'],
            'TXGS' => ['TXGS EROPA', 'TXGS JAPAN'],
        ];

        return $map[$typePlan] ?? [];
    }

    /**
     * Resolve Id_Member (iseki_aspro) dari NIK.
     * Prioritas iseki_rifa (employees), fallback iseki_aspro (members).
     */
    protected function resolveMemberId(string $nik): ?int
    {
        $empId = DB::connection('rifa')
            ->table('employees')
            ->where('nik', $nik)
            ->value('id');

        if ($empId) {
            return (int) $empId;
        }

        $memberId = DB::connection('aspro')
            ->table('members')
            ->where('NIK_Member', $nik)
            ->value('Id_Member');

        return $memberId ? (int) $memberId : null;
    }

    /**
     * Salin prosedur jobdesc dari member yang digantikan ke member pengganti.
     *
     * @param string $sourceNik  NIK pemilik daily job (Nik_Daily_Job).
     * @param string $targetNik  NIK member pengganti.
     * @param string $typePlan   Type_Plan dari plan (untuk mapping tractor).
     * @param string $startReport Tanggal acuan Start_Report (Y-m-d).
     * @throws \Exception
     */
    public function copyJobdesc(
        string $sourceNik,
        string $targetNik,
        ?string $typePlan,
        ?string $startReport,
        ?string $sequenceNo = null,
        ?string $productionDate = null
    ): array {
        $result = ['success' => false, 'copied' => 0, 'message' => ''];

        $sourceMemberId = $this->resolveMemberId($sourceNik);
        $targetMemberId = $this->resolveMemberId($targetNik);

        if (! $sourceMemberId || ! $targetMemberId) {
            $result['message'] = 'Member tidak ditemukan (source atau target).';
            return $result;
        }

        $startReportDate = $startReport;
        if (! $startReportDate) {
            $startReportDate = now()->format('Y-m-d');
        }

        // 1. Report sumber: milik pemilik daily job pada bulan Start_Report
        $sourceReport = DB::connection('aspro')
            ->table('reports')
            ->where('Id_Member', $sourceMemberId)
            ->whereYear('Start_Report', Carbon::parse($startReportDate)->year)
            ->whereMonth('Start_Report', Carbon::parse($startReportDate)->month)
            ->orderBy('Start_Report', 'asc')
            ->first();

        if (! $sourceReport) {
            $result['message'] = 'Report sumber untuk member yang digantikan tidak ditemukan di bulan tersebut.';
            return $result;
        }

        $sourceReportStart = Carbon::parse($sourceReport->Start_Report)->format('Y-m-d');

        // 2. Cari / buat Report target untuk member pengganti (tanggal sama dengan sumber)
        $targetReport = DB::connection('aspro')
            ->table('reports')
            ->where('Id_Member', $targetMemberId)
            ->whereDate('Start_Report', $sourceReportStart)
            ->first();

        if (! $targetReport) {
            $targetId = DB::connection('aspro')->table('reports')->insertGetId([
                'Id_Member'   => $targetMemberId,
                'Start_Report' => $sourceReportStart,
                'Name_Report'  => null,
            ]);
            $targetReport = (object) ['Id_Report' => $targetId];
        }

        // 3. Mapping Type_Plan -> tractors
        $mappedTractors = $this->mapTypePlanToTractors($typePlan);
        if (empty($mappedTractors)) {
            $result['message'] = 'Type_Plan tidak memiliki mapping tractor.';
            return $result;
        }

        // 4. Ambil list_reports sumber yang sesuai tractor
        $sourceListReports = DB::connection('aspro')
            ->table('list_reports')
            ->where('Id_Report', $sourceReport->Id_Report)
            ->whereIn('Name_Tractor', $mappedTractors)
            ->get();

        if ($sourceListReports->isEmpty()) {
            $result['message'] = 'Tidak ada prosedur jobdesc pada tractor tersebut untuk disalin.';
            return $result;
        }

        // Nama member target
        $targetName = DB::connection('rifa')->table('employees')->where('id', $targetMemberId)->value('nama')
            ?? DB::connection('aspro')->table('members')->where('Id_Member', $targetMemberId)->value('Name_Member')
            ?? null;

        $sourceFolder = 'reports/' . $sourceReportStart . '_' . $sourceMemberId;
        $targetFolder = 'reports/' . $sourceReportStart . '_' . $targetMemberId;

        $this->ensureDir('reports/' . $sourceReportStart . '_' . $targetMemberId);

        $copied = 0;
        foreach ($sourceListReports as $slr) {
            // Cek sudah ada di target
            $exists = DB::connection('aspro')
                ->table('list_reports')
                ->where('Id_Report', $targetReport->Id_Report)
                ->where('Name_Procedure', $slr->Name_Procedure)
                ->where('Name_Area', $slr->Name_Area)
                ->where('Name_Tractor', $slr->Name_Tractor)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection('aspro')->table('list_reports')->insert([
                'Id_Report'             => $targetReport->Id_Report,
                'Name_Procedure'        => $slr->Name_Procedure,
                'Name_Area'             => $slr->Name_Area,
                'Name_Tractor'          => $slr->Name_Tractor,
                'Item_Procedure'        => $slr->Item_Procedure,
                'Time_List_Report'      => null,
                'Time_Approved_Leader'  => null,
                'Time_Approved_Auditor' => null,
                'Reporter_Name'         => $targetName,
                'Leader_Name'           => null,
                'Auditor_Name'          => null,
            ]);

            // Salin file PDF dari master prosedur
            $this->copyProcedureFile($slr, $targetFolder, $copied);
            $copied++;
        }

        // Simpan header ke tabel report_replacements (agar tampil di list_report & flag is_copied)
        $paddedSeq = $sequenceNo;
        if ($paddedSeq !== null && stripos((string) $paddedSeq, 'T') === false) {
            $paddedSeq = str_pad(trim((string) $paddedSeq), 5, '0', STR_PAD_LEFT);
        }

        $existsHeader = DB::connection('aspro')
            ->table('report_replacements')
            ->where('Id_Report', $sourceReport->Id_Report)
            ->where('NIK_Replacement', $targetNik)
            ->where('Sequence_No_Plan', $paddedSeq)
            ->first();

        $repHeaderId = $existsHeader
            ? $existsHeader->Id_Report_Replacement
            : DB::connection('aspro')->table('report_replacements')->insertGetId([
                'Id_Report'           => $sourceReport->Id_Report,
                'NIK_Replacement'     => $targetNik,
                'Name_Tractor'        => implode(',', $mappedTractors),
                'Sequence_No_Plan'    => $paddedSeq,
                'Production_Date_Plan' => $productionDate,
                'Type_Plan'           => $typePlan,
                'Id_Report_Target'    => $targetReport->Id_Report,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

        // Simpan list_report_replacements + salin file PDF ke folder report_replacements/{id}
        $repFolder = 'report_replacements/' . $repHeaderId;
        $this->ensureDir($repFolder);

        foreach ($sourceListReports as $slr) {
            $existsItem = DB::connection('aspro')
                ->table('list_report_replacements')
                ->where('Id_Report_Replacement', $repHeaderId)
                ->where('Name_Procedure', $slr->Name_Procedure)
                ->where('Name_Tractor', $slr->Name_Tractor)
                ->exists();

            if (! $existsItem) {
                DB::connection('aspro')->table('list_report_replacements')->insert([
                    'Id_Report_Replacement' => $repHeaderId,
                    'Name_Procedure'        => $slr->Name_Procedure,
                    'Name_Area'             => $slr->Name_Area,
                    'Name_Tractor'          => $slr->Name_Tractor,
                    'Item_Procedure'        => $slr->Item_Procedure,
                    'Time_List_Report'      => null,
                    'Time_Approved_Leader'  => null,
                    'Time_Approved_Auditor' => null,
                    'Reporter_Name'         => $targetName,
                    'Leader_Name'           => null,
                    'Auditor_Name'          => null,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            $this->copyReplacementFile($slr, $repFolder);
        }

        $result['success'] = true;
        $result['copied'] = $copied;
        $result['message'] = "Berhasil menyalin {$copied} prosedur ke member pengganti.";
        return $result;
    }

    /**
     * Salin file PDF prosedur ke folder report_replacements/{id}.
     */
    protected function copyReplacementFile($listReport, string $repFolder): void
    {
        $root = rtrim($this->storageRoot, '/\\');

        $masterSource = $root . DIRECTORY_SEPARATOR . 'procedures'
            . DIRECTORY_SEPARATOR . $listReport->Name_Tractor
            . DIRECTORY_SEPARATOR . $listReport->Name_Area
            . DIRECTORY_SEPARATOR . $listReport->Name_Procedure . '.pdf';

        $targetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $repFolder)
            . DIRECTORY_SEPARATOR . $listReport->Name_Procedure . '.pdf';

        if (is_file($masterSource) && ! is_file($targetPath)) {
            @copy($masterSource, $targetPath);
        }
    }

    /**
     * Salin file PDF prosedur dari folder master ke folder report target.
     */
    protected function copyProcedureFile($listReport, string $targetFolder, int $index): void
    {
        $root = rtrim($this->storageRoot, '/\\');

        $masterSource = $root . DIRECTORY_SEPARATOR . 'procedures'
            . DIRECTORY_SEPARATOR . $listReport->Name_Tractor
            . DIRECTORY_SEPARATOR . $listReport->Name_Area
            . DIRECTORY_SEPARATOR . $listReport->Name_Procedure . '.pdf';

        $targetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetFolder)
            . DIRECTORY_SEPARATOR . $listReport->Name_Procedure . '.pdf';

        if (is_file($masterSource) && ! is_file($targetPath)) {
            @copy($masterSource, $targetPath);
        }
    }

    protected function ensureDir(string $path): void
    {
        $root = rtrim($this->storageRoot, '/\\');
        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_dir($full)) {
            @mkdir($full, 0777, true);
        }
    }
}