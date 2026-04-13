<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for user login requests.
 *
 * Carries and validates the `email` and `password` fields
 * required to authenticate a user and issue a JWT.
 *
 * @package App\DTO
 */
class LoginDTO implements DtoInterface
{
    /**
     * The email address of the user attempting to log in.
     *
     * @var string
     */
    public string $email;

    /**
     * The plain-text password provided by the user.
     *
     * @var string
     */
    public string $password;

    /**
     * Construct a LoginDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `email` and `password`.
     */
    public function __construct(array $data)
    {
        $this->email    = isset($data['email'])    ? (string) $data['email']    : '';
        $this->password = isset($data['password']) ? (string) $data['password'] : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `email`    must be a valid email address format.
     * - `password` must be a non-empty string.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'email must be a valid email address.';
        }

        if (trim($this->password) === '') {
            $errors[] = 'password must not be empty.';
        }

        return $errors;
    }
}
