
//$('input, select, textarea').attr('autocomplete', 'off');
$("input.phone").mask("+7 (999) 999 - 9999",{placeholder: "_", completed:function(){}});
$('.anima').each(function() {
	// Получаем текст (без тегов)
	$(this).css('opacity','1');
	var text = $(this).text();       
	// Разбиваем на слова
	var words = text.split(/\s+/).filter(function(word) {
		return word.length > 0;
	});        
	// Оборачиваем каждое слово в span
	var wrapped = '';
	words.forEach(function(word, index) {
		wrapped += '<span ' +
		'data-aos="fade-down" ' +
		'data-aos-delay="' + (index * 100) + '" ' +
		'data-aos-duration="400">' + 
		word + '</span> ';
	});       
	// Заменяем содержимое
	$(this).html(wrapped);
});


/* =========================
	КЭШ ЭЛЕМЕНТОВ
========================= */
const $header     = $('.fix-header');
const $userBtn    = $('.fix-header-btn-user, .fix-header-user');
const $hammBtn    = $('.fix-header-btn-hamm');
const $mailBtn    = $('.fix-header-btn-mail');

const $mailMenu   = $('.fix-header-menu-1');
const $userMenu   = $('.fix-header-menu-2');
const $hammMenu   = $('.fix-header-menu-3');



const allMenus    = $('.fix-header-menu');
const allButtons  = $('.fix-header-btn-user, .fix-header-btn-hamm, .fix-header-user, .fix-header-btn-mail');


/* =========================
	BREAKPOINT
========================= */
function isDesktop() {
	return window.matchMedia('(min-width: 768px)').matches;
}

let isDesktopState = isDesktop();


/* =========================
	СБРОС СОСТОЯНИЯ
========================= */
function resetMenus() {
	// Убираем inline-стили от jQuery
	allMenus.removeAttr('style');
	
	// Убираем классы состояний
	allMenus.removeClass('open');
	allButtons.removeClass('active');
}


/* =========================
	ЗАКРЫТИЕ ВСЕХ МЕНЮ
========================= */
function closeAll() {
	allMenus.removeClass('open');
	if (!isDesktop()) {
		allMenus
		.stop(true, true)
		.slideUp(300, function () {
			// чистим display после анимации
			$(this).css('display', '');
		});
	}
	allButtons.removeClass('active');
}


/* =========================
	ОТКРЫТИЕ МЕНЮ
========================= */
function openMenu($button, $menu) {
	
	// если это меню уже активно — закрываем всё
	if ($button.hasClass('active')) {
		closeAll();
		return;
	}
	
	closeAll();
	$button.addClass('active');
	$menu.addClass('open');
	if (!isDesktop()) {
		$menu
		.stop(true, true)
		.slideDown(300);
	}	
}


/* =========================
	ОБРАБОТЧИКИ КНОПОК
========================= */
$userBtn.on('click', function (e) {
	e.stopPropagation();
	openMenu($userBtn, $userMenu);
});

$hammBtn.on('click', function (e) {
	e.stopPropagation();
	openMenu($hammBtn, $hammMenu);
});
$mailBtn.on('click', function (e) {
	e.stopPropagation();
	openMenu($mailBtn, $mailMenu);
});	

/* =========================
	КЛИК ВНЕ ШАПКИ
========================= */
$(document).on('click', function (e) {
	if (!$(e.target).closest('.fix-header').length) {
		closeAll();
	}
});


/* =========================
	КЛИК ВНУТРИ МЕНЮ
========================= */
allMenus.on('click', function (e) {
	e.stopPropagation();
});


/* =========================
	ESC — ЗАКРЫТЬ
========================= */
$(document).on('keyup', function (e) {
	if (e.key === 'Escape') {
		closeAll();
	}
});


/* =========================
	КЛИК ПО ССЫЛКЕ В МЕНЮ
========================= */
/*
	$('.fix-header-menu a').on('click', function () {
	closeAll();
	});
*/

