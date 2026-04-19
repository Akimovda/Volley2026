{{-- body_class - класс для body --}}
<x-voll-layout body_class="broadcasts-create-page">
    
    <x-slot name="title">
        Новая рассылка
    </x-slot>
    
    <x-slot name="description">
        Создание новой рассылки
    </x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.broadcasts.create') }}
    </x-slot>
    
    <x-slot name="style">
        <style>
            .broadcasts-create-page .ramka { margin-bottom: 20px; }
            .broadcasts-create-page .ramka h2 { font-size: 18px; font-weight: 700; margin-bottom: 15px; }

            .broadcasts-create-page .card {
                background: var(--card-bg, rgba(255,255,255,0.05));
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 8px;
                padding: 12px 15px;
                margin-bottom: 10px;
            }

            .broadcasts-create-page label:not(.checkbox-item) {
                display: block;
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 6px;
                opacity: 0.7;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .broadcasts-create-page input[type="text"],
            .broadcasts-create-page input[type="datetime-local"],
            .broadcasts-create-page textarea,
            .broadcasts-create-page select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid var(--border-color, rgba(255,255,255,0.15));
                border-radius: 6px;
                background: var(--input-bg, rgba(255,255,255,0.08));
                color: var(--text-color, #fff);
                font-size: 15px;
                transition: border-color 0.2s;
            }

            .broadcasts-create-page input[type="text"]:focus,
            .broadcasts-create-page input[type="datetime-local"]:focus,
            .broadcasts-create-page textarea:focus,
            .broadcasts-create-page select:focus {
                outline: none;
                border-color: var(--accent, #f57c00);
                box-shadow: 0 0 0 2px rgba(245, 124, 0, 0.15);
            }

            .broadcasts-create-page textarea { resize: vertical; min-height: 120px; }

            .broadcasts-create-page select option {
                background: #2a2a2a;
                color: #fff;
            }

            /* Preview */
            .broadcasts-create-page #preview-title { color: #333 !important; font-weight: 700; }
            .broadcasts-create-page #preview-body { color: #555 !important; }
            .broadcasts-create-page .bg-light {
                background: #fff !important;
                border-radius: 10px;
                overflow: hidden;
            }

            /* Buttons */
            .broadcasts-create-page .btn-secondary {
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                color: #fff;
            }
            .broadcasts-create-page .btn-secondary:hover { background: rgba(255,255,255,0.15); }

            .broadcasts-create-page .btn-warning {
                background: #f57c00;
                border-color: #f57c00;
                color: #fff;
            }

            .broadcasts-create-page .btn-purple {
                background: #7c3aed !important;
                border-color: #7c3aed !important;
                color: #fff;
            }

            /* Action result & dry run */
            .broadcasts-create-page #action-result { font-size: 14px; }
            .broadcasts-create-page #dry-run-list .card {
                background: rgba(255,255,255,0.05) !important;
                border: 1px solid rgba(255,255,255,0.1);
            }
            .broadcasts-create-page #dry-run-list .card-body { padding: 12px 15px; }
            .broadcasts-create-page #dry-run-list .fw-bold { color: var(--text-color, #fff); }
            .broadcasts-create-page #dry-run-list .text-secondary { color: rgba(255,255,255,0.5) !important; }

            /* Submit buttons */
            .broadcasts-create-page .text-center .btn {
                padding: 10px 30px;
                font-weight: 600;
                font-size: 15px;
            }

            /* Checkbox alignment */
            .broadcasts-create-page .checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        </style>
    </x-slot>
    
    <x-slot name="h1">
        Новая рассылка
    </x-slot>
    
    
    <x-slot name="t_description">
        Заполните форму для создания новой рассылки
    </x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
		</li>	
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.broadcasts.index') }}" itemprop="item">
                <span itemprop="name">Рассылки</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Новая рассылка</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>
    
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
        </script>
    </x-slot>
    
    <div class="container">
                <form id="broadcast-form" method="POST" action="{{ route('admin.broadcasts.store') }}" class="form">
                    @csrf

                    @include('admin.broadcasts.partials.form', [
                        'broadcast' => $broadcast,
                        'channelOptions' => $channelOptions,
                        'statusOptions' => $statusOptions,
                        'channels' => [],
                        'filters' => [],
                    ])

                    <div class="ramka text-center">
                        <button type="submit" name="action" value="save" class="btn mr-1">
                            Сохранить
                        </button>

                        <button type="submit" name="action" value="save_and_launch" class="btn">
                            Сохранить и запустить
                        </button>
                    </div>
                </form>
        </div>

</x-voll-layout>