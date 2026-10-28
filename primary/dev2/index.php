<?php
function isWebOSTV(string $ua): bool
{
    // Ищем webOS, NetCast, NetCast.TV, LG Browser, LGE — надёжные сигнатуры LG webOS/NetCast
    return (bool) preg_match('/web0S|NetCast|netcast|lg|NetCast\.TV|LG Browser|LGE/i', $ua);
}

$company = 'primary';

// Читаем конфигурацию
$config_data = json_decode($config_json, true);
$dirValue = $config_data['config']['dir'] ?? '';
$dirParts = explode('/', $dirValue);
$theme = $dirParts[0] ?? '';
$color = $dirParts[1] ?? '';

if (isset($build_mode) && $build_mode === 'dev') {
    if (($_GET['devvv'] ?? null) == 3) {
        $pathIndex = 'dev3/';
    } elseif (($_GET['devvv'] ?? null) == 2) {
        $pathIndex = 'dev2/';
    } elseif (($_GET['devvv'] ?? null) == 1) {
      $pathIndex = 'dev1/';
    } else {
      $pathIndex = 'dev/';
    }
    $link_dir_player = '';
} else {
    $link_dir_player = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/';
    $pathIndex = '/';
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (isWebOSTV($ua) == true) {
    $versionPath = 'modern';
} elseif ($islegacy) {
    $versionPath = 'legacy';
} else {
    $versionPath = 'modern';
}

// Проверяем и корректируем тему
$themePath = __DIR__ . "/$theme";
if (!is_dir($themePath)) {
    $themes = glob(__DIR__ . "/*", GLOB_ONLYDIR);
    if (!empty($themes)) {
        $theme = basename($themes[0]);
    } else {
        $theme = '';
    }
}

// Проверяем и корректируем цвет
$colorMatches = $color !== ''
    ? glob(__DIR__ . "/$theme/$versionPath/styles/$color.*.min.css")
    : [];
if (empty($colorMatches)) {
    $colors = glob(__DIR__ . "/$theme/$versionPath/styles/*.min.css");
    if (!empty($colors)) {
        $colorName = pathinfo($colors[0], PATHINFO_FILENAME);
        $color = strtok($colorName, '.');
    } else {
        $color = '';
    }
}

$bundle_fallback = [
    'sources' => [
        'https://legendcatalan.github.io/videojs/',
    ],
];
$bundle_fallback_json = json_encode($bundle_fallback, JSON_UNESCAPED_SLASHES);

// Формируем дополнительные стили и скрипты
$config_data['config']['dir'] = "$theme/$color";
$config_data['config']['staticPath'] = $link_dir_player;
$config_json = json_encode($config_data, JSON_NUMERIC_CHECK);
$configScript = "<script>window.playerData = $config_json;</script>";
$adsScript = "<script>window.adsConfig = $ads_json;</script>";
$fallbackScript = "<script>window.bundleFallback = $bundle_fallback_json;</script>";

// Собираем все дополнительные ресурсы
$additionalResources = implode("\n", array_filter([
    $configScript,
    $adsScript,
    $fallbackScript
]));

// Читаем нужный HTML файл
$htmlFile = "$theme/$versionPath/index.html";
$htmlContent = file_get_contents(__DIR__ . '/' . $htmlFile);

// Вставляем дополнительные ресурсы перед закрывающим тегом head
$htmlContent = preg_replace('/defer="defer" src="(.*?)"/', 'defer="defer" src="'.$link_dir_player.'$1"', $htmlContent);
$htmlContent = str_replace('<meta name="csrf-token" content="">', '<meta name="csrf-token" content="' . $token . '">', $htmlContent);
$htmlContent = str_replace('</head>', "\n$additionalResources\n</head>", $htmlContent);

// Защита от прямого доступа вне фрейма
$direct_access = !isset($_GET['vvv']) ? '<script>var isFramed=!1;try{isFramed=window!=window.top||document!=top.document||self.location!=top.location}catch(e){isFramed=!0}if(!isFramed){document.querySelectorAll(\'body\')[0].remove();document.querySelectorAll(\'html\')[0].innerHTML=\'<html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>404 Not Found</h1></center><hr><center>NGINX</center></body></html>\'}</script>' : '';
$htmlContent = str_replace('<body>', "<body>$direct_access", $htmlContent);

// Выводим результат
echo $htmlContent;
