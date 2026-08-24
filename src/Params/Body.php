<?php

namespace Eorbahapi\Params;

use Svinx\Validator\FieldInfo;
use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body extends FieldInfo {
    
}