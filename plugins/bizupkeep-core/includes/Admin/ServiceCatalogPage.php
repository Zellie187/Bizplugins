<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Admin;

use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use BizUpKeep\Core\Policies\Capabilities;
use BizUpKeep\Core\Services\ServiceSyncService;
use DateTimeImmutable;

/**
 * Staff-facing view/edit of the Service catalog. The row set itself is
 * fixed by ServiceCatalogSeeder at activation - this page edits the
 * staff-editable metadata (VAT treatment, recurring flag, notes,
 * active) that BizUpKeep Bookkeeping's revenue automation will later
 * read via ServiceRepositoryInterface::findByKey(), and for
 * Fixed-pricing rows, the linked WooCommerce product's price (read
 * live via ServiceSyncService, never cached here). No create/delete
 * UI, deliberately: adding a new service is a code-level change
 * (a new seed row + real WooCommerce product), not a staff task.
 *
 * @package BizUpKeep\Core\Admin
 */
final class ServiceCatalogPage
{
    public const SLUG = 'bizupkeep-core-services';

    private const SAVE_NONCE_ACTION = 'bizupkeep_core_service_catalog_save';

    private const SAVE_NONCE_FIELD = 'bizupkeep_core_service_catalog_save_nonce';

    public function __construct(
        private readonly ServiceRepositoryInterface $services,
        private readonly ServiceSyncService $sync,
        private readonly AuthorizationServiceInterface $authorization
    ) {
    }

