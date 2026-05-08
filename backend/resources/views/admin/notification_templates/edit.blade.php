{{-- body_class - класс для body --}}
<x-voll-layout body_class="notification-templates-page">
    
    <x-slot name="title">
        {{ __('admin.nt_edit_title') }}
	</x-slot>
    
    <x-slot name="description">
        {{ __('admin.nt_edit_title') }} {{ $template->name }}
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.notification_templates.edit', $template->id) }}
	</x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили при необходимости */
		</style>
	</x-slot>
    
    <x-slot name="h1">
        {{ __('admin.nt_edit_title') }}
	</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
            <meta itemprop="position" content="2">
		</li>	
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.notification_templates.index') }}" itemprop="item">
                <span itemprop="name">{{ __('admin.nt_breadcrumb') }}</span>
			</a>
            <meta itemprop="position" content="3">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.bc_breadcrumb_edit') }}</span>
            <meta itemprop="position" content="4">
		</li>
	</x-slot>
    
    <x-slot name="t_description">
        <div class="f-22 b-600">
			{{ __('admin.nt_code_label') }} <span class="cd">{{ $template->code }}</span>
		</div>		
        <div class="f-22 b-600">
			{{ __('admin.nt_channel_label') }} <span class="cd">{{ $template->channel ?: __('admin.nt_channel_general') }}</span>
		</div>	
	</x-slot>
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
		</script>
	</x-slot>
    
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if(session('status'))
				<div class="ramka">
                    <div class="alert alert-success">
                        {{ session('status') }}
					</div>
				</div>
                @endif
                
                <form method="POST" action="{{ route('admin.notification_templates.update', $template->id) }}" class="form">
                    @csrf
                    @method('PATCH')
                    
                    <div class="row row2">
                        <div class="col-12 col-lg-8">
                            <div class="ramka">
								<h2 class="-mt-05">Основная информация</h2>
								<div class="row">
									<div class="col-12">
										<div class="card">
											<label>Название</label>
											<input type="text" name="name" value="{{ old('name', $template->name) }}" >
										</div>	
									</div>
									<div class="col-12">
										<div class="card">
											<label>Заголовок</label>
											<input type="text" name="title_template" value="{{ old('title_template', $template->title_template) }}" >
										</div>
									</div>
									<div class="col-12">
										<div class="card">
											<label>Текст</label>
											<textarea name="body_template" rows="10" >{{ old('body_template', $template->body_template) }}</textarea>
										</div>
									</div>
								</div>
							</div>
                            {{-- ===== ХЕЛПЕР ШОРТКОДОВ ===== --}}
                            <div class="ramka mt-2">
                                <h2 class="-mt-05">📋 Доступные шорткоды</h2>
                                <div class="row row2">

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">📅 Дата и время</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{event_date}</td><td>Дата мероприятия (напр. 25.04.2026)</td></tr>
                                                <tr><td class="cd b-600 nowrap">{event_time}</td><td>Время начала (напр. 19:00)</td></tr>
                                                <tr><td class="cd b-600 nowrap">{event_datetime}</td><td>Дата и время вместе</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">🏐 Мероприятие</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{event_title}</td><td>Название мероприятия</td></tr>
                                                <tr><td class="cd b-600 nowrap">{event_url}</td><td>Ссылка на страницу мероприятия</td></tr>
                                                <tr><td class="cd b-600 nowrap">{cancel_reason}</td><td>Причина отмены (для event_cancelled)</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">📍 Место проведения</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{event_address}</td><td>Адрес локации</td></tr>
                                                <tr><td class="cd b-600 nowrap">{event_location}</td><td>Название локации</td></tr>
                                                <tr><td class="cd b-600 nowrap">{event_city}</td><td>Город</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">👤 Пользователь</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{user_name}</td><td>Полное имя получателя</td></tr>
                                                <tr><td class="cd b-600 nowrap">{user_first_name}</td><td>Только имя (без фамилии)</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">👥 Группы и команды</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{invite_id}</td><td>ID приглашения в группу</td></tr>
                                                <tr><td class="cd b-600 nowrap">{team_name}</td><td>Название команды (турнир)</td></tr>
                                                <tr><td class="cd b-600 nowrap">{captain_name}</td><td>Имя капитана команды</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="b-600 mb-1">💰 Оплата</div>
                                            <table class="table f-16">
                                                <tr><td class="cd b-600 nowrap">{price}</td><td>Стоимость участия</td></tr>
                                                <tr><td class="cd b-600 nowrap">{currency}</td><td>Валюта (RUB, USD…)</td></tr>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                                <ul class="list f-16 mt-1">
                                    <li>Шорткоды работают в полях <strong>Заголовок</strong>, <strong>Текст</strong> и <strong>Ссылка кнопки</strong>.</li>
                                    <li>Если переменная не передана — шорткод заменяется пустой строкой.</li>
                                </ul>
                            </div>
                            {{-- ===== END ХЕЛПЕР ===== --}}
						</div>
                        
                        <div class="col-12 col-lg-4">
							<div class="sticky">
								<div class="ramka">
									<h2 class="-mt-05">Дополнительные настройки</h2>
									<div class="row">
										<div class="col-12">		
											<div class="card">
												<label>Картинка (URL)</label>
												<input type="text" name="image_url" value="{{ old('image_url', $template->image_url) }}" >
											</div>
										</div>
										<div class="col-12">		
											<div class="card">
												<label>Текст кнопки</label>
												<input type="text" name="button_text" value="{{ old('button_text', $template->button_text) }}" >
											</div>
										</div>
										<div class="col-12">		
											<div class="card">
												<label>Ссылка кнопки</label>
												<input type="text" name="button_url_template" value="{{ old('button_url_template', $template->button_url_template) }}" >
											</div>
										</div>
									</div>
                                    <div class="mb-2 mt-2">
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $template->is_active) ? 'checked' : '' }}>
                                            <div class="custom-checkbox"></div>
                                            <span>Шаблон активен</span>
										</label>
									</div>
								</div>
								<div class="ramka">
                                    <div class="text-center">
										<button type="submit" class="btn">
											Сохранить
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
    
</x-voll-layout>
