<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use Throwable;

final class CvController extends Controller
{
    /** @param array{key: string} $params */
    public function download(Request $request, array $params): Response
    {
        $cv = $this->app->content()->find('cv', $params['key']);
        if ($cv === null || !$this->cvAvailable($cv)) {
            return $this->notFound($request);
        }
        $file = $this->app->root . '/storage/cv/' . basename((string) $cv['file']);

        try {
            $this->app->db()->prepare('INSERT INTO cv_downloads (cv_key, ip_hash, user_agent) VALUES (?, ?, ?)')
                ->execute([$cv['key'], Security::hash($request->ip()), mb_substr($request->header('User-Agent'), 0, 255)]);
        } catch (Throwable $e) {
            // Counting is nice to have; never block the download on it.
            $this->app->log('warning', 'CV download not counted: ' . $e->getMessage());
        }

        $name = preg_replace('/[^A-Za-z0-9-]+/', '-', ($this->app->content()->site()['name'] ?? 'cv') . '-' . $cv['label']);
        return new Response('', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) filesize($file),
            'Content-Disposition' => 'attachment; filename="' . trim((string) $name, '-') . '.pdf"',
            'Cache-Control' => 'no-cache',
        ], static function () use ($file): void {
            readfile($file);
        });
    }
}
