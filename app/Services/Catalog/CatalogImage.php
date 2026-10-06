<?php

namespace App\Services\Catalog;

use App\Models\ProductImage;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CatalogImage
{
    public function dataUri(?ProductImage $image): ?string
    {
        if (! $image || ! function_exists('imagecreatefromstring')) return null;
        try {
            if ($image->external_url) {
                return Cache::remember('catalog-image:'.hash('sha256', $image->external_url), now()->addMinutes(15), fn () => $this->convert($this->download($image->external_url)));
            }
            $root = realpath(Storage::disk('public')->path(''));
            $path = realpath(Storage::disk('public')->path($image->path));
            if (!$root || !$path || !str_starts_with($path, $root.DIRECTORY_SEPARATOR) || filesize($path) > 5 * 1024 * 1024) return null;
            return $this->convert(file_get_contents($path));
        } catch (Throwable $exception) {
            // A missing photograph must not prevent downloading the collection.
            return null;
        }
    }

    private function download(string $url): string
    {
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $host = parse_url($url, PHP_URL_HOST);
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !$host || parse_url($url, PHP_URL_USER) || (parse_url($url, PHP_URL_PORT) ?? 443) !== 443) throw new \RuntimeException('Invalid image URL');
            $ips = gethostbynamel($host) ?: [];
            if (!$ips) throw new \RuntimeException('Image host not found');
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Private address rejected');
            }
            // Pin the validated address to prevent DNS rebinding. Redirects are revalidated.
            $response = Http::connectTimeout(2)->timeout(5)->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ips[0]]],
                'progress' => function ($total, $downloaded) {
                    if (max($total, $downloaded) > 5 * 1024 * 1024) throw new \RuntimeException('Image too large');
                },
            ])->get($url);
            if (in_array($response->status(), [301,302,303,307,308], true) && $response->header('Location')) {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
                continue;
            }
            $response->throw();
            return $response->body();
        }
        throw new \RuntimeException('Too many redirects');
    }

    private function convert(string $bytes): ?string
    {
        if (strlen($bytes) > 5 * 1024 * 1024) return null;
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] * $size[1] > 20000000 || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
        $source = @imagecreatefromstring($bytes);
        if (!$source) return null;
        $scale = min(1, 1400 / max($size[0], $size[1]));
        $width = max(1, (int) ($size[0] * $scale)); $height = max(1, (int) ($size[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start(); imagejpeg($canvas, null, 90); $jpeg = ob_get_clean();
        imagedestroy($source); imagedestroy($canvas);
        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
