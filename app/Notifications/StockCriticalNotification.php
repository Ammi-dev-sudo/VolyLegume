<?php

namespace App\Notifications;

use App\Models\Input;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockCriticalNotification extends Notification
{
    use Queueable;

    public function __construct(public Input $input) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $farm = $this->input->farm;

        return [
            'farm_id' => $this->input->farm_id,
            'farm_name' => $farm?->name,
            'input_id' => $this->input->id,
            'input_code' => $this->input->code,
            'input_name' => $this->input->name,
            'quantity' => (float) $this->input->quantity,
            'minimum_stock' => (float) $this->input->minimum_stock,
            'unit' => $this->input->base_unit->value,
            'message' => sprintf(
                'Stock critique : %s (%s) — %s %s disponible(s), seuil %s %s.',
                $this->input->name,
                $this->input->code,
                (float) $this->input->quantity,
                $this->input->base_unit->value,
                (float) $this->input->minimum_stock,
                $this->input->base_unit->value,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
