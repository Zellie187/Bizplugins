<?php

declare(strict_types=1);

namespace BizHub\Documents\Entities;

/**
 * Document category classification.
 *
 * @package BizHub\Documents\Entities
 */
enum DocumentCategory: string
{
    case ID_DOCUMENT = 'id_document';
    case PROOF_OF_ADDRESS = 'proof_of_address';
    case COMPANY_REGISTRATION = 'company_registration';
    case TAX_CERTIFICATE = 'tax_certificate';
    case BANK_STATEMENT = 'bank_statement';
    case CONTRACT = 'contract';
    case OTHER = 'other';

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ID_DOCUMENT => 'ID Document',
            self::PROOF_OF_ADDRESS => 'Proof of Address',
            self::COMPANY_REGISTRATION => 'Company Registration',
            self::TAX_CERTIFICATE => 'Tax Certificate',
            self::BANK_STATEMENT => 'Bank Statement',
            self::CONTRACT => 'Contract',
            self::OTHER => 'Other',
        };
    }
}
