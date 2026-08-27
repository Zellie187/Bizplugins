<?php

declare(strict_types=1);

namespace BizUpKeep\Core\Admin;

use BizHub\Security\Authorization\Contracts\AuthorizationServiceInterface;
use BizUpKeep\Core\Contracts\ServiceRepositoryInterface;
use BizUpKeep\Core\Entities\Service;
use BizUpKeep\Core\Enums\ServicePricingMode;
use BizUpKeep\Core\Enums\ServiceVatTreatment;
use BizUpKeep\Core\Policies\Capabilities;
use DateTimeImmutable;

/**
 * Staff-facing view/edit of the Service catalog. The row set itself is
 * fixed by ServiceCatalogSeeder at activation - this page edits the
 * staff-editable metadata (VAT treatment, recurring flag, notes,
 * active, and for Fixed-pricing rows, the catalog-owned price) that
 * BizUpKeep Payments reads via ServiceRepositoryInterface::findByKey()
 * when starting a checkout. No create/delete UI, deliberately: adding
 * a new service is a code-level change (a new seed row), not a staff
 * task.
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
            'Price, VAT treatment, recurring flag, and notes per service.',
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
        $priceMinor = $this->resolveSubmittedPrice($service);

        $this->services->save(new Service(
            uuid: $service->uuid,
            serviceKey: $service->serviceKey,
            name: $service->name,
            pricingMode: $service->pricingMode,
            priceMinor: $priceMinor,
            productSku: $service->productSku,
            productSlug: $service->productSlug,
            vatTreatment: $vatTreatment,
            isRecurring: $this->postParam('is_recurring') === '1',
            notes: $this->postTextareaParam('notes'),
            isActive: $this->postParam('is_active') === '1',
            createdAt: $service->createdAt,
            updatedAt: new DateTimeImmutable(),
        ));

        return ['success', __('Service updated.', 'bizupkeep-core')];
    }

    /**
     * A Quoted-pricing service (Annual Return) never has a catalog
     * price, regardless of what was submitted - its amount always
     * comes from the workflow's own per-client quote. For a
     * Fixed-pricing service, an empty/invalid submission leaves the
     * existing price untouched rather than clearing it.
     */
    private function resolveSubmittedPrice(Service $service): ?int
    {
        if ($service->pricingMode !== ServicePricingMode::Fixed) {
            return null;
        }

        $priceParam = $this->postParam('price');

        if ($priceParam === '' || ! is_numeric($priceParam) || (float) $priceParam < 0) {
            return $service->priceMinor;
        }

        return (int) round(round((float) $priceParam, 2) * 100);
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
        echo '<tr><form method="post">';
        wp_nonce_field(self::SAVE_NONCE_ACTION, self::SAVE_NONCE_FIELD);
        echo '<input type="hidden" name="bizupkeep_core_service_catalog_action" value="save" />';
        echo '<input type="hidden" name="service_uuid" value="' . esc_attr($service->uuid) . '" />';

        echo '<td><code>' . esc_html($service->serviceKey) . '</code></td>';
        echo '<td>' . esc_html($service->name) . '</td>';
        echo '<td>' . $this->renderPriceCell($service) . '</td>';

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

    private function renderPriceCell(Service $service): string
    {
        if ($service->pricingMode === ServicePricingMode::Quoted) {
            return esc_html__('Per-client quote (set on the workflow)', 'bizupkeep-core');
        }

        if ($service->priceMinor === null) {
            return '<input type="number" step="0.01" min="0" name="price" value="" style="width:6em;" />'
                . '<br /><small>' . esc_html__('Not yet configured.', 'bizupkeep-core') . '</small>';
        }

        $rands = number_format($service->priceMinor / 100, 2, '.', '');

        return '<input type="number" step="0.01" min="0" name="price" value="' . esc_attr($rands)
            . '" style="width:6em;" />';
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