/* =========================
	RESIZE (КЛЮЧЕВОЙ МОМЕНТ)
========================= */
$(window).on('resize', function () {
	const nowDesktop = isDesktop();
	
	// сбрасываем ТОЛЬКО при смене режима
	if (nowDesktop !== isDesktopState) {
		resetMenus();
		isDesktopState = nowDesktop;
	}
});

function margin() {	
	// Проверяем видимую ширину окна
	if (window.innerWidth >= 992) {
		$("main").css({'margin-bottom': $("footer").height()});
		} else {
		$("main").css({'margin-bottom': 0});
	}
	
	// ДОБАВЛЯЕМ это сюда же - управление фоном
	const footer = $("footer");
	const bgEffects = $(".bg-effects");
	const windowHeight = $(window).height();
	const scrollTop = $(window).scrollTop();
	const documentHeight = $(document).height();
	
	// Футер появился? (с запасом 50px)
	const footerVisible = (scrollTop + windowHeight) >= (documentHeight - footer.height() - 50);
	
	if (footerVisible) {
		bgEffects.css({'opacity': '0'});
		} else {
		bgEffects.css({'opacity': '1'});
	}
}				
margin();

$(window).on('resize', function() {
	setTimeout(margin, 50);
});

// ДОБАВЛЯЕМ еще и скролл
$(window).on('scroll', function() {
	requestAnimationFrame(margin);
});



$('[data-href]').on('click', function() {
	const url = $(this).data('href');
	if (url) {
		window.location.href = url;
	}
});

$('.fix-header-btn-theme').click(function(e) {
	e.preventDefault();
	
	// 1. Добавляем класс, отключающий ВСЕ анимации
	$('body').addClass('no-transitions');
	
	// 2. Переключаем тему
	$('body').toggleClass('dark');
	
	// 3. Сохраняем в localStorage
	const isDark = $('body').hasClass('dark');
	if (isDark) {
		localStorage.setItem('theme', 'dark');
		} else {
		localStorage.removeItem('theme');
	}
	
	// 4. Через минимальную задержку убираем класс (после перерисовки)
	setTimeout(() => {
		$('body').removeClass('no-transitions');
	}, 50); // 50ms обычно достаточно
	
	// 5. Эффект нажатия на кнопку (опционально)
	$(this).css('transform', 'scale(0.95)');
	setTimeout(() => {
		$(this).css('transform', '');
	}, 100);
});

// Добавляем эффект нажатия для ВСЕХ кнопок в header
$('.fix-header-btn-user, .fix-header-btn-hamm, .fix-header-btn-theme, .fix-header-btn-mail').on('click', function() {
	const $btn = $(this);
	$btn.css({
		'transform': 'scale(0.95)',
		'transition': 'transform 0.1s ease'
	});
	
	setTimeout(() => {
		$btn.css({
			'transform': '',
			'transition': ''
		});
	}, 100);
});	

// paralax
const $image = $('.top-section-img img');
const $container = $('.top-section-img');



// Функция обновления
function updateSmoothParallax() {
	const containerHeight = $container.outerHeight();
	const windowHeight = $(window).height();	
	const scrollTop = $(window).scrollTop();
	const windowBottom = scrollTop + windowHeight;
	const containerBottom = $container.offset().top + containerHeight;
	
	// Если картинка еще в зоне видимости
	if (windowBottom > $container.offset().top) {
		// Вычисляем прогресс скролла относительно картинки (0-1)
		const progress = Math.min(scrollTop / containerHeight, 1);
		
		// Параллакс эффект (движение вниз)
		const parallaxValue = progress * containerHeight * 0.38;
		
		// Эффект scale (увеличение)
		const scaleValue = 1 + progress * -0.15; // Увеличиваем до 110%
		
		
		// Применяем все сразу
		$image.css({
			'transform': `translateY(${parallaxValue}px) scale(${scaleValue})`
		});
	}
}

