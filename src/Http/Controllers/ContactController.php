<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security;

final class ContactController extends Controller
{
    public function submit(Request $request): Response
    {
        $input = [
            'name' => mb_substr($request->input('name'), 0, 120),
            'email' => mb_substr($request->input('email'), 0, 190),
            'subject' => mb_substr($request->input('subject'), 0, 190),
            'message' => mb_substr($request->input('message'), 0, 5000),
            'project' => mb_substr($request->input('project'), 0, 64),
        ];

        // Honeypot: real people never fill the hidden "website" field.
        if ($request->input('website') !== '') {
            return $this->done($request, true);
        }

        $errors = [];
        if ($input['name'] === '') {
            $errors['name'] = 'Please tell me your name.';
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (mb_strlen($input['message']) < 10) {
            $errors['message'] = 'Please write at least a sentence.';
        }
        if ($errors === [] && !$this->app->rateLimiter()->attempt('contact:' . $request->ip(), 5, 3600)) {
            $errors['form'] = 'Too many messages from your connection. Please try again later or email me directly.';
        }

        if ($errors !== []) {
            if ($request->wantsJson()) {
                return Response::json(['errors' => $errors], 422);
            }
            $this->flash('contact_errors', $errors);
            $this->flash('contact_old', $input);
            return $this->redirect('/#contact');
        }

        $db = $this->app->db();
        $db->prepare('INSERT INTO messages (name, email, subject, body, project, ip_hash) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$input['name'], $input['email'], $input['subject'], $input['message'], $input['project'] ?: null, Security::hash($request->ip())]);
        $id = (int) $db->lastInsertId();

        $body = "From: {$input['name']} <{$input['email']}>\n"
            . ($input['project'] !== '' ? "Project: {$input['project']}\n" : '')
            . "\n{$input['message']}\n";
        $sent = $this->app->mailer()->send('[Portfolio] ' . ($input['subject'] ?: 'New message'), $body, $input['email'], $input['name']);
        if ($sent) {
            $db->prepare('UPDATE messages SET mail_sent = 1 WHERE id = ?')->execute([$id]);
        }
        return $this->done($request, true);
    }

    private function done(Request $request, bool $ok): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => $ok]);
        }
        $this->flash('contact_sent', $ok);
        return $this->redirect('/#contact');
    }
}
