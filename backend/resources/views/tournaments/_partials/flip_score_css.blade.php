{{-- Стили табло-счёта (перекидные плитки) и шапки матча: общие для ввода очков (rally_screen) и ввода счёта по сетам (score). Включать один раз на страницу. --}}
<style>
/* счёт в шапке матча — плитки как у перекидных часов (две цифры на команду, переворот при изменении) */
.rw-flipscore{display:inline-flex;align-items:center;justify-content:center;gap:.22em;font-size:clamp(28px,9vw,40px);line-height:1;white-space:nowrap}
.rw-flipgroup{display:inline-flex;gap:.07em}
.rw-flipscore--mini{font-size:24px;gap:.16em}
.rw-flipscore--mini .rw-fd{box-shadow:0 1px 3px rgba(10,16,40,.35)}
.rw-fcolon{font-weight:800;opacity:.5;font-size:.6em;line-height:1;padding-bottom:.08em}
.rw-fd{position:relative;display:inline-block;width:.66em;height:1em;perspective:7em;color:#fff;font-weight:800;font-variant-numeric:tabular-nums;border-radius:.13em;box-shadow:0 .05em .16em rgba(10,16,40,.4)}
.rw-fd i{font-style:normal;position:absolute;left:0;right:0;height:1em;line-height:1em;text-align:center}
.rw-fd-t,.rw-fd-b,.rw-fd-ft,.rw-fd-fb{position:absolute;left:0;right:0;height:50%;overflow:hidden;background:linear-gradient(#323655,#222540);backface-visibility:hidden}
.rw-fd-t,.rw-fd-ft{top:0;border-radius:.13em .13em 0 0}
.rw-fd-b,.rw-fd-fb{bottom:0;border-radius:0 0 .13em .13em;background:linear-gradient(#1d2036,#14162a)}
.rw-fd-t i,.rw-fd-ft i{top:0}
.rw-fd-b i,.rw-fd-fb i{top:-100%}
/* табло: левая (первая) команда — красная, правая — синяя, как на реальном счётчике; нижняя половина плитки темнее */
.rw-flipgroup[data-side=home] .rw-fd-t,.rw-flipgroup[data-side=home] .rw-fd-ft{background:linear-gradient(#e64a4a,#c92d2d)}
.rw-flipgroup[data-side=home] .rw-fd-b,.rw-flipgroup[data-side=home] .rw-fd-fb{background:linear-gradient(#b32424,#8e1a1a)}
.rw-flipgroup[data-side=away] .rw-fd-t,.rw-flipgroup[data-side=away] .rw-fd-ft{background:linear-gradient(#3f86e8,#2368c8)}
.rw-flipgroup[data-side=away] .rw-fd-b,.rw-flipgroup[data-side=away] .rw-fd-fb{background:linear-gradient(#1b57ad,#133f82)}
.rw-fd::after{content:'';position:absolute;left:0;right:0;top:50%;height:2px;margin-top:-1px;background:rgba(0,0,0,.55);z-index:6}
.rw-fd-ft{transform-origin:50% 100%;z-index:4;animation:rw-flip-top .26s ease-in forwards}
.rw-fd-fb{transform-origin:50% 0;transform:rotateX(90deg);z-index:3;animation:rw-flip-bot .26s .26s ease-out forwards}
@keyframes rw-flip-top{to{transform:rotateX(-90deg)}}
@keyframes rw-flip-bot{to{transform:rotateX(0)}}
@media (prefers-reduced-motion:reduce){.rw-fd-ft,.rw-fd-fb{animation-duration:.01s;animation-delay:0s}}

/* названия команд — в цветах сторон; на узком экране — вторым рядом под табло (симметрично), табло крупнее */
.rw-hteam--home .b-700,.rw-hteam--home a{color:#c92d2d}
.rw-hteam--away .b-700,.rw-hteam--away a{color:#2368c8}
@media(max-width:560px){
	.rw-headrow{display:grid !important;grid-template-columns:1fr 1fr;grid-template-areas:"s s" "h a";row-gap:12px;align-items:start}
	.rw-headrow .rw-flipscore{font-size:clamp(40px,14vw,58px)}
	.rw-headrow>.rw-flipscore,.rw-headrow>.rw-scorebox{grid-area:s;justify-self:center}
	.rw-hteam{min-width:0;text-align:center}
	.rw-hteam--home{grid-area:h}.rw-hteam--away{grid-area:a}
}
</style>
