<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;

final class ApiController extends Controller
{
    /** Everything the terminal needs, in one small cacheable response. */
    public function content(Request $request): Response
    {
        $site = $this->app->content()->site();
        $data = $this->publicContent();
        $payload = [
            'site' => array_intersect_key($site, array_flip([
                'name', 'title', 'tagline', 'location', 'availability', 'version', 'status',
                'bio', 'years_coding', 'email', 'whatsapp', 'github', 'linkedin',
            ])),
            'skills' => array_map(static fn (array $s): array => ['id' => $s['id'], 'label' => $s['label'], 'items' => $s['items']], $data['skills']),
            'projects' => array_map(fn (array $p): array => [
                'slug' => $p['slug'],
                'title' => $p['title'],
                'version' => $p['version'],
                'released' => $p['released'],
                'summary' => $p['summary'],
                'stack' => $p['stack'],
                'url' => empty($p['work_project']) ? $this->app->url('projects/' . $p['slug']) : null,
            ], $data['projects']),
            'achievements' => array_map(static fn (array $a): array => [
                'title' => $a['title'], 'issuer' => $a['issuer'], 'date' => $a['date'], 'category' => $a['category'],
            ], $data['achievements']),
            'cvs' => array_map(fn (array $cv): array => [
                'key' => $cv['key'],
                'label' => $cv['label'],
                'available' => $cv['available'],
                'url' => $this->app->url('cv/' . $cv['key']),
            ], $data['cvs']),
        ];
        return Response::json($payload)->withHeader('Cache-Control', 'public, max-age=300');
    }

    public function health(Request $request): Response
    {
        $site = $this->app->content()->site();
        return Response::json(['status' => 'UP', 'version' => $site['version'] ?? null]);
    }
}
