<?php

namespace Maxiviper117\Access\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Model
{
    use HasAccess;

    protected $guarded = [];
}
