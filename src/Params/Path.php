<?php

namespace Eorbahapi\Params;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Path extends Param {
    public $in_ = ParamTypes::class;

    public function __construct(

    ) {} 
}