// Оптимизация скролла
let ticking = false;
$(window).on('scroll', function() {
	if (!ticking) {
		requestAnimationFrame(function() {
			updateSmoothParallax();
			ticking = false;
		});
		ticking = true;
	}
});

// При ресайзе
$(window).on('resize', function() {
	requestAnimationFrame(updateSmoothParallax);
});

// Инициализация
updateSmoothParallax();



// 1. Сначала объявляем функцию для таблиц
function initTableScroll() {
	$('.table-scrollable').each(function() {
		var $tableWrapper = $(this);
		
		// Проверяем, нужен ли скролл вообще
		function checkIfScrollable() {
			// Если элемент не виден - пропускаем
			if (!$tableWrapper.is(':visible')) {
				$tableWrapper.removeClass('scrollable');
				return false;
			}
			var isScrollable = $tableWrapper[0].scrollWidth > $tableWrapper[0].clientWidth;
			$tableWrapper.toggleClass('scrollable', isScrollable);
			return isScrollable;
		}
		
		var isDragging = false;
		var startX, scrollLeft;
		var velocity = 0;
		var lastX = 0;
		var animationId;
		var isHorizontalScroll = false;
		
		// Универсальное получение координаты X
		function getPageX(e) {
			if (e.pageX !== undefined) return e.pageX;
			if (e.originalEvent && e.originalEvent.touches) {
				return e.originalEvent.touches[0].pageX;
			}
			return 0;
		}
		
		// Drag-скролл мышью
		function startDrag(e) {
			$tableWrapper.addClass('hide-indicator');
			if (!checkIfScrollable()) return;
			
			isDragging = true;
			$tableWrapper.addClass('dragging');
			startX = getPageX(e);
			scrollLeft = $tableWrapper.scrollLeft();
			lastX = startX;
			velocity = 0;
			isHorizontalScroll = false;
		}
		
		function doDrag(e) {
			if (!isDragging) return;
			
			var x = getPageX(e);
			if (!x) return;
			
			var deltaX = startX - x;
			
			if (!isHorizontalScroll) {
				var deltaY;
				if (e.movementY !== undefined) {
					deltaY = Math.abs(e.movementY);
					} else if (e.originalEvent && e.originalEvent.touches) {
					var touch = e.originalEvent.touches[0];
					deltaY = Math.abs(touch.pageY - startX);
					} else {
					deltaY = 0;
				}
				
				if (Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
					isHorizontalScroll = true;
					e.preventDefault();
				}
			}
			
			if (isHorizontalScroll) {
				var walk = deltaX * 1.5;
				$tableWrapper.scrollLeft(scrollLeft + walk);
				
				velocity = (x - lastX) * 16;
				lastX = x;
			}
		}
		
		function endDrag() {
			if (!isDragging) return;
			
			isDragging = false;
			$tableWrapper.removeClass('dragging');
			
			if (isHorizontalScroll && Math.abs(velocity) > 1) {
				cancelAnimationFrame(animationId);
				
				var inertiaScroll = function() {
					velocity *= 0.92;
					
					if (Math.abs(velocity) > 0.5) {
						$tableWrapper.scrollLeft($tableWrapper.scrollLeft() - velocity);
						animationId = requestAnimationFrame(inertiaScroll);
						} else {
						cancelAnimationFrame(animationId);
					}
				};
				
				animationId = requestAnimationFrame(inertiaScroll);
			}
		}
		
		// События для мыши
		$tableWrapper.on('mousedown', startDrag);
		$(document).on('mousemove', doDrag);
		$(document).on('mouseup', function() {
			endDrag();
			$(document).off('mousemove', doDrag);
		});
		
		$tableWrapper.on('mousedown', function() {
			$(document).on('mousemove', doDrag);
		});
		
		// События для touch-устройств
		$tableWrapper.on('touchstart', function(e) {
			$tableWrapper.addClass('hide-indicator');
		});
		
		
		// Отмена контекстного меню при drag
		$tableWrapper.on('contextmenu', function(e) {
			if (isDragging) {
				e.preventDefault();
				return false;
			}
		});
		
		// Инициализация
		setTimeout(function() {
			checkIfScrollable();
		}, 100);
		
		// Обновляем при изменении размера окна
		$(window).on('resize', function() {
			checkIfScrollable();
		});
		
		// Отменяем drag если клик был на ссылке или кнопке внутри таблицы
		$tableWrapper.on('mousedown touchstart', 'a, button, .clickable', function(e) {
			e.stopPropagation();
		});
	});
}
// Таблицы
initTableScroll();

