{{-- resources/views/staff/index.blade.php --}}
<x-voll-layout body_class="staff-page">
    <x-slot name="title">Мои помощники</x-slot>
    <x-slot name="h1">🧑‍💻 Мои помощники (Staff)</x-slot>
    <x-slot name="t_description">Управление помощниками организатора</x-slot>

    <div class="container">
        <div class="row row2">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
                            'menuUser'       => auth()->user(),
                            'isEditingOther' => false,
                            'activeMenu'     => 'staff',
                        ])
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-xl-9 order-1">

                @if(session('status'))
                <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
                @endif
                @if(session('error'))
                <div class="ramka"><div class="alert alert-error">{{ session('error') }}</div></div>
                @endif

                {{-- Форма добавления --}}
                <div class="ramka">
                    <h2 class="-mt-05">Добавить помощника</h2>
                    <form method="POST" action="{{ route('staff.store') }}" id="staffAddForm" class="form">
                        @csrf
                        <input type="hidden" name="staff_user_id" id="staff_user_id_input" value="{{ old('staff_user_id') }}">
                        <div class="card" style="overflow:visible;height:auto">
                            <label for="staff_search_input">Поиск пользователя</label>
                            <div style="position:relative" id="staff_ac_wrap">
                                <input type="text" id="staff_search_input"
                                       placeholder="Введите имя или фамилию..."
                                       autocomplete="off">
                                <div id="staff_search_results" class="form-select-dropdown staff-dd"></div>
                            </div>
                            <div id="staff_selected" class="f-15 mt-1" style="display:none;"></div>
                            @error('staff_user_id')
                            <div class="f-14 red mt-05">{{ $message }}</div>
                            @enderror
                            <div class="f-13 mt-1" style="opacity:.6;">Начните вводить — минимум 2 символа. Помощник получит доступ к управлению вашими мероприятиями, действия фиксируются в логах.</div>
                            <button type="submit" class="btn mt-2 w-100" id="staff_submit_btn" disabled>Назначить помощником</button>
                        </div>
                    </form>
                </div>

                {{-- Список помощников --}}
                <div class="ramka">
                    <h2 class="-mt-05">Текущие помощники</h2>
                    @if($staffMembers->isEmpty())
                    <div class="alert alert-info">У вас пока нет помощников.</div>
                    @else
                    <div class="row row2">
                        @foreach($staffMembers as $assignment)
                        <div class="col-md-6">
                            <div class="card mb-2" style="height:auto">
                                <div class="d-flex fvc gap-2">
                                    <img src="{{ $assignment->staff->profile_photo_url }}"
                                         alt="" style="width:5rem;height:5rem;border-radius:50%;object-fit:cover;">
                                    <div style="flex:1;min-width:0">
                                        <div class="b-600">{{ trim($assignment->staff->first_name . ' ' . $assignment->staff->last_name) }}</div>
                                        <div class="f-13" style="opacity:.6;word-break:break-all">{{ $assignment->staff->email }}</div>
                                        <div class="f-13 mt-05" style="opacity:.6;">
                                            С {{ $assignment->created_at->format('d.m.Y') }}
                                        </div>
                                        @if($assignment->can_manage_subs)
                                        <div class="f-13 mt-05 b-600">⭐ Мастер: абонементы и купоны</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <a href="{{ route('users.show', $assignment->staff->id) }}"
                                       class="btn btn-secondary btn-small">👤 Профиль</a>
                                    <form method="POST" action="{{ route('staff.master', $assignment->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-small"
                                                title="Мастер может создавать шаблоны и выдавать абонементы и купоны от вашего имени">
                                            {{ $assignment->can_manage_subs ? '⭐ Снять права мастера' : '⭐ Сделать мастером' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.destroy', $assignment->id) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="btn-alert btn btn-danger btn-small"
                                                data-title="Снять помощника?"
                                                data-text="{{ $assignment->staff->first_name }} потеряет права Staff"
                                                data-confirm-text="Да, снять"
                                                data-cancel-text="Отмена">
                                            Снять
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="ramka text-center">
                    <a href="{{ route('staff.logs') }}" class="btn btn-secondary">📋 Логи действий</a>
                </div>

            </div>
        </div>
    </div>

    <x-slot name="script">
        <style>
            /* Дропдаун поиска: показ через display (без transition — иначе в Safari внутри backdrop-filter ломается скролл) */
            .staff-dd { display:none; opacity:1; visibility:visible; transform:none; transition:none; }
            .staff-dd .staff-result-item { padding:1rem 1.4rem; cursor:pointer; }
            .staff-dd .staff-result-meta { font-size:1.3rem; opacity:.6; }
            .staff-dd .staff-bot-badge { display:inline-block; padding:.1rem .8rem; border-radius:1rem; font-size:1.1rem; font-weight:600; background:#fef3c7; color:#92400e; margin-left:.5rem; }
        </style>
        <script src="/assets/fas.js"></script>
        <script>
        (function() {
            const wrap         = document.getElementById('staff_ac_wrap');
            const searchInput  = document.getElementById('staff_search_input');
            const resultsBox   = document.getElementById('staff_search_results');
            const selectedBox  = document.getElementById('staff_selected');
            const hiddenInput  = document.getElementById('staff_user_id_input');
            const submitBtn    = document.getElementById('staff_submit_btn');
            const ramkaEl      = wrap ? wrap.closest('.ramka, .card-ramka') : null;
            let searchTimeout  = null;
            let reqSeq         = 0;

            function esc(v) {
                return String(v == null ? '' : v).replace(/[&<>"']/g, function(c) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            }
            // Поднимаем свою .ramka выше соседних (backdrop-filter создаёт stacking context)
            function showDd() { resultsBox.style.display = 'block'; if (ramkaEl) ramkaEl.classList.add('select-dropdown-open'); }
            function hideDd() { resultsBox.style.display = 'none';  if (ramkaEl) ramkaEl.classList.remove('select-dropdown-open'); }

            function selectUser(id, name) {
                hiddenInput.value = id;
                searchInput.value = name;
                hideDd();
                selectedBox.style.display = '';
                selectedBox.innerHTML = '<span class="cd">✅ Выбран:</span> <strong>' + esc(name) + '</strong> <span style="opacity:.4;">(#' + esc(id) + ')</span>';
                submitBtn.disabled = false;
            }

            searchInput.addEventListener('input', function() {
                const q = this.value.trim();
                hiddenInput.value = '';
                submitBtn.disabled = true;
                selectedBox.style.display = 'none';
                clearTimeout(searchTimeout);
                if (q.length < 2) { hideDd(); return; }
                searchTimeout = setTimeout(async function() {
                    const seq = ++reqSeq;
                    let data = { ok: false, items: [] };
                    try {
                        const res = await fetch('/ajax/users/search?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                        if (res.ok) data = await res.json();
                    } catch (e) {}
                    if (seq !== reqSeq) return; // устаревший ответ
                    if (!data.ok || !data.items || !data.items.length) {
                        resultsBox.innerHTML = '<div class="city-message">Ничего не найдено</div>';
                        showDd();
                        return;
                    }
                    resultsBox.innerHTML = data.items.slice(0, 8).map(function(u) {
                        const name = u.full_name || u.label || u.name || ('#' + u.id);
                        const botBadge = u.is_bot ? '<span class="staff-bot-badge">🤖 бот</span>' : '';
                        return '<div class="staff-result-item form-select-option" data-id="' + esc(u.id) + '" data-name="' + esc(name) + '">'
                            + '<div class="b-600">' + esc(name) + botBadge + '</div>'
                            + '<div class="staff-result-meta">#' + esc(u.id) + '</div>'
                            + '</div>';
                    }).join('');
                    showDd();
                    resultsBox.querySelectorAll('.staff-result-item').forEach(function(el) {
                        el.addEventListener('click', function() { selectUser(el.dataset.id, el.dataset.name); });
                    });
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
            document.addEventListener('click', function(e) {
                if (wrap && !wrap.contains(e.target)) hideDd();
            });
        })();
        </script>
    </x-slot>
</x-voll-layout>
