<?php

namespace App\Support;

/**
 * Gráficos SVG mínimos generados en servidor (sin librerías de cliente).
 * Un solo eje, una tinta por serie, texto con tokens del tema.
 */
class Chart
{
    public static function sparkline(array $vals, int $w = 96, int $h = 36, string $cls = ''): string
    {
        $vals = array_values(array_map('floatval', $vals));
        if (count($vals) < 2) {
            $vals = [0, 0];
        }
        $mx = max($vals); $mn = min($vals); $r = ($mx - $mn) ?: 1; $n = count($vals);
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [round($i * ($w - 4) / ($n - 1) + 2, 1), round($h - 4 - ($v - $mn) / $r * ($h - 8), 1)];
        }
        $d = '';
        foreach ($pts as $i => [$x, $y]) {
            $d .= ($i ? 'L' : 'M')."$x $y ";
        }
        [$lx, $ly] = end($pts);

        return "<svg class=\"spark chart $cls\" viewBox=\"0 0 $w $h\" preserveAspectRatio=\"none\"><path class=\"area\" d=\"{$d}L$lx $h L2 {$h}Z\"/><path class=\"line\" d=\"$d\"/><circle class=\"pt\" cx=\"$lx\" cy=\"$ly\" r=\"3\"/></svg>";
    }

    /** @param array<int, array{l:string, v:float}> $items */
    public static function bars(array $items, int $w = 520, int $h = 180, ?string $hi = null, string $unit = ''): string
    {
        if (! $items) {
            return '';
        }
        $mx = max(array_map(fn ($i) => (float) $i['v'], $items)) ?: 1;
        $padL = 32; $padB = 26; $padT = 10; $bw = ($w - $padL - 10) / count($items);
        $out = '<g class="grid">';
        foreach ([0, .5, 1] as $t) {
            $y = round($padT + (1 - $t) * ($h - $padT - $padB), 1);
            $out .= "<line x1=\"$padL\" x2=\"".($w - 4)."\" y1=\"$y\" y2=\"$y\"/><text x=\"".($padL - 6)."\" y=\"".($y + 4)."\" text-anchor=\"end\">".number_format(round($mx * $t), 0, ',', '.').'</text>';
        }
        $out .= '</g>';
        foreach (array_values($items) as $i => $it) {
            $bh = round((float) $it['v'] / $mx * ($h - $padT - $padB), 1);
            $x = round($padL + $i * $bw + $bw * .18, 1);
            $y = round($h - $padB - $bh, 1);
            $cls = $hi !== null && $it['l'] === $hi ? 'cbar hi' : 'cbar';
            $out .= '<g><title>'.e($it['l']).': '.number_format((float) $it['v'], 0, ',', '.')." $unit</title><rect class=\"$cls\" x=\"$x\" y=\"$y\" width=\"".round($bw * .64, 1)."\" height=\"$bh\" rx=\"4\"/><text x=\"".round($x + $bw * .32, 1)."\" y=\"".($h - 8)."\" text-anchor=\"middle\">".e($it['l']).'</text></g>';
        }

        return "<svg class=\"chart\" viewBox=\"0 0 $w $h\">$out</svg>";
    }

    /** @param array<int, array{v: array<float>, c: string}> $series */
    public static function lines(array $series, array $labels = [], int $w = 520, int $h = 180): string
    {
        if (! $series || count($series[0]['v']) < 2) {
            return '';
        }
        $all = array_merge(...array_map(fn ($s) => $s['v'], $series));
        $mx = max($all) ?: 1; $padL = 34; $padB = 24; $padT = 10; $n = count($series[0]['v']);
        $X = fn ($i) => round($padL + $i * ($w - $padL - 10) / ($n - 1), 1);
        $Y = fn ($v) => round($padT + (1 - $v / $mx) * ($h - $padT - $padB), 1);
        $out = '<g class="grid">';
        foreach ([0, .5, 1] as $t) {
            $out .= "<line x1=\"$padL\" x2=\"".($w - 4)."\" y1=\"{$Y($mx * $t)}\" y2=\"{$Y($mx * $t)}\"/><text x=\"".($padL - 6)."\" y=\"".($Y($mx * $t) + 4)."\" text-anchor=\"end\">".number_format(round($mx * $t), 0, ',', '.').'</text>';
        }
        $out .= '</g>';
        foreach ($series as $k => $s) {
            $d = '';
            foreach ($s['v'] as $i => $v) {
                $d .= ($i ? 'L' : 'M').$X($i).' '.$Y($v).' ';
            }
            $c = e($s['c']);
            if ($k === 0) {
                $out .= "<path class=\"area\" style=\"fill:$c\" d=\"{$d}L{$X($n - 1)} ".($h - $padB)." L{$X(0)} ".($h - $padB).'Z"/>';
            }
            $out .= "<path class=\"line\" style=\"stroke:$c\" d=\"$d\"/><circle class=\"pt\" style=\"fill:$c\" cx=\"{$X($n - 1)}\" cy=\"{$Y(end($s['v']))}\" r=\"3.5\"/>";
        }
        foreach ($labels as $i => $l) {
            $out .= "<text x=\"{$X($i)}\" y=\"".($h - 6)."\" text-anchor=\"middle\">".e($l).'</text>';
        }

        return "<svg class=\"chart\" viewBox=\"0 0 $w $h\">$out</svg>";
    }
}
