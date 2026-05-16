<?php

namespace Maxiviper117\Access\Contracts;

use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Data\AccessContext;

interface AccessActor
{
    public function in(Model $scope): AccessContext;
}
