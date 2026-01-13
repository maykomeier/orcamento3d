<?php
declare(strict_types=1);
function parseGcode(string $filePath): array {
    $isMulticolor = false;
    $grams = [];
    $gramsFilled = false;
    $seconds = 0;
    $thumbnail = '';
    $inThumb = false;
    $thumbLines = [];
    $bestThumbSize = 0;
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return ['is_multicolor' => false, 'filament_grams' => [], 'print_time_seconds' => 0, 'thumbnail_base64' => ''];
    }
    while (($line = fgets($handle)) !== false) {
        if (preg_match('/^T(\d+)/', $line)) { $isMulticolor = true; }
        if (!$gramsFilled && preg_match('/filament\s*used\s*\[g\]\s*[:=]\s*(.+)/i', $line, $mul)) {
            $list = trim($mul[1]);
            $tokens = preg_split('/\s*,\s*/', $list);
            foreach ($tokens as $tok) {
                if ($tok !== '') { $grams[] = (float)str_replace(',', '.', $tok); }
            }
            $gramsFilled = count($grams) > 0;
            continue;
        }
        if (!$gramsFilled && (preg_match('/material_weight\s*\(g\)\s*[:=]\s*([\d\.,\s]+)/i', $line, $mm)
            || preg_match('/material_weights?\s*\(g\)\s*[:=]\s*([\d\.,\s]+)/i', $line, $mm))) {
            foreach (preg_split('/\s*,\s*/', trim($mm[1])) as $token) {
                if ($token !== '') { $grams[] = (float)str_replace(',', '.', $token); }
            }
            $gramsFilled = count($grams) > 0;
            continue;
        }
        if (!$gramsFilled && preg_match('/total\s*filament\s*used\s*\[g\]\s*[:=]\s*(\d+(?:\.\d+)?)/i', $line, $tf)) {
            $grams[] = (float)$tf[1];
            $gramsFilled = true;
            continue;
        }
        if ($seconds === 0) {
            if (preg_match('/[;#]\s*(TIME|PRINT_TIME|TIME_USED\(s\)|TIME_USED)\s*[:=]\s*(\d+)/i', $line, $m)) {
                $seconds = (int)$m[2];
            } elseif (preg_match('/[;#]\s*(?:Estimated\s*)?(?:Printing|Print|Time)\b[^\n]*?(\d+):(\d+):(\d+)/i', $line, $m)) {
                $seconds = ((int)$m[1]) * 3600 + ((int)$m[2]) * 60 + (int)$m[3];
            } elseif (preg_match('/[;#]\s*(?:Total\s*)?Time\s*[:=]\s*(\d+):(\d+):(\d+)/i', $line, $tm)) {
                $seconds = ((int)$tm[1]) * 3600 + ((int)$tm[2]) * 60 + (int)$tm[3];
            } elseif (preg_match('/[;#]\s*Estimated\s*Time\s*\(s\)\s*[:=]\s*(\d+)/i', $line, $em)) {
                $seconds = (int)$em[1];
            } elseif (preg_match('/[;#]\s*Print\s*Time\s*\(s\)\s*[:=]\s*(\d+)/i', $line, $pm)) {
                $seconds = (int)$pm[1];
            } elseif (preg_match('/[;#]?\s*estimated\s*printing\s*time\s*\(normal\s*mode\)\s*[:=]\s*(\d+):(\d+):(\d+)/i', $line, $nm)) {
                $seconds = ((int)$nm[1]) * 3600 + ((int)$nm[2]) * 60 + (int)$nm[3];
            } elseif (preg_match('/[;#]?\s*estimated\s*printing\s*time\s*\(normal\s*mode\)\s*[:=]\s*(\d+)\s*h\s*(\d+)\s*m\s*(\d+)\s*s/i', $line, $hm)) {
                $seconds = ((int)$hm[1]) * 3600 + ((int)$hm[2]) * 60 + (int)$hm[3];
            } elseif (preg_match('/[;#]\s*estimated_print_time\s*[:=]\s*(\d+(?:\.\d+)?)/i', $line, $ep)) {
                $seconds = (int)round((float)$ep[1]);
            } elseif (preg_match('/[;#]\s*total_time\s*[:=]\s*(\d+):(\d+):(\d+)/i', $line, $tt)) {
                $seconds = ((int)$tt[1]) * 3600 + ((int)$tt[2]) * 60 + (int)$tt[3];
            } elseif (preg_match('/[;#]\s*total_time\s*[:=]\s*(\d+)\s*h\s*(\d+)\s*m\s*(\d+)\s*s/i', $line, $tth)) {
                $seconds = ((int)$tth[1]) * 3600 + ((int)$tth[2]) * 60 + (int)$tth[3];
            }
        }
        if (!$gramsFilled && preg_match('/filament\s*weight\s*[:=]\s*([\d\.,]+)\s*g/i', $line, $fw)) {
            $grams[] = (float)str_replace(',', '.', $fw[1]);
            $gramsFilled = true;
            continue;
        }
        if (!$inThumb && preg_match('/thumbnail\s+begin\s*(\d+)[x\s](\d+)/i', $line, $tm)) {
            $w = (int)$tm[1];
            $h = (int)$tm[2];
            $area = $w * $h;
            if ($area > $bestThumbSize) {
                $inThumb = true;
                $bestThumbSize = $area;
                $thumbLines = []; // Clear previous smaller thumb
                $thumbnail = ''; // Reset
            }
            continue;
        } elseif (!$inThumb && preg_match('/thumbnail\s+begin/i', $line)) {
            // Fallback for thumbnails without size in header, only if we haven't found a sized one yet
            if ($bestThumbSize === 0) {
                $inThumb = true;
                $thumbLines = [];
                $thumbnail = '';
            }
            continue;
        }

        if ($inThumb) {
            if (preg_match('/thumbnail\s+end/i', $line)) {
                $inThumb = false;
                $currentThumb = implode('', array_map(static function ($s) { return preg_replace('/^;\s*/', '', rtrim($s)); }, $thumbLines));
                if (strlen($currentThumb) > strlen($thumbnail)) {
                     $thumbnail = $currentThumb;
                }
                $thumbLines = [];
            } else { $thumbLines[] = $line; }
        }
    }
    fclose($handle);
    if (count($grams) > 1) {
        $uniq = [];
        foreach ($grams as $g) {
            $key = sprintf('%.3f', (float)$g);
            if (!isset($uniq[$key])) { $uniq[$key] = (float)$g; }
        }
        $grams = array_values($uniq);
    }
    return [
        'is_multicolor' => $isMulticolor || count($grams) > 1,
        'filament_grams' => $grams,
        'print_time_seconds' => $seconds,
        'thumbnail_base64' => $thumbnail
    ];
}