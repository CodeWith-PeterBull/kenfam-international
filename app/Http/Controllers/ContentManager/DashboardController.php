<?php

namespace App\Http\Controllers\ContentManager;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboards.content-manager.index', [
            'eyebrow' => 'Content management',
            'title' => 'Content workspace',
            'summary' => 'Coordinate publishing priorities, review readiness, scheduled releases, and media requests.',
            'primaryAction' => ['label' => 'Open content queue', 'route' => 'content-manager.queue', 'icon' => 'ti-list-check'],
            'stats' => [
                ['label' => 'Draft entries', 'value' => 14, 'icon' => 'ti-file-pencil', 'color' => 'var(--aureon-primary)'],
                ['label' => 'Awaiting review', 'value' => 6, 'icon' => 'ti-eye-check', 'color' => 'var(--aureon-secondary)'],
                ['label' => 'Scheduled releases', 'value' => 8, 'icon' => 'ti-calendar-time', 'color' => '#8a6427'],
                ['label' => 'Media requests', 'value' => 3, 'icon' => 'ti-photo-edit', 'color' => '#4f5d75'],
            ],
            'focusItems' => [
                ['title' => 'Annual report landing page', 'meta' => 'Copy and media review', 'status' => 'Review today'],
                ['title' => 'Leadership insight series', 'meta' => 'Three entries scheduled', 'status' => 'This week'],
                ['title' => 'Corporate capabilities update', 'meta' => 'Metadata requires approval', 'status' => 'Pending'],
            ],
            'quickLinks' => [
                ['label' => 'Content queue', 'route' => 'content-manager.queue', 'icon' => 'ti-list-check'],
                ['label' => 'Editorial calendar', 'route' => 'content-manager.calendar', 'icon' => 'ti-calendar-event'],
                ['label' => 'Account profile', 'route' => 'profile.edit', 'icon' => 'ti-user-circle'],
            ],
        ]);
    }

    public function queue(): View
    {
        return $this->workspace(
            title: 'Content queue',
            subtitle: 'Current editorial workload and publishing readiness.',
            items: [
                ['label' => 'Ready for review', 'value' => '6 entries', 'detail' => 'Priority copy and page updates'],
                ['label' => 'Changes requested', 'value' => '4 entries', 'detail' => 'Returned to assigned editors'],
                ['label' => 'Ready to schedule', 'value' => '8 entries', 'detail' => 'Approved release candidates'],
            ],
        );
    }

    public function calendar(): View
    {
        return $this->workspace(
            title: 'Editorial calendar',
            subtitle: 'A compact release outlook for the content team.',
            items: [
                ['label' => '18 Jul', 'value' => 'Annual outlook', 'detail' => 'Corporate insight'],
                ['label' => '24 Jul', 'value' => 'Digital operations brief', 'detail' => 'News and updates'],
                ['label' => '02 Aug', 'value' => 'Leadership profile', 'detail' => 'People and culture'],
            ],
        );
    }

    /** @param list<array{label: string, value: string, detail: string}> $items */
    private function workspace(string $title, string $subtitle, array $items): View
    {
        return view('dashboards.workspace', [
            'title' => $title,
            'subtitle' => $subtitle,
            'homeRoute' => 'content-manager.dashboard',
            'items' => $items,
        ]);
    }
}
