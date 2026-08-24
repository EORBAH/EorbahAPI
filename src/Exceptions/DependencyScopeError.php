<?php

namespace Eorbahapi\Exceptions;

/*A dependency declared that it depends on another dependency with an invalid
    (narrower) scope.*/
class DependencyScopeError extends EorbahAPIError {}