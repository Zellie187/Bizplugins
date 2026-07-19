<?php

declare(strict_types=1);

namespace BizHub\Dashboard;

use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\Dashboard\Widgets\ApplicationWidget;
use BizHub\Dashboard\Widgets\CompanyWidget;
use BizHub\Dashboard\Widgets\NotificationWidget;
use BizHub\Dashboard\Widgets\RecentDocumentsWidget;
use BizHub\Dashboard\Widgets\TaskWidget;

/**
 * Assembles the complete client portal dashboard payload.
 *
 * @package BizHub\Dashboard
 */
final class DashboardBuilder
{
    private readonly WidgetManager $widgetManager;

    public function __construct(
        private readonly ClientServiceInterface $clients,
        CompanyWidget $companyWidget,
        ApplicationWidget $applicationWidget,
        TaskWidget $taskWidget,
        NotificationWidget $notificationWidget,
        RecentDocumentsWidget $recentDocumentsWidget
    ) {
        $this->widgetManager = new WidgetManager();
        $this->widgetManager->register($companyWidget);
        $this->widgetManager->register($applicationWidget);
        $this->widgetManager->register($taskWidget);
        $this->widgetManager->register($notificationWidget);
        $this->widgetManager->register($recentDocumentsWidget);
    }

    /**
     * Build the dashboard payload for a WordPress user.
     *
     * @return array<string,mixed>
     */
    public function buildForWpUser(int $wpUserId): array
    {
        $client = $this->clients->getClientByWpUserId($wpUserId);

        return [
            'client' => $client->toArray(),
            'widgets' => $this->widgetManager->buildAll($wpUserId, $client->getUuid()),
        ];
    }
}
