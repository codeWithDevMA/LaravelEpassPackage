<?php

namespace Ghazali\EPassPackage\Models;

use Illuminate\Database\Eloquent\Model;
use Ghazali\UniversalProbabilisticEngine\Traits\ShieldsWithUpe;

class EpassCache extends Model
{
    use ShieldsWithUpe;

    protected $table = 'epass_cache';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'expiration',
    ];

    protected array $upeShieldedColumns = [
        'key',
    ];
}
