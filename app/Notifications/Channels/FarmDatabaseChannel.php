<?php

namespace App\Notifications\Channels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification en base qui renseigne l'exploitation dans la colonne
 * dédiée `farm_id` (indexée et clé étrangère).
 *
 * Le canal standard ne connaît que la charge utile JSON : le filtrage par
 * exploitation devrait alors passer par l'opérateur JSON, non indexé et
 * dépendant du type de la colonne. Ce canal reporte l'identifiant
 * d'exploitation dans la colonne prévue par le schéma.
 */
class FarmDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return Model
     */
    public function send($notifiable, Notification $notification)
    {
        $payload = $this->buildPayload($notifiable, $notification);
        $data = $payload['data'] ?? [];

        if (is_array($data) && isset($data['farm_id'])) {
            $payload['farm_id'] = (int) $data['farm_id'];
        }

        return $notifiable->routeNotificationFor('database', $notification)->create($payload);
    }
}
