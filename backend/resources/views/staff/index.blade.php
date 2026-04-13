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
                            'activeMenu'     => 'org_dashboard',
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
                    <div class="card">
                        <form method="POST" action="{{ route('staff.store') }}" id="staffAddForm">
                            @csrf
                            <input type="hidden" name="staff_user_id" id="staff_user_id_input" value="{{ old('staff_user_id') }}">
                            <label>Поиск пользователя</label>
                            <input type="text" id="staff_search_input"
                                   placeholder="Введите имя, фамилию или email..."
                                   autocomplete="off" class="w-100">
                            <div id="staff_search_results" class="mt-1" style="display:none;border:0.1rem solid var(--border-color,#eee);border-radius:0.8rem;overflow:hidden;"></div>
                            <div id="staff_selected" class="mt-1 f-15" style="display:none;"></div>
                            @error('staff_user_id')
                            <div class="f-14 red mt-05">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn mt-2 w-100" id="staff_submit_btn" disabled>Назначить помощником</button>
                        </form>
                    </div>
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
                            <div class="card">
                                <div class="d-flex fvc gap-2">
                                    <img src="{{ $assignment->staff->profile_photo_url }}"
                                         alt="" style="width:5rem;height:5rem;border-radius:50%;object-fit:cover;">
                                    <div class="flex-1">
                                        <div class="b-600">{{ trim($assignment->staff->first_name . ' ' . $assignment->staff->last_name) }}</div>
                                        <div class="f-13" style="opacity:.6;">{{ $assignment->staff->email }}</div>
                                        <div class="f-13 mt-05" style="opacity:.6;">
                                            С {{ $assignment->created_at->format('d.m.Y') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 mt-2">
                                    <a href="{{ route('users.show', $assignment->staff->id) }}"
                                       class="btn btn-secondary btn-small">👤 Профиль</a>
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
        <script src="/assets/fas.js"></script>
        <script>
        (function() {
            const searchInput  = document.getElementById('staff_search_input');
            const resultsBox   = document.getElementById('staff_search_results');
            const selectedBox  = document.getElementById('staff_selected');
            const hiddenInput  = document.getElementById('staff_user_id_input');
            const submitBtn    = document.getElementById('staff_submit_btn');
            let searchTimeout  = null;

            function selectUser(id, name) {
                hiddenInput.value = id;
                searchInput.value = name;
                resultsBox.style.display = 'none';
                selectedBox.style.display = '';
                selectedBox.innerHTML = '<span style="color:var(--cd)">✅ Выбран:</span> <strong>' + name + '</strong> <span style="opacity:.4;"> (#' + id + ')</span>';
                submitBtn.disabled = false;
            }

            searchInput.addEventListener('input', function() {
                const q = this.value.trim();
                hiddenInput.value = '';
                submitBtn.disabled = true;
                selectedBox.style.display = 'none';
                clearTimeout(searchTimeout);
                if (q.length < 2) { resultsBox.style.display = 'none'; return; }
                searchTimeout = setTimeout(async function() {
                    const res  = await fetch('/api/users/search?q=' + encodeURIComponent(q));
                    const data = await res.json();
                    if (!data.ok || !data.items.length) {
                        resultsBox.innerHTML = '<div class="p-2 f-14" style="opacity:.6;">Ничего не найдено</div>';
                        resultsBox.style.display = '';
                        return;
                    }
                    console.log('items:', JSON.stringify(data.items[0]));
                    resultsBox.innerHTML = data.items.slice(0, 8).map(u => {
                        const name = u.full_name || u.label || u.name || ('#' + u.id);
                        return '<div class="staff-result-item" data-id="' + u.id + '" data-name="' + name + '" style="padding:.8rem 1.2rem;cursor:pointer;border-bottom:0.1rem solid var(--border-color,#eee);">'
                            + '<div class="b-600">' + name + '</div>'
                            + '<div class="f-13" style="opacity:.6;">#' + u.id + '</div>'
                            + '</div>';
                    }).join('');
                    resultsBox.style.display = '';
                    resultsBox.querySelectorAll('.staff-result-item').forEach(el => {
                        el.addEventListener('mouseenter', () => el.style.background = 'var(--bg2,#f5f5f5)');
                        el.addEventListener('mouseleave', () => el.style.background = '');
                        el.addEventListener('click', () => selectUser(el.dataset.id, el.dataset.name));
                    });
                }, 300);
            });

            document.addEventListener('click', function(e) {
                if (!resultsBox.contains(e.target) && e.target !== searchInput) {
                    resultsBox.style.display = 'none';
                }
            });
        })();
        </script>
    </x-slot>
</x-voll-layout>
