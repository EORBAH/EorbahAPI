<?php

namespace Eorbahapi\Params;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
abstract class Param {
    public function __construct() {} 
}