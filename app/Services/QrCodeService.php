<?php

namespace App\Services;

use App\Models\ProduceLot;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeService
{
    public function ensureForLot(ProduceLot $lot): ProduceLot
    {
        if ($lot->trace_token === null) {
            $lot->trace_token = Str::random(40);
        }

        if ($lot->qr_code_path === null || ! Storage::disk('public')->exists($lot->qr_code_path)) {
            $lot->qr_code_path = $this->generate($lot);
        }

        $lot->save();

        return $lot->fresh();
    }

    public function generate(ProduceLot $lot): string
    {
        if ($lot->trace_token === null) {
            $lot->trace_token = Str::random(40);
            $lot->save();
        }

        $url = route('traceability.show', ['token' => $lot->trace_token]);
        $extension = $this->usesGd() ? 'png' : 'svg';
        $filename = 'qr-codes/lot-'.Str::slug($lot->code).'-'.Str::slug(substr($lot->trace_token, 0, 12)).'.'.$extension;
        $fullPath = Storage::disk('public')->path($filename);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Writer($this->renderer());
        $writer->writeFile($url, $fullPath, encoding: 'UTF-8', ecLevel: ErrorCorrectionLevel::M());

        return $filename;
    }

    public function url(ProduceLot $lot): ?string
    {
        $lot = $this->ensureForLot($lot);

        if ($lot->qr_code_path && Storage::disk('public')->exists($lot->qr_code_path)) {
            return asset('storage/'.$lot->qr_code_path);
        }

        if ($lot->trace_token) {
            return route('traceability.show', ['token' => $lot->trace_token]);
        }

        return null;
    }

    public function traceUrl(ProduceLot $lot): ?string
    {
        $lot = $this->ensureForLot($lot);

        if (! $lot->trace_token) {
            return null;
        }

        return route('traceability.show', ['token' => $lot->trace_token]);
    }

    private function renderer(): GDLibRenderer|ImageRenderer
    {
        if ($this->usesGd()) {
            return new GDLibRenderer(400, 2, 'png', 9);
        }

        return new ImageRenderer(
            new RendererStyle(400, 2),
            new SvgImageBackEnd,
        );
    }

    private function usesGd(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }
}
