{{--
    Partial: trainer._rating_widget

    Expects:
      - $occurrenceId
      - $trainerUserId
      - $trainerName
      - $existingScore   (nullable int 1..10)
      - $existingComment (nullable string)
--}}
@php
    $trUid = 'tr-rate-' . $occurrenceId . '-' . $trainerUserId;
@endphp
<div class="tr-rate-widget" id="{{ $trUid }}"
     data-url="{{ route('occurrences.trainers.rating', ['occurrence' => $occurrenceId, 'trainer' => $trainerUserId]) }}">
    <button type="button" class="btn btn-secondary btn-small mt-05 tr-rate-open">
        {{ $existingScore ? __('trainers.rate_edit_btn') : __('trainers.rate_btn') }}
    </button>

    <div id="{{ $trUid }}-modal" style="display:none">
        <div class="card form tr-rate-panel" style="height:auto;max-width:400px;">
            <h3 class="-mt-05">{{ $trainerName }}</h3>

            <div class="tr-rate-slider-wrap" style="position:relative;padding-top:36px;">
                <div class="tr-rate-flag" style="position:absolute;top:0;left:0;transform:translateX(-50%);font-size:26px;line-height:1;">😐</div>
                <svg width="100%" height="12" viewBox="0 0 300 12" preserveAspectRatio="none" style="display:block;">
                    <line x1="5" y1="6" x2="295" y2="6" stroke="#ddd" stroke-width="4" stroke-linecap="round"/>
                    <circle class="tr-rate-dot" cx="5" cy="6" r="7" fill="#2967BA"/>
                </svg>
                <input type="range" class="tr-rate-range" min="1" max="10" step="1"
                       value="{{ $existingScore ?? 5 }}" style="width:100%;margin-top:6px;">
                <div class="tr-rate-value f-15 b-600 mt-1">{{ $existingScore ?? 5 }}/10</div>
            </div>

            <div class="mt-2">
                <textarea class="tr-rate-comment" rows="3" maxlength="2000" style="min-height:auto;"
                          placeholder="{{ __('trainers.rate_comment_ph') }}">{{ $existingComment }}</textarea>
            </div>

            <button type="button" class="btn mt-2 tr-rate-submit">{{ __('trainers.rate_submit_btn') }}</button>
            <div class="tr-rate-status f-13 mt-1"></div>
        </div>
    </div>
</div>

<script>
(function(){
    function emojiFor(v){
        if (v<=2) return '😖';
        if (v<=4) return '😞';
        if (v<=6) return '😐';
        if (v<=8) return '🙂';
        return '😄';
    }

    function bootOne(root){
        if (root.__trBooted) return;
        root.__trBooted = true;

        var openBtn = root.querySelector('.tr-rate-open');
        var modalId = '#' + root.id + '-modal';
        var modal   = document.querySelector(modalId);
        if (!openBtn || !modal) return;
        var range   = modal.querySelector('.tr-rate-range');
        var valueEl = modal.querySelector('.tr-rate-value');
        var flag    = modal.querySelector('.tr-rate-flag');
        var dot     = modal.querySelector('.tr-rate-dot');
        var comment = modal.querySelector('.tr-rate-comment');
        var submit  = modal.querySelector('.tr-rate-submit');
        var status  = modal.querySelector('.tr-rate-status');
        var url     = root.getAttribute('data-url');
        if (!range || typeof jQuery === 'undefined') return;

        function render(v){
            valueEl.textContent = v + '/10';
            var pct = (v - 1) / 9;
            flag.style.left = (pct * 100) + '%';
            flag.textContent = emojiFor(v);
            dot.setAttribute('cx', 5 + pct * 290);
        }

        openBtn.addEventListener('click', function(){
            jQuery.fancybox.open({ src: modalId, type: 'inline' });
        });

        render(parseInt(range.value, 10));

        // WKWebView: input/change на range ненадёжны — поллинг вместо события (см. CLAUDE.md)
        var lastVal = range.value;
        setInterval(function(){
            if (range.value !== lastVal) {
                lastVal = range.value;
                render(parseInt(lastVal, 10));
            }
        }, 200);

        submit.addEventListener('click', function(){
            submit.disabled = true;
            status.textContent = '';
            jQuery.ajax({
                url: url,
                method: 'POST',
                xhrFields: { withCredentials: true },
                dataType: 'json',
                data: {
                    score: range.value,
                    comment: comment.value,
                    _token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                }
            }).done(function(){
                status.style.color = '#4caf50';
                status.textContent = @json(__('trainers.rate_submit_ok'));
                openBtn.textContent = @json(__('trainers.rate_edit_btn'));
                setTimeout(function(){
                    if (typeof jQuery.fancybox !== 'undefined') jQuery.fancybox.close();
                }, 900);
            }).fail(function(xhr){
                status.style.color = '#e53e3e';
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || @json(__('trainers.rate_submit_error'));
                status.textContent = msg;
            }).always(function(){
                submit.disabled = false;
            });
        });
    }

    function boot(){
        if (typeof jQuery === 'undefined') { return setTimeout(boot, 150); }
        document.querySelectorAll('.tr-rate-widget').forEach(bootOne);
    }
    boot();
})();
</script>
