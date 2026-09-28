<?php

declare(strict_types=1);

namespace App\Core;

use App\Content\ContentRepository;
use App\Db\Database;
use App\Mail\Mailer;
use App\Mpesa\DarajaClient;
use App\Mpesa\FakeDarajaClient;
use App\Mpesa\HttpDarajaClient;
use App\Tips\Ledger;
use App\Tips\TipService;
use PDO;
use Throwable;

/**
 * Application container and HTTP kernel. Services are created lazily so a page
 * that never touches the database never opens a connection.
 */
final class App
{
    private ?PDO $db = null;
    private ?ContentRepository $content = null;
    private ?View $view = null;
    private ?TipService $tips = null;
    private ?Router $router = null;

    public function __construct(
        public readonly string $root,
        public readonly string $basePath = '',
    ) {
    }

    public function db(): PDO
    {
        return $this->db ??= Database::connect();
    }

    /** Lets tests inject an existing connection. */
    public function setDb(PDO $db): void
    {
        $this->db = $db;
    }

    public function content(): ContentRepository
    {
        return $this->content ??= new ContentRepository($this->root . '/data');
    }

    public function view(): View
    {
        if ($this->view === null) {
            $this->view = new View($this->root . '/templates', $this->basePath, $this->root . '/public');
            $this->view->share('site', $this->content()->site());
            $this->view->share('primaryCv', $this->primaryCv());
            $this->view->share('nonce', Security::nonce());
        }
        return $this->view;
    }

    /** @return array<string, mixed>|null The master CV, with an "available" flag. */
    public function primaryCv(): ?array
    {
        foreach ($this->content()->all('cv') as $cv) {
            if (!empty($cv['primary'])) {
                $file = basename((string) ($cv['file'] ?? ''));
                return $cv + ['available' => $file !== '' && is_file($this->root . '/storage/cv/' . $file)];
            }
        }
        return null;
    }

    public function tips(): TipService
    {
        return $this->tips ??= new TipService(new Ledger($this->db()), $this->daraja());
    }

    public function daraja(): DarajaClient
    {
        return match (Env::get('MPESA_ENV', 'fake')) {
            'sandbox', 'production' => new HttpDarajaClient($this->root . '/storage/cache'),
            default => new FakeDarajaClient($this->root . '/storage/cache'),
        };
    }

    public function mailer(): Mailer
    {
        return new Mailer();
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->db());
    }

    public function router(): Router
    {
        if ($this->router === null) {
            $this->router = new Router();
            (require $this->root . '/src/routes.php')($this->router);
        }
        return $this->router;
    }

    public function url(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $e) {
            $this->log('error', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $response = $this->errorResponse($request, 500, Env::bool('APP_DEBUG') ? $e->getMessage() : null);
        }
        return Security::apply($response, $request->isHttps());
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router()->match($request->method, $request->path);

        if ($match['status'] === 404) {
            return $this->errorResponse($request, 404);
        }
        if ($match['status'] === 405) {
            return $this->errorResponse($request, 405)->withHeader('Allow', implode(', ', $match['allowed'] ?? []));
        }

        // Every state-changing request needs a CSRF token, except the Daraja
        // callback, which is authenticated by the secret token in its path.
        if ($request->method === 'POST'
            && !str_starts_with($request->path, '/api/mpesa/callback/')
            && !Csrf::valid($request)) {
            return $this->errorResponse($request, 419, 'Your session expired. Please reload the page and try again.');
        }

        $handler = $match['handler'];
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [new $class($this), $method];
        }
        return $handler($request, $match['params'] ?? []);
    }

    public function errorResponse(Request $request, int $status, ?string $detail = null): Response
    {
        $titles = [404 => 'Not found', 405 => 'Method not allowed', 419 => 'Session expired', 429 => 'Too many requests', 500 => 'Server error'];
        $title = $titles[$status] ?? 'Error';
        if ($request->wantsJson() || str_starts_with($request->path, '/api/')) {
            return Response::json(['error' => $detail ?? $title], $status);
        }
        try {
            $html = $this->view()->render('pages/error', [
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
            ]);
        } catch (Throwable) {
            $html = '<h1>' . $status . ' ' . htmlspecialchars($title) . '</h1>';
        }
        return Response::html($html, $status);
    }

    public function log(string $level, string $message): void
    {
        $line = sprintf("[%s] %s: %s\n", date('c'), strtoupper($level), $message);
        @file_put_contents($this->root . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
