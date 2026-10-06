<?php

namespace App\Enums;

// Granularite du tableau de bord (cahier des charges NJ Global Trade v2, section 2.3 :
// "Vue jour / semaine / mois" pour le KPI CA et commission). Reutilisee comme parametre de
// fenetre pour l'ensemble des KPI du tableau de bord (App\Http\Controllers\Dashboard\
// DashboardController), pas seulement le CA.
enum DashboardPeriod: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
}