// Дополнительно при ресайзе
$(window).on('resize', function() {
	setTimeout(initTableScroll, 100);
});	

// Глобальная функция для обновления подсветки
window.updateAllTabHighlights = function() {
	$('.tabs-content').each(function() {
		var $container = $(this);
		// Ищем .tabs ТОЛЬКО внутри этого контейнера
		var $tabsContainer = $container.find('.tabs');
		
		// Если нет .tabs, возможно табы лежат просто в родителе (как в твоем случае)
		if ($tabsContainer.length === 0) {
			// Ищем родителя табов (первый общий контейнер для всех .tab)
			var $firstTab = $container.find('.tab').first();
			if ($firstTab.length) {
				$tabsContainer = $firstTab.parent();
			}
		}
		
		if (!$tabsContainer || !$tabsContainer.length) return;
		
		var $highlight = $tabsContainer.find('.tab-highlight');
		var $activeTab = $tabsContainer.find('.tab.active');
		
		if ($activeTab.length) {
			var tabPosition = $activeTab.position();
			var tabWidth = $activeTab.outerWidth();
			var tabHeight = $activeTab.outerHeight();
			
			if (!$highlight.length) {
				$highlight = $('<div class="tab-highlight"></div>');
				$tabsContainer.append($highlight);
			}
			
			$highlight.css({
				width: tabWidth + 'px',
				height: tabHeight + 'px',
				transform: 'translate(' + tabPosition.left + 'px, ' + tabPosition.top + 'px)',
				opacity: 1
			});
		}
	});
};

// Функция для инициализации табов (почти без изменений)
function initTabSet($container) {
	// Ищем ТОЛЬКО непосредственных детей для табов и панелей
	// Это защищает от вложенных табов
	var $tabs = $container.children('.tabs').children('.tab');
	var $panes = $container.children('.tab-panes').children('.tab-pane');
	
	// Если нет структуры .tbs/.tab-panes, ищем глубже (для твоего случая)
	if ($tabs.length === 0) {
		$tabs = $container.find('.tab');
		// Фильтруем, чтобы не захватывать вложенные табы
		$tabs = $tabs.filter(function() {
			// Проверяем, что таб находится в том же контейнере, что и панели
			return $(this).closest('.tabs-content').is($container);
		});
	}
	
	if ($panes.length === 0) {
		$panes = $container.find('.tab-pane');
		$panes = $panes.filter(function() {
			return $(this).closest('.tabs-content').is($container);
		});
	}
	
	if ($tabs.length > 0 && $tabs.filter('.active').length === 0) {
		var $firstTab = $tabs.first();
		var firstTabId = $firstTab.data('tab');
		
		$firstTab.addClass('active');
		$panes.filter('#' + firstTabId).addClass('active');
	}
	
	$tabs.off('click').on('click', function() {
		if ($(this).hasClass('no-highlight')) return;
		var tabId = $(this).data('tab');
		
		$tabs.removeClass('active');
		$(this).addClass('active');
		
		$panes.removeClass('active');
		$panes.filter('#' + tabId).addClass('active');
		
		window.updateAllTabHighlights();
		
		setTimeout(initTableScroll, 100);
	});
}

// Инициализация табов
$('.tabs-content').each(function() {
	initTabSet($(this));
});