    /**
     * Render the page. Registered as the admin_menu callback for
     * self::SLUG.
     */
    public function render(): void
    {
        if (! $this->authorization->can(get_current_user_id(), Capabilities::MANAGE_SERVICES)) {
            wp_die(esc_html__('You are not permitted to access this page.', 'bizupkeep-core'));
        }

        $notice = $this->handleSave();

        echo '<div class="wrap"><h1>' . esc_html__('Service Catalog', 'bizupkeep-core') . '</h1>';
        echo '<p>' . esc_html__(
            'VAT treatment, recurring flag, and notes per service. Pricing is managed on the WooCommerce product.',
            'bizupkeep-core'
        ) . '</p>';

        $this->renderNotice($notice);
        $this->renderTable();

        echo '</div>';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function handleSave(): ?array
    {
        if ($this->requestMethod() !== 'POST' || $this->postParam('bizupkeep_core_service_catalog_action') !== 'save') {
            return null;
        }

        check_admin_referer(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);

        $uuid = $this->postParam('service_uuid');
        $service = $this->findServiceByUuid($uuid);

        if ($service === null) {
            return ['error', __('That service could not be found.', 'bizupkeep-core')];
        }

        $vatTreatment = ServiceVatTreatment::tryFrom($this->postParam('vat_treatment')) ?? $service->vatTreatment;

        $this->services->save(new Service(
            uuid: $service->uuid,
            serviceKey: $service->serviceKey,
            name: $service->name,
            pricingMode: $service->pricingMode,
            productSku: $service->productSku,
            productSlug: $service->productSlug,
            vatTreatment: $vatTreatment,
            isRecurring: $this->postParam('is_recurring') === '1',
            notes: $this->postTextareaParam('notes'),
            isActive: $this->postParam('is_active') === '1',
            createdAt: $service->createdAt,
            updatedAt: new DateTimeImmutable(),
        ));

        return $this->applySubmittedPrice($service)
            ?? ['success', __('Service updated.', 'bizupkeep-core')];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function applySubmittedPrice(Service $service): ?array
    {
        if ($service->pricingMode !== ServicePricingMode::Fixed) {
            return null;
        }

        $priceParam = $this->postParam('price');

        if ($priceParam === '' || ! is_numeric($priceParam) || (float) $priceParam < 0) {
            return null;
        }

        $priceMinor = (int) round(round((float) $priceParam, 2) * 100);

        if ($this->sync->updatePrice($service, $priceMinor)) {
            return null;
        }

        return ['error', __(
            'Other fields saved, but the price could not be updated - no matching WooCommerce product was found.',
            'bizupkeep-core'
        )];
    }

    private function findServiceByUuid(string $uuid): ?Service
    {
        foreach ($this->services->findAll() as $service) {
            if ($service->uuid === $uuid) {
                return $service;
            }
        }

        return null;
    }

    private function renderTable(): void
    {
        $services = $this->services->findAll();

        echo '<table class="widefat"><thead><tr>'
            . '<th>' . esc_html__('Key', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Name', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Product', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Price (R)', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('VAT Treatment', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Recurring', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Notes', 'bizupkeep-core') . '</th>'
            . '<th>' . esc_html__('Active', 'bizupkeep-core') . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';

        foreach ($services as $service) {
            $this->renderRow($service);
        }

        echo '</tbody></table>';
    }

    private function renderRow(Service $service): void
    {
        $productId = $this->sync->resolveProductId($service);

        echo '<tr><form method="post">';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_core_service_catalog_action" value="save" />';
        echo '<input type="hidden" name="service_uuid" value="' . esc_attr($service->uuid) . '" />';

        echo '<td><code>' . esc_html($service->serviceKey) . '</code></td>';
        echo '<td>' . esc_html($service->name) . '</td>';
        echo '<td>' . $this->productLink($service, $productId) . '</td>';
        echo '<td>' . $this->renderPriceCell($service, $productId) . '</td>';

        echo '<td><select name="vat_treatment">';
        foreach (ServiceVatTreatment::cases() as $treatment) {
            echo '<option value="' . esc_attr($treatment->value) . '"'
                . selected($service->vatTreatment->value, $treatment->value, false) . '>'
                . esc_html($treatment->value) . '</option>';
        }
        echo '</select></td>';

        echo '<td><input type="checkbox" name="is_recurring" value="1"'
            . checked($service->isRecurring, true, false) . ' /></td>';

        echo '<td><textarea name="notes" rows="2" cols="30">' . esc_textarea($service->notes) . '</textarea></td>';

        echo '<td><input type="checkbox" name="is_active" value="1"'
            . checked($service->isActive, true, false) . ' /></td>';

        echo '<td><button type="submit" class="button">' . esc_html__('Save', 'bizupkeep-core') . '</button></td>';

        echo '</form></tr>';
    }

    private function productLink(Service $service, ?int $productId): string
    {
        $label = $service->productSku ?? $service->productSlug;

        if ($productId === null) {
            return esc_html((string) $label);
        }

        $editUrl = get_edit_post_link($productId, 'raw');

        if (! is_string($editUrl)) {
            return esc_html((string) $label);
        }

        return '<a href="' . esc_url($editUrl) . '">' . esc_html((string) $label) . '</a>';
    }

    private function renderPriceCell(Service $service, ?int $productId): string
    {
        if ($service->pricingMode === ServicePricingMode::Quoted) {
            return esc_html__('Per-client quote (set on the workflow)', 'bizupkeep-core');
        }

        if ($productId === null && ! $this->canAutoCreate($service)) {
            return esc_html__('Not found in WooCommerce', 'bizupkeep-core');
        }

        $priceMinor = $productId !== null ? ($this->sync->currentPriceMinor($service) ?? 0) : 0;
        $rands = number_format($priceMinor / 100, 2, '.', '');

        $field = '<input type="number" step="0.01" min="0" name="price" value="' . esc_attr($rands)
            . '" style="width:6em;" />';

        if ($productId === null) {
            $field .= '<br /><small>' . esc_html__('Will be created on save.', 'bizupkeep-core') . '</small>';
        }

        return $field;
    }

    /**
     * Only Bookkeeping Monthly's placeholder product may be lazily
     * created from a price save here, mirroring
     * ServiceSyncService::updatePrice()'s own rule exactly -
     * Registration/Amendment are update-only.
     */
    private function canAutoCreate(Service $service): bool
    {
        return $service->serviceKey === 'bookkeeping_monthly';
    }

    /**
     * @param array{0:string,1:string}|null $notice
     */
    private function renderNotice(?array $notice): void
    {
        if ($notice === null) {
            return;
        }

        [$type, $message] = $notice;
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
            . esc_html($message) . '</p></div>';
    }

    private function postParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this is used.
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function postTextareaParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this is used.
        return isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
    }

    private function requestMethod(): string
    {
        return isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    }
}
