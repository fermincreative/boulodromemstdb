<?php

namespace App\Services;

use App\Models\Membership;
use App\Support\Color;
use GdImage;
use RuntimeException;
use TCPDF;
use TCPDF2DBarcode;

/**
 * Renders the membership card as PDF: a "phone" version (70 × 150 mm page) and a "print" version
 * (A4 with a 54 × 85.6 mm card and cut marks). Layout ported from the old ledenpas tool.
 *
 * The QR code points to the public verify page and carries a signature that must stay
 * compatible with cards that were already sent:
 *   strtoupper(substr(hash_hmac('sha256', "{number}|{full name}|{year}", QR_SECRET), 0, 10))
 */
class MembershipCard
{
    public const PHONE = 'phone';

    public const PRINT = 'print';

    private const FONT = 'dejavusans';

    /**
     * Card layouts in mm, measured from the original cards. y-values are baselines unless noted.
     *
     * @var array<string, array<string, mixed>>
     */
    private const LAYOUTS = [
        self::PHONE => [
            'width' => 70, 'height' => 150, 'radius' => 0,
            'header_height' => 32, 'accent_bar' => 1.6, 'bottom_bar' => 2,
            'title' => ['y' => 13.93, 'size' => 17, 'width' => 60],
            'subtitle' => ['y' => 22.35, 'size' => 7, 'spacing' => 0.5, 'with_year' => true],
            'qr' => ['x' => 10, 'y' => 41, 'size' => 50],
            'scan_hint' => ['y' => 95.76, 'size' => 6.2],
            'watermark' => ['x' => 6, 'y' => 106.87, 'width' => 58],
            'name' => ['y' => 104.47, 'size' => 13, 'width' => 62],
            'club' => ['y' => 111.62, 'size' => 9],
            'divider' => ['y' => 123, 'x1' => 6, 'x2' => 64],
            'labels' => ['y' => 127.35, 'size' => 6.2, 'spacing' => 0.3],
            'values' => ['y' => 136.64, 'size' => 17],
            'badge' => ['top' => 129.96, 'height' => 8.84, 'radius' => 1.88, 'padding' => 2.21],
            'valid_until' => ['y' => 144.23, 'size' => 6],
            'left' => 6, 'right' => 64,
        ],
        self::PRINT => [
            'width' => 54, 'height' => 85.6, 'radius' => 3,
            'header_height' => 19, 'accent_bar' => 1.1, 'bottom_bar' => 1.4,
            'title' => ['y' => 8.36, 'size' => 13, 'width' => 48],
            'subtitle' => ['y' => 13.56, 'size' => 5.4, 'spacing' => 0.35, 'with_year' => false],
            'qr' => ['x' => 12.5, 'y' => 22.6, 'size' => 29],
            'scan_hint' => null,
            'watermark' => ['x' => 5, 'y' => 59.31, 'width' => 44],
            'name' => ['y' => 57.09, 'size' => 10, 'width' => 48],
            'club' => ['y' => 61.76, 'size' => 6.8],
            'divider' => ['y' => 67.5, 'x1' => 4, 'x2' => 50],
            'labels' => ['y' => 70.73, 'size' => 4.6, 'spacing' => 0.3],
            'values' => ['y' => 77.06, 'size' => 11],
            'badge' => ['top' => 72.75, 'height' => 5.72, 'radius' => 1.22, 'padding' => 1.43],
            'valid_until' => ['y' => 82.24, 'size' => 4.4],
            'left' => 4, 'right' => 50,
        ],
    ];

    /** Position of the card on the A4 print page (mm). */
    private const PRINT_CARD_X = 78;

    private const PRINT_CARD_Y = 50;

    private ?string $watermarkPath = null;

    /**
     * @param  self::PHONE|self::PRINT  $variant
     */
    public function pdf(Membership $membership, string $variant): string
    {
        return match ($variant) {
            self::PHONE => $this->phonePdf($membership),
            self::PRINT => $this->printPdf($membership),
        };
    }

