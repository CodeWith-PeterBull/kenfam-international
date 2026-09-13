<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboards.editor.index', [
            'eyebrow' => 'Editorial workspace',
            'title' => 'Editor dashboard',
            'summary' => 'Keep drafts, assigned reviews, due dates, and recent publishing outcomes within reach.',
            'primaryAction' => ['label' => 'Open my drafts', 'route' => 'editor.drafts', 'icon' => 'ti-pencil'],
            'stats' => [
                ['label' => 'My drafts', 'value' => 5, 'icon' => 'ti-file-pencil', 'color' => '#70233a'],
                ['label' => 'Review notes', 'value' => 3, 'icon' => 'ti-message-check', 'color' => '#28656b'],
                ['label' => 'Due this week', 'value' => 4, 'icon' => 'ti-clock-due', 'color' => '#8a6427'],
                ['label' => 'Published this month', 'value' => 11, 'icon' => 'ti-circle-check', 'color' => '#397a70'],
            ],
            'focusItems' => [
                ['title' => 'Advisory services overview', 'meta' => 'Second revision', 'status' => 'Due today'],
                ['title' => 'Industry trends article', 'meta' => 'Editorial review assigned', 'status' => 'Due Friday'],
                ['title' => 'Team profile: Operations', 'meta' => 'Image and biography update', 'status' => 'In progress'],
            ],
            'quickLinks' => [
                ['label' => 'My drafts', 'route' => 'editor.drafts', 'icon' => 'ti-pencil'],
                ['label' => 'Review queue', 'route' => 'editor.reviews', 'icon' => 'ti-eye-check'],
                ['label' => 'Account profile', 'route' => 'profile.edit', 'icon' => 'ti-user-circle'],
            ],
        ]);
    }

    public function drafts(): View
    {
        return $this->workspace(
            title: 'My drafts',
            subtitle: 'Editorial items currently assigned to you.',
            items: [
                ['label' => 'Due today', 'value' => 'Advisory services overview', 'detail' => 'Second revision'],
                ['label' => 'Due Friday', 'value' => 'Industry trends article', 'detail' => 'Initial draft'],
                ['label' => 'Next week', 'value' => 'Operations team profile', 'detail' => 'Biography refresh'],
            ],
        );
    }

    public function reviews(): View
    {
        return $this->workspace(
            title: 'Review queue',
            subtitle: 'Feedback and approvals requiring editorial attention.',
            items: [
                ['label' => 'Copy review', 'value' => 'Annual report introduction', 'detail' => 'Two comments open'],
                ['label' => 'Metadata', 'value' => 'Capabilities page', 'detail' => 'Description update requested'],
                ['label' => 'Media check', 'value' => 'Leadership article', 'detail' => 'Hero image replacement'],
            ],
        );
    }

    /** @param list<array{label: string, value: string, detail: string}> $items */
    private function workspace(string $title, string $subtitle, array $items): View
    {
        return view('dashboards.workspace', [
            'title' => $title,
            'subtitle' => $subtitle,
            'homeRoute' => 'editor.dashboard',
            'items' => $items,
        ]);
    }
}
