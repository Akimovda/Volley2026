{{-- Стили страницы расширенной статистики матча (префикс mx-). Подключается и в страницу, и в PDF. --}}
<style>
.mx-page { --mx-home:#2967BA; --mx-away:#E7612F; --mx-err:#ef4444; --mx-sticky:#fff; --mx-line:rgba(0,0,0,.08); }
body.dark .mx-page { --mx-sticky:#262738; --mx-line:rgba(255,255,255,.1); }
.mx-meta { display:flex; flex-wrap:wrap; gap:.4rem 1.6rem; font-size:1.4rem; opacity:.75; margin-bottom:1rem; }
.mx-meta b { font-weight:700; }
.mx-live { display:inline-block; padding:.2rem .8rem; border-radius:.8rem; background:var(--mx-err); color:#fff; font-size:1.2rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
.mx-head { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:1.2rem; margin:1rem 0 1.4rem; }
.mx-head-team { font-size:2rem; font-weight:800; line-height:1.2; overflow-wrap:anywhere; }
.mx-head-team--home { color:var(--mx-home); text-align:right; }
.mx-head-team--away { color:var(--mx-away); text-align:left; }
.mx-head-team.is-winner { text-decoration:underline; text-decoration-thickness:.3rem; text-underline-offset:.5rem; }
.mx-actions svg { width:1.8rem; height:1.8rem; flex:0 0 auto; }
.mx-head-score { text-align:center; }
.mx-head-score-main { font-size:3.6rem; font-weight:800; line-height:1; }
.mx-head-score-sets { font-size:1.4rem; opacity:.7; margin-top:.4rem; white-space:nowrap; }
.mx-actions { display:flex; flex-wrap:wrap; gap:.8rem; }
.mx-actions .btn { display:inline-flex; align-items:center; gap:.6rem; }
.mx-tabs { display:flex; gap:.4rem; overflow-x:auto; -webkit-overflow-scrolling:touch; border-bottom:1px solid var(--mx-line); margin:0 0 1.4rem; scrollbar-width:none; }
.mx-tabs::-webkit-scrollbar { display:none; }
.mx-tab { flex:0 0 auto; background:none; border:0; border-bottom:3px solid transparent; padding:1rem 1.4rem; font:inherit; font-size:1.5rem; font-weight:700; color:inherit; opacity:.6; cursor:pointer; white-space:nowrap; }
.mx-tab.is-active { opacity:1; border-bottom-color:var(--mx-home); color:var(--mx-home); }
body.dark .mx-tab.is-active { color:#FFB171; border-bottom-color:#FFB171; }
.mx-pane { display:none; }
.mx-pane.is-active { display:block; }
.mx-sub { font-size:1.3rem; opacity:.65; margin:.2rem 0 1rem; }
.mx-seg { display:flex; flex-wrap:wrap; gap:.6rem; margin:.4rem 0 1rem; }
.mx-seg button { border:1px solid var(--mx-line); background:none; color:inherit; border-radius:2rem; padding:.5rem 1.4rem; font:inherit; font-size:1.4rem; font-weight:700; cursor:pointer; opacity:.75; }
.mx-seg button.is-active { background:var(--mx-home); border-color:var(--mx-home); color:#fff; opacity:1; }
.mx-chart-wrap { display:none; }
.mx-chart-wrap.is-active, .mx-print .mx-chart-wrap { display:block; }
.mx-chart { width:100%; max-width:68rem; height:auto; display:block; margin:0 auto; }
.mx-chart text { font-size:12px; fill:currentColor; opacity:.65; }
.mx-chart .mx-lead-txt { opacity:1; font-weight:700; font-size:13px; }
.mx-caption { font-size:1.4rem; margin-top:.6rem; }
.mx-caption div { margin-top:.2rem; }
.mx-legend { display:flex; flex-wrap:wrap; gap:.4rem 1.4rem; font-size:1.3rem; margin-top:.4rem; }
.mx-dot { display:inline-block; width:1rem; height:1rem; border-radius:50%; margin-right:.5rem; vertical-align:middle; }
.mx-dot--home { background:var(--mx-home); } .mx-dot--away { background:var(--mx-away); }
.mx-sbar-row { margin:1.1rem 0; }
.mx-sbar-title { display:flex; justify-content:space-between; font-size:1.4rem; font-weight:700; margin-bottom:.4rem; }
.mx-sbar-line { display:grid; grid-template-columns:5.2rem 1fr; align-items:center; gap:.8rem; margin:.3rem 0; font-size:1.4rem; }
.mx-sbar-line small { opacity:.65; font-size:1.2rem; margin-left:.4rem; }
.mx-sbar-track { height:1rem; border-radius:.6rem; background:rgba(0,0,0,.06); overflow:hidden; }
body.dark .mx-sbar-track { background:rgba(255,255,255,.1); }
.mx-sbar-fill { height:100%; border-radius:.6rem; min-width:2px; }
.mx-sbar-fill--home { background:var(--mx-home); } .mx-sbar-fill--away { background:var(--mx-away); }
.mx-sbar-val { font-weight:800; text-align:right; }
.mx-note { font-size:1.25rem; opacity:.65; margin-top:.8rem; }
.mx-note--warn { opacity:.85; }
.mx-origin-sub .ms-bar-label { font-weight:500; text-transform:none; opacity:.55; font-size:1.25rem; }
.mx-origin-sub .ms-bar-value { font-size:1.3rem; opacity:.8; }
.mx-team-title { font-weight:800; font-size:1.7rem; margin:1.4rem 0 .6rem; }
.mx-team-title--home { color:var(--mx-home); } .mx-team-title--away { color:var(--mx-away); }
.mx-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.mx-table { border-collapse:separate; border-spacing:0; width:100%; font-size:1.45rem; min-width:56rem; }
.mx-table th { font-size:1.15rem; text-transform:uppercase; opacity:.55; font-weight:700; padding:.6rem .8rem; text-align:center; border-bottom:1px solid var(--mx-line); white-space:nowrap; }
.mx-table td { padding:.7rem .8rem; text-align:center; border-bottom:1px solid var(--mx-line); }
.mx-table th:first-child, .mx-table td:first-child { position:sticky; left:0; z-index:1; background:var(--mx-sticky); text-align:left; min-width:15rem; max-width:20rem; box-shadow:1px 0 0 var(--mx-line); }
.mx-table td:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mx-table .is-err { color:var(--mx-err); font-weight:700; }
.mx-table .is-zero { opacity:.35; }
.mx-table .mx-bal { font-weight:800; }
.mx-table .mx-bal.is-neg { color:var(--mx-err); }
.mx-table th.mx-sort { cursor:pointer; opacity:.85; }
.mx-table tr.mx-muted td { opacity:.45; }
.mx-table tr.mx-row--hl td { background:rgba(41,103,186,.14); }
.mx-table tr.mx-row--hl td:first-child { background:var(--mx-sticky); box-shadow:inset 4px 0 0 var(--mx-home), 1px 0 0 var(--mx-line); }
.mx-filters { display:flex; flex-wrap:wrap; gap:.8rem; margin-bottom:1.2rem; }
.mx-filters select { min-width:16rem; }
.mx-set-h { font-weight:800; font-size:1.6rem; margin:1.6rem 0 .6rem; }
.mx-ev { display:grid; grid-template-columns:3.4rem 6.4rem 1fr; gap:.8rem; align-items:baseline; padding:.55rem 0; border-bottom:1px solid var(--mx-line); font-size:1.4rem; }
.mx-ev-n { opacity:.45; font-size:1.2rem; text-align:right; }
.mx-ev-score { font-weight:800; white-space:nowrap; }
.mx-ev-score .w { color:var(--mx-home); } .mx-ev--away .mx-ev-score .w { color:var(--mx-away); }
.mx-ev-txt .t { font-weight:700; }
.mx-ev-txt .t--home { color:var(--mx-home); } .mx-ev-txt .t--away { color:var(--mx-away); }
.mx-ev-txt .err { color:var(--mx-err); }
.mx-ev-by { opacity:.5; font-size:1.2rem; margin-left:.6rem; }
.mx-ev--lineup { background:rgba(0,0,0,.03); }
body.dark .mx-ev--lineup { background:rgba(255,255,255,.04); }
.mx-ev.is-hidden, .mx-set-h.is-hidden { display:none; }
.mx-ev mark, .mx-hl { background:rgba(41,103,186,.16); border-radius:.3rem; }
.mx-lu-grid { display:grid; grid-template-columns:1fr 1fr; gap:2rem; }
.mx-lu-team h4 { font-size:1.5rem; margin:.4rem 0; }
.mx-lu-list { list-style:none; margin:0 0 1rem; padding:0; font-size:1.4rem; }
.mx-lu-list li { padding:.3rem 0; border-bottom:1px solid var(--mx-line); }
.mx-lu-list .z { display:inline-block; min-width:5.6rem; opacity:.55; font-size:1.2rem; }
.mx-empty { text-align:center; opacity:.55; padding:2rem 0; font-size:1.4rem; }
@media (max-width:768px) {
  .mx-head { grid-template-columns:1fr; text-align:center; gap:.4rem; }
  .mx-head-team--home, .mx-head-team--away { text-align:center; }
  .mx-head-team { font-size:1.7rem; }
  .mx-lu-grid { grid-template-columns:1fr; }
  .mx-ev { grid-template-columns:2.6rem 5.6rem 1fr; }
  .mx-table { min-width:48rem; }
}
</style>
