<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class ProductImageUploader
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private KernelInterface $kernel)
    {
    }

    public function upload(?UploadedFile $imageFile): ?string
    {
        if (!$imageFile || !$imageFile->isValid()) {
            return null;
        }

        if (!in_array($imageFile->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        if ($imageFile->getSize() > 5 * 1024 * 1024) {
            return null;
        }

        $extension = $imageFile->guessExtension();
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadDir = $this->kernel->getProjectDir() . '/public/uploads/products';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }

        try {
            $imageFile->move($uploadDir, $newFilename);
        } catch (\Throwable) {
            return null;
        }

        return 'uploads/products/' . $newFilename;
    }
}
