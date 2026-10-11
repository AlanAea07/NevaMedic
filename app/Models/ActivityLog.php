<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'actor', 'before', 'after', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'actor' => 'array', 'before' => 'array', 'after' => 'array', 'metadata' => 'array'];
    }

    public static function record(?User $actor, string $action, string $type, int $id, ?array $before = null, ?array $after = null, array $metadata = []): self
    {
        $entry = static::create([
            'user_id' => $actor?->id,
            'actor' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'role' => $actor->isAdministrator() ? 'admin' : $actor->role] : ['name' => 'Sistema', 'role' => 'system'],
            'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'before' => $before, 'after' => $after,
            'metadata' => array_merge(['route' => request()->route()?->getName(), 'method' => app()->runningInConsole() ? null : request()->method()], $metadata),
            'created_at' => now(),
        ]);
        request()->attributes->set('activity_recorded', true);

        return $entry;
    }

    public function getActionLabelAttribute(): string
    {
        return ['created' => 'Creación', 'updated' => 'Edición', 'deleted' => 'Enviado a papelera', 'restored' => 'Restauración', 'purged' => 'Eliminación definitiva', 'activated' => 'Activación de cuenta', 'deactivated' => 'Desactivación de cuenta', 'visited' => 'Consulta', 'executed' => 'Acción realizada', 'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión'][$this->action] ?? $this->action;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