// Первичная инициализация подсветки
window.updateAllTabHighlights();

// Обновление при ресайзе
var resizeTimer;
$(window).on('resize', function() {
	clearTimeout(resizeTimer);
	resizeTimer = setTimeout(window.updateAllTabHighlights, 250);
});




// Функция для создания кастомного селекта
function createCustomSelect($select) {
	// Создаём обёртку
	const $wrapper = $('<div>', {
		class: 'form-select-wrapper',
		'data-select-id': $select.attr('id') || 'select-' + Math.random().toString(36).substr(2, 9)
	});
	
	// Создаём кастомный элемент
	const $customSelect = $('<div>', {
		class: 'form-select-custom' + ($select.is(':disabled') ? ' form-select-custom--disabled' : '')
	});
	
	// Создаём отображаемое значение
	const $valueSpan = $('<span>', {
		class: 'form-select-value'
	});
	
	// Стрелка (SVG)
	const $arrow = $('<span>', {
		class: 'form-select-arrow',
		html: `<svg viewBox="0 0 24 24">
		<polyline points="6 9 12 15 18 9"></polyline>
		</svg>`
	});
	
	// Добавляем в кастомный селект
	$customSelect.append($valueSpan, $arrow);
	
	// Создаём выпадающий список
	const $dropdown = $('<div>', {
		class: 'form-select-dropdown'
	});
	
	// Собираем опции из оригинального селекта
	$select.find('option').each(function() {
		const $option = $(this);
		
		// Проверяем, есть ли у опции атрибут hidden или style="display: none"
		const isHidden = $option.is('[hidden]') || $option.css('display') === 'none';
		
		const $customOption = $('<div>', {
			class: 'form-select-option' + 
			($option.is(':selected') ? ' form-select-option--selected' : '') +
			($option.is(':disabled') ? ' form-select-option--disabled' : ''),
			'data-value': $option.val(),
			html: $option.text()
		});
		
		// Если опция скрыта, добавляем класс и скрываем
		if (isHidden) {
			$customOption.addClass('form-select-option--hidden').hide();
		}
		
		// Обработчик клика на опцию
		$customOption.on('click', function(e) {
			if ($option.is(':disabled') || $select.is(':disabled')) return;
			
			e.stopPropagation();
			
			// Обновляем значение в оригинальном селекте
			$select.val($option.val());
			
			// Принудительно вызываем все возможные события для стороннего кода
			$select.trigger('change');
			$select.trigger('input');
			$select.trigger('custom-change');
			
			// Если используется нативный change, пробуем и его
			if (typeof Event === 'function') {
				const nativeEvent = new Event('change', { bubbles: true });
				$select[0].dispatchEvent(nativeEvent);
			}
			
			// Обновляем отображаемое значение
			updateCustomSelect($select, $wrapper);
			
			// Закрываем dropdown
			$customSelect.removeClass('form-select-custom--active');
			$dropdown.removeClass('form-select-dropdown--active');
		});
		
		$dropdown.append($customOption);
	});
	
	// Собираем всё вместе
	$wrapper.append($customSelect, $dropdown);
	
	// Вставляем перед оригинальным селектом
	$select.before($wrapper);
	
	// Обновляем отображение
	updateCustomSelect($select, $wrapper);
	
	// Обработчики событий для кастомного селекта
	$customSelect.on('click', function(e) {
		if ($select.is(':disabled')) return;
		
		e.stopPropagation();
		
		// Закрываем другие открытые селекты
		$('.form-select-custom--active').not($customSelect).removeClass('form-select-custom--active');
		$('.form-select-dropdown--active').not($dropdown).removeClass('form-select-dropdown--active');
		
		// Переключаем состояние текущего
		$customSelect.toggleClass('form-select-custom--active');
		$dropdown.toggleClass('form-select-dropdown--active');
	});
	
	// Закрытие при клике вне
	$(document).on('click', function(e) {
		if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
			$customSelect.removeClass('form-select-custom--active');
			$dropdown.removeClass('form-select-dropdown--active');
		}
	});
	
	// Слушаем изменения оригинального селекта
	$select.on('change', function() {
		updateCustomSelect($(this), $wrapper);
	});
	
	return $wrapper;
}

