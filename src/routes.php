<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SupportController;

return static function (Router $r): void {
    // Public pages
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/projects', [ProjectsController::class, 'index']);
    $r->get('/projects/{slug}', [ProjectsController::class, 'show']);
    $r->get('/cv/{key}', [CvController::class, 'download']);
    $r->post('/contact', [ContactController::class, 'submit']);

    // Buy Me a Tea
    $r->get('/support', [SupportController::class, 'show']);
    $r->post('/support', [SupportController::class, 'start']);
    $r->get('/support/tip/{id}', [SupportController::class, 'status']);
    $r->post('/api/tips', [SupportController::class, 'start']);
    $r->get('/api/tips/{id}', [SupportController::class, 'status']);
    $r->post('/api/mpesa/callback/{token}', [SupportController::class, 'callback']);

    // API + SEO
    $r->get('/api/content', [ApiController::class, 'content']);
    $r->get('/healthz', [ApiController::class, 'health']);
    $r->get('/sitemap.xml', [SeoController::class, 'sitemap']);

    // Admin
    $r->get('/admin', [AdminController::class, 'dashboard']);
    $r->get('/admin/login', [AdminController::class, 'loginForm']);
    $r->post('/admin/login', [AdminController::class, 'login']);
    $r->post('/admin/logout', [AdminController::class, 'logout']);
    $r->get('/admin/collections/{name}', [AdminController::class, 'collection']);
    $r->get('/admin/collections/{name}/new', [AdminController::class, 'edit']);
    $r->get('/admin/collections/{name}/edit/{key}', [AdminController::class, 'edit']);
    $r->post('/admin/collections/{name}/save', [AdminController::class, 'save']);
    $r->post('/admin/collections/{name}/move', [AdminController::class, 'move']);
    $r->post('/admin/collections/{name}/delete', [AdminController::class, 'delete']);
    $r->post('/admin/collections/{name}/restore', [AdminController::class, 'restore']);
    $r->get('/admin/uploads', [AdminController::class, 'uploads']);
    $r->post('/admin/uploads/cv', [AdminController::class, 'uploadCv']);
    $r->post('/admin/uploads/image', [AdminController::class, 'uploadImage']);
    $r->get('/admin/ledger', [AdminController::class, 'ledger']);
    $r->get('/admin/messages', [AdminController::class, 'messages']);
    $r->post('/admin/messages/read', [AdminController::class, 'markRead']);
};
