<?php

declare(strict_types=1);

namespace BizHub\Companies\DTO;

/**
 * Data Transfer Object representing a registered company address.
 *
 * This immutable DTO is used to transfer address information between
 * the repository, service and presentation layers.
 *
 * @package BizHub\Companies\DTO
 */
final readonly class AddressData
{
    /**
     * Create a new Address DTO.
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
        public string $addressLine1,
        public string $addressLine2,
        public string $suburb,
        public string $city,
        public string $province,
        public string $postalCode,
        public string $country = 'South Africa',
    ) {
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
     * Export the DTO as an array.
     *
     * @return array<string,string>
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