// Функция обновления отображаемого значения
function updateCustomSelect($select, $wrapper) {
	const $valueSpan = $wrapper.find('.form-select-value');
	const $dropdown = $wrapper.find('.form-select-dropdown');
	
	const selectedValue = $select.val();
	const selectedText = $select.find('option:selected').text();
	
	// Обновляем отображаемое значение
	$valueSpan.text(selectedText);
	if (!selectedValue && $select.find('option[value=""]').length) {
		$valueSpan.addClass('form-select-placeholder');
		} else {
		$valueSpan.removeClass('form-select-placeholder');
	}
	
	// Обновляем выбранную опцию в dropdown
	$dropdown.find('.form-select-option').removeClass('form-select-option--selected');
	$dropdown.find(`.form-select-option[data-value="${selectedValue}"]`).addClass('form-select-option--selected');
	
	// НЕ обновляем видимость опций здесь, чтобы случайно не скрыть все
	// Видимость определяется только при создании
}

// Инициализация всех селектов в формах
function initCustomSelects() {
	$('.form select').each(function() {
		const $select = $(this);
		
		// Проверяем, не инициализирован ли уже этот селект
		if ($select.data('custom-initialized')) return;
		
		// Создаём кастомный селект
		createCustomSelect($select);
		
		// Помечаем как инициализированный
		$select.data('custom-initialized', true);
	});
}


initCustomSelects();



// Публичные методы для использования извне
window.customSelect = {
	update: function(selectId) {
		const $select = $(`#${selectId}`);
		const $wrapper = $select.prev('.form-select-wrapper');
		if ($wrapper.length) {
			updateCustomSelect($select, $wrapper);
		}
	},
	
	destroy: function(selectId) {
		const $select = $(`#${selectId}`);
		const $wrapper = $select.prev('.form-select-wrapper');
		
		if ($wrapper.length) {
			$wrapper.remove();
			$select.removeData('custom-initialized');
		}
	},
	
	disable: function(selectId, disabled) {
		const $select = $(`#${selectId}`);
		const $wrapper = $select.prev('.form-select-wrapper');
		
		if ($wrapper.length) {
			const $customSelect = $wrapper.find('.form-select-custom');
			if (disabled) {
				$customSelect.addClass('form-select-custom--disabled');
				} else {
				$customSelect.removeClass('form-select-custom--disabled');
			}
		}
	}
};



// Отслеживаем движение мыши внутри .ramka и .card-ramka
$('.ramka, .card-ramka').on('mousemove', function(e) {
	
	// Координаты относительно текущего элемента
	const rect = this.getBoundingClientRect();
	const x = e.clientX - rect.left;
	const y = e.clientY - rect.top;
	
	// Устанавливаем CSS-переменные для этого элемента
	this.style.setProperty('--mouse-x', `${x}px`);
	this.style.setProperty('--mouse-y', `${y}px`);
});

// Опционально: убираем свечение, когда мышь уходит с родителя
$('.ramka, .card-ramka').on('mouseleave', function() {
	// Можно оставить как есть, а можно убрать переменные
	// this.style.removeProperty('--mouse-x');
	// this.style.removeProperty('--mouse-y');
	// Оставляем как есть — свечение гаснет через transition
});	

$('.ufilter-btn').on('click', function() {
	$('.users-filter').toggleClass('open');
	$('.top-section-img').toggleClass('mhide');
});



document.addEventListener('DOMContentLoaded', function() {
	AOS.init({
		duration: 800,
		once: true,
		offset: 100,
		initClassName: 'aos-init', // Класс применяется сразу
		//disableMutationObserver: true // ← Важно! Отключаем наблюдение
		disable: 'mobile' // Отключаем на мобилках для производительности
	});
});

