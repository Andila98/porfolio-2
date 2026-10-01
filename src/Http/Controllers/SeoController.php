<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;

final class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        $paths = ['/', '/projects', '/support'];
        foreach ($this->app->content()->all('projects') as $project) {
            if (empty($project['work_project'])) {
                $paths[] = '/projects/' . $project['slug'];
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paths as $path) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $path, ENT_XML1) . "</loc></url>\n";
        }
        $xml .= "</urlset>\n";
        return Response::text($xml, 200, 'application/xml');
    }
}
