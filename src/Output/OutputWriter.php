<?php

declare(strict_types=1);

namespace App\Output;

use App\Model\Document;

final class OutputWriter
{
    /**
     * Write rendered documents to their output paths.
     *
     * @param Document[] $documents
     *
     * @throws \RuntimeException if duplicate output paths are detected
     */
    public function write(array $documents): int
    {
        $this->detectDuplicates($documents);

        $count = 0;

        foreach ($documents as $doc) {
            if (null === $doc->renderedContent) {
                continue;
            }

            $dir = \dirname($doc->outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            file_put_contents($doc->outputPath, $doc->renderedContent);
            ++$count;
        }

        return $count;
    }

    /**
     * @param Document[] $documents
     */
    private function detectDuplicates(array $documents): void
    {
        $seen = [];

        foreach ($documents as $doc) {
            if (null === $doc->renderedContent) {
                continue;
            }

            $path = $doc->outputPath;
            if (isset($seen[$path])) {
                throw new \RuntimeException(\sprintf(
                    'Duplicate output path "%s" from "%s" and "%s".',
                    $path,
                    $seen[$path],
                    $doc->relativePath,
                ));
            }
            $seen[$path] = $doc->relativePath;
        }
    }
}
