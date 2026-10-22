<?php

namespace App\Services\Documents;

use App\Exceptions\ImageNotConvertible;
use Dompdf\Dompdf;
use Dompdf\Options;
use GdImage;

/**
 * An uploaded photo as a one-page Letter PDF (req 2.4, decided Q8): the EXIF orientation is
 * applied, the longest side is capped before rendering (12 §3), and the image fits inside the
 * margins on a page turned to its aspect ratio.
 */
class ImageToPdfConverter
{
    public const MAX_SIDE = 3000;

    /** Larger images are refused before decoding: GD holds 4 bytes per pixel. */
    public const MAX_PIXELS = 50_000_000;

    private const PAGE = [612, 792]; // Letter, points

    private const MARGIN = 36; // 0.5 in

    public function render(string $bytes): string
    {
        [$image, $type] = $this->prepare($bytes);
        // PNG (scans, screenshots) stays lossless; photos are embedded as JPEG
        $png = $type === IMAGETYPE_PNG;
        [$width, $height] = [imagesx($image), imagesy($image)];
        $landscape = $width > $height;

        [$boxWidth, $boxHeight] = $landscape
            ? [self::PAGE[1] - 2 * self::MARGIN, self::PAGE[0] - 2 * self::MARGIN]
            : [self::PAGE[0] - 2 * self::MARGIN, self::PAGE[1] - 2 * self::MARGIN];
        $scale = min($boxWidth / $width, $boxHeight / $height);

        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 90);
        $encoded = (string) ob_get_clean();

        $dompdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]));
        $dompdf->setPaper('letter', $landscape ? 'landscape' : 'portrait');
        $dompdf->loadHtml(sprintf(
            '<html><head><style>@page { margin: %dpt; } body { margin: 0; text-align: center; }</style></head>'
            .'<body><img src="data:image/%s;base64,%s" style="width: %.2fpt; height: %.2fpt;"></body></html>',
            self::MARGIN, $png ? 'png' : 'jpeg', base64_encode($encoded), $width * $scale, $height * $scale,
        ));
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Decoded, scaled to MAX_SIDE, flattened onto white and turned upright, with its IMAGETYPE.
     *
     * @return array{0: GdImage, 1: int}
     */
    private function prepare(string $bytes): array
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new ImageNotConvertible('The file is not a JPG, PNG or WebP image.');
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new ImageNotConvertible("The image has {$size[0]}×{$size[1]} pixels, more than can be converted.");
        }

        $source = @imagecreatefromstring($bytes) ?: throw new ImageNotConvertible('The image could not be read.');
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        [$newWidth, $newHeight] = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];

        $image = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        unset($source);

        return [$size[2] === IMAGETYPE_JPEG ? $this->upright($image, $this->orientation($bytes)) : $image, $size[2]];
    }

    private function orientation(string $bytes): int
    {
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    private function upright(GdImage $image, int $orientation): GdImage
    {
        // imagerotate turns counter-clockwise
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        } ?: $image;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }
}
