<?php

declare(strict_types=1);

use App\Controllers\AboutController;
use App\Controllers\MembershipsController;
use App\Controllers\Api\CourtController;
use App\Controllers\Api\HourController;
use App\Controllers\Api\LocalController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\VideoController;
use App\Core\Database;
use App\Repositories\AuthRepository;
use App\Repositories\CourtRepository;
use App\Repositories\LocalRepository;
use App\Repositories\VideoRepository;

require_once __DIR__ . '/autoload.php';

$pdo = Database::connection();

$videoRepository  = new VideoRepository($pdo);
$courtRepository  = new CourtRepository($pdo);
$localRepository  = new LocalRepository($pdo);
$authRepository   = new AuthRepository($pdo);

return [
    'home'     => new HomeController($videoRepository, $localRepository, $courtRepository),
    'about'    => new AboutController(),
    'memberships' => new MembershipsController(),
    'auth'     => new AuthController($authRepository),
    'video'    => new VideoController($videoRepository),
    'apiCourt' => new CourtController($courtRepository),
    'apiLocal' => new LocalController($localRepository),
    'apiHour'  => new HourController($videoRepository),
];
