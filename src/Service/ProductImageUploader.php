<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductImageUploader
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private string $productImagesDirectory,
    ) {
    }

    public function upload(
        ?UploadedFile $imageFile,
        int $fournisseurId,
    ): ?string
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
        $uploadDirectory = $this->productImagesDirectory
            . DIRECTORY_SEPARATOR
            . $fournisseurId;

        if (
            !is_dir($uploadDirectory)
            && !mkdir($uploadDirectory, 0755, true)
            && !is_dir($uploadDirectory)
        ) {
            return null;
        }

        try {
            $imageFile->move($uploadDirectory, $newFilename);
        } catch (\Throwable) {
            return null;
        }

        return sprintf(
            '%d/%s',
            $fournisseurId,
            $newFilename,
        );
    }

    public function delete(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        $uploadDirectory = realpath($this->productImagesDirectory);

        if ($uploadDirectory === false) {
            return;
        }

        $file = realpath(
            $this->productImagesDirectory
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $imagePath)
        );

        if (
            $file !== false
            && str_starts_with(
                $file,
                $uploadDirectory . DIRECTORY_SEPARATOR
            )
            && is_file($file)
        ) {
            unlink($file);
        }
    }
}
