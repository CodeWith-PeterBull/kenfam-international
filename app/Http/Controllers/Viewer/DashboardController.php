<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboards.viewer.index', [
            'eyebrow' => 'Viewer workspace',
            'title' => 'Your corporate workspace',
            'summary' => 'Browse approved resources, upcoming events, saved references, and recent organization notices.',
            'primaryAction' => ['label' => 'Browse content', 'route' => 'viewer.library', 'icon' => 'ti-books'],
            'stats' => [
                ['label' => 'Published resources', 'value' => 86, 'icon' => 'ti-books', 'color' => '#70233a'],
                ['label' => 'Upcoming events', 'value' => 12, 'icon' => 'ti-calendar-event', 'color' => '#28656b'],
                ['label' => 'Saved items', 'value' => 7, 'icon' => 'ti-bookmark', 'color' => '#8a6427'],
                ['label' => 'New notices', 'value' => 3, 'icon' => 'ti-bell', 'color' => '#4f5d75'],
            ],
            'focusItems' => [
                ['title' => 'Annual corporate outlook', 'meta' => 'Featured insight', 'status' => 'New'],
                ['title' => 'Digital operations briefing', 'meta' => 'Online event', 'status' => '24 Jul'],
                ['title' => 'Industry partners roundtable', 'meta' => 'Mombasa', 'status' => '02 Aug'],
            ],
            'quickLinks' => [
                ['label' => 'Content library', 'route' => 'viewer.library', 'icon' => 'ti-books'],
                ['label' => 'Saved items', 'route' => 'viewer.saved', 'icon' => 'ti-bookmark'],
                ['label' => 'Account profile', 'route' => 'profile.edit', 'icon' => 'ti-user-circle'],
            ],
        ]);
    }

    public function library(): View
    {
        return $this->workspace(
            title: 'Content library',
            subtitle: 'Recently published corporate resources and updates.',
            items: [
                ['label' => 'Insight', 'value' => 'Annual corporate outlook', 'detail' => 'Published 15 July'],
                ['label' => 'Briefing', 'value' => 'Digital operations update', 'detail' => 'Published 10 July'],
                ['label' => 'Profile', 'value' => 'Leadership and governance', 'detail' => 'Updated 08 July'],
            ],
        );
    }

    public function saved(): View
    {
        return $this->workspace(
            title: 'Saved items',
            subtitle: 'A compact sample collection for future bookmark functionality.',
            items: [
                ['label' => 'Saved insight', 'value' => 'Market outlook', 'detail' => 'Added 12 July'],
                ['label' => 'Saved event', 'value' => 'Strategy leadership forum', 'detail' => 'Added 09 July'],
                ['label' => 'Saved profile', 'value' => 'Advisory leadership', 'detail' => 'Added 04 July'],
            ],
        );
    }

    /** @param list<array{label: string, value: string, detail: string}> $items */
    private function workspace(string $title, string $subtitle, array $items): View
    {
        return view('dashboards.workspace', [
            'title' => $title,
            'subtitle' => $subtitle,
            'homeRoute' => 'viewer.dashboard',
            'items' => $items,
        ]);
    }
}