/**
	* Универсальный скрипт подтверждения действий
	* Использование: 
	* <button class="btn-alert" data-title="Удалить?" data-text="Точно?">Действие</button>
	* <form>...</form> с такой кнопкой внутри
	* <a href="..." class="btn-alert" data-method="DELETE">Удалить</a>
*/

(function() {
	
	// await salert('ошибка');
	// Глобальная функция, которая возвращает Promise
	window.salert = function(message, callback) {
		return swal({
			title: message,
			icon: 'info',
			buttons: {
				confirm: {
					text: 'OK',
					value: true,
					visible: true,
					closeModal: true
				}
			}
			}).then(() => {
			if (callback && typeof callback === 'function') {
				callback();
			}
		});
	};		
	
    // Переопределяем глобальный alert на SweetAlert
	
    const originalAlert = window.alert;
	
    window.alert = function(message) {
        // Используем SweetAlert вместо нативного alert
        swal({
            title: message,
            icon: 'info',
            buttons: {
                confirm: {
                    text: 'OK',
                    value: true,
                    visible: true,
                    closeModal: true
				}
			}
		}).catch(swal.noop); // Игнорируем ошибки если пользователь закрыл без подтверждения
	};
	
    document.addEventListener('DOMContentLoaded', function() {
        // Находим все элементы с классом btn-alert
        document.querySelectorAll('.btn-alert').forEach(function(element) {
            element.addEventListener('click', function(e) {
				
                e.preventDefault();
				
				
                const target = this;
                
                // Получаем данные из атрибутов или используем значения по умолчанию
                const title = target.dataset.title || 'Подтвердите действие';
                const text = target.dataset.text || 'Вы уверены?';
                const icon = target.dataset.icon || 'warning';
                const confirmText = target.dataset.confirmText || 'Да, выполнить';
                const cancelText = target.dataset.cancelText || 'Отмена';
                
                // Определяем, что будем делать после подтверждения
                let action = null;
                let method = 'GET';
                let form = null;
                
                // Если это кнопка внутри формы
                if (target.tagName === 'BUTTON' && target.closest('form')) {
                    form = target.closest('form');
                    action = form.action;
                    method = form.method;
                    
                    // Проверяем, есть ли в форме скрытое поле _method (для DELETE/PUT)
                    const methodField = form.querySelector('input[name="_method"]');
                    if (methodField) {
                        method = methodField.value;
					}
				} 
                // Если это ссылка с data-method
                else if (target.tagName === 'A') {
                    action = target.href;
                    method = target.dataset.method || 'GET';
				}
                
                // Показываем SweetAlert
                swal({
                    title: title,
                    text: text,
                    icon: icon,
                    buttons: {
                        cancel: {
                            text: cancelText,
                            value: null,
                            visible: true,
                            className: '',
                            closeModal: true,
						},
                        confirm: {
                            text: confirmText,
                            value: true,
                            visible: true,
                            className: 'btn-danger',
                            closeModal: true
						}
					},
                    dangerMode: icon === 'warning',
					}).then((value) => {
                    if (value) {
                        // Подтверждено - выполняем действие
                        if (form) {
                            // Отправляем форму
                            form.submit();
							} else if (action) {
                            // Для ссылок создаём временную форму
                            const tempForm = document.createElement('form');
                            tempForm.method = 'POST';
                            tempForm.action = action;
                            tempForm.style.display = 'none';
                            
                            // Добавляем CSRF
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            if (csrfToken) {
                                const csrfInput = document.createElement('input');
                                csrfInput.type = 'hidden';
                                csrfInput.name = '_token';
                                csrfInput.value = csrfToken;
                                tempForm.appendChild(csrfInput);
							}
                            
                            // Добавляем метод если не GET
                            if (method !== 'GET') {
                                const methodInput = document.createElement('input');
                                methodInput.type = 'hidden';
                                methodInput.name = '_method';
                                methodInput.value = method;
                                tempForm.appendChild(methodInput);
							}
                            
                            document.body.appendChild(tempForm);
                            tempForm.submit();
						}
					}
				});
			});
		});
	});
	
	
	// === Функция обновления темы карты ===
	function updateMapTheme() {
		const theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
		const maps = document.querySelectorAll('iframe.iframe-map');
		
		maps.forEach(iframe => {
			if (iframe.src && iframe.src.includes('yandex.ru')) {
				// Убираем старую тему из URL
				let baseSrc = iframe.src.split('&theme=')[0];
				// Добавляем новую
				iframe.src = baseSrc + '&theme=' + theme;
			}
		});
	}
	
	// === Ленивая загрузка карт ===
	const lazyMaps = document.querySelectorAll('iframe.lazy-map');
	
	if (lazyMaps.length && 'IntersectionObserver' in window) {
		const mapObserver = new IntersectionObserver((entries) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					const iframe = entry.target;
					const theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
					
					// Загружаем карту с текущей темой
					iframe.src = iframe.dataset.src + '&theme=' + theme;
					
					iframe.classList.remove('lazy-map');
					mapObserver.unobserve(iframe);
				}
			});
			}, {
			rootMargin: '100px'
		});
		
		lazyMaps.forEach(map => mapObserver.observe(map));
		} else {
		// Fallback
		lazyMaps.forEach(iframe => {
			const theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
			iframe.src = iframe.dataset.src + '&theme=' + theme;
		});
	}
	
	// === Переключатель темы (ваш существующий код) ===
	$('.fix-header-btn-theme').click(function(e) {
		setTimeout(() => {
			updateMapTheme();
		}, 150);
	});	
	
	
})();


