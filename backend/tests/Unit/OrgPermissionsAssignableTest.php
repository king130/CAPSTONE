<?php

namespace Tests\Unit;

use App\Support\OrgPermissions;
use PHPUnit\Framework\TestCase;

class OrgPermissionsAssignableTest extends TestCase
{
    public function test_assignable_keys_do_not_list_report_view_duplicates(): void
    {
        $assignable = OrgPermissions::assignableOrganizationPermissionKeys();

        $this->assertContains('org.view_reports', $assignable);
        $this->assertNotContains('org.reports_view', $assignable);
        $this->assertNotContains('view_reports', $assignable);
    }

    public function test_view_reports_alias_group_covers_legacy_keys(): void
    {
        $group = OrgPermissions::aliasGroupFor('org.view_reports');

        $this->assertSame(
            ['org.view_reports', 'org.reports_view', 'view_reports'],
            $group
        );
        $this->assertSame($group, OrgPermissions::aliasGroupFor('view_reports'));
        $this->assertSame($group, OrgPermissions::aliasGroupFor('org.reports_view'));
    }
}
