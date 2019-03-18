<?php
declare(strict_types=1);

$file = __DIR__ . '/html/index.html';
$html = file_get_contents($file);
$lead = substr($html, 0, strpos($html, '<div class="container">'));
$tail = substr($html, strpos($html, '</footer>') + 9);
$html = $lead . file_get_contents(__DIR__ . '/front.html') . $tail;
file_put_contents($file, $html);
mkdir(__DIR__ . '/html/img');
$htmlMessageBox = __DIR__ . '/html/img/cqrs_messagebox_swagger.png';
$htmlLogo = __DIR__ . '/html/img/prooph_features.png';
$htmlTutorial = __DIR__ . '/html/img/tutorial_screen.png';
$htmlIntro = __DIR__ . '/html/img/Event_Engine_Intro.png';
$htmlChooseFlavour = __DIR__ . '/html/img/Choose_Flavour.png';
foreach ([$htmlMessageBox, $htmlLogo, $htmlTutorial, $htmlIntro, $htmlChooseFlavour] as $file) {
    if(file_exists($file)) unlink($file);
}
copy(__DIR__ . '/img/cqrs_messagebox_swagger.png', $htmlMessageBox);
copy(__DIR__ . '/img/prooph_features.png', $htmlLogo);
copy(__DIR__ . '/img/tutorial_screen.png', $htmlTutorial);
copy(__DIR__ . '/img/Event_Engine_Intro.png', $htmlIntro);
copy(__DIR__ . '/img/Choose_Flavour.png', $htmlChooseFlavour);