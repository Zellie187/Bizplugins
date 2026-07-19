<?php

declare(strict_types=1);

namespace BizHub\Companies\Entities;

use InvalidArgumentException;

/**
 * Represents a company's registered address.
 *
 * This entity is persistence-agnostic and contains no WordPress
 * specific functionality.
 *
 * @package BizHub\Companies\Entities
 */
final class RegisteredAddress
{
    /**
     * Create a new registered address.
     *
     * @param string $addressLine1 Primary address line.
     * @param string $addressLine2 Secondary address line.
     * @param string $suburb       Suburb or district.
     * @param string $city         City or town.
     * @param string $province     Province or state.
     * @param string $postalCode   Postal code.
     * @param string $country      Country.
     */
    public function __construct(
        private string $addressLine1,
        private string $addressLine2,
        private string $suburb,
        private string $city,
        private string $province,
        private string $postalCode,
        private string $country = 'South Africa',
    ) {
        $this->validate();
    }

    /**
     * Validate the entity.
     */
    private function validate(): void
    {
        if (trim($this->addressLine1) === '') {
            throw new InvalidArgumentException(
                'Address line 1 cannot be empty.'
            );
        }

        if (trim($this->city) === '') {
            throw new InvalidArgumentException(
                'City cannot be empty.'
            );
        }

        if (trim($this->postalCode) === '') {
            throw new InvalidArgumentException(
                'Postal code cannot be empty.'
            );
        }
    }

    /**
     * Get address line 1.
     */
    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    /**
     * Get address line 2.
     */
    public function getAddressLine2(): string
    {
        return $this->addressLine2;
    }

    /**
     * Get suburb.
     */
    public function getSuburb(): string
    {
        return $this->suburb;
    }

    /**
     * Get city.
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * Get province.
     */
    public function getProvince(): string
    {
        return $this->province;
    }

    /**
     * Get postal code.
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * Get country.
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * Return the formatted address.
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->addressLine1,
            $this->addressLine2,
            $this->suburb,
            $this->city,
            $this->province,
            $this->postalCode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Export entity as an array.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'suburb'         => $this->suburb,
            'city'           => $this->city,
            'province'       => $this->province,
            'postal_code'    => $this->postalCode,
            'country'        => $this->country,
        ];
    }
}
