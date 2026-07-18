<?php

declare(strict_types=1);

namespace BizHub\Integrations\Forminator;

use BizHub\Framework\Logging\Logger;
use BizHub\Framework\Validation\ValidationException;
use Forminator_Form_Entry_Model;

/**
 * Listens for Forminator form submissions and generates BizHub
 * applications for tracked forms.
 *
 * Field extraction assumes each form's fields have been named/slugged to
 * match BizHub's expected keys ("client_id", "email", etc.) via
 * Forminator's field admin labels. Adjust extractFields() if a site's
 * forms use different field slugs.
 *
 * @package BizHub\Integrations\Forminator
 */
final class FormSubmissionListener
{
    public function __construct(
        private readonly FormMapper $formMapper,
        private readonly Validation $validation,
        private readonly ApplicationGenerator $applicationGenerator,
        private readonly Logger $logger
    ) {
    }

    /**
     * Register Forminator hooks.
     */
    public function register(): void
    {
        add_action('forminator_form_after_save_entry', [$this, 'handleSubmission'], 10, 2);
    }

    /**
     * Handle the 'forminator_form_after_save_entry' hook.
     */
    public function handleSubmission(int $formId, Forminator_Form_Entry_Model $entry): void
    {
        $formIdString = (string) $formId;

        $applicationType = $this->formMapper->applicationTypeForForm($formIdString);

        if ($applicationType === null) {
            return;
        }

        $fields = $this->extractFields($entry);

        try {
            $validated = $this->validation->validate($fields);
        } catch (ValidationException $e) {
            $this->logger->warning(
                sprintf('Forminator submission for form #%d failed BizHub validation.', $formId),
                ['errors' => $e->errors()]
            );

            return;
        }

        $application = $this->applicationGenerator->generate($applicationType, $validated);

        $this->logger->info(
            sprintf('Created BizHub application "%s" from Forminator form #%d.', $application->getUuid(), $formId)
        );
    }

    /**
     * Extract a flat field-value array from a Forminator entry.
     *
     * @return array<string,mixed>
     */
    private function extractFields(Forminator_Form_Entry_Model $entry): array
    {
        $fields = [];

        foreach ($entry->meta_data as $key => $meta) {
            $fields[$key] = $meta['value'] ?? null;
        }

        return $fields;
    }
}
