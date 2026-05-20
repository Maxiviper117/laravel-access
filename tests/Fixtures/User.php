<?php

namespace Maxiviper117\Access\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;

    protected $guarded = [];
}
