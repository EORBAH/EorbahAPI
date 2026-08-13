<?php

namespace Eorbahapi\Responses;

function JSONResponse($data) {
    echo json_encode($data);
}