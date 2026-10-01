<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function page(string $template, array $data = [], int $status = 200, ?string $layout = 'layout'): Response
    {
        return Response::html($this->app->view()->render($template, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->app->url($path));
    }

    protected function notFound(Request $request): Response
    {
        return $this->app->errorResponse($request, 404);
    }

    /** One-request flash data (form errors, success notices). */
    protected function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    protected function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** @return array<string, mixed> Content shared by the home page and the terminal API. */
    protected function publicContent(): array
    {
        $content = $this->app->content();
        $achievements = $content->all('achievements');
        usort($achievements, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
        return [
            'skills' => $content->all('skills'),
            'projects' => $content->all('projects'),
            'achievements' => $achievements,
            'experience' => $content->all('experience'),
            'cvs' => array_map(fn (array $cv): array => $cv + ['available' => $this->cvAvailable($cv)], $content->all('cv')),
        ];
    }

    /** @param array<string, mixed> $cv */
    protected function cvAvailable(array $cv): bool
    {
        $file = basename((string) ($cv['file'] ?? ''));
        return $file !== '' && is_file($this->app->root . '/storage/cv/' . $file);
    }
}
