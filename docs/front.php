<?php
declare(strict_types=1);

$file = __DIR__ . '/html/index.html';
$html = file_get_contents($file);
$lead = substr($html, 0, strpos($html, '<div class="container">'));
$tail = substr($html, strpos($html, '</footer>') + 9);
$html = $lead . file_get_contents(__DIR__ . '/front.html') . $tail;
file_put_contents($file, $html);

(function (string $sourceDir, string $destDir) {
    function copy_dir(string $sourceDir, string $destDir) {
        if(!is_dir($destDir)) {
            mkdir($destDir);
        }

        $source = new DirectoryIterator($sourceDir);

        foreach ($source as $file) {
            if($file->isDot()) {
                continue;
            }

            if($file->isDir()) {
                copy_dir($sourceDir . '/' . $file->getFilename(), $destDir . '/' . $file->getFilename());
                continue;
            }

            $destFilename = $destDir . '/' . $file->getFilename();

            if(file_exists($destFilename)) {
                unlink($destFilename);
            }

            copy($sourceDir . '/' . $file->getFilename(), $destFilename);
        }
    };

    copy_dir($sourceDir, $destDir);
})(__DIR__ . '/img', __DIR__ . '/html/img');