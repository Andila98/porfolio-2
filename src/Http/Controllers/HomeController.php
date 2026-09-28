<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Tips\TipService;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $site = $this->app->content()->site();
        $sameAs = array_values(array_filter([$site['github'] ?? '', $site['linkedin'] ?? '']));
        return $this->page('pages/home', $this->publicContent() + [
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $site['name'] ?? '',
                'jobTitle' => $site['title'] ?? '',
                'address' => $site['location'] ?? '',
                'sameAs' => $sameAs,
            ],
            'presets' => TipService::PRESETS,
            'contactSent' => $this->pullFlash('contact_sent', false),
            'contactErrors' => $this->pullFlash('contact_errors', []),
            'contactOld' => $this->pullFlash('contact_old', ['project' => $request->query('project')]),
        ]);
    }
}
