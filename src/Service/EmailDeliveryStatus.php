<?php

declare(strict_types=1);

namespace App\Service;

enum EmailDeliveryStatus: string
{
    // Accepté par le serveur SMTP pendant la requête (transport synchrone).
    case Sent = 'sent';
    // Confié à la file Messenger : l'envoi réel dépend du worker.
    case Queued = 'queued';
    // Ni envoyé ni mis en file.
    case Failed = 'failed';
}
