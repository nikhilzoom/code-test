<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for user registration requests.
 *
 * Carries and validates the `name`, `email`, and `password` fields
 * required to register a new user account.
 *
 * @package App\DTO
 */
class RegisterDTO implements DtoInterface
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
     * The plain-text password chosen by the user.
     *
     * @var string
     */
    public string $password;

    /**
     * Construct a RegisterDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `name`, `email`, and `password`.
     */
    public function __construct(array $data)
    {
        $this->name     = isset($data['name'])     ? (string) $data['name']     : '';
        $this->email    = isset($data['email'])    ? (string) $data['email']    : '';
        $this->password = isset($data['password']) ? (string) $data['password'] : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `name`     must be a non-empty string.
     * - `email`    must be a valid email address format.
     * - `password` must be at least 6 characters.
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

        if (strlen($this->password) < 6) {
            $errors[] = 'password must be at least 6 characters.';
        }

        return $errors;
    }
}