    public function phonePdf(Membership $membership): string
    {
        $layout = self::LAYOUTS[self::PHONE];
        $pdf = $this->newDocument([$layout['width'], $layout['height']], $membership);
        $this->drawCard($pdf, $membership, 0, 0, $layout);

        return $pdf->Output('', 'S');
    }

    public function printPdf(Membership $membership): string
    {
        $pdf = $this->newDocument('A4', $membership);
        $member = $membership->member;

        $this->text($pdf, 20, 25.09, 170, "Lidmaatschapskaart {$membership->cardYear()} — {$member->full_name}", 'L', 'B', 13, $this->color('text'));
        $this->text($pdf, 20, 31.08, 170, 'Print deze pagina op ware grootte (100%, niet "passend maken") en knip de kaart uit langs de snijtekens.', 'L', '', 9, [85, 85, 85]);
        $this->text($pdf, 20, 35.05, 170, 'Tip: lamineren of in een pashouder steken maakt de kaart duurzamer.', 'L', '', 9, [85, 85, 85]);

        $layout = self::LAYOUTS[self::PRINT];
        $this->drawCard($pdf, $membership, self::PRINT_CARD_X, self::PRINT_CARD_Y, $layout);
        $this->drawCutMarks($pdf, self::PRINT_CARD_X, self::PRINT_CARD_Y, $layout['width'], $layout['height']);

        return $pdf->Output('', 'S');
    }

    public function filename(Membership $membership, string $variant): string
    {
        $suffix = $variant === self::PHONE ? 'telefoon' : 'print';

        return "Lidmaatschapskaart-{$membership->cardYear()}-{$membership->member->display_number}-{$suffix}.pdf";
    }

