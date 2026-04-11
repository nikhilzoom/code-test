<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for user creation and update requests.
 *
 * Carries the raw input data from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class UserDTO
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
}
