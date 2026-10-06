<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Module Notifications (Doc/notifications_modele_donnees.md, decision §2.8) : premiere tache
// planifiee du projet. Prerequis d'infrastructure non couvert par ce code : "php artisan
// schedule:run" doit etre ajoute au crontab du serveur de production (voir §4 du cadrage) --
// sans cette entree cron, cette planification ne se declenche jamais, meme en production.
Schedule::command('notifications:scan-alerts')->dailyAt('07:00');
