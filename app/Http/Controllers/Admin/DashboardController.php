<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboards.admin.index', [
            'stats' => [
                ['label' => 'Published posts', 'value' => 48, 'icon' => 'ti-file-text', 'color' => '#70233a'],
                ['label' => 'Upcoming events', 'value' => 12, 'icon' => 'ti-calendar-event', 'color' => '#28656b'],
                ['label' => 'Team members', 'value' => 24, 'icon' => 'ti-users', 'color' => '#b28a4b'],
                ['label' => 'Expenditure items', 'value' => 31, 'icon' => 'ti-receipt', 'color' => '#4f5d75'],
                ['label' => 'Media assets', 'value' => 286, 'icon' => 'ti-photo', 'color' => '#8a3d54'],
                ['label' => 'Open inquiries', 'value' => 17, 'icon' => 'ti-message-dots', 'color' => '#397a70'],
                ['label' => 'Managed pages', 'value' => 73, 'icon' => 'ti-layout', 'color' => '#6e5a3b'],
                ['label' => 'Platform users', 'value' => 9, 'icon' => 'ti-user-shield', 'color' => '#343941'],
            ],
            'recentUpdates' => [
                ['title' => 'Annual outlook article', 'meta' => 'Post updated by Content Manager', 'time' => '18 min ago'],
                ['title' => 'Advisory services page', 'meta' => 'Page moved to review', 'time' => '1 hr ago'],
                ['title' => 'Leadership profile', 'meta' => 'Team profile published', 'time' => 'Yesterday'],
            ],
            'upcomingEvents' => [
                ['date' => '18 Jul', 'title' => 'Strategy leadership forum', 'location' => 'Nairobi'],
                ['date' => '24 Jul', 'title' => 'Digital operations briefing', 'location' => 'Online'],
                ['date' => '02 Aug', 'title' => 'Industry partners roundtable', 'location' => 'Mombasa'],
            ],
            'approvals' => [
                ['label' => 'Posts awaiting review', 'count' => 4],
                ['label' => 'Event updates', 'count' => 2],
                ['label' => 'Media replacements', 'count' => 7],
            ],
            'budget' => [
                'allocated' => 4800000,
                'spent' => 3175000,
                'percentage' => 66,
            ],
        ]);
    }

    public function blank(): View
    {
        return view('dashboards.blank');
    }
}
