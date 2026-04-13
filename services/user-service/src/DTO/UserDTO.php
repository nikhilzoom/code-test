<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for user creation requests.
 *
 * Carries validated input from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class UserDTO implements DtoInterface
{
    /**
     * The full name of the user.
     *
     * @var string
     */
    public string $name;

    /**
     * The email address of the user.
     *
     * @var string
     */
    public string $email;

    /**
     * Construct a UserDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `name` and `email`.
     */
    public function __construct(array $data)
    {
        $this->name  = isset($data['name'])  ? (string) $data['name']  : '';
        $this->email = isset($data['email']) ? (string) $data['email'] : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `name`  must be a non-empty string.
     * - `email` must be a valid email address format.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if (trim($this->name) === '') {
            $errors[] = 'name must not be empty.';
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'email must be a valid email address.';
        }

        return $errors;
    }
}
