<?php

namespace Eorbahapi\Adapter\Datastructures;

use Eorbahapi\Contracts\UploadFileInterface;
use Eorbahapi\Datastructures\UploadFile;

/**
 * Adapte `UploadFile` au contrat `UploadFileInterface` (accesseurs typés).
 */
final class UploadFileAdapter implements UploadFileInterface
{
    public function __construct(private readonly UploadFile $file)
    {
    }

    public function unwrap(): UploadFile
    {
        return $this->file;
    }

    public function getFilename(): string
    {
        return (string) $this->file->filename;
    }

    public function getContentType(): string
    {
        return (string) $this->file->content_type;
    }

    public function read(?int $length = null): string|false
    {
        /** @var string|false $data */
        $data = $this->file->read($length);

        return $data;
    }

    public function write(string $data): int|false
    {
        /** @var int|false $written */
        $written = $this->file->write($data);

        return $written;
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        return (int) $this->file->seek($offset, $whence);
    }

    public function close(): bool
    {
        return (bool) $this->file->close();
    }
}
