<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Tips\PhoneNumber;
use App\Tips\TipException;
use App\Tips\TipService;

/** Buy Me a Tea: the page, the STK Push start and status polling. */
final class SupportController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->page('pages/support', [
            'pageTitle' => 'Buy Me a Tea',
            'presets' => TipService::PRESETS,
            'error' => $this->pullFlash('tip_error'),
            'old' => $this->pullFlash('tip_old', []),
        ]);
    }

    /** POST /api/tips (JSON, from tea.js) or /support (plain form, no JS). */
    public function start(Request $request): Response
    {
        $phone = $request->input('phone');
        $amount = $request->input('amount') ?: $request->input('custom_amount');

        // Validate first: typos should not use up the visitor's rate-limit quota.
        $normalized = PhoneNumber::normalize($phone);
        try {
            if ($normalized === null) {
                throw new TipException('Enter a Safaricom number like 0712 345 678.');
            }
            TipService::validateAmount($amount);
        } catch (TipException $e) {
            return $this->fail($request, $e->getMessage(), 422, $phone);
        }

        // Only requests that would really reach Safaricom count towards the limits.
        $limiter = $this->app->rateLimiter();
        if (!$limiter->attempt('tip-ip:' . $request->ip(), 5, 600)
            || !$limiter->attempt('tip-phone:' . $normalized, 3, 600)) {
            return $this->fail($request, 'Too many attempts. Please wait a few minutes and try again.', 429, $phone);
        }

        try {
            $tipId = $this->app->tips()->start($phone, $amount, $this->callbackUrl());
        } catch (TipException $e) {
            return $this->fail($request, $e->getMessage(), 422, $phone);
        }

        if ($request->wantsJson()) {
            return Response::json(['tipId' => $tipId], 201);
        }
        return $this->redirect('/support/tip/' . $tipId);
    }

    /** @param array{id: string} $params */
    public function status(Request $request, array $params): Response
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $params['id'])) {
            return $this->notFound($request);
        }
        $status = $this->app->tips()->status($params['id']);
        if ($status === null) {
            return $this->notFound($request);
        }
        if ($request->wantsJson() || str_starts_with($request->path, '/api/')) {
            return Response::json($status);
        }
        $response = $this->page('pages/tip-status', ['pageTitle' => 'Buy Me a Tea', 'tip' => $status, 'tipId' => $params['id']]);
        // No-JS fallback: the browser reloads this page until the payment settles.
        return $status['status'] === 'pending' ? $response->withHeader('Refresh', '4') : $response;
    }

    /** POST /api/mpesa/callback/{token}: called by Safaricom, always answered with 200. */
    public function callback(Request $request, array $params): Response
    {
        if (!hash_equals(TipService::callbackToken(), $params['token'])) {
            $this->app->log('warning', 'M-Pesa callback with a bad token from ' . $request->ip());
            return Response::json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
        }
        $outcome = $this->app->tips()->handleCallback($request->json());
        if ($outcome === 'unknown') {
            $this->app->log('warning', 'M-Pesa callback for an unknown checkout: ' . mb_substr($request->rawBody(), 0, 500));
        }
        return Response::json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function callbackUrl(): string
    {
        $base = rtrim((string) Env::get('MPESA_CALLBACK_BASE', Env::get('APP_URL', 'http://localhost')), '/');
        return $base . '/api/mpesa/callback/' . TipService::callbackToken();
    }

    private function fail(Request $request, string $message, int $status, string $phone): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }
        $this->flash('tip_error', $message);
        $this->flash('tip_old', ['phone' => $phone]);
        return $this->redirect('/support');
    }
}
