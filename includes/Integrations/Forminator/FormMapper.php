<?php

declare(strict_types=1);

namespace BizHub\Integrations\Forminator;

/**
 * Maps Forminator forms to BizHub application types.
 *
 * The mapping is stored as a WordPress option ("bizhub_forminator_form_map"),
 * keyed by form ID, so it can be configured from the admin without code
 * changes.
 *
 * @package BizHub\Integrations\Forminator
 */
final class FormMapper
{
    private const OPTION_KEY = 'bizhub_forminator_form_map';

    /**
     * Determine whether a form is tracked by BizHub.
     */
    public function isTracked(string $formId): bool
    {
        return $this->applicationTypeForForm($formId) !== null;
    }

    /**
     * Return the BizHub application type a form maps to, if any.
     */
    public function applicationTypeForForm(string $formId): ?string
    {
        $map = $this->map();

        return $map[$formId] ?? null;
    }

    /**
     * Assign a form to a BizHub application type.
     */
    public function mapFormToApplicationType(string $formId, string $applicationType): void
    {
        $map = $this->map();
        $map[$formId] = $applicationType;

        update_option(self::OPTION_KEY, $map);
    }

    /**
     * Remove a form's BizHub application type mapping.
     */
    public function unmapForm(string $formId): void
    {
        $map = $this->map();
        unset($map[$formId]);

        update_option(self::OPTION_KEY, $map);
    }

    /**
     * Return the full form-to-application-type map.
     *
     * @return array<string,string>
     */
    private function map(): array
    {
        $map = get_option(self::OPTION_KEY, []);

        return \is_array($map) ? $map : [];
    }
}
