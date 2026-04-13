<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The application kernel for the Ledger Service.
 *
 * Bootstraps the Symfony application, registers bundles, and configures
 * the container and routing for the Ledger microservice.

 * @package App
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
