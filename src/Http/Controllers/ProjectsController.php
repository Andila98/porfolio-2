<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;

final class ProjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = $this->app->content()->all('projects');
        $tags = [];
        foreach ($projects as $project) {
            foreach ($project['tags'] ?? [] as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
        arsort($tags);

        $active = $request->query('tag');
        if ($active !== '') {
            $projects = array_values(array_filter(
                $projects,
                static fn (array $p): bool => in_array($active, $p['tags'] ?? [], true),
            ));
        }
        return $this->page('pages/projects', [
            'projects' => $projects,
            'tags' => array_keys($tags),
            'activeTag' => $active,
            'pageTitle' => 'Production Deployments',
        ]);
    }

    /** @param array{slug: string} $params */
    public function show(Request $request, array $params): Response
    {
        $project = $this->app->content()->find('projects', $params['slug']);
        if ($project === null || !empty($project['work_project'])) {
            return $this->notFound($request);
        }
        return $this->page('pages/project', [
            'project' => $project,
            'pageTitle' => $project['title'],
            'metaDescription' => $project['summary'],
        ]);
    }
}
