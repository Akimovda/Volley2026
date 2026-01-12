<div class="v-card border border-red-200">
    <div class="v-card__body">
        <div class="text-lg font-semibold text-red-700 mb-2">Опасная зона</div>
        <div class="text-sm text-gray-600 mb-3">
            Удаление безвозвратно удалит пользователя и связанные с ним записи (привязки, сессии, регистрации и т.д.).
        </div>

        @error('purge')
            <div class="v-alert v-alert--danger mb-3">
                <div class="v-alert__text">{{ $message }}</div>
            </div>
        @enderror

        <form method="POST" action="{{ route('admin.users.purge', $user) }}"
              onsubmit="return confirm('Вы точно хотите удалить все данные? Это необратимо.');">
            @csrf

            <label class="flex items-center gap-2 text-sm mb-3">
                <input type="checkbox" name="confirm" value="yes" required />
                Да, удалить все данные пользователя
            </label>

            <button class="v-btn v-btn--danger" type="submit">
                Удалить пользователя и все данные
            </button>
        </form>
    </div>
</div>