    /**
     * The URL encoded in the QR code.
     */
    /**
     * The card's QR code as inline SVG for a web page: the same payload and error correction as on the PDF,
     * with a white quiet zone so phone scanners read it from a screen.
     */
    public function qrSvg(Membership $membership): string
    {
        $qr = (new TCPDF2DBarcode($this->qrPayload($membership), 'QRCODE,M'))->getBarcodeArray();
        $quiet = 4;
        $size = $qr['num_cols'] + 2 * $quiet;
        $path = '';

        foreach ($qr['bcode'] as $row => $cells) {
            foreach ($cells as $col => $on) {
                if ($on) {
                    $path .= 'M'.($col + $quiet).' '.($row + $quiet).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="crispEdges" role="img" '
            .'aria-label="QR-code lidmaatschapskaart '.$membership->cardYear().'">'
            .'<rect width="'.$size.'" height="'.$size.'" fill="#fff"/><path d="'.$path.'" fill="#000"/></svg>';
    }

    public function qrPayload(Membership $membership): string
    {
        $member = $membership->member;
        $year = $membership->cardYear();

        $query = http_build_query([
            'l' => $member->member_number,
            'n' => $member->full_name,
            'j' => $year,
            'c' => self::signature($member->member_number, $member->full_name, $year),
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->verifyUrl().'?'.$query;
    }

    public static function signature(int|string $number, string $fullName, int|string $year): string
    {
        $secret = config('membership_card.qr_secret');

        if (blank($secret)) {
            throw new RuntimeException('QR_SECRET is niet ingesteld in .env; zonder geheim kunnen geen kaarten worden gemaakt of gecontroleerd.');
        }

        return strtoupper(substr(hash_hmac('sha256', "{$number}|{$fullName}|{$year}", $secret), 0, 10));
    }

    public static function hasValidSignature(string $number, string $fullName, string $year, string $signature): bool
    {
        return hash_equals(self::signature($number, $fullName, $year), strtoupper($signature));
    }

    private function verifyUrl(): string
    {
        return rtrim(config('membership_card.verify_url') ?: url('/verify'), '/');
    }

    /**
     * @param  string|array{0: float, 1: float}  $format
     */
    private function newDocument(string|array $format, Membership $membership): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', $format, true, 'UTF-8', false);
        $pdf->SetCreator(config('membership_card.association_name'));
        $pdf->SetAuthor(config('membership_card.association_name'));
        $pdf->SetTitle("Lidmaatschapskaart {$membership->cardYear()} — {$membership->member->full_name}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $l
     */
    private function drawCard(TCPDF $pdf, Membership $membership, float $ox, float $oy, array $l): void
    {
        $member = $membership->member;
        $year = (string) $membership->cardYear();
        $w = $l['width'];
        $h = $l['height'];
        $r = $l['radius'];
        $accent = $this->color('accent');

        // Card body (print version: white rounded card with a thin border).
        if ($r > 0) {
            $pdf->RoundedRect($ox, $oy, $w, $h, $r, '1111', 'DF', ['width' => 0.2, 'color' => [204, 204, 204]], [255, 255, 255]);
        }

        // Header, accent line and bottom bar.
        $header = $this->color('header');
        if ($r > 0) {
            $pdf->RoundedRect($ox, $oy, $w, $l['header_height'], $r, '1001', 'F', [], $header);
            $pdf->RoundedRect($ox, $oy + $h - $l['bottom_bar'], $w, $l['bottom_bar'], $l['bottom_bar'], '0110', 'F', [], $accent);
        } else {
            $pdf->Rect($ox, $oy, $w, $l['header_height'], 'F', [], $header);
            $pdf->Rect($ox, $oy + $h - $l['bottom_bar'], $w, $l['bottom_bar'], 'F', [], $accent);
        }
        $pdf->Rect($ox, $oy + $l['header_height'], $w, $l['accent_bar'], 'F', [], $accent);

        $inner = $ox + ($w - $l['title']['width']) / 2;
        $this->fittedText($pdf, $inner, $oy + $l['title']['y'], $l['title']['width'], config('membership_card.association_name'), 'B', $l['title']['size'], $accent);

        $subtitle = 'LIDMAATSCHAPSKAART'.($l['subtitle']['with_year'] ? "  {$year}" : '');
        $this->text($pdf, $ox, $oy + $l['subtitle']['y'], $w, $subtitle, 'C', '', $l['subtitle']['size'], $this->color('accent_text'), $l['subtitle']['spacing']);

        // Watermark logo, drawn before the text so it sits behind name and club.
        $wm = $l['watermark'];
        $pdf->SetAlpha(0.08);
        $pdf->Image($this->watermark(), $ox + $wm['x'], $oy + $wm['y'], $wm['width'], 0, 'PNG');
        $pdf->SetAlpha(1);

        $qr = $l['qr'];
        $pdf->write2DBarcode($this->qrPayload($membership), 'QRCODE,M', $ox + $qr['x'], $oy + $qr['y'], $qr['size'], $qr['size'], [
            'border' => false,
            'padding' => 0,
            'fgcolor' => $this->color('text'),
            'bgcolor' => false,
        ], 'N');

        if ($l['scan_hint']) {
            $this->text($pdf, $ox, $oy + $l['scan_hint']['y'], $w, 'Toon deze code bij de ingang', 'C', '', $l['scan_hint']['size'], $this->color('muted'));
        }

        $nameX = $ox + ($w - $l['name']['width']) / 2;
        $this->fittedText($pdf, $nameX, $oy + $l['name']['y'], $l['name']['width'], $member->full_name, 'B', $l['name']['size'], $this->color('text'));

        $club = $member->club?->name;
        if (filled($club) && ! in_array($club, config('membership_card.hidden_clubs'), true)) {
            $this->text($pdf, $ox, $oy + $l['club']['y'], $w, $club, 'C', '', $l['club']['size'], $this->color('muted'));
        }

        $div = $l['divider'];
        $pdf->Line($ox + $div['x1'], $oy + $div['y'], $ox + $div['x2'], $oy + $div['y'], ['width' => 0.15, 'color' => $this->color('line')]);

        $left = $ox + $l['left'];
        $right = $ox + $l['right'];
        $labels = $l['labels'];
        $this->text($pdf, $left, $oy + $labels['y'], $right - $left, 'LIDNUMMER', 'L', '', $labels['size'], $this->color('muted'), $labels['spacing']);
        $this->text($pdf, $left, $oy + $labels['y'], $right - $left, 'JAAR', 'R', '', $labels['size'], $this->color('muted'), $labels['spacing']);

        $values = $l['values'];

        // Year on a badge, right-aligned, with a clear gap below the "JAAR" label. Colours can differ per year.
        $badge = $l['badge'];
        $yearStyle = app(CardYearStyles::class)->for($membership->cardYear());
        $pdf->SetFont(self::FONT, 'B', $values['size']);
        $badgeWidth = $pdf->GetStringWidth($year) + 2 * $badge['padding'];
        $pdf->RoundedRect($right - $badgeWidth, $oy + $badge['top'], $badgeWidth, $badge['height'], $badge['radius'], '1111', 'F', [], Color::rgb($yearStyle['badge']));
        $this->text($pdf, $right - $badgeWidth, $oy + $values['y'], $badgeWidth, $year, 'C', 'B', $values['size'], Color::rgb($yearStyle['text']));

        // Member number ("SBM-0261-91") in the space left of the badge, shrunk to fit.
        $numberWidth = $right - $badgeWidth - $badge['padding'] - $left;
        $this->fittedText($pdf, $left, $oy + $values['y'], $numberWidth, $member->display_number, 'B', $values['size'], [0, 0, 0], 'L');

        $this->text($pdf, $ox, $oy + $l['valid_until']['y'], $w, 'Geldig t/m '.$membership->ends_at->format('d-m-Y'), 'C', '', $l['valid_until']['size'], $this->color('muted'));
    }

    private function drawCutMarks(TCPDF $pdf, float $x, float $y, float $w, float $h): void
    {
        $style = ['width' => 0.15, 'color' => [153, 153, 153]];
        $gap = 1.5;
        $length = 4;

        foreach ([[$x, $y, -1, -1], [$x + $w, $y, 1, -1], [$x, $y + $h, -1, 1], [$x + $w, $y + $h, 1, 1]] as [$cx, $cy, $dx, $dy]) {
            $pdf->Line($cx + $dx * $gap, $cy, $cx + $dx * ($gap + $length), $cy, $style);
            $pdf->Line($cx, $cy + $dy * $gap, $cx, $cy + $dy * ($gap + $length), $style);
        }
    }

    /**
     * Writes a single line of text with its baseline at $baseline.
     *
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function text(TCPDF $pdf, float $x, float $baseline, float $width, string $text, string $align, string $style, float $size, array $color, float $spacing = 0): void
    {
        $pdf->SetFont(self::FONT, $style, $size);
        $pdf->setFontSpacing($spacing);
        $pdf->SetTextColorArray($color);
        $pdf->SetXY($x, $baseline);
        $pdf->Cell($width, 0, $text, 0, 0, $align, false, '', 0, false, 'L', 'M');
        $pdf->setFontSpacing(0);
    }

    /**
     * Text that shrinks (in 0.25 pt steps) until it fits $width, like the original.
     *
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function fittedText(TCPDF $pdf, float $x, float $baseline, float $width, string $text, string $style, float $maxSize, array $color, string $align = 'C'): void
    {
        $size = $maxSize;
        $pdf->SetFont(self::FONT, $style, $size);

        while ($size > 5 && $pdf->GetStringWidth($text) > $width) {
            $size -= 0.25;
            $pdf->SetFont(self::FONT, $style, $size);
        }

        $this->text($pdf, $x, $baseline, $width, $text, $align, $style, $size, $color);
    }

    /**
     * The logo with white made transparent (GD), cached for this request.
     */
    private function watermark(): string
    {
        if ($this->watermarkPath && is_file($this->watermarkPath)) {
            return $this->watermarkPath;
        }

        $source = config('membership_card.logo_path');
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Logo kan niet worden gelezen: {$source}");
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        // Black-on-white logo → black with alpha = darkness, so anti-aliased edges stay smooth.
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $darkness = 255 - (int) round(($rgb['red'] + $rgb['green'] + $rgb['blue']) / 3);
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 0, 0, 0, 127 - intdiv($darkness, 2)));
            }
        }

        $path = storage_path('framework/cache/card-watermark-'.md5_file($source).'.png');
        imagepng($out, $path);

        return $this->watermarkPath = $path;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function color(string $name): array
    {
        return Color::rgb(config("membership_card.colors.{$name}"));
    }
}
