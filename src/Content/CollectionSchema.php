<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Field definitions for every content file in /data. The admin editor, the
 * validator and the JSON writer all read from here, so adding a field to a
 * collection only means adding one line below.
 *
 * Field types: text, textarea, url, date, bool, select, list (one item per line),
 * links (one "Label | https://url" per line).
 */
final class CollectionSchema
{
    /** @return array<string, array{label: string, singleton?: bool, key: string, title: string, fields: array<string, array<string, mixed>>}> */
    public static function all(): array
    {
        return [
            'site' => [
                'label' => 'Site settings',
                'singleton' => true,
                'key' => 'name',
                'title' => 'name',
                'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Name', 'required' => true],
                    'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                    'tagline' => ['type' => 'textarea', 'label' => 'One-line value statement'],
                    'location' => ['type' => 'text', 'label' => 'Location'],
                    'availability' => ['type' => 'text', 'label' => 'Availability (e.g. Open to backend roles)'],
                    'version' => ['type' => 'text', 'label' => 'Site version (status badge)'],
                    'status' => ['type' => 'text', 'label' => 'System status word (e.g. Online)'],
                    'philosophy' => ['type' => 'list', 'label' => 'Engineering philosophy (one per line)'],
                    'bio' => ['type' => 'textarea', 'label' => 'Short bio'],
                    'years_coding' => ['type' => 'text', 'label' => 'Years coding'],
                    'email' => ['type' => 'text', 'label' => 'Email'],
                    'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number (2547…)'],
                    'github' => ['type' => 'url', 'label' => 'GitHub URL'],
                    'linkedin' => ['type' => 'url', 'label' => 'LinkedIn URL'],
                    'meta_description' => ['type' => 'textarea', 'label' => 'SEO description'],
                ],
            ],
            'projects' => [
                'label' => 'Projects',
                'key' => 'slug',
                'title' => 'title',
                'fields' => [
                    'slug' => ['type' => 'text', 'label' => 'Slug (URL part)', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                    'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                    'version' => ['type' => 'text', 'label' => 'Release version (e.g. v1.0.0)'],
                    'released' => ['type' => 'date', 'label' => 'Release date'],
                    'type' => ['type' => 'text', 'label' => 'Type (SaaS, Android app, …)'],
                    'summary' => ['type' => 'textarea', 'label' => 'Summary (changelog line)', 'required' => true],
                    'stack' => ['type' => 'list', 'label' => 'Stack'],
                    'tags' => ['type' => 'list', 'label' => 'Filter tags'],
                    'role' => ['type' => 'text', 'label' => 'My role'],
                    'problem' => ['type' => 'textarea', 'label' => 'Problem'],
                    'solution' => ['type' => 'textarea', 'label' => 'Solution / approach'],
                    'highlights' => ['type' => 'list', 'label' => 'Highlights'],
                    'challenges' => ['type' => 'textarea', 'label' => 'Challenges'],
                    'results' => ['type' => 'textarea', 'label' => 'Results'],
                    'repos' => ['type' => 'links', 'label' => 'Repositories'],
                    'private_repo' => ['type' => 'bool', 'label' => 'Private repo (show "walkthrough on request")'],
                    'live_url' => ['type' => 'url', 'label' => 'Live demo URL'],
                    'docs_url' => ['type' => 'url', 'label' => 'Docs URL'],
                    'featured' => ['type' => 'bool', 'label' => 'Featured on home page'],
                    'work_project' => ['type' => 'bool', 'label' => 'Employer project (brief mention, no detail page)'],
                    'hidden' => ['type' => 'bool', 'label' => 'Hidden'],
                ],
            ],
            'achievements' => [
                'label' => 'Achievements',
                'key' => 'id',
                'title' => 'title',
                'fields' => [
                    'id' => ['type' => 'text', 'label' => 'ID', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                    'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                    'issuer' => ['type' => 'text', 'label' => 'Issuer'],
                    'date' => ['type' => 'date', 'label' => 'Date'],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => ['certification', 'award', 'hackathon', 'milestone']],
                    'description' => ['type' => 'textarea', 'label' => 'Description'],
                    'proof_url' => ['type' => 'url', 'label' => 'Proof link'],
                    'hidden' => ['type' => 'bool', 'label' => 'Hidden'],
                ],
            ],
            'skills' => [
                'label' => 'Skills',
                'key' => 'id',
                'title' => 'label',
                'fields' => [
                    'id' => ['type' => 'text', 'label' => 'ID (terminal flag, e.g. backend)', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                    'label' => ['type' => 'text', 'label' => 'Group label', 'required' => true],
                    'items' => ['type' => 'list', 'label' => 'Skills'],
                    'primary' => ['type' => 'bool', 'label' => 'Show as stack badges in NODE 01'],
                    'hidden' => ['type' => 'bool', 'label' => 'Hidden'],
                ],
            ],
            'experience' => [
                'label' => 'Experience',
                'key' => 'id',
                'title' => 'role',
                'fields' => [
                    'id' => ['type' => 'text', 'label' => 'ID', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                    'kind' => ['type' => 'select', 'label' => 'Kind', 'options' => ['work', 'education']],
                    'role' => ['type' => 'text', 'label' => 'Role / qualification', 'required' => true],
                    'org' => ['type' => 'text', 'label' => 'Organisation'],
                    'start' => ['type' => 'date', 'label' => 'Start'],
                    'end' => ['type' => 'date', 'label' => 'End (empty = present)'],
                    'summary' => ['type' => 'textarea', 'label' => 'Summary'],
                    'hidden' => ['type' => 'bool', 'label' => 'Hidden'],
                ],
            ],
            'cv' => [
                'label' => 'CV versions',
                'key' => 'key',
                'title' => 'label',
                'fields' => [
                    'key' => ['type' => 'text', 'label' => 'Key (URL: /cv/key)', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'],
                    'label' => ['type' => 'text', 'label' => 'Label', 'required' => true],
                    'file' => ['type' => 'text', 'label' => 'File name in storage/cv (upload on Uploads page)'],
                    'updated' => ['type' => 'date', 'label' => 'Last updated'],
                    'primary' => ['type' => 'bool', 'label' => 'Master CV (header button)'],
                    'hidden' => ['type' => 'bool', 'label' => 'Hidden'],
                ],
            ],
        ];
    }

    /** @return array{label: string, singleton?: bool, key: string, title: string, fields: array<string, array<string, mixed>>} */
    public static function get(string $collection): array
    {
        $all = self::all();
        if (!isset($all[$collection])) {
            throw new \InvalidArgumentException("Unknown collection: {$collection}");
        }
        return $all[$collection];
    }

    /**
     * Converts raw form input into a typed record and collects validation errors.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function fromInput(string $collection, array $input): array
    {
        $record = [];
        $errors = [];
        foreach (self::get($collection)['fields'] as $name => $field) {
            $raw = $input[$name] ?? null;
            $value = match ($field['type']) {
                'bool' => !empty($raw),
                'list' => self::lines($raw),
                'links' => array_values(array_filter(array_map(static function (string $line): ?array {
                    $parts = array_map('trim', explode('|', $line, 2));
                    if (count($parts) === 1) {
                        $parts = [parse_url($parts[0], PHP_URL_PATH) ? basename((string) parse_url($parts[0], PHP_URL_PATH)) : 'Link', $parts[0]];
                    }
                    return $parts[1] === '' ? null : ['label' => $parts[0], 'url' => $parts[1]];
                }, self::lines($raw)))),
                default => is_scalar($raw) ? trim((string) $raw) : '',
            };

            if (!empty($field['required']) && ($value === '' || $value === [])) {
                $errors[$name] = 'Required.';
            } elseif (is_string($value) && $value !== '') {
                if (isset($field['pattern']) && !preg_match($field['pattern'], $value)) {
                    $errors[$name] = 'Use lowercase letters, numbers and dashes only.';
                } elseif ($field['type'] === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[$name] = 'Must be a full URL (https://…).';
                } elseif ($field['type'] === 'date' && !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $value)) {
                    $errors[$name] = 'Use YYYY-MM or YYYY-MM-DD.';
                } elseif ($field['type'] === 'select' && !in_array($value, $field['options'], true)) {
                    $errors[$name] = 'Pick one of the options.';
                }
            }
            if ($field['type'] === 'links') {
                foreach ($value as $link) {
                    if (!filter_var($link['url'], FILTER_VALIDATE_URL)) {
                        $errors[$name] = 'Each line must be "Label | https://url".';
                    }
                }
            }
            $record[$name] = $value;
        }
        return [$record, $errors];
    }

    /** @return list<string> */
    private static function lines(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $raw)), 'strlen'));
        }
        $lines = preg_split('/\R/', is_scalar($raw) ? (string) $raw : '') ?: [];
        return array_values(array_filter(array_map('trim', $lines), 'strlen'));
    }
}
