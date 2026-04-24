{{--
    Partial: events._partials.title_desc

    Блок "Название и описание" для occurrence_edit: текстовое поле title
    и Trix-редактор под description_html.

    Expects in scope:
      - $titleVal  (string) — значение title (с override-логикой)
      - $descVal   (string) — HTML описания (с override-логикой)

    Особенности:
      - label у title: "Название (для этой даты)" — явный хинт что правим
        override, а не серию.
      - Trix input id = "occ_desc_input" — уникален для occurrence_edit
        (в /events/create используется id="description_html"; на одной
        странице два редактора одновременно не отрисовываются).
      - TODO: подключить trix-paste cleanup (есть в create, нет здесь) —
        вынесется вместе при рефакторинге create.
--}}
<div class="ramka">
    <h2 class="-mt-05">Название и описание</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <label>Название (для этой даты)</label>
                <input type="text" name="title" maxlength="255" value="{{ old('title', $titleVal) }}">
                @error('title') <div class="f-13" style="margin-top:4px">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <label>Описание</label>
                <input id="occ_desc_input" type="hidden" name="description_html" value="{{ old('description_html', $descVal) }}">
                <trix-editor input="occ_desc_input"></trix-editor>
            </div>
        </div>
    </div>
</div>
