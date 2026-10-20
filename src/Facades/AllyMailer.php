<?php

declare(strict_types=1);

namespace AllyMailer\Wrapper\Facades;

use Illuminate\Support\Facades\Facade;
use AllyMailer\AllyMailerClient;

/**
 * AllyMailer API client of the container: AllyMailer::email()->send(...).
 *
 * @mixin AllyMailerClient
 */
final class AllyMailer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AllyMailerClient::class;
    }
}
