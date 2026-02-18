<?php

namespace App\Helpers;

class Formatter
{
    public static function decimalToJamMenit($decimal)
    {
        $isNegative = $decimal < 0;
        $decimal = abs((float) $decimal);
        $hours = floor($decimal);
        $minutes = round(($decimal - $hours) * 60);

        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }

        $text = '';
        if ($hours > 0) {
            $text .= "$hours jam ";
        }
        if ($minutes > 0 || $hours === 0) {
            $text .= "$minutes menit";
        }

        if ($isNegative) {
            $text = "−" . $text; // Gunakan tanda minus Unicode (lebih rapi)
        }

        return compact('hours', 'minutes', 'text', 'isNegative');
    }
}
