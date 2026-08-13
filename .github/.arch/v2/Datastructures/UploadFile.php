<?php

namespace Eorbahapi\Datastructures;

// Représente un fichier uploadé (filename, content_type, file, write, read, seek, close)
class UploadFile {
    public $filename;
    public $content_type;
    public $file; // Resource or stream

    public function __construct($filename, $content_type, $file) {
        $this->filename = $filename;
        $this->content_type = $content_type;
        $this->file = $file;
    }

    public function write($data) {
        return fwrite($this->file, $data);
    }

    public function read($length = null) {
        return $length ? fread($this->file, $length) : fread($this->file, 8192);
    }

    public function seek($offset, $whence = SEEK_SET) {
        return fseek($this->file, $offset, $whence);
    }

    public function close() {
        return fclose($this->file);
    }
}