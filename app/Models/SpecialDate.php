<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialDate extends Model
{
    protected $connection = 'rifa';

    protected $table = 'special_dates';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'jenis_tanggal',
    ];

    /**
     * Hitung jumlah hari kerja (weekday) dalam rentang tanggal,
     * tidak termasuk weekend (Sabtu/Minggu) dan tanggal libur (kecuali "libur masuk").
     *
     * - libur nasional / cuti perusahaan / libur pengganti → dikecualikan
     * - libur masuk → tetap dihitung sebagai hari kerja (meski di weekend)
     */
    public static function countWorkdays(string $startDate, string $endDate): int
    {
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end   = \Carbon\Carbon::parse($endDate)->startOfDay();

        // Tanggal yang dikecualikan (libur nasional, cuti perusahaan, libur pengganti)
        $excludedDates = self::whereDate('tanggal', '>=', $start)
            ->whereDate('tanggal', '<=', $end)
            ->whereIn('jenis_tanggal', ['libur nasional', 'cuti perusahaan', 'libur pengganti'])
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Tanggal "libur masuk" — tetap dihitung meski di weekend
        $forcedWorkdays = self::whereDate('tanggal', '>=', $start)
            ->whereDate('tanggal', '<=', $end)
            ->where('jenis_tanggal', 'libur masuk')
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->format('Y-m-d');
            $isWorkday = $cursor->isWeekday() || in_array($dateStr, $forcedWorkdays);
            if ($isWorkday && !in_array($dateStr, $excludedDates)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
