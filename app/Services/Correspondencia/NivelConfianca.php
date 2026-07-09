<?php

namespace App\Services\Correspondencia;

enum NivelConfianca: string
{
    case Exata = 'exata';
    case Provavel = 'provavel';
    case Ambigua = 'ambigua';
    case SemCorrespondencia = 'sem_correspondencia';
}
