<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The application kernel for the User Service.
 *
 * Bootstraps the Symfony application, registers bundles, and configures
 * the container and routing for the User microservice.
 *
 * @package App
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
