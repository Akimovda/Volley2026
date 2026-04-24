{{--
    Partial: events._partials.trainer

    Блок "Тренер" для occurrence_edit: hidden input trainer_user_id,
    текстовый поиск, контейнер для результатов autocomplete.

    Expects in scope:
      - $trainerId    (int|null)  — ID тренера с override-логикой
      - $trainerName  (string)   — ФИО тренера (для предзаполнения поиска)

    JS autocomplete (в occurrence_edit) привязан к id:
      - occ_trainer_id      — hidden, отправляется в форме
      - occ_trainer_search  — текстовый input для поиска
      - occ_trainer_results — контейнер выпадающего списка
--}}
<div class="ramka">
    <h2 class="-mt-05">Тренер</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <label>Тренер занятия</label>
                <input type="hidden" name="trainer_user_id" id="occ_trainer_id" value="{{ old('trainer_user_id', $trainerId) }}">
                <input type="text" id="occ_trainer_search" value="{{ $trainerName }}" placeholder="Поиск по имени...">
                <div id="occ_trainer_results" style="position:relative"></div>
                <div class="f-13" style="margin-top:.5rem">Начните вводить имя — выберите из списка. Чтобы очистить, сотрите текст.</div>
            </div>
        </div>
    </div>
</div>
