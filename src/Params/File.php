<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Fichier téléversé (`$_FILES`).
 *
 *     #[File] UploadFile $avatar
 *     #[File('documents')] array $documents
 *
 * Lorsque le type déclaré est `Eorbahapi\Datastructures\UploadFile`, l'entrée
 * de `$_FILES` est convertie en objet (flux ouvert en lecture) ; sinon l'entrée
 * brute (`name`, `type`, `tmp_name`, `error`, `size`) est transmise.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class File extends Form
{
    public function location(): ParamTypes
    {
        return ParamTypes::FILE;
    }
}
