<?php

namespace App\Support;

use GdImage;

/**
 * DemoArt — seed-time GD illustration engine (owner demo-data pack).
 *
 * Renders flat-style product illustrations in the store identity
 * (ink #111827 / yellow #FFC107) so the demo seeder can generate every
 * image locally at seed time: NO binary image files live in the repo.
 *
 * Robustness rules: works with or without a TTF font (falls back to
 * the GD built-in font), only Latin labels are drawn (GD cannot shape
 * Arabic), and every public method returns an absolute path to a WebP
 * file under storage/app/demo-art for media attachment.
 */
class DemoArt
{
    private const INK = [17, 24, 39];

    private const YELLOW = [255, 193, 7];

    private static ?string $font = null;

    private static bool $fontChecked = false;

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /** 1000x1000 product illustration on a soft studio background. */
    public static function product(string $type, int $variant = 0): string
    {
        $im = self::canvas(1000, 1000);
        self::studioBg($im, 1000, 1000, $variant);
        $art = self::productCanvas($type, $variant);
        imagecopy($im, $art, 0, 0, 0, 0, 1000, 1000);
        imagedestroy($art);

        return self::save($im, "product-{$type}-{$variant}");
    }

    /** Category tile: same studio product art. */
    public static function category(string $type, int $variant = 0): string
    {
        return self::product($type, $variant);
    }

    /** 1600x900 banner: ink background, circuit motif, glowing product art, no text. */
    public static function banner(string $type, int $variant = 0): string
    {
        $im = self::canvas(1600, 900);
        self::inkBg($im, 1600, 900);
        self::circuit($im, 1600, 900, crc32("banner-{$type}-{$variant}"), self::YELLOW, 78, 16);

        // Yellow glow behind the product (concentric translucent ellipses).
        for ($r = 460; $r > 60; $r -= 40) {
            $glow = imagecolorallocatealpha($im, 255, 193, 7, 118 - (int) (($r / 460) * 22));
            imagefilledellipse($im, 1150, 450, $r * 2, $r * 2, $glow);
        }
        $ring = imagecolorallocatealpha($im, 255, 193, 7, 55);
        imagesetthickness($im, 3);
        imageellipse($im, 1150, 450, 880, 880, $ring);
        imageellipse($im, 1150, 450, 700, 700, $ring);

        $art = self::productCanvas($type, $variant);
        imagecopyresampled($im, $art, 800, 110, 0, 0, 700, 700, 1000, 1000);
        imagedestroy($art);

        // Accent bar on the left edge.
        imagefilledrectangle($im, 0, 0, 14, 900, self::col($im, self::YELLOW));

        return self::save($im, "banner-{$type}-{$variant}");
    }

    /** 600x600 brand logo: ink rounded square, Latin initials, yellow bolt. */
    public static function brandLogo(string $initials, int $variant = 0): string
    {
        $im = self::canvas(600, 600);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagealphablending($im, false);
        imagefilledrectangle($im, 0, 0, 600, 600, $transparent);
        imagealphablending($im, true);

        self::rrect($im, 30, 30, 570, 570, 64, self::INK);
        self::rrect($im, 52, 52, 548, 548, 48, self::YELLOW);
        self::rrect($im, 60, 60, 540, 540, 44, self::INK);

        self::bolt($im, 300, 168, 54, self::YELLOW);
        $len = strlen($initials);
        $size = $len <= 2 ? 148 : ($len <= 4 ? 104 : 76);
        self::label($im, strtoupper($initials), 300, 356, $size, [255, 255, 255]);
        imagefilledrectangle($im, 215, 442, 385, 454, self::col($im, self::YELLOW));

        return self::save($im, 'brand-'.strtolower(preg_replace('/[^a-z0-9]+/i', '-', $initials)));
    }

    // ------------------------------------------------------------------
    // Canvases & backgrounds
    // ------------------------------------------------------------------

    private static function canvas(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        return $im;
    }

