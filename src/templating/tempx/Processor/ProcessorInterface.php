<?php

namespace EorBah545\Eorbahapi\templating\tempx\Processor;

interface ProcessorInterface
{
    public function process(string $content, array &$data): string;
}