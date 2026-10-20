<?php

namespace Eorbahapi\Examples;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\File;

/**
 * Téléversement de fichiers (`multipart/form-data`).
 */
final class FilesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Le type déclaré `UploadFile` construit l'objet depuis $_FILES.
        $app->post('/upload', function (#[File] UploadFile $avatar) {
            $content = $avatar->read(64);

            return [
                'filename' => $avatar->filename,
                'content_type' => $avatar->content_type,
                'preview' => substr($content, 0, 5),
            ];
        });

        // Sans conversion : l'entrée brute de $_FILES est transmise.
        $app->post('/meta', function (#[File('document')] array $document) {
            return ['name' => $document['name'], 'size' => $document['size']];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        $tmp = __FILE__;

        return [
            self::scenario('POST', '/upload', [
                'files' => [
                    'avatar' => [
                        'name' => 'photo.png',
                        'type' => 'image/png',
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($tmp),
                    ],
                ],
            ], json: [
                'filename' => 'photo.png',
                'content_type' => 'image/png',
                'preview' => '<?php',
            ]),

            self::scenario('POST', '/meta', [
                'files' => [
                    'document' => [
                        'name' => 'rapport.pdf',
                        'type' => 'application/pdf',
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' => 1024,
                    ],
                ],
            ], json: ['name' => 'rapport.pdf', 'size' => 1024]),

            self::scenario('POST', '/upload', [], status: 422, contains: 'missing'),
        ];
    }
}
