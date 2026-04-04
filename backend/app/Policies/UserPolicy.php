<?php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Можно ли смотреть профиль
     */
    public function view(User $actor, User $target): bool
    {
        // админ — всегда
        if ($actor->isAdmin()) {
            return true;
        }

        // организатор — можно чужих
        if ($actor->isOrganizer()) {
            return true;
        }

        // пользователь — только себя
        return $actor->id === $target->id;
    }

    /**
     * Можно ли редактировать профиль (в принципе)
     */
    public function update(User $actor, User $target): bool
    {
        // админ — всё
        if ($actor->isAdmin()) {
            return true;
        }

        // организатор — чужих
        if ($actor->isOrganizer() && $actor->id !== $target->id) {
            return true;
        }

        // пользователь — себя
        return $actor->id === $target->id;
    }

    /**
     * Удаление — только админ
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }
}