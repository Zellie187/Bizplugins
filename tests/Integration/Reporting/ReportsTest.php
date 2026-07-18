<?php

declare(strict_types=1);

namespace BizHub\Tests\Integration\Reporting;

use BizHub\Reporting\ApplicationReport;
use BizHub\Reporting\CompanyReport;
use BizHub\Reporting\UserActivityReport;
use BizHub\Tests\Mocks\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase
{
    private InMemoryDatabase $db;

    protected function setUp(): void
    {
        $this->db = new InMemoryDatabase();

        $this->db->seed('bizhub_companies', [
            ['status' => 'active', 'incorporation_date' => '2026-01-15'],
            ['status' => 'active', 'incorporation_date' => '2026-02-10'],
            ['status' => 'pending_documents', 'incorporation_date' => null],
        ]);

        $this->db->seed('bizhub_applications', [
            ['type' => 'company_registration', 'status' => 'approved', 'submitted_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-05 00:00:00'],
            ['type' => 'company_registration', 'status' => 'draft', 'submitted_at' => null, 'updated_at' => null],
        ]);

        $this->db->seed('bizhub_audit_log', [
            ['user_id' => 1, 'action' => 'company.created'],
            ['user_id' => 1, 'action' => 'company.updated'],
            ['user_id' => 2, 'action' => 'company.created'],
        ]);
    }

    public function test_company_report(): void
    {
        $report = new CompanyReport($this->db);

        $this->assertSame(3, $report->total());
        $this->assertSame(2, $report->countsByStatus()['active']);
        $this->assertSame(1, $report->countsByStatus()['pending_documents']);
        $this->assertCount(1, $report->registeredBetween('2026-01-01', '2026-01-31'));
    }

    public function test_application_report(): void
    {
        $report = new ApplicationReport($this->db);

        $this->assertSame(2, $report->total());
        $this->assertSame(2, $report->countsByType()['company_registration']);
        $this->assertSame(4.0, $report->averageResolutionDays());
    }

    public function test_user_activity_report(): void
    {
        $report = new UserActivityReport($this->db);

        $this->assertSame(2, $report->actionCountForUser(1));

        $mostActive = $report->mostActiveUsers();
        $this->assertSame(1, $mostActive[0]['user_id']);
        $this->assertSame(2, $mostActive[0]['action_count']);
    }
}
