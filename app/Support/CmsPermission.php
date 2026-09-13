<?php

namespace App\Support;

use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

final class CmsPermission
{
    public const VIEW_APPLICATION_LOGS = 'view_application_logs';

    public const MANAGE_APPLICATION_LOGS = 'manage_application_logs';

    public const VIEW_SYSTEM_ACTIVITIES = 'view_system_activities';

    public const VIEW_INSTITUTION_DETAILS = 'view_institution_details';

    public const MANAGE_INSTITUTION_DETAILS = 'manage_institution_details';

    public const PREVIEW_COMMUNICATION_TEMPLATES = 'preview_communication_templates';

    public const SEND_TEST_NOTIFICATIONS = 'send_test_notifications';

    public const VIEW_USERS = 'view_users';

    public const MANAGE_USERS = 'manage_users';

    public const VIEW_ROLES_AND_PERMISSIONS = 'view_roles_and_permissions';

    public const MANAGE_ROLES_AND_PERMISSIONS = 'manage_roles_and_permissions';

    /**
     * Developer-owned capability catalogue used by seeders and management UI.
     *
     * @return array<string, array{label: string, group: string, description: string}>
     */
    public static function catalogue(): array
    {
        return [
            self::VIEW_SYSTEM_ACTIVITIES => [
                'label' => 'View system activities',
                'group' => 'Governance',
                'description' => 'Inspect the structured cross-module audit trail.',
            ],
            self::VIEW_APPLICATION_LOGS => [
                'label' => 'View application logs',
                'group' => 'Governance',
                'description' => 'Open, search, and download application log files.',
            ],
            self::MANAGE_APPLICATION_LOGS => [
                'label' => 'Manage application logs',
                'group' => 'Governance',
                'description' => 'Delete application log files through the protected viewer.',
            ],
            self::VIEW_INSTITUTION_DETAILS => [
                'label' => 'View institution details',
                'group' => 'Configuration',
                'description' => 'View the institutional identity and communication profile.',
            ],
            self::MANAGE_INSTITUTION_DETAILS => [
                'label' => 'Manage institution details',
                'group' => 'Configuration',
                'description' => 'Edit institution identity, contacts, addresses, and brand assets.',
            ],
            self::PREVIEW_COMMUNICATION_TEMPLATES => [
                'label' => 'Preview communication templates',
                'group' => 'Configuration',
                'description' => 'Preview mail notifications and PDF report layouts.',
            ],
            self::SEND_TEST_NOTIFICATIONS => [
                'label' => 'Send test notifications',
                'group' => 'Configuration',
                'description' => 'Send throttled test messages from the template workspace.',
            ],
            self::VIEW_USERS => [
                'label' => 'View users',
                'group' => 'Users and access',
                'description' => 'Browse user accounts, profiles, statuses, and assigned roles.',
            ],
            self::MANAGE_USERS => [
                'label' => 'Manage users',
                'group' => 'Users and access',
                'description' => 'Create, update, activate, deactivate, and delete user accounts.',
            ],
            self::VIEW_ROLES_AND_PERMISSIONS => [
                'label' => 'View roles and permissions',
                'group' => 'Users and access',
                'description' => 'Inspect role membership and the application permission catalogue.',
            ],
            self::MANAGE_ROLES_AND_PERMISSIONS => [
                'label' => 'Manage roles and permissions',
                'group' => 'Users and access',
                'description' => 'Create role bundles and assign code-owned permissions to them.',
            ],
            ...CommercePermission::catalogue(),
            ...PropertyBookingPermission::catalogue(),
        ];
    }

    /**
     * Permissions required by the reusable CMS foundation.
     *
     * @return list<string>
     */
    public static function foundational(): array
    {
        return array_keys(self::catalogue());
    }

    private function __construct() {}
}
