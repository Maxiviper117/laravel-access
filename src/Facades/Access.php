<?php

namespace Maxiviper117\Access\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Maxiviper117\Access\Access
 */
class Access extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Maxiviper117\Access\Access::class;
    }
}
