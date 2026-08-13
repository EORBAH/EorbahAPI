<?php

namespace PhoenixAccount\Users;

use EorBah545\Eorbahdb\Media;
use PhoenixAccount\Storage;

class Service extends Storage {
    private $media;

    public function __construct() {
        $this->media = new Media();
    }

    public function get_users_files($user_id, $file_name) {
        $userId = $this->sanitizePath($user_id);
        $fileName = $this->sanitizePath($file_name);
        $filePath = __DIR__ . "/../../data/storage/users/$userId/$fileName";
        if (is_dir($filePath)) {
            return false;
        }

        if (!file_exists($filePath) || !$this->isInDirectory($filePath)) {
            return false;
        }

        $this->sendFile($filePath);
    }
}