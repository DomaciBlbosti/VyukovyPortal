<?php
/**
 * Vygeneruje launcher ikony pro Android z PWA ikon v kořeni projektu.
 * Výsledek se commituje, aby build v CI nepotřeboval PHP ani GD.
 *
 * Spuštění:  php android/make-icons.php
 */
$root = dirname(__DIR__);
$res  = __DIR__ . '/app/src/main/res';

// Klasická ikona (Android < 8) i adaptivní popředí (Android 8+).
// Pro popředí bereme maskable variantu — má kolem motivu rezervu, takže
// ji systém může oříznout do kolečka i do čtverce bez uříznutých okrajů.
$sources = [
    'ic_launcher'            => $root . '/icon-512.png',
    'ic_launcher_foreground' => $root . '/icon-maskable-512.png',
];

// hustota => [velikost klasické ikony (48dp), velikost popředí (108dp)]
$densities = [
    'mdpi'    => [48,  108],
    'hdpi'    => [72,  162],
    'xhdpi'   => [96,  216],
    'xxhdpi'  => [144, 324],
    'xxxhdpi' => [192, 432],
];

foreach ($densities as $density => [$legacy, $adaptive]) {
    $dir = "$res/mipmap-$density";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    foreach ($sources as $name => $file) {
        $size = $name === 'ic_launcher' ? $legacy : $adaptive;
        $src  = imagecreatefrompng($file);
        $dst  = imagecreatetruecolor($size, $size);

        // Zachovej průhlednost, jinak by se z ní stalo černé pozadí
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size,
                           imagesx($src), imagesy($src));
        imagepng($dst, "$dir/$name.png");
        imagedestroy($dst);
        imagedestroy($src);

        echo "mipmap-$density/$name.png ({$size}px)\n";
    }
}