    private static function studioBg(GdImage $im, int $w, int $h, int $variant): void
    {
        $tops = [[247, 249, 252], [246, 248, 250], [248, 248, 245], [245, 248, 252]];
        $top = $tops[$variant % count($tops)];
        $bottom = [226, 232, 240];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $c = imagecolorallocate($im,
                (int) ($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) ($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) ($top[2] + ($bottom[2] - $top[2]) * $t));
            imagefilledrectangle($im, 0, $y, $w, $y, $c);
        }
        // Floor line.
        $line = imagecolorallocatealpha($im, 120, 130, 145, 70);
        imagefilledrectangle($im, 0, 812, $w, 814, $line);
        self::circuit($im, $w, $h, 1234 + $variant, [148, 163, 184], 96, 7);
    }

    private static function inkBg(GdImage $im, int $w, int $h): void
    {
        $top = [9, 13, 24];
        $bottom = [23, 32, 48];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $c = imagecolorallocate($im,
                (int) ($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) ($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) ($top[2] + ($bottom[2] - $top[2]) * $t));
            imagefilledrectangle($im, 0, $y, $w, $y, $c);
        }
    }

    /** Subtle right-angle circuit traces with node pads, seeded per image. */
    private static function circuit(GdImage $im, int $w, int $h, int $seed, array $rgb, int $alpha, int $count): void
    {
        mt_srand($seed);
        $color = imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
        imagesetthickness($im, 2);
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand(20, $w - 220);
            $y = mt_rand(20, $h - 120);
            $len1 = mt_rand(60, 190);
            $len2 = mt_rand(40, 140);
            $dir = mt_rand(0, 1) === 1 ? 1 : -1;
            imageline($im, $x, $y, $x + $len1, $y, $color);
            imageline($im, $x + $len1, $y, $x + $len1, $y + $dir * $len2, $color);
            imagefilledellipse($im, $x, $y, 9, 9, $color);
            imagefilledellipse($im, $x + $len1, $y + $dir * $len2, 9, 9, $color);
        }
        mt_srand();
    }

    /** Transparent 1000x1000 canvas holding one product drawing + its shadow. */
    private static function productCanvas(string $type, int $variant): GdImage
    {
        $im = self::canvas(1000, 1000);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagealphablending($im, false);
        imagefilledrectangle($im, 0, 0, 1000, 1000, $transparent);
        imagealphablending($im, true);
        self::shadow($im, 500, 806, 430, 54);

        $v = abs($variant);
        match ($type) {
            'breaker' => self::drawBreaker($im, $v),
            'distribution-board' => self::drawDistributionBoard($im, $v),
            'switch' => self::drawSwitch($im, $v),
            'socket' => self::drawSocket($im, $v),
            'cable-coil' => self::drawCableCoil($im, $v),
            'led-bulb' => self::drawLedBulb($im, $v),
            'led-panel' => self::drawLedPanel($im, $v),
            'floodlight' => self::drawFloodlight($im, $v),
            'drill' => self::drawDrill($im, $v),
            'multimeter' => self::drawMultimeter($im, $v),
            'ceiling-fan' => self::drawCeilingFan($im, $v),
            'stabilizer' => self::drawStabilizer($im, $v),
            'conduit' => self::drawConduit($im, $v),
            'battery' => self::drawBattery($im, $v),
            default => self::drawBreaker($im, $v),
        };

        return $im;
    }

    // ------------------------------------------------------------------
    // Drawing primitives
    // ------------------------------------------------------------------

    private static function col(GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private static function shade(array $rgb, float $f): array
    {
        return [
            max(0, min(255, (int) ($rgb[0] * $f))),
            max(0, min(255, (int) ($rgb[1] * $f))),
            max(0, min(255, (int) ($rgb[2] * $f))),
        ];
    }

    private static function rrect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, array $rgb): void
    {
        $c = self::col($im, $rgb);
        $r = min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2));
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $c);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $c);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $c);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $c);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $c);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $c);
    }

    private static function shadow(GdImage $im, int $cx, int $cy, int $w, int $h): void
    {
        for ($i = 5; $i >= 1; $i--) {
            $c = imagecolorallocatealpha($im, 15, 23, 42, 108 + $i * 3);
            imagefilledellipse($im, $cx, $cy, (int) ($w * $i / 5), (int) ($h * $i / 5), $c);
        }
    }

    private static function bolt(GdImage $im, int $cx, int $cy, int $s, array $rgb): void
    {
        $pts = [
            $cx + (int) ($s * 0.18), $cy - $s,
            $cx - (int) ($s * 0.52), $cy + (int) ($s * 0.18),
            $cx - (int) ($s * 0.05), $cy + (int) ($s * 0.18),
            $cx - (int) ($s * 0.18), $cy + $s,
            $cx + (int) ($s * 0.52), $cy - (int) ($s * 0.18),
            $cx + (int) ($s * 0.05), $cy - (int) ($s * 0.18),
        ];
        imagefilledpolygon($im, $pts, self::col($im, $rgb));
    }

    private static function screw(GdImage $im, int $x, int $y, int $r): void
    {
        imagefilledellipse($im, $x, $y, $r * 2, $r * 2, self::col($im, [154, 161, 171]));
        imagesetthickness($im, 2);
        $dark = self::col($im, [90, 98, 108]);
        imageline($im, $x - $r + 3, $y, $x + $r - 3, $y, $dark);
    }

    /** Centered Latin label; TTF when available, GD built-in font otherwise. */
    private static function label(GdImage $im, string $text, int $cx, int $cy, int $size, array $rgb): void
    {
        $font = self::fontPath();
        $color = self::col($im, $rgb);
        if ($font !== null) {
            $box = imagettfbbox($size, 0, $font, $text);
            $w = $box[2] - $box[0];
            $h = $box[1] - $box[7];
            imagettftext($im, $size, 0, (int) ($cx - $w / 2), (int) ($cy + $h / 2), $color, $font, $text);
        } else {
            $f = 5;
            $w = imagefontwidth($f) * strlen($text);
            imagestring($im, $f, (int) ($cx - $w / 2), (int) ($cy - imagefontheight($f) / 2), $text, $color);
        }
    }

    private static function fontPath(): ?string
    {
        if (self::$fontChecked) {
            return self::$font;
        }
        self::$fontChecked = true;
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return self::$font = $path;
            }
        }
        foreach (glob('/usr/share/fonts/*/*/*.ttf') ?: [] as $path) {
            if (stripos($path, 'bold') !== false) {
                return self::$font = $path;
            }
        }

        return self::$font = null;
    }

    private static function save(GdImage $im, string $name): string
    {
        $dir = storage_path('app/demo-art');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/'.preg_replace('/[^a-z0-9\-]+/i', '-', $name).'-'.uniqid().'.webp';
        imagewebp($im, $path, 84);
        imagedestroy($im);

        return $path;
    }

    // ------------------------------------------------------------------
    // Product drawings (1000x1000, product zone roughly x 200-800)
    // ------------------------------------------------------------------

    private static function drawBreaker(GdImage $im, int $v): void
    {
        $amps = ['10A', '16A', '32A', '63A'][$v % 4];
        self::rrect($im, 392, 252, 608, 748, 16, [160, 167, 176]);
        self::rrect($im, 398, 246, 602, 742, 16, [233, 236, 240]);
        self::rrect($im, 420, 282, 580, 560, 10, [248, 249, 250]);
        // Terminal screws top & bottom.
        foreach ([450, 500, 550] as $x) {
            self::screw($im, $x, 266, 8);
            self::screw($im, $x, 724, 8);
        }
        // Toggle lever with yellow tip.
        self::rrect($im, 464, 352, 536, 478, 10, self::INK);
        imagefilledrectangle($im, 464, 352, 536, 392, self::col($im, self::YELLOW));
        // Status window.
        imagefilledrectangle($im, 516, 300, 544, 322, self::col($im, [198, 40, 40]));
        self::label($im, $amps, 500, 516, 36, self::INK);
        self::label($im, 'CURVE C', 500, 610, 17, [107, 114, 128]);
        self::label($im, '6000', 500, 648, 17, [107, 114, 128]);
        self::bolt($im, 500, 700, 14, self::YELLOW);
    }

    private static function drawDistributionBoard(GdImage $im, int $v): void
    {
        $ways = ['8 WAY', '12 WAY', '18 WAY', '24 WAY'][$v % 4];
        self::rrect($im, 246, 176, 754, 806, 14, [140, 148, 158]);
        self::rrect($im, 252, 182, 748, 800, 12, [206, 212, 218]);
        self::rrect($im, 276, 206, 724, 776, 10, [226, 231, 236]);
        imagefilledrectangle($im, 300, 228, 700, 292, self::col($im, self::INK));
        self::label($im, $ways, 470, 262, 26, self::YELLOW);
        self::bolt($im, 664, 260, 20, self::YELLOW);
        // Three rows of miniature breakers.
        foreach ([330, 462, 594] as $rowY) {
            for ($i = 0; $i < 6; $i++) {
                $x = 306 + $i * 66;
                self::rrect($im, $x, $rowY, $x + 54, $rowY + 96, 6, [244, 245, 247]);
                self::rrect($im, $x + 16, $rowY + 22, $x + 38, $rowY + 58, 4, self::INK);
                imagefilledrectangle($im, $x + 16, $rowY + 22, $x + 38, $rowY + 34, self::col($im, self::YELLOW));
            }
        }
        // Warning triangle.
        $tri = [648, 692, 692, 748, 604, 748];
        imagefilledpolygon($im, $tri, self::col($im, self::YELLOW));
        self::label($im, '!', 648, 730, 26, self::INK);
    }

    private static function drawSwitch(GdImage $im, int $v): void
    {
        $accents = [self::YELLOW, self::INK, [176, 141, 87], [148, 163, 184]];
        self::rrect($im, 318, 298, 682, 662, 30, [203, 208, 214]);
        self::rrect($im, 324, 292, 676, 656, 30, [250, 250, 250]);
        foreach ([[352, 326], [648, 326], [352, 622], [648, 622]] as [$x, $y]) {
            self::screw($im, $x, $y, 8);
        }
        self::rrect($im, 428, 356, 572, 596, 16, [203, 208, 214]);
        self::rrect($im, 434, 350, 566, 590, 16, [240, 242, 244]);
        // Accent half of the rocker.
        self::rrect($im, 434, 478, 566, 590, 16, $accents[$v % 4]);
        imagefilledrectangle($im, 434, 478, 566, 520, self::col($im, $accents[$v % 4]));
        imagefilledellipse($im, 500, 402, 13, 13, self::col($im, [198, 40, 40]));
    }

    private static function drawSocket(GdImage $im, int $v): void
    {
        self::rrect($im, 298, 298, 702, 662, 30, [203, 208, 214]);
        self::rrect($im, 304, 292, 696, 656, 30, [250, 250, 250]);
        foreach ([[332, 326], [668, 326], [332, 622], [668, 622]] as [$x, $y]) {
            self::screw($im, $x, $y, 8);
        }
        if ($v % 4 === 2) {
            // Single module + USB ports variant.
            self::socketModule($im, 402, 356, 598, 560);
            self::rrect($im, 448, 584, 552, 622, 6, [226, 231, 236]);
            imagefilledrectangle($im, 470, 596, 496, 610, self::col($im, self::INK));
            imagefilledrectangle($im, 504, 596, 530, 610, self::col($im, self::INK));
        } else {
            self::socketModule($im, 330, 366, 482, 586);
            self::socketModule($im, 518, 366, 670, 586);
        }
    }

    private static function socketModule(GdImage $im, int $x1, int $y1, int $x2, int $y2): void
    {
        self::rrect($im, $x1, $y1, $x2, $y2, 14, [226, 231, 236]);
        self::rrect($im, $x1 + 6, $y1 + 6, $x2 - 6, $y2 - 6, 12, [243, 244, 246]);
        $cx = (int) (($x1 + $x2) / 2);
        $cy = (int) (($y1 + $y2) / 2);
        $hole = self::col($im, self::INK);
        imagefilledellipse($im, $cx - 30, $cy + 26, 24, 24, $hole);
        imagefilledellipse($im, $cx + 30, $cy + 26, 24, 24, $hole);
        imagefilledellipse($im, $cx, $cy - 30, 24, 24, $hole);
    }

    private static function drawCableCoil(GdImage $im, int $v): void
    {
        $bases = [[198, 40, 40], [21, 101, 192], [33, 33, 33], [46, 125, 50]];
        $labels = ['1.5 mm2', '2.5 mm2', '4 mm2', '6 mm2'];
        $base = $bases[$v % 4];
        // Wound rings: alternating shades from outside in.
        for ($i = 0; $i < 6; $i++) {
            $f = $i % 2 === 0 ? 1.0 : 0.78;
            $w = 470 - $i * 52;
            $h = 392 - $i * 44;
            imagefilledellipse($im, 500, 470, $w, $h, self::col($im, self::shade($base, $f)));
        }
        $holeC = imagecolorallocatealpha($im, 10, 14, 20, 42);
        imagefilledellipse($im, 500, 470, 118, 96, $holeC);
        // Loose cable end.
        imagesetthickness($im, 17);
        $c = self::col($im, $base);
        imageline($im, 640, 620, 716, 716, $c);
        imageline($im, 716, 716, 806, 726, $c);
        imagefilledellipse($im, 812, 727, 30, 20, self::col($im, self::shade($base, 0.6)));
        // Spec tag.
        self::rrect($im, 586, 300, 772, 362, 10, self::YELLOW);
        self::label($im, $labels[$v % 4], 679, 333, 25, self::INK);
        self::label($im, 'CU / PVC', 500, 474, 22, [255, 255, 255]);
    }

    private static function drawLedBulb(GdImage $im, int $v): void
    {
        $watts = ['5W', '9W', '12W', '20W'];
        $temps = [[255, 214, 120], [255, 244, 200], [240, 246, 255], [255, 226, 150]];
        $tint = $temps[$v % 4];
        // Warm glow.
        for ($r = 250; $r > 60; $r -= 38) {
            $g = imagecolorallocatealpha($im, 255, 193, 7, 112 - (int) (($r / 250) * 20));
            imagefilledellipse($im, 500, 428, $r * 2, $r * 2, $g);
        }
        // Glass dome.
        imagefilledellipse($im, 500, 428, 308, 330, self::col($im, $tint));
        imagefilledellipse($im, 452, 372, 96, 120, self::col($im, [255, 255, 255]));
        // Filament.
        imagesetthickness($im, 6);
        $fil = self::col($im, [255, 160, 0]);
        imageline($im, 462, 470, 486, 408, $fil);
        imageline($im, 486, 408, 514, 408, $fil);
        imageline($im, 514, 408, 538, 470, $fil);
        self::label($im, $watts[$v % 4], 500, 520, 30, [146, 120, 40]);
        // Neck + screw base.
        imagefilledrectangle($im, 452, 578, 548, 616, self::col($im, [232, 234, 236]));
        foreach ([0, 1, 2, 3] as $i) {
            $y = 620 + $i * 22;
            self::rrect($im, 448, $y, 552, $y + 16, 6, $i % 2 === 0 ? [176, 182, 190] : [148, 155, 164]);
        }
        imagefilledellipse($im, 500, 724, 44, 22, self::col($im, [90, 98, 108]));
    }

    private static function drawLedPanel(GdImage $im, int $v): void
    {
        $watts = ['12W', '18W', '24W', '36W'];
        for ($r = 0; $r < 4; $r++) {
            $g = imagecolorallocatealpha($im, 255, 214, 90, 104 + $r * 5);
            self::rrect($im, 258 - $r * 10, 306 - $r * 10, 742 + $r * 10, 694 + $r * 10, 24, [255, 214, 90]);
        }
        self::rrect($im, 262, 310, 738, 690, 22, [176, 182, 190]);
        self::rrect($im, 272, 320, 728, 680, 18, [255, 253, 234]);
        // Subtle diffuser grid.
        $grid = imagecolorallocatealpha($im, 190, 185, 150, 88);
        imagesetthickness($im, 2);
        for ($x = 352; $x < 728; $x += 80) {
            imageline($im, $x, 330, $x, 670, $grid);
        }
        for ($y = 400; $y < 680; $y += 70) {
            imageline($im, 282, $y, 718, $y, $grid);
        }
        self::label($im, $watts[$v % 4].' LED', 500, 502, 42, [154, 143, 106]);
    }

    private static function drawFloodlight(GdImage $im, int $v): void
    {
        $watts = ['50W', '100W', '150W', '200W'];
        // Glow.
        for ($r = 300; $r > 80; $r -= 44) {
            $g = imagecolorallocatealpha($im, 255, 220, 110, 110 - (int) (($r / 300) * 18));
            imagefilledellipse($im, 500, 452, $r * 2, (int) ($r * 1.5), $g);
        }
        self::rrect($im, 282, 292, 718, 628, 26, [52, 58, 66]);
        self::rrect($im, 292, 302, 708, 618, 22, [30, 34, 40]);
        // LED array 4x3.
        for ($row = 0; $row < 3; $row++) {
            for ($colI = 0; $colI < 4; $colI++) {
                $x = 382 + $colI * 80;
                $y = 382 + $row * 78;
                imagefilledellipse($im, $x, $y, 56, 56, self::col($im, self::YELLOW));
                imagefilledellipse($im, $x, $y, 42, 42, self::col($im, [255, 246, 201]));
            }
        }
        self::label($im, $watts[$v % 4], 500, 592, 24, [200, 208, 218]);
        // Mounting bracket.
        $dark = self::col($im, [30, 34, 40]);
        imagefilledpolygon($im, [428, 628, 470, 628, 452, 736, 410, 736], $dark);
        imagefilledpolygon($im, [572, 628, 530, 628, 548, 736, 590, 736], $dark);
        self::rrect($im, 388, 730, 612, 768, 10, [30, 34, 40]);
        self::screw($im, 500, 749, 9);
    }

    private static function drawDrill(GdImage $im, int $v): void
    {
        $bodies = [[0, 131, 143], [239, 108, 0], [198, 40, 40], [21, 101, 192]];
        $labels = ['650W', '800W', '1050W', '12V'];
        $base = $bodies[$v % 4];
        $dark = self::shade($base, 0.72);
        // Handle (behind body).
        imagefilledpolygon($im, [428, 452, 522, 452, 548, 700, 452, 700], self::col($im, $dark));
        imagesetthickness($im, 4);
        $grip = self::col($im, self::shade($base, 0.5));
        for ($i = 0; $i < 4; $i++) {
            imageline($im, 452, 540 + $i * 34, 540, 520 + $i * 34, $grip);
        }
        // Body.
        self::rrect($im, 292, 322, 648, 478, 34, $base);
        self::rrect($im, 292, 322, 648, 386, 30, self::shade($base, 1.14));
        imagefilledrectangle($im, 292, 356, 648, 386, self::col($im, self::shade($base, 1.14)));
        // Gear head + chuck + bit.
        imagefilledpolygon($im, [648, 348, 706, 372, 706, 428, 648, 452], self::col($im, [107, 114, 128]));
        imagefilledpolygon($im, [706, 380, 752, 394, 752, 406, 706, 420], self::col($im, [75, 85, 99]));
        imagesetthickness($im, 7);
        imageline($im, 752, 400, 824, 400, self::col($im, [55, 62, 72]));
        // Trigger.
        self::rrect($im, 512, 470, 548, 540, 8, self::YELLOW);
        // Battery pack.
        self::rrect($im, 424, 692, 576, 764, 12, self::INK);
        imagefilledrectangle($im, 452, 716, 548, 730, self::col($im, self::YELLOW));
        self::label($im, $labels[$v % 4], 452, 424, 34, [255, 255, 255]);
        self::bolt($im, 600, 424, 22, self::YELLOW);
    }

    private static function drawMultimeter(GdImage $im, int $v): void
    {
        $accents = [self::YELLOW, [239, 108, 0], [38, 166, 154], [92, 141, 246]];
        $accent = $accents[$v % 4];
        self::rrect($im, 344, 194, 656, 806, 38, [13, 18, 22]);
        self::rrect($im, 352, 202, 648, 798, 34, [38, 50, 56]);
        // Screen.
        self::rrect($im, 384, 238, 616, 344, 10, [169, 199, 172]);
        self::rrect($im, 390, 244, 610, 338, 8, [200, 230, 201]);
        self::label($im, '220.5', 500, 282, 46, [27, 40, 30]);
        self::label($im, 'V ~ AUTO', 500, 326, 16, [74, 96, 78]);
        // Dial.
        imagefilledellipse($im, 500, 528, 216, 216, self::col($im, [55, 71, 79]));
        imagefilledellipse($im, 500, 528, 188, 188, self::col($im, [30, 38, 44]));
        imagesetthickness($im, 3);
        $tick = self::col($im, [176, 190, 197]);
        for ($i = 0; $i < 12; $i++) {
            $a = $i * M_PI / 6;
            imageline($im,
                (int) (500 + cos($a) * 96), (int) (528 + sin($a) * 96),
                (int) (500 + cos($a) * 106), (int) (528 + sin($a) * 106), $tick);
        }
        $ang = -2.2 + ($v % 4) * 0.9;
        imagesetthickness($im, 8);
        imageline($im, 500, 528, (int) (500 + cos($ang) * 74), (int) (528 + sin($ang) * 74), self::col($im, $accent));
        imagefilledellipse($im, 500, 528, 30, 30, self::col($im, $accent));
        self::label($im, 'OFF  V  A  OHM', 500, 672, 15, [144, 164, 174]);
        // Probe jacks.
        imagefilledellipse($im, 414, 742, 34, 34, self::col($im, [10, 12, 14]));
        imagefilledellipse($im, 500, 752, 34, 34, self::col($im, [10, 12, 14]));
        imagefilledellipse($im, 586, 742, 34, 34, self::col($im, [198, 40, 40]));
    }

    private static function drawCeilingFan(GdImage $im, int $v): void
    {
        $finishes = [[141, 110, 99], [225, 229, 233], [38, 50, 56], [176, 141, 87]];
        $base = $finishes[$v % 4];
        $dark = self::shade($base, 0.78);
        // Blades (behind hub).
        $b = self::col($im, $base);
        imagefilledpolygon($im, [568, 392, 812, 344, 824, 414, 578, 458], $b);
        imagefilledpolygon($im, [432, 392, 188, 344, 176, 414, 422, 458], $b);
        imagefilledpolygon($im, [428, 486, 296, 648, 366, 700, 492, 516], $b);
        imagefilledpolygon($im, [572, 486, 704, 648, 634, 700, 508, 516], self::col($im, $dark));
        // Down rod + canopy.
        imagefilledrectangle($im, 486, 196, 514, 348, self::col($im, [107, 114, 128]));
        self::rrect($im, 456, 148, 544, 204, 12, [75, 85, 99]);
        // Motor hub.
        imagefilledellipse($im, 500, 428, 184, 128, self::col($im, $dark));
        imagefilledellipse($im, 500, 420, 150, 96, self::col($im, $base));
        imagefilledellipse($im, 500, 424, 44, 44, self::col($im, self::YELLOW));
        self::label($im, '56 in', 500, 600, 26, [107, 114, 128]);
    }

    private static function drawStabilizer(GdImage $im, int $v): void
    {
        $bodies = [[55, 71, 79], [69, 90, 100], [38, 50, 56], [78, 52, 46]];
        $kva = ['3 KVA', '5 KVA', '10 KVA', '15 KVA'];
        $base = $bodies[$v % 4];
        self::rrect($im, 276, 356, 724, 746, 18, self::shade($base, 0.7));
        self::rrect($im, 282, 362, 718, 740, 16, $base);
        self::rrect($im, 306, 386, 694, 716, 12, self::shade($base, 0.62));
        // Analog gauge.
        imagefilledellipse($im, 418, 486, 128, 128, self::col($im, [243, 244, 246]));
        imagesetthickness($im, 3);
        $tick = self::col($im, [90, 98, 108]);
        for ($i = 0; $i < 7; $i++) {
            $a = M_PI * 0.75 + $i * M_PI / 4;
            imageline($im,
                (int) (418 + cos($a) * 48), (int) (486 + sin($a) * 48),
                (int) (418 + cos($a) * 56), (int) (486 + sin($a) * 56), $tick);
        }
        imagesetthickness($im, 5);
        imageline($im, 418, 486, 448, 452, self::col($im, [198, 40, 40]));
        self::label($im, 'V', 418, 528, 18, self::INK);
        // Digital display.
        self::rrect($im, 516, 436, 664, 506, 8, [10, 26, 32]);
        self::label($im, '220', 590, 474, 36, [105, 240, 174]);
        // LEDs.
        foreach ([[530, 552, [76, 175, 80]], [578, 552, self::YELLOW], [626, 552, [198, 40, 40]]] as [$x, $y, $c]) {
            imagefilledellipse($im, $x, $y, 20, 20, self::col($im, $c));
        }
        // Power switch.
        self::rrect($im, 330, 616, 414, 692, 8, [13, 18, 22]);
        imagefilledrectangle($im, 330, 616, 414, 646, self::col($im, self::YELLOW));
        // Vents.
        imagesetthickness($im, 5);
        $vent = self::col($im, self::shade($base, 0.45));
        for ($i = 0; $i < 4; $i++) {
            imageline($im, 486, 622 + $i * 20, 668, 622 + $i * 20, $vent);
        }
        self::label($im, $kva[$v % 4], 590, 410, 21, self::YELLOW);
    }

    private static function drawConduit(GdImage $im, int $v): void
    {
        $sets = [
            [[236, 239, 241], [207, 216, 220], [176, 190, 197]],
            [[255, 213, 128], [255, 193, 7], [224, 168, 0]],
            [[255, 183, 140], [255, 138, 76], [230, 110, 50]],
            [[207, 216, 220], [176, 190, 197], [144, 164, 174]],
        ];
        $labels = ['16 mm', '20 mm', '25 mm', '32 mm'];
        $pipes = $sets[$v % 4];
        foreach ([0, 1, 2] as $i) {
            $cy = 392 + $i * 96;
            $shadeC = $pipes[$i];
            self::rrect($im, 216, $cy - 34, 756, $cy + 34, 34, self::shade($shadeC, 0.82));
            self::rrect($im, 216, $cy - 34, 756, $cy + 10, 30, $shadeC);
            imagefilledrectangle($im, 216, $cy - 12, 756, $cy + 8, self::col($im, $shadeC));
            // Open end: outer rim + hollow core.
            imagefilledellipse($im, 756, $cy, 68, 68, self::col($im, self::shade($shadeC, 0.9)));
            imagefilledellipse($im, 756, $cy, 38, 38, self::col($im, [38, 44, 52]));
        }
        // Colored stripe on the top pipe.
        imagefilledrectangle($im, 260, 372, 700, 384, self::col($im, self::YELLOW));
        self::label($im, 'PVC '.$labels[$v % 4], 500, 716, 30, [90, 98, 108]);
    }

    private static function drawBattery(GdImage $im, int $v): void
    {
        $labels = ['12V 70Ah', '12V 100Ah', '24V 120Ah', '12V 55Ah'];
        // Terminals + caps.
        self::rrect($im, 356, 300, 424, 352, 8, [176, 190, 197]);
        self::rrect($im, 576, 300, 644, 352, 8, [176, 190, 197]);
        self::label($im, '+', 390, 330, 34, [198, 40, 40]);
        self::label($im, '-', 610, 328, 34, self::INK);
        self::rrect($im, 448, 306, 552, 352, 8, [13, 18, 22]);
        // Body.
        self::rrect($im, 296, 348, 704, 726, 16, [13, 18, 22]);
        self::rrect($im, 302, 354, 698, 720, 14, [38, 50, 56]);
        // Label plate with bolt.
        self::rrect($im, 330, 392, 670, 566, 10, [236, 239, 241]);
        self::bolt($im, 500, 452, 34, self::YELLOW);
        self::label($im, $labels[$v % 4], 500, 528, 27, self::INK);
        // Charge bars.
        for ($i = 0; $i < 4; $i++) {
            $filledBars = 2 + ($v % 3);
            $c = $i < $filledBars ? [105, 240, 174] : [90, 104, 112];
            self::rrect($im, 352 + $i * 78, 626, 410 + $i * 78, 668, 6, $c);
        }
    }
}
