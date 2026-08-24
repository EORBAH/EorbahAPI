<?php

namespace Eorbahapi\Openapi;

class Constants {
    public const METHODS_WITH_BODY = ["GET", "HEAD", "POST", "PUT", "DELETE", "PATCH"];
    public const REF_PREFIX = "#/components/schemas/";
    public const REF_TEMPLATE = "#/components/schemas/{model}";
}