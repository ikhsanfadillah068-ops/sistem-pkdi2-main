<?php

namespace App\Support;

/**
 * Pure-PHP ISO/IEC 18004 QR Code Matrix Generator (Byte mode, ECC Level L, Version 1–8).
 * Zero external dependencies — supports both SVG output (for Blade UI) and matrix output (for vector PDF rendering).
 */
class QrCodeGenerator
{
    /**
     * Version capacities & EC config for Error Correction Level L (1-block for v1-v5, 2-block for v6-v8):
     * [version => [size, totalDataCodewords, ecCodewordsPerBlock, numBlocks, alignmentCenters]]
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int, 4: list<int>}>
     */
    private const VERSION_SPEC = [
        1 => [21, 19, 7, 1, []],
        2 => [25, 34, 10, 1, [6, 18]],
        3 => [29, 55, 15, 1, [6, 22]],
        4 => [33, 80, 20, 1, [6, 26]],
        5 => [37, 108, 26, 1, [6, 30]],
        6 => [41, 136, 18, 2, [6, 34]],
        7 => [45, 156, 20, 2, [6, 22, 38]],
        8 => [49, 194, 24, 2, [6, 24, 42]],
    ];

    /**
     * Precomputed 15-bit format info strings for ECC Level L (01) and masks 0..7.
     *
     * @var array<int, int>
     */
    private const FORMAT_INFO_L = [
        0 => 0x77C4,
        1 => 0x72F3,
        2 => 0x7DAA,
        3 => 0x789D,
        4 => 0x662F,
        5 => 0x6318,
        6 => 0x6C41,
        7 => 0x6976,
    ];

    /**
     * Precomputed 18-bit version info strings for versions 7..8.
     *
     * @var array<int, int>
     */
    private const VERSION_INFO = [
        7 => 0x07C94,
        8 => 0x085BC,
    ];

    /**
     * Generates a 2D boolean matrix (true = dark module, false = light module).
     *
     * @return list<list<bool>>
     */
    public static function matrix(string $data): array
    {
        $bytes = array_values(unpack('C*', $data) ?: []);
        $len = count($bytes);

        $version = 1;
        foreach (self::VERSION_SPEC as $ver => $spec) {
            // Byte mode overhead: 4 bits mode + 8 bits length + 4 bits terminator = 2 bytes
            if ($len + 2 <= $spec[1]) {
                $version = $ver;
                break;
            }
            $version = $ver;
        }

        [$size, $totalDataCw, $ecPerBlock, $numBlocks, $alignCenters] = self::VERSION_SPEC[$version];

        // 1. Encode data bits (Byte mode 0100)
        $bits = [];
        self::pushBits($bits, 0b0100, 4);
        self::pushBits($bits, min($len, $totalDataCw - 2), 8);
        for ($i = 0; $i < min($len, $totalDataCw - 2); $i++) {
            self::pushBits($bits, $bytes[$i], 8);
        }

        $maxBits = $totalDataCw * 8;
        $termLen = min(4, $maxBits - count($bits));
        self::pushBits($bits, 0, $termLen);
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $dataCodewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $val = 0;
            for ($b = 0; $b < 8; $b++) {
                $val = ($val << 1) | ($bits[$i + $b] ?? 0);
            }
            $dataCodewords[] = $val;
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataCodewords) < $totalDataCw) {
            $dataCodewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // 2. Reed-Solomon EC codewords (split into equal blocks since all v1..v8 L specs have even dataCW / numBlocks)
        $blockSize = intdiv($totalDataCw, $numBlocks);
        $dataBlocks = [];
        $ecBlocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $slice = array_slice($dataCodewords, $b * $blockSize, $blockSize);
            $dataBlocks[] = $slice;
            $ecBlocks[] = self::rsComputeEc($slice, $ecPerBlock);
        }

        // Interleave data then EC
        $allCodewords = [];
        for ($i = 0; $i < $blockSize; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $allCodewords[] = $dataBlocks[$b][$i];
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $allCodewords[] = $ecBlocks[$b][$i];
            }
        }

        $bitStream = [];
        foreach ($allCodewords as $cw) {
            self::pushBits($bitStream, $cw, 8);
        }

        // 3. Build function modules matrix & reservation mask
        $grid = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Finder patterns + separators
        self::placeFinder($grid, $reserved, $size, 0, 0);
        self::placeFinder($grid, $reserved, $size, $size - 7, 0);
        self::placeFinder($grid, $reserved, $size, 0, $size - 7);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = ($i % 2 === 0);
            $grid[6][$i] = $dark;
            $reserved[6][$i] = true;
            $grid[$i][6] = $dark;
            $reserved[$i][6] = true;
        }

        // Alignment patterns
        if ($alignCenters !== []) {
            foreach ($alignCenters as $cy) {
                foreach ($alignCenters as $cx) {
                    if ($reserved[$cy][$cx]) {
                        continue;
                    }
                    self::placeAlignment($grid, $reserved, $cx, $cy);
                }
            }
        }

        // Dark module
        $grid[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // Version info for v >= 7
        if ($version >= 7 && isset(self::VERSION_INFO[$version])) {
            $vBits = self::VERSION_INFO[$version];
            for ($i = 0; $i < 18; $i++) {
                $bit = (($vBits >> $i) & 1) === 1;
                $r = intdiv($i, 3);
                $c = $size - 11 + ($i % 3);
                $grid[$r][$c] = $bit;
                $reserved[$r][$c] = true;
                $grid[$c][$r] = $bit;
                $reserved[$c][$r] = true;
            }
        }

        // 4. Place data bits in zigzag upward/downward columns with Mask 0: (row + col) % 2 == 0
        $maskPattern = 0;
        $bitIdx = 0;
        $totalBits = count($bitStream);
        $upward = true;

        for ($col = $size - 1; $col >= 1; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? ($size - 1 - $step) : $step;
                for ($dx = 0; $dx < 2; $dx++) {
                    $c = $col - $dx;
                    if ($c < 0 || $reserved[$row][$c]) {
                        continue;
                    }
                    $bit = $bitIdx < $totalBits ? ($bitStream[$bitIdx] === 1) : false;
                    $bitIdx++;
                    if ((($row + $c) % 2) === 0) {
                        $bit = ! $bit;
                    }
                    $grid[$row][$c] = $bit;
                }
            }
            $upward = ! $upward;
        }

        // 5. Write format info for ECC-L, Mask 0
        $fmt = self::FORMAT_INFO_L[$maskPattern];
        $fmtBits = [];
        for ($i = 0; $i < 15; $i++) {
            $fmtBits[$i] = (($fmt >> $i) & 1) === 1;
        }

        // Top-left copy
        $tlCoords1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c] = $tlCoords1[$i];
            $grid[$r][$c] = $fmtBits[14 - $i];
        }

        // Bottom-left & top-right copy
        for ($i = 0; $i < 7; $i++) {
            $grid[$size - 1 - $i][8] = $fmtBits[$i];
        }
        for ($i = 7; $i < 15; $i++) {
            $grid[8][$size - 15 + $i] = $fmtBits[$i];
        }

        return $grid;
    }

    /**
     * Generates an inline SVG string of the QR code suitable for embedding in Blade views.
     */
    public static function svg(string $data, int $pixelSize = 148, string $fg = '#18181b', string $bg = '#ffffff'): string
    {
        $matrix = self::matrix($data);
        $count = count($matrix);
        $quiet = 2;
        $viewSize = $count + ($quiet * 2);

        $paths = [];
        for ($r = 0; $r < $count; $r++) {
            for ($c = 0; $c < $count; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $paths[] = "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        $d = implode('', $paths);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges" role="img" aria-label="QR Code Verifikasi PKDI"><rect width="%d" height="%d" fill="%s"/><path d="%s" fill="%s"/></svg>',
            $pixelSize,
            $pixelSize,
            $viewSize,
            $viewSize,
            $viewSize,
            $viewSize,
            $bg,
            $d,
            $fg
        );
    }

    /**
     * @param  list<int>  $bits
     */
    private static function pushBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * @param  list<list<bool>>  $grid
     * @param  list<list<bool>>  $reserved
     */
    private static function placeFinder(array &$grid, array &$reserved, int $size, int $topRow, int $leftCol): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $topRow + $r;
                $cc = $leftCol + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                $inOuter = ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6);
                $onBorder = ($r === 0 || $r === 6 || $c === 0 || $c === 6);
                $inCore = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                $grid[$rr][$cc] = $inOuter && ($onBorder || $inCore);
                $reserved[$rr][$cc] = true;
            }
        }
    }

    /**
     * @param  list<list<bool>>  $grid
     * @param  list<list<bool>>  $reserved
     */
    private static function placeAlignment(array &$grid, array &$reserved, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $grid[$cy + $dy][$cx + $dx] = ($dist === 2 || $dist === 0);
                $reserved[$cy + $dy][$cx + $dx] = true;
            }
        }
    }

    /**
     * Reed-Solomon error correction over GF(256) with primitive polynomial 0x11d.
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function rsComputeEc(array $data, int $ecCount): array
    {
        $gen = [1];
        $root = 1;
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::gfMul($coef, $root);
            }
            $gen = $next;
            $root = self::gfMul($root, 2);
        }

        $rem = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($rem);
            $rem[] = 0;
            for ($j = 0; $j < $ecCount; $j++) {
                $rem[$j] ^= self::gfMul($gen[$j + 1], $factor);
            }
        }

        return $rem;
    }

    private static function gfMul(int $a, int $b): int
    {
        $res = 0;
        for ($i = 0; $i < 8; $i++) {
            if (($b & 1) !== 0) {
                $res ^= $a;
            }
            $hi = $a & 0x80;
            $a = ($a << 1) & 0xFF;
            if ($hi !== 0) {
                $a ^= 0x1D;
            }
            $b >>= 1;
        }

        return $res;
    }
}
