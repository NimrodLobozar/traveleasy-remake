<?php

declare(strict_types=1);

namespace App\Support\Console;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Draws a QR code with Unicode half blocks, two modules per character row, for scanning straight off a terminal.
 *
 * Plain text, no colours. By default the light modules are drawn (block characters), which reads as a normal QR code on
 * the usual dark terminal background; `forLightBackground` draws the dark modules instead. A 4-module quiet zone is
 * included. Used by the secrets helper (TE-025) for the super_admin's TOTP secret, which is always printed as text too.
 */
final readonly class TerminalQrCode
{
    public const int QUIET_ZONE = 4;

    private const string FULL = "\u{2588}";

    private const string UPPER = "\u{2580}";

    private const string LOWER = "\u{2584}";

    private const string EMPTY = ' ';

    /**
     * @return list<string> the lines of the drawing, all the same width
     */
    public static function render(#[\SensitiveParameter] string $content, bool $forLightBackground = false): array
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING, prefixEci: false)
            ->getMatrix();
        $size = $matrix->getWidth();

        $drawn = static function (int $x, int $y) use ($matrix, $size, $forLightBackground): bool {
            $dark = $x >= 0 && $y >= 0 && $x < $size && $y < $size && $matrix->get($x, $y) === 1;

            return $forLightBackground ? $dark : ! $dark;
        };

        $from = -self::QUIET_ZONE;
        $to = $size + self::QUIET_ZONE;
        $lines = [];

        for ($y = $from; $y < $to; $y += 2) {
            $line = '';

            for ($x = $from; $x < $to; $x++) {
                $top = $drawn($x, $y);
                $bottom = $y + 1 < $to && $drawn($x, $y + 1);

                $line .= match (true) {
                    $top && $bottom => self::FULL,
                    $top => self::UPPER,
                    $bottom => self::LOWER,
                    default => self::EMPTY,
                };
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
