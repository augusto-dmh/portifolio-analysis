<?php

use Illuminate\Database\Eloquent\Model;

arch('portfolio models')
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend(Model::class);

arch('portfolio enums')
    ->expect('App\Enums')
    ->toBeEnums();