document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('loaded');
});

window.addEventListener('load', function() {
    document.body.classList.add('img-loaded');
});
$(document).ready(function() {
    var $menuCol = $('.col-lg-4.col-xl-3');
    var $menuSticky = $menuCol.find('.sticky');
    var $contentCol = $('.col-lg-8.col-xl-9');
    var $contentRamka = $contentCol.find('.ramka');
    var $moveLeft = $('.menu-move-left');
    var $moveRight = $('.menu-move-right');
    
    var STORAGE_KEY = 'menu_shifted_state';
    
    function getShiftAmount() {
        var menuWidth = $menuCol.outerWidth();
        var visiblePart = 100;
        return menuWidth - visiblePart;
    }
    
    function setMenuShifted(shifted, saveState = true) {
        if (shifted) {
            var shift = getShiftAmount();
            $menuSticky.css({
                'transform': 'translateX(' + shift + 'px)',
                'transition': 'transform 0.3s ease-in-out'
            });
            // Анимируем margin-right вместо width
            $contentRamka.css({
                'margin-right': '-' + shift + 'px',
                'transition': 'margin-right 0.3s ease-in-out'
            });
            $moveRight.hide();
            $moveLeft.show();
        } else {
            $menuSticky.css('transform', 'translateX(0)');
            $contentRamka.css({
                'margin-right': '',
                'transition': 'margin-right 0.3s ease-in-out'
            });
            $moveLeft.hide();
            $moveRight.show();
        }
        
        if (saveState) {
            localStorage.setItem(STORAGE_KEY, shifted ? '1' : '0');
        }
    }
    
    var savedState = localStorage.getItem(STORAGE_KEY);
    var isShifted = savedState === '1';
    
    $moveRight.on('click', function() { setMenuShifted(true); });
    $moveLeft.on('click', function() { setMenuShifted(false); });
    
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var currentState = localStorage.getItem(STORAGE_KEY) === '1';
            if (currentState) {
                var shift = getShiftAmount();
                $menuSticky.css('transform', 'translateX(' + shift + 'px)');
                $contentRamka.css('margin-right', '-' + shift + 'px');
            }
        }, 150);
    });
    
    setMenuShifted(isShifted, false);
});