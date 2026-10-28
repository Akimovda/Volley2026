{{-- Шаблон табло «Вкладки» (theme.template = tabs): стили. Шрифт Oswald — локальный (/assets/fonts), внешних запросов нет. --}}
@font-face{font-family:"Oswald";font-weight:200 700;font-display:block;src:url(/assets/fonts/oswald-cyrillic.woff2) format("woff2");unicode-range:U+0301,U+0400-045F,U+0490-0491,U+04B0-04B1,U+2116}
@font-face{font-family:"Oswald";font-weight:200 700;font-display:block;src:url(/assets/fonts/oswald-latin.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
#tabs{--u:calc(var(--u0)*var(--k,.7));width:max-content;font-family:"Oswald","Commissioner",system-ui,sans-serif;color:var(--tb-fg,#fff)}
#tabs.tl{left:calc(var(--u0)*var(--edge));top:calc(var(--u0)*var(--edge))}
#tabs.tr{right:calc(var(--u0)*var(--edge));top:calc(var(--u0)*var(--edge))}
#tabs.bl{left:calc(var(--u0)*var(--edge));bottom:calc(var(--u0)*var(--edge))}
#tabs.br{right:calc(var(--u0)*var(--edge));bottom:calc(var(--u0)*var(--edge))}
/* вкладки не влияют на ширину блока: ширину задаёт табло, вкладки растягиваются по ней */
.tb-tabs{display:flex;align-items:flex-end;width:0;min-width:100%;gap:calc(var(--u)*4)}
.tb-t1,.tb-t2{height:calc(var(--u)*46);line-height:calc(var(--u)*46);padding:0 calc(var(--u)*24);font-size:calc(var(--u)*28);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-radius:calc(var(--u)*10) calc(var(--u)*10) 0 0;letter-spacing:.02em}
.tb-t1.sq{max-width:none;padding:0 calc(var(--u)*12);width:calc(var(--u)*211);box-sizing:border-box}
.tb-t1{flex:none;max-width:45%;background:var(--tb-t1-bg,#FFB171);color:var(--tb-t1-fg,#4A1B0C)}
.tb-t2{flex:1;min-width:0;background:var(--tb-t2-bg,linear-gradient(90deg,#E7612F,#2967BA));color:var(--tb-t2-fg,#fff)}
.tb-main{display:flex;overflow:hidden;border-radius:0 calc(var(--u)*10) calc(var(--u)*10) calc(var(--u)*10)}
/* логотип — квадрат со стороной в высоту двух строк команд (104+107) */
.tb-logo{flex:none;width:calc(var(--u)*211);height:calc(var(--u)*211);display:flex;align-items:center;justify-content:center;background:#000;overflow:hidden}
.tb-logo img{width:calc(var(--u)*160);max-height:calc(var(--u)*160);object-fit:contain}
.tb-logo.cv img{width:100%;height:100%;max-height:none;object-fit:cover;object-position:center}
.tb-grid{display:grid}
.tb-grid>div{display:flex;align-items:center;height:calc(var(--u)*104);min-width:0}
.tb-grid>.r2{border-top:calc(var(--u)*3) solid var(--tb-line,#E9EBEF);height:calc(var(--u)*107)}
.tb-n{background:var(--tb-n-bg,rgba(0,0,0,var(--pa,.85)))}
.tb-bar{flex:none;width:calc(var(--u)*6);align-self:stretch}
.tb-lg{flex:none;width:calc(var(--u)*74);height:calc(var(--u)*74);margin:0 calc(var(--u)*8) 0 calc(var(--u)*14);border-radius:50%;object-fit:cover}
.tb-nm{flex:1;min-width:0;padding:0 calc(var(--u)*8) 0 calc(var(--u)*18)}
.tb-fit{display:block;width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-transform:uppercase;font-weight:600;line-height:1.12;font-size:calc(var(--u)*48)}
.tb-fit.two{font-size:calc(var(--u)*34);font-weight:500;line-height:1.14}
.tb-sv{flex:none;width:calc(var(--u)*46);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.tb-sv.on{opacity:1}
.tb-sv i{display:block;width:0;height:0;border-style:solid;border-width:calc(var(--u)*16) calc(var(--u)*26) calc(var(--u)*16) 0;border-color:transparent var(--tb-t1-bg,#FFB171) transparent transparent}
.tb-p{justify-content:center;background:var(--tb-p-bg,#F2F3F5);color:var(--tb-p-fg,#0B0D12);font-size:calc(var(--u)*72);font-weight:600;font-variant-numeric:tabular-nums}
.tb-p span{display:block}
.tb-p span.bump{animation:bump .3s ease}
.tb-s{justify-content:center;background:var(--tb-s-bg,rgba(92,96,104,var(--pa,.85)));border-left:calc(var(--u)*3) solid var(--tb-sbd,#000);font-size:calc(var(--u)*40);font-weight:500;color:var(--tb-s-fg,rgba(255,255,255,.62));font-variant-numeric:tabular-nums}
.tb-s.win{color:var(--tb-s-win,#fff);font-weight:600}
.tb-w{justify-content:center;background:var(--tb-w-bg,#14306B);color:var(--tb-w-fg,inherit);border-left:calc(var(--u)*6) solid var(--tb-line,#E9EBEF);font-size:calc(var(--u)*52);font-weight:600;font-variant-numeric:tabular-nums}
/* скины AVP: структура табло не меняется, меняются только цвета (переменные --tb-*) */
#tabs.sk-night{--tb-n-bg:rgba(8,10,18,var(--pa,.85));--tb-p-bg:#1B2238;--tb-p-fg:#fff;--tb-s-bg:rgba(30,38,60,var(--pa,.85));--tb-s-fg:rgba(255,255,255,.55);--tb-sbd:#0B0D12;--tb-w-bg:#E7612F;--tb-w-fg:#fff;--tb-line:#0B0D12;--tb-t1-bg:#E7612F;--tb-t1-fg:#fff;--tb-t2-bg:linear-gradient(90deg,#1B2238,#2B3A66)}
#tabs.sk-light{--tb-fg:#0B0D12;--tb-n-bg:rgba(255,255,255,var(--pa,.92));--tb-p-bg:#14306B;--tb-p-fg:#fff;--tb-s-bg:rgba(226,230,240,var(--pa,.92));--tb-s-fg:#4C5566;--tb-s-win:#0B0D12;--tb-sbd:#C8CDD8;--tb-w-bg:#E7612F;--tb-w-fg:#fff;--tb-line:#C8CDD8;--tb-t1-bg:#14306B;--tb-t1-fg:#fff;--tb-t2-bg:linear-gradient(90deg,#E7612F,#FF9A4A)}
#tabs.sk-forest{--tb-n-bg:rgba(6,32,20,var(--pa,.88));--tb-p-bg:#E9F7EE;--tb-p-fg:#06371F;--tb-s-bg:rgba(23,80,52,var(--pa,.88));--tb-s-fg:rgba(233,247,238,.65);--tb-sbd:#04200F;--tb-w-bg:#0F7A45;--tb-w-fg:#fff;--tb-line:#CFEFD9;--tb-t1-bg:#A7F0C3;--tb-t1-fg:#06371F;--tb-t2-bg:linear-gradient(90deg,#0F7A45,#2BB673)}
