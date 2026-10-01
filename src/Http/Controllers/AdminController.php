<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Admin\Auth;
use App\Content\CollectionSchema;
use App\Core\Request;
use App\Core\Response;
use App\Tips\Ledger;
use RuntimeException;
use Throwable;

/** Single-user admin: content editor, uploads, tip ledger and messages. */
final class AdminController extends Controller
{
    private const MAX_CV_BYTES = 5 * 1024 * 1024;
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    // ----------------------------------------------------------------- auth

    public function loginForm(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/admin');
        }
        return $this->admin('admin/login', ['error' => $this->pullFlash('login_error'), 'configured' => Auth::configured()], 'admin/layout-bare');
    }

    public function login(Request $request): Response
    {
        if (!$this->app->rateLimiter()->attempt('admin-login:' . $request->ip(), 5, 900)) {
            $this->flash('login_error', 'Too many attempts. Try again in 15 minutes.');
            return $this->redirect('/admin/login');
        }
        if (!Auth::attempt($request->input('user'), (string) ($request->post['password'] ?? ''))) {
            $this->app->log('notice', 'Failed admin login from ' . $request->ip());
            $this->flash('login_error', 'Wrong username or password.');
            return $this->redirect('/admin/login');
        }
        return $this->redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->redirect('/admin/login');
    }

    // ------------------------------------------------------------ dashboard

    public function dashboard(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $stats = ['unread' => null, 'downloads' => [], 'totals' => []];
        try {
            $db = $this->app->db();
            $stats['unread'] = (int) $db->query('SELECT COUNT(*) FROM messages WHERE read_at IS NULL')->fetchColumn();
            $stats['downloads'] = $db->query('SELECT cv_key, COUNT(*) AS n FROM cv_downloads GROUP BY cv_key ORDER BY n DESC')->fetchAll();
            $stats['totals'] = (new Ledger($db))->monthlyTotals();
        } catch (Throwable $e) {
            $stats['dbError'] = $e->getMessage();
        }
        return $this->admin('admin/dashboard', ['stats' => $stats, 'collections' => CollectionSchema::all()]);
    }

    // ---------------------------------------------------------- collections

    /** @param array{name: string} $params */
    public function collection(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $schema = $this->schema($params['name']);
        if ($schema === null) {
            return $this->notFound($request);
        }
        if (!empty($schema['singleton'])) {
            return $this->editForm($params['name'], $schema, $this->app->content()->site(), $params['name']);
        }
        return $this->admin('admin/collection-list', [
            'name' => $params['name'],
            'schema' => $schema,
            'records' => $this->app->content()->all($params['name'], true),
            'history' => $this->app->content()->history($params['name']),
            'notice' => $this->pullFlash('notice'),
            'error' => $this->pullFlash('error'),
        ]);
    }

    /** @param array{name: string, key?: string} $params */
    public function edit(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $schema = $this->schema($params['name']);
        if ($schema === null) {
            return $this->notFound($request);
        }
        $record = [];
        if (isset($params['key'])) {
            $record = $this->app->content()->find($params['name'], $params['key'], true);
            if ($record === null) {
                return $this->notFound($request);
            }
        }
        return $this->editForm($params['name'], $schema, $record, $params['key'] ?? null);
    }

    /** @param array{name: string} $params */
    public function save(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $name = $params['name'];
        $schema = $this->schema($name);
        if ($schema === null) {
            return $this->notFound($request);
        }
        [$record, $errors] = CollectionSchema::fromInput($name, $request->post);
        $originalKey = $request->input('_original_key') ?: null;

        if ($errors === []) {
            try {
                $this->app->content()->upsert($name, $record, $originalKey);
                $this->flash('notice', 'Saved.');
                return $this->redirect('/admin/collections/' . $name);
            } catch (RuntimeException $e) {
                $errors['_form'] = $e->getMessage();
            }
        }
        return $this->editForm($name, $schema, $record, $originalKey, $errors, 422);
    }

    /** @param array{name: string} $params */
    public function move(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        if ($this->schema($params['name']) === null) {
            return $this->notFound($request);
        }
        $this->app->content()->move($params['name'], $request->input('key'), $request->input('direction') === 'up' ? -1 : 1);
        return $this->redirect('/admin/collections/' . $params['name']);
    }

    /** @param array{name: string} $params */
    public function delete(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $schema = $this->schema($params['name']);
        if ($schema === null || !empty($schema['singleton'])) {
            return $this->notFound($request);
        }
        $this->app->content()->delete($params['name'], $request->input('key'));
        $this->flash('notice', 'Deleted. You can undo this with "Restore" below.');
        return $this->redirect('/admin/collections/' . $params['name']);
    }

    /** @param array{name: string} $params */
    public function restore(Request $request, array $params): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        if ($this->schema($params['name']) === null) {
            return $this->notFound($request);
        }
        try {
            $this->app->content()->restore($params['name'], $request->input('file'));
            $this->flash('notice', 'Restored ' . $request->input('file') . '.');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/collections/' . $params['name']);
    }

    // -------------------------------------------------------------- uploads

    public function uploads(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $content = $this->app->content();
        return $this->admin('admin/uploads', [
            'cvs' => array_map(fn (array $cv): array => $cv + ['available' => $this->cvAvailable($cv)], $content->all('cv', true)),
            'projects' => array_values(array_filter($content->all('projects', true), static fn (array $p): bool => empty($p['work_project']))),
            'notice' => $this->pullFlash('notice'),
            'error' => $this->pullFlash('error'),
        ]);
    }

    public function uploadCv(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $content = $this->app->content();
        $cv = $content->find('cv', $request->input('key'), true);
        $file = $request->files['file'] ?? null;
        $error = $this->checkUpload($file, self::MAX_CV_BYTES, ['application/pdf' => 'pdf']);
        if ($cv === null) {
            $error = 'Pick a CV version.';
        }
        if ($error !== null) {
            $this->flash('error', $error);
            return $this->redirect('/admin/uploads');
        }

        $filename = 'cv-' . $cv['key'] . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $this->app->root . '/storage/cv/' . $filename)) {
            $this->flash('error', 'Could not save the file. Check that storage/cv is writable.');
            return $this->redirect('/admin/uploads');
        }
        $content->upsert('cv', ['file' => $filename, 'updated' => date('Y-m-d')] + $cv, $cv['key']);
        $this->flash('notice', "Uploaded {$cv['label']}.");
        return $this->redirect('/admin/uploads');
    }

    public function uploadImage(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $project = $this->app->content()->find('projects', $request->input('slug'), true);
        $file = $request->files['file'] ?? null;
        $error = $this->checkUpload($file, self::MAX_IMAGE_BYTES, self::IMAGE_TYPES);
        if ($project === null) {
            $error = 'Pick a project.';
        }
        if ($error !== null) {
            $this->flash('error', $error);
            return $this->redirect('/admin/uploads');
        }

        $dir = $this->app->root . '/public/assets/img/projects/' . $project['slug'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $extension = self::IMAGE_TYPES[(string) mime_content_type($file['tmp_name'])];
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo((string) $file['name'], PATHINFO_FILENAME))), '-') ?: 'image';
        $target = $dir . '/' . $base . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->flash('error', 'Could not save the image.');
            return $this->redirect('/admin/uploads');
        }
        $this->flash('notice', 'Image added to ' . $project['title'] . '.');
        return $this->redirect('/admin/uploads');
    }

    // --------------------------------------------------------------- ledger

    public function ledger(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $ledger = new Ledger($this->app->db());

        if ($request->query('format') === 'csv') {
            return $this->ledgerCsv($ledger);
        }
        $tipId = $request->query('tip');
        if (preg_match('/^[a-f0-9]{32}$/', $tipId)) {
            return $this->admin('admin/ledger-tip', ['tipId' => $tipId, 'events' => $ledger->events($tipId)]);
        }
        $page = max(1, (int) $request->query('page', '1'));
        return $this->admin('admin/ledger', [
            'tips' => $ledger->tips(50, ($page - 1) * 50),
            'totals' => $ledger->monthlyTotals(),
            'page' => $page,
        ]);
    }

    private function ledgerCsv(Ledger $ledger): Response
    {
        $out = fopen('php://temp', 'r+');
        $columns = ['id', 'created_at', 'tip_id', 'event', 'amount', 'phone_last3', 'mpesa_receipt', 'checkout_request_id', 'result_code', 'result_desc', 'source', 'environment'];
        fputcsv($out, $columns, escape: '');
        foreach ($ledger->allEvents() as $row) {
            fputcsv($out, array_map(static fn (string $c): string => (string) $row[$c], $columns), escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="tip-ledger-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ------------------------------------------------------------- messages

    public function messages(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $messages = $this->app->db()->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();
        return $this->admin('admin/messages', ['messages' => $messages]);
    }

    public function markRead(Request $request): Response
    {
        if ($guard = $this->guard()) {
            return $guard;
        }
        $this->app->db()->prepare('UPDATE messages SET read_at = NOW() WHERE id = ? AND read_at IS NULL')
            ->execute([(int) $request->input('id')]);
        return $this->redirect('/admin/messages');
    }

    // -------------------------------------------------------------- helpers

    private function guard(): ?Response
    {
        return Auth::check() ? null : $this->redirect('/admin/login');
    }

    /** @return array<string, mixed>|null */
    private function schema(string $name): ?array
    {
        return CollectionSchema::all()[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $record
     * @param array<string, string> $errors
     */
    private function editForm(string $name, array $schema, array $record, ?string $originalKey, array $errors = [], int $status = 200): Response
    {
        return $this->admin('admin/collection-edit', [
            'name' => $name,
            'schema' => $schema,
            'record' => $record,
            'originalKey' => $originalKey,
            'errors' => $errors,
        ], 'admin/layout', $status);
    }

    /** @param array<string, mixed> $data */
    private function admin(string $template, array $data = [], string $layout = 'admin/layout', int $status = 200): Response
    {
        return $this->page($template, $data + ['pageTitle' => 'Admin'], $status, $layout)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, mixed>|null $file
     * @param array<string, string> $allowedTypes
     */
    private function checkUpload(?array $file, int $maxBytes, array $allowedTypes): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choose a file to upload.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'The upload failed. Try again.';
        }
        if ($file['size'] > $maxBytes) {
            return sprintf('File is too large (max %d MB).', $maxBytes / 1024 / 1024);
        }
        if (!isset($allowedTypes[(string) mime_content_type($file['tmp_name'])])) {
            return 'Wrong file type. Allowed: ' . implode(', ', array_values($allowedTypes)) . '.';
        }
        return null;
    }
}
