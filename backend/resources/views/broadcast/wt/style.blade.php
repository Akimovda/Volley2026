{{-- Табло «Мировой Тур» (theme.board = wt): одна полоса по центру сверху или снизу. Скины — переменные --wt-*. --}}
#wt{--u:calc(var(--u0)*var(--k,.7));width:calc(var(--u)*1180);margin-left:calc(var(--u)*-590);left:50%;font-family:"Oswald","Commissioner",system-ui,sans-serif;color:var(--wt-fg,#fff);
    --wt-bar:rgba(12,18,34,var(--pa,.9));--wt-pts:linear-gradient(180deg,#F25A2A,#D8361A);--wt-pts-fg:#fff;--wt-frame:#C8281C;--wt-sets:rgba(6,10,22,.92);--wt-sets-fg:#fff}
#wt.top{top:calc(var(--u0)*var(--edge))}
#wt.bottom{bottom:calc(var(--u0)*var(--edge))}
.wt-bar{display:flex;align-items:center;height:calc(var(--u)*112);background:var(--wt-bar);border-radius:calc(var(--u)*8);overflow:hidden;padding:0 calc(var(--u)*10) 0 calc(var(--u)*10)}
.wt-side{flex:1;min-width:0;display:flex;align-items:center;gap:calc(var(--u)*10);height:100%}
.wt-side.away{flex-direction:row-reverse;text-align:right}
.wt-stripe{flex:none;width:calc(var(--u)*16);height:calc(var(--u)*88);border-radius:calc(var(--u)*2)}
.wt-ball{flex:none;width:calc(var(--u)*54);height:calc(var(--u)*54);opacity:0;display:flex;align-items:center;justify-content:center}
.wt-ball.on{opacity:1}
.wt-ball svg{width:100%;height:100%}
.wt-name{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-transform:uppercase;font-weight:600;font-size:calc(var(--u)*68);letter-spacing:.01em;line-height:1}
.wt-name.long{font-size:calc(var(--u)*50)}
.wt-score{flex:none;display:flex;height:calc(var(--u)*88);border:calc(var(--u)*2) solid var(--wt-frame)}
.wt-sets{width:calc(var(--u)*112);display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--wt-sets);color:var(--wt-sets-fg);line-height:1}
.wt-sets small{font-size:calc(var(--u)*27);font-weight:600;letter-spacing:.04em;margin-bottom:calc(var(--u)*2)}
.wt-sets b{font-size:calc(var(--u)*48);font-weight:600;font-variant-numeric:tabular-nums}
.wt-pts{width:calc(var(--u)*150);display:flex;align-items:center;justify-content:center;background:var(--wt-pts);color:var(--wt-pts-fg);font-size:calc(var(--u)*76);font-weight:600;font-variant-numeric:tabular-nums}
.wt-pts span{display:block}
.wt-pts span.bump{animation:bump .3s ease}
.wt-emb{flex:none;width:calc(var(--u)*130);height:calc(var(--u)*88);margin:0 calc(var(--u)*14);display:flex;align-items:center;justify-content:center}
.wt-emb{overflow:hidden;border-radius:calc(var(--u)*6)}
.wt-emb img{max-width:100%;max-height:100%;object-fit:contain}
/* свой логотип (организатор/любая картинка): обрезается по контейнеру, без полей */
.wt-emb.cv img{width:100%;height:100%;max-width:none;max-height:none;object-fit:cover;object-position:center}
/* скины */
#wt.sk-blue{--wt-pts:linear-gradient(180deg,#3E86F5,#1C55CC);--wt-frame:#1E4CA8}
#wt.sk-gold{--wt-pts:linear-gradient(180deg,#F3CE5C,#C99A1B);--wt-pts-fg:#1E1604;--wt-frame:#8A6A10;--wt-bar:rgba(20,16,8,var(--pa,.9))}
#wt.sk-light{--wt-fg:#10141C;--wt-bar:rgba(255,255,255,var(--pa,.94));--wt-pts:linear-gradient(180deg,#27447F,#14306B);--wt-pts-fg:#fff;--wt-frame:#14306B;--wt-sets:rgba(228,232,242,.95);--wt-sets-fg:#10141C}
#wt.sk-light .wt-ball svg,#wt.sk-light .wt-emb:not(.cv) img{filter:invert(1)}
/* плашки «Смена сторон»/метка платформы не должны наезжать на полосу */
body.wt-top #sw{top:calc(var(--u0)*var(--edge) + var(--u0)*var(--k,.7)*130)}
body.wt-bottom #tag{bottom:calc(var(--u0)*var(--edge) + var(--u0)*var(--k,.7)*128)}
