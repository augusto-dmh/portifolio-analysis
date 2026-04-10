<?php

use Illuminate\Database\Eloquent\Model;

arch('portfolio models')
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend(Model::class);

arch('portfolio enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('controllers do not use the db facade directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');
