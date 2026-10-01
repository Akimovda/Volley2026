<style>
	.gm-podium { display:flex; gap:.6rem; align-items:flex-end; justify-content:center; }
	.gm-podium-item { flex:1 1 0; max-width:22rem; min-width:0; display:flex; flex-direction:column; align-items:center; text-align:center; }
	.gm-podium-1 { order:2; } .gm-podium-2 { order:1; } .gm-podium-3 { order:3; }
	.gm-podium-top { padding:0 .4rem 1rem; width:100%; }
	.gm-step { width:100%; display:flex; align-items:flex-start; justify-content:center; padding-top:.8rem; font-size:3.4rem; font-weight:800; color:#fff; border-radius:1rem 1rem 0 0; }
	.gm-podium-1 .gm-step { height:11rem; background:linear-gradient(180deg,#f2b83a,#d9930f); }
	.gm-podium-2 .gm-step { height:8rem; background:linear-gradient(180deg,#b7bec9,#8d96a3); }
	.gm-podium-3 .gm-step { height:6rem; background:linear-gradient(180deg,#d9955a,#b06a2f); }
	.gm-scorers { display:flex; flex-direction:column; gap:.6rem; }
	.gm-scorer { display:flex; align-items:center; gap:1.2rem; padding:.7rem 1.2rem; border-radius:1.2rem; background:rgba(41,103,186,.07); }
	.gm-scorer-n { width:3.4rem; height:3.4rem; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.6rem; color:#fff; background:#7c8aa5; }
	.gm-scorer-1 .gm-scorer-n { background:#e0a021; } .gm-scorer-1 { background:rgba(245,158,11,.15); }
	.gm-scorer-2 .gm-scorer-n { background:#8d96a3; } .gm-scorer-2 { background:rgba(141,150,163,.18); }
	.gm-scorer-3 .gm-scorer-n { background:#b06a2f; } .gm-scorer-3 { background:rgba(176,106,47,.15); }
	.gm-scorer img { width:3.6rem; height:3.6rem; border-radius:50%; object-fit:cover; flex-shrink:0; }
	.gm-scorer-pts { margin-left:auto; font-weight:700; white-space:nowrap; }
	.gm-medal { font-size:2.4rem; line-height:1; margin-bottom:.4rem; }
	.gm-ava { width:5.2rem; height:5.2rem; border-radius:50%; object-fit:cover; margin-bottom:.5rem; }
	.gm-team-tag { display:inline-block; padding:.2rem .9rem; border-radius:2rem; font-size:1.3rem; font-weight:600; color:#fff; }
	.gm-c-red{background:#d64545}.gm-c-blue{background:#2f6fd6}.gm-c-green{background:#2e9e5b}
	.gm-c-yellow{background:#d9a100}.gm-c-white{background:#9aa3b2}.gm-c-black{background:#2b2d33}
	.gm-match-score { font-size:2.4rem; font-weight:700; line-height:1.1; }
</style>
