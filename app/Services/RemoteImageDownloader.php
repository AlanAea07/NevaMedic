<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class RemoteImageDownloader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function download(string $url): string
    {
        for ($hop = 0; $hop < 5; $hop++) {
            [$host, $port, $ip] = $this->validateDestination($url);
            $sink = tmpfile();
            if ($sink === false) {
                throw new RuntimeException('No se pudo crear el archivo temporal de descarga.');
            }
            try {
                $response = Http::timeout(10)->connectTimeout(5)
                    ->withUserAgent('Mozilla/5.0 (compatible; NevaMedic/1.0; ProductImages)')
                    ->withOptions([
                        'allow_redirects' => false,
                        'sink' => $sink,
                        'curl' => [
                            CURLOPT_PROXY => '',
                            CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
                        ],
                        'on_headers' => function (ResponseInterface $response) {
                            if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                                throw new RuntimeException('La imagen supera 10 MB.');
                            }
                        },
                        'progress' => function ($total, $downloaded) {
                            if (max($total, $downloaded) > self::MAX_BYTES) {
                                throw new RuntimeException('La imagen supera 10 MB.');
                            }
                        },
                    ])->get($url);

                if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $location = $response->header('Location');
                    if (! $location) {
                        throw new RuntimeException('Redirección sin destino.');
                    }
                    // Every redirect gets fresh DNS validation and a pinned connection.
                    $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                    continue;
                }
                if (! $response->successful()) {
                    throw new RuntimeException('El servidor de imágenes respondió HTTP '.$response->status().'.');
                }
                if (! str_starts_with(strtolower($response->header('Content-Type') ?? ''), 'image/')) {
                    throw new RuntimeException('El enlace no devuelve una imagen; puede ser una página de vista previa o requerir permiso.');
                }
                $stream = $response->toPsrResponse()->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                $bytes = '';
                while (! $stream->eof()) {
                    $bytes .= $stream->read(65536);
                    if (strlen($bytes) > self::MAX_BYTES) {
                        throw new RuntimeException('La imagen supera 10 MB.');
                    }
                }

                return $bytes;
            } finally {
                if (is_resource($sink)) {
                    fclose($sink);
                }
            }
        }
        throw new RuntimeException('El enlace tiene demasiadas redirecciones.');
    }

    private function validateDestination(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || ! in_array($port, [80, 443], true)
            || ! $host || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('Usa una URL pública http/https sin credenciales y con puerto 80 o 443.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveAddresses($host);
        if (! $ips) {
            throw new RuntimeException('No se pudo resolver el servidor de la imagen.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || str_starts_with(strtolower($ip), '::ffff:')) {
                throw new RuntimeException('No se permiten direcciones internas, privadas o reservadas.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    protected function resolveAddresses(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
