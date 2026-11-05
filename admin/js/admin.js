document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.icd-form-grid input[type="tel"],.icd-form-row input[type="tel"]').forEach(el=>el.setAttribute('inputmode','tel'));

  const modal=document.getElementById('icd-school-calendar-modal');
  if(!modal || typeof ICDVisitasAdmin==='undefined') return;

  const grid=document.getElementById('icd-calendar-grid');
  const details=document.getElementById('icd-calendar-details');
  const schoolLabel=document.getElementById('icd-calendar-school');
  const monthLabel=document.getElementById('icd-calendar-month');
  let schoolId=0;
  let month='';
  let payload=null;

  const esc=(value)=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const shiftMonth=(ym,delta)=>{
    const [y,m]=ym.split('-').map(Number);
    const d=new Date(y,m-1+delta,1);
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
  };
  const close=()=>{ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('icd-modal-open'); };
  const open=()=>{ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('icd-modal-open'); };

  const renderDetails=(day)=>{
    const visits=(payload && payload.visits && payload.visits[day]) || [];
    if(!visits.length){ details.innerHTML='<p class="icd-empty">Nenhuma visita neste dia.</p>'; return; }
    details.innerHTML=`<h3>Visitas de ${String(day).padStart(2,'0')}/${payload.month.slice(5,7)}/${payload.month.slice(0,4)}</h3>`+visits.map(v=>`<article class="icd-calendar-visit"><div class="icd-calendar-visit-top"><strong>${esc(v.turno)}</strong><span class="icd-status icd-status-${esc(v.status_key)}">${esc(v.status)}</span></div><p><b>Pedagoga:</b> ${esc(v.pedagogas)}</p><p><b>Mediadores:</b> ${esc(v.mediadores)}</p>${v.observacoes?`<p><b>Observações:</b> ${esc(v.observacoes)}</p>`:''}<a class="button button-small" href="${esc(v.edit_url)}">Editar visita</a></article>`).join('');
  };

  const renderCalendar=()=>{
    grid.innerHTML='';
    for(let i=0;i<payload.firstWeekday;i++) grid.insertAdjacentHTML('beforeend','<span class="icd-calendar-day is-empty"></span>');
    for(let d=1;d<=payload.daysInMonth;d++){
      const has=payload.visits && payload.visits[d] && payload.visits[d].length;
      const count=has?payload.visits[d].length:0;
      grid.insertAdjacentHTML('beforeend',`<button type="button" class="icd-calendar-day ${has?'has-visit':''}" data-day="${d}" ${has?'':'disabled'}><span>${d}</span>${has?`<small>${count} ${count===1?'visita':'visitas'}</small>`:''}</button>`);
    }
    grid.querySelectorAll('.has-visit').forEach(btn=>btn.addEventListener('click',()=>{
      grid.querySelectorAll('.icd-calendar-day').forEach(x=>x.classList.remove('is-selected'));
      btn.classList.add('is-selected');
      renderDetails(btn.dataset.day);
    }));
    details.innerHTML='<p class="icd-empty">Clique em um dia verde para ver as visitas.</p>';
  };

  const loadCalendar=async(requestedMonth='')=>{
    grid.innerHTML='<div class="icd-calendar-loading">Carregando calendário...</div>';
    details.innerHTML='';
    const url=new URL(ICDVisitasAdmin.ajaxurl);
    url.searchParams.set('action','icd_school_calendar');
    url.searchParams.set('nonce',ICDVisitasAdmin.calendarNonce);
    url.searchParams.set('school_id',schoolId);
    if(requestedMonth) url.searchParams.set('month',requestedMonth);
    try{
      const res=await fetch(url.toString(),{credentials:'same-origin'});
      const json=await res.json();
      if(!json.success) throw new Error(json.data && json.data.message ? json.data.message : 'Falha ao carregar calendário.');
      payload=json.data; month=payload.month;
      schoolLabel.textContent=payload.school.name;
      monthLabel.textContent=payload.label;
      renderCalendar();
    }catch(err){ grid.innerHTML=`<p class="icd-error">${esc(err.message)}</p>`; }
  };

  document.querySelectorAll('.icd-school-calendar-open').forEach(btn=>btn.addEventListener('click',()=>{
    schoolId=Number(btn.dataset.schoolId||0); month=''; open(); loadCalendar('');
  }));
  document.querySelectorAll('[data-icd-close-modal]').forEach(el=>el.addEventListener('click',close));
  document.getElementById('icd-calendar-prev')?.addEventListener('click',()=>loadCalendar(shiftMonth(month,-1)));
  document.getElementById('icd-calendar-next')?.addEventListener('click',()=>loadCalendar(shiftMonth(month,1)));
  document.addEventListener('keydown',e=>{ if(e.key==='Escape' && modal.classList.contains('is-open')) close(); });
});

// 0.8.0 - Importador inteligente: feedback de envio e amostras do mapeamento.
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.icd-import-submit-lock').forEach(form=>form.addEventListener('submit',()=>{
    form.classList.add('is-submitting');
    const btn=form.querySelector('button[type="submit"],button:not([type])');
    if(btn){ btn.disabled=true; btn.dataset.originalText=btn.textContent; btn.textContent='Processando…'; }
  }));
  document.querySelectorAll('.icd-mapping-row select').forEach(select=>select.addEventListener('change',()=>{
    const sample=select.closest('.icd-mapping-row')?.querySelector('.icd-map-samples');
    if(!sample)return;
    const opt=select.options[select.selectedIndex]; sample.textContent=opt && opt.value ? (opt.dataset.samples||'—') : '—';
  }));
});
