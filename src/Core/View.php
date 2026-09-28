<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renders plain PHP templates from /templates. Templates receive their data as
 * local variables plus $view (this object) for helpers such as e() and url().
 */
final class View
{
    /** @var array<string, mixed> Shared with every template (site settings, nonce, …). */
    private array $shared = [];

    public function __construct(
        private readonly string $templateDir,
        private readonly string $basePath,
        private readonly string $publicDir,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->partial($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        $view = $this;
        extract($this->shared + $data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Absolute path for an internal URL, respecting the base path (XAMPP subfolders). */
    public function url(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    /** Asset URL with a modification-time query string for cache busting. */
    public function asset(string $path): string
    {
        $file = $this->publicDir . '/assets/' . ltrim($path, '/');
        $version = is_file($file) ? (string) filemtime($file) : '0';
        return $this->url('assets/' . ltrim($path, '/')) . '?v=' . $version;
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . $this->e(Csrf::token()) . '">';
    }

    public function date(?string $date, string $format = 'M Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $time = strtotime($date);
        return $time === false ? $date : date($format, $time);
    }
}
