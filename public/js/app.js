document.addEventListener('DOMContentLoaded',()=>{
  const pkCfg=(typeof ICDVisitasPublic!=='undefined')?ICDVisitasPublic:null;
  const pkStatus=document.getElementById('icd-login-status');
  const pkSecurityStatus=document.getElementById('icd-passkey-status');
  const pkLoginBtn=document.getElementById('icd-passkey-login');
  const pkLoginNote=document.getElementById('icd-webauthn-note');
  const pkPasswordForm=document.getElementById('icd-password-login-form');
  const pkOnboarding=document.getElementById('icd-passkey-onboarding');
  const pkOnboardingActivate=document.getElementById('icd-passkey-onboarding-activate');
  const pkOnboardingStatus=document.getElementById('icd-passkey-onboarding-status');
  const pkDetectedDevice=document.getElementById('icd-passkey-detected-device');
  const profilePasskeyActivate=document.getElementById('icd-profile-passkey-activate');
  const profilePasskeyConfirm=document.getElementById('icd-passkey-confirm');
  const profilePasskeyConfirmPassword=document.getElementById('icd-passkey-confirm-password');
  const profilePasskeyConfirmActivate=document.getElementById('icd-passkey-confirm-activate');
  const profileForm=document.getElementById('icd-profile-form');
  const profileStatus=document.getElementById('icd-profile-status');
  const changePasswordForm=document.getElementById('icd-change-password-form');
  const changePasswordStatus=document.getElementById('icd-change-password-status');
  const forgotOpen=document.getElementById('icd-forgot-open');
  const forgotClose=document.getElementById('icd-forgot-close');
  const forgotForm=document.getElementById('icd-forgot-password-form');
  const resetForm=document.getElementById('icd-password-reset-form');
  const webauthnAvailable=!!(window.PublicKeyCredential&&navigator.credentials&&window.isSecureContext);

  const b64urlToBytes=(value)=>{let s=String(value||'').replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';const raw=atob(s);const out=new Uint8Array(raw.length);for(let i=0;i<raw.length;i++)out[i]=raw.charCodeAt(i);return out;};
  const bytesToB64url=(buffer)=>{const bytes=new Uint8Array(buffer);let raw='';for(let i=0;i<bytes.byteLength;i++)raw+=String.fromCharCode(bytes[i]);return btoa(raw).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');};
  const uiMessage=(el,msg,type='info')=>{if(!el)return;el.hidden=false;el.className=`icd-login-status is-${type}`;el.textContent=msg;};
  const api=async(path,body={},logged=false)=>{
    if(!pkCfg)throw new Error('Configuração do aplicativo indisponível.');
    const headers={'Content-Type':'application/json'};
    if(logged&&pkCfg.restNonce)headers['X-WP-Nonce']=pkCfg.restNonce;
    const base=new URL(pkCfg.restUrl,window.location.origin);let endpoint;
    if(base.searchParams.has('rest_route')){const r=(base.searchParams.get('rest_route')||'').replace(/\/$/,'')+'/'+path;base.searchParams.set('rest_route',r);endpoint=base.toString();}
    else endpoint=pkCfg.restUrl.replace(/\/?$/,'/')+path;
    const res=await fetch(endpoint,{method:'POST',headers,credentials:'same-origin',body:JSON.stringify(body)});
    let json={};try{json=await res.json();}catch(e){}
    if(!res.ok){const err=new Error(json.message||'Não foi possível concluir a operação.');err.status=res.status;throw err;}
    return json;
  };
  const deviceLabel=()=>{
    const ua=navigator.userAgent||'';
    if(/iPhone/i.test(ua))return 'iPhone';
    if(/iPad/i.test(ua))return 'iPad';
    if(/Android/i.test(ua)){
      const match=ua.match(/Android[^;]*;\s*([^;\)]+?)(?:\s+Build\/|;|\))/i);
      if(match&&match[1]){const model=match[1].replace(/^wv\s*/i,'').trim();if(model&&!/^K$/i.test(model))return model+' (Android)';}
      return 'Celular Android';
    }
    if(/Windows/i.test(ua))return 'Computador Windows';
    if(/Macintosh|Mac OS X/i.test(ua))return 'Mac';
    return 'Este dispositivo';
  };
  const detectedDeviceName=deviceLabel();
  if(pkDetectedDevice)pkDetectedDevice.textContent=detectedDeviceName;

  const registerCurrentDevice=async(statusEl,button,password='')=>{
    if(!webauthnAvailable)throw new Error(window.isSecureContext?'Este navegador não oferece suporte a Passkeys.':'A biometria exige acesso por HTTPS.');
    if(button)button.disabled=true;uiMessage(statusEl,'Solicitando confirmação no aparelho…','info');
    try{
      const opts=await api('passkeys/register/options',password?{password}:{},true);const po=opts.publicKey;
      po.challenge=b64urlToBytes(po.challenge);po.user.id=b64urlToBytes(po.user.id);
      if(Array.isArray(po.excludeCredentials))po.excludeCredentials=po.excludeCredentials.map(c=>({...c,id:b64urlToBytes(c.id)}));
      const credential=await navigator.credentials.create({publicKey:po});
      if(!credential)throw new Error('Não foi possível criar a Passkey.');
      const r=credential.response;
      if(typeof r.getPublicKey!=='function'||typeof r.getPublicKeyAlgorithm!=='function'||typeof r.getAuthenticatorData!=='function')throw new Error('Seu navegador precisa ser atualizado para cadastrar a biometria com segurança.');
      const publicKey=r.getPublicKey();if(!publicKey)throw new Error('O dispositivo não retornou a chave pública da Passkey.');
      const result=await api('passkeys/register/verify',{
        flow:opts.flow,credentialId:bytesToB64url(credential.rawId),clientDataJSON:bytesToB64url(r.clientDataJSON),
        authenticatorData:bytesToB64url(r.getAuthenticatorData()),publicKey:bytesToB64url(publicKey),algorithm:r.getPublicKeyAlgorithm(),
        transports:typeof r.getTransports==='function'?r.getTransports():[],deviceName:detectedDeviceName
      },true);
      uiMessage(statusEl,result.message||'Biometria ativada.','success');
      try{sessionStorage.removeItem('icdPasskeyPromptDismissed');}catch(e){}
      setTimeout(()=>window.location.reload(),650);
      return result;
    }catch(err){
      const msg=(err&&err.name==='NotAllowedError')?'Cadastro cancelado no aparelho.':(err.message||'Não foi possível cadastrar a Passkey.');
      uiMessage(statusEl,msg,'error');if(button)button.disabled=false;throw err;
    }
  };

  if(pkLoginBtn){
    if(!webauthnAvailable){pkLoginBtn.disabled=true;pkLoginBtn.classList.add('is-disabled');if(pkLoginNote)pkLoginNote.textContent=window.isSecureContext?'Este navegador não oferece suporte ao login por Passkey.':'O login por biometria exige acesso por HTTPS.';}
    else if(pkLoginNote)pkLoginNote.textContent='O aparelho solicitará sua confirmação quando necessário.';
    pkLoginBtn.addEventListener('click',async()=>{
      if(!webauthnAvailable)return;
      pkLoginBtn.disabled=true;uiMessage(pkStatus,'Solicitando confirmação no dispositivo…','info');
      try{
        const opts=await api('auth/passkey/options',{},false);opts.publicKey.challenge=b64urlToBytes(opts.publicKey.challenge);
        const credential=await navigator.credentials.get({publicKey:opts.publicKey});if(!credential)throw new Error('Nenhuma Passkey foi selecionada.');
        const r=credential.response;
        const result=await api('auth/passkey/verify',{flow:opts.flow,credentialId:bytesToB64url(credential.rawId),clientDataJSON:bytesToB64url(r.clientDataJSON),authenticatorData:bytesToB64url(r.authenticatorData),signature:bytesToB64url(r.signature),userHandle:r.userHandle?bytesToB64url(r.userHandle):'',redirect:pkCfg.redirect},false);
        window.location.assign(result.redirect||pkCfg.redirect||'/');
      }catch(err){const msg=(err&&err.name==='NotAllowedError')?'Login cancelado ou nenhuma biometria cadastrada foi encontrada neste aparelho.':(err.message||'Não foi possível usar a biometria.');uiMessage(pkStatus,msg,'error');pkLoginBtn.disabled=false;}
    });
  }

  if(pkPasswordForm){
    pkPasswordForm.addEventListener('submit',async(e)=>{e.preventDefault();const btn=pkPasswordForm.querySelector('button[type="submit"]');if(btn)btn.disabled=true;const fd=new FormData(pkPasswordForm);
      try{const result=await api('auth/password',{username:fd.get('username'),password:fd.get('password'),redirect:pkCfg.redirect},false);uiMessage(pkStatus,'Login realizado.','success');window.location.assign(result.redirect||pkCfg.redirect||'/');}
      catch(err){uiMessage(pkStatus,err.message||'Falha no login.','error');if(btn)btn.disabled=false;}
    });
  }

  if(forgotOpen&&forgotForm&&pkPasswordForm){forgotOpen.addEventListener('click',()=>{pkPasswordForm.hidden=true;pkLoginBtn&&(pkLoginBtn.hidden=true);const div=document.querySelector('.icd-login-divider');if(div)div.hidden=true;if(pkLoginNote)pkLoginNote.hidden=true;forgotOpen.hidden=true;forgotForm.hidden=false;});}
  if(forgotClose&&forgotForm&&pkPasswordForm){forgotClose.addEventListener('click',()=>{forgotForm.hidden=true;pkPasswordForm.hidden=false;pkLoginBtn&&(pkLoginBtn.hidden=false);const div=document.querySelector('.icd-login-divider');if(div)div.hidden=false;if(pkLoginNote)pkLoginNote.hidden=false;forgotOpen.hidden=false;});}
  if(forgotForm){forgotForm.addEventListener('submit',async(e)=>{e.preventDefault();const btn=forgotForm.querySelector('button[type="submit"]');if(btn)btn.disabled=true;const fd=new FormData(forgotForm);try{const r=await api('auth/password/request-reset',{identity:fd.get('identity')},false);uiMessage(pkStatus,r.message||'Confira seu e-mail.','success');forgotForm.reset();}catch(err){uiMessage(pkStatus,err.message||'Não foi possível solicitar a redefinição.','error');}finally{if(btn)btn.disabled=false;}});}
  if(resetForm){resetForm.addEventListener('submit',async(e)=>{e.preventDefault();const btn=resetForm.querySelector('button[type="submit"]');if(btn)btn.disabled=true;const fd=new FormData(resetForm);try{const r=await api('auth/password/reset',{login:fd.get('login'),key:fd.get('key'),newPassword:fd.get('newPassword'),confirmPassword:fd.get('confirmPassword')},false);uiMessage(pkStatus,r.message||'Senha redefinida.','success');setTimeout(()=>window.location.assign(r.redirect||pkCfg.homeUrl),500);}catch(err){uiMessage(pkStatus,err.message||'Não foi possível redefinir a senha.','error');if(btn)btn.disabled=false;}});}

  const openPkOnboarding=()=>{if(!pkOnboarding)return;pkOnboarding.classList.add('is-open');pkOnboarding.setAttribute('aria-hidden','false');document.body.classList.add('icd-front-modal-open');};
  const closePkOnboarding=()=>{if(!pkOnboarding)return;pkOnboarding.classList.remove('is-open');pkOnboarding.setAttribute('aria-hidden','true');document.body.classList.remove('icd-front-modal-open');};
  document.querySelectorAll('[data-icd-passkey-onboarding-close]').forEach(btn=>btn.addEventListener('click',()=>{try{sessionStorage.setItem('icdPasskeyPromptDismissed','1');}catch(e){}closePkOnboarding();}));
  if(pkOnboardingActivate)pkOnboardingActivate.addEventListener('click',()=>registerCurrentDevice(pkOnboardingStatus,pkOnboardingActivate).catch(()=>{}));
  if(pkOnboarding&&pkCfg&&Number(pkCfg.showPasskeyPrompt)===1&&webauthnAvailable){let dismissed=false;try{dismissed=sessionStorage.getItem('icdPasskeyPromptDismissed')==='1';}catch(e){}if(!dismissed)setTimeout(openPkOnboarding,260);}

  if(profilePasskeyActivate){
    if(!webauthnAvailable){profilePasskeyActivate.disabled=true;uiMessage(pkSecurityStatus,window.isSecureContext?'Este navegador não oferece suporte a Passkeys.':'A biometria exige HTTPS.','error');}
    profilePasskeyActivate.addEventListener('click',async()=>{try{await registerCurrentDevice(pkSecurityStatus,profilePasskeyActivate);}catch(err){if(err.status===401&&profilePasskeyConfirm){profilePasskeyConfirm.hidden=false;if(profilePasskeyConfirmPassword)profilePasskeyConfirmPassword.focus();}}});
  }
  if(profilePasskeyConfirmActivate){profilePasskeyConfirmActivate.addEventListener('click',async()=>{const password=profilePasskeyConfirmPassword?profilePasskeyConfirmPassword.value:'';if(!password){uiMessage(pkSecurityStatus,'Confirme sua senha atual.','error');return;}try{await registerCurrentDevice(pkSecurityStatus,profilePasskeyConfirmActivate,password);}catch(e){}});}

  document.querySelectorAll('[data-passkey-delete]').forEach(btn=>btn.addEventListener('click',async()=>{
    const id=btn.dataset.passkeyDelete;if(!id||!confirm('Remover este aparelho do login por biometria?'))return;btn.disabled=true;
    try{await api(`passkeys/${id}/delete`,{},true);const item=btn.closest('.icd-passkey-item');if(item)item.remove();uiMessage(pkSecurityStatus,'Aparelho removido.','success');const list=document.getElementById('icd-passkey-list');if(list&&!list.querySelector('.icd-passkey-item'))list.innerHTML='<p class="icd-empty" data-no-passkeys>Nenhum aparelho cadastrado.</p>';}
    catch(err){uiMessage(pkSecurityStatus,err.message||'Não foi possível remover o aparelho.','error');btn.disabled=false;}
  }));

  if(profileForm){profileForm.addEventListener('submit',async(e)=>{e.preventDefault();const btn=profileForm.querySelector('button[type="submit"]');if(btn)btn.disabled=true;const fd=new FormData(profileForm);try{const r=await api('account/profile',{name:fd.get('name'),email:fd.get('email'),phone:fd.get('phone')},true);uiMessage(profileStatus,r.message||'Dados atualizados.','success');}catch(err){uiMessage(profileStatus,err.message||'Não foi possível atualizar seus dados.','error');}finally{if(btn)btn.disabled=false;}});}
  if(changePasswordForm){changePasswordForm.addEventListener('submit',async(e)=>{e.preventDefault();const btn=changePasswordForm.querySelector('button[type="submit"]');if(btn)btn.disabled=true;const fd=new FormData(changePasswordForm);try{const r=await api('account/password',{currentPassword:fd.get('currentPassword'),newPassword:fd.get('newPassword'),confirmPassword:fd.get('confirmPassword')},true);uiMessage(changePasswordStatus,r.message||'Senha alterada.','success');changePasswordForm.reset();setTimeout(()=>window.location.reload(),700);}catch(err){uiMessage(changePasswordStatus,err.message||'Não foi possível alterar a senha.','error');}finally{if(btn)btn.disabled=false;}});}
  const school=document.getElementById('icd-school');
  if(school){
    const options=[...document.querySelectorAll('.icd-mediator-option')];
    const filter=()=>{const sid=school.value;options.forEach(el=>{const ids=(el.dataset.schools||'').split(',').filter(Boolean);el.style.display=!sid||ids.includes(sid)?'flex':'none';if(el.style.display==='none')el.querySelector('input').checked=false;});};
    school.addEventListener('change',filter);filter();
  }

  const summaryModal=document.getElementById('icd-visit-summary-modal');
  const summaryContent=document.getElementById('icd-visit-summary-content');
  const esc=(value)=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const openSummary=()=>{if(!summaryModal)return;summaryModal.classList.add('is-open');summaryModal.setAttribute('aria-hidden','false');document.body.classList.add('icd-front-modal-open');};
  const closeSummary=()=>{if(!summaryModal)return;summaryModal.classList.remove('is-open');summaryModal.setAttribute('aria-hidden','true');const calendarModal=document.getElementById('icd-front-school-calendar-modal');if(!calendarModal||!calendarModal.classList.contains('is-open'))document.body.classList.remove('icd-front-modal-open');};
  const loadVisitSummary=async(id)=>{
    if(!summaryModal||!summaryContent||typeof ICDVisitasPublic==='undefined'||!id)return;
    summaryContent.innerHTML='<p class="icd-empty">Carregando visita...</p>';openSummary();
    const url=new URL(ICDVisitasPublic.ajaxurl);url.searchParams.set('action','icd_visit_details');url.searchParams.set('nonce',ICDVisitasPublic.visitNonce);url.searchParams.set('visit_id',id);
    try{
      const res=await fetch(url.toString(),{credentials:'same-origin'});const json=await res.json();if(!json.success)throw new Error(json.data&&json.data.message?json.data.message:'Falha ao carregar a visita.');const v=json.data;
      const edit=(Number(ICDVisitasPublic.canManage)===1&&v.front_edit_url)?`<a class="icd-edit-btn" href="${esc(v.front_edit_url)}">Editar visita</a>`:'';
      summaryContent.innerHTML=`<div class="icd-visit-summary-head"><small>Resumo da visita</small><h3 id="icd-visit-summary-title">${esc(v.escola)}</h3><span>${esc(v.data)}</span></div><div class="icd-visit-summary-grid"><div><small>Pedagoga</small><strong>${esc(v.pedagogas)}</strong></div><div><small>Mediadores</small><strong>${esc(v.mediadores)}</strong></div><div><small>Turno</small><strong>${esc(v.turno)}</strong></div><div><small>Status</small><strong>${esc(v.status)}</strong></div></div><div class="icd-visit-summary-notes"><small>Observações</small><p>${esc(v.observacoes)}</p></div>${edit}`;
    }catch(err){summaryContent.innerHTML=`<p class="icd-front-calendar-error">${esc(err.message)}</p>`;}
  };
  document.addEventListener('click',e=>{
    const trigger=e.target.closest('.icd-visit-summary-open');if(!trigger)return;
    if(e.target.closest('a,button,form,input,select,textarea,label'))return;
    e.preventDefault();loadVisitSummary(trigger.dataset.visitId);
  });
  document.addEventListener('keydown',e=>{
    const trigger=e.target.closest&&e.target.closest('.icd-visit-summary-open');if(trigger&&(e.key==='Enter'||e.key===' ')){e.preventDefault();loadVisitSummary(trigger.dataset.visitId);}
  });
  document.querySelectorAll('[data-icd-visit-close]').forEach(el=>el.addEventListener('click',closeSummary));

  const modal=document.getElementById('icd-front-school-calendar-modal');
  if(!modal || typeof ICDVisitasPublic==='undefined') return;
  const grid=document.getElementById('icd-front-calendar-grid');
  const details=document.getElementById('icd-front-calendar-details');
  const schoolLabel=document.getElementById('icd-front-calendar-school');
  const monthLabel=document.getElementById('icd-front-calendar-month');
  let schoolId=0, month='', payload=null;
  const shiftMonth=(ym,delta)=>{const [y,m]=ym.split('-').map(Number);const d=new Date(y,m-1+delta,1);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;};
  const close=()=>{modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('icd-front-modal-open');};
  const open=()=>{modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.classList.add('icd-front-modal-open');};

  const renderDetails=(day)=>{
    const visits=(payload&&payload.visits&&payload.visits[day])||[];
    if(!visits.length){details.innerHTML='<p class="icd-empty">Nenhuma visita neste dia.</p>';return;}
    details.innerHTML=`<h4>Visitas de ${String(day).padStart(2,'0')}/${payload.month.slice(5,7)}/${payload.month.slice(0,4)}</h4>`+visits.map(v=>{
      const edit=(Number(ICDVisitasPublic.canManage)===1&&v.front_edit_url)?`<a class="icd-edit-btn" href="${esc(v.front_edit_url)}">Editar visita</a>`:'';
      return `<article class="icd-front-calendar-visit icd-visit-summary-open" data-visit-id="${v.id}" role="button" tabindex="0"><div class="icd-front-calendar-visit-top"><strong>${esc(v.turno)}</strong><span>${esc(v.status)}</span></div><p><b>Pedagoga:</b> ${esc(v.pedagogas)}</p><p><b>Mediadores:</b> ${esc(v.mediadores)}</p>${v.observacoes?`<p><b>Observações:</b> ${esc(v.observacoes)}</p>`:''}${edit}</article>`;
    }).join('');
  };

  const renderCalendar=()=>{
    grid.innerHTML='';
    for(let i=0;i<payload.firstWeekday;i++) grid.insertAdjacentHTML('beforeend','<span class="icd-front-calendar-day is-empty"></span>');
    for(let d=1;d<=payload.daysInMonth;d++){
      const has=payload.visits&&payload.visits[d]&&payload.visits[d].length;
      const count=has?payload.visits[d].length:0;
      grid.insertAdjacentHTML('beforeend',`<button type="button" class="icd-front-calendar-day ${has?'has-visit':''}" data-day="${d}" ${has?'':'disabled'}><span>${d}</span>${has?`<small>${count}</small>`:''}</button>`);
    }
    grid.querySelectorAll('.has-visit').forEach(btn=>btn.addEventListener('click',()=>{grid.querySelectorAll('.icd-front-calendar-day').forEach(x=>x.classList.remove('is-selected'));btn.classList.add('is-selected');renderDetails(btn.dataset.day);}));
    details.innerHTML='<p class="icd-empty">Toque em um dia verde para ver as visitas.</p>';
  };

  const loadCalendar=async(requestedMonth='')=>{
    grid.innerHTML='<div class="icd-front-calendar-loading">Carregando calendário...</div>';details.innerHTML='';
    const url=new URL(ICDVisitasPublic.ajaxurl);url.searchParams.set('action','icd_school_calendar');url.searchParams.set('nonce',ICDVisitasPublic.calendarNonce);url.searchParams.set('school_id',schoolId);if(requestedMonth)url.searchParams.set('month',requestedMonth);
    try{const res=await fetch(url.toString(),{credentials:'same-origin'});const json=await res.json();if(!json.success)throw new Error(json.data&&json.data.message?json.data.message:'Falha ao carregar calendário.');payload=json.data;month=payload.month;schoolLabel.textContent=payload.school.name;monthLabel.textContent=payload.label;renderCalendar();}
    catch(err){grid.innerHTML=`<p class="icd-front-calendar-error">${esc(err.message)}</p>`;}
  };

  document.querySelectorAll('.icd-front-school-calendar-open').forEach(btn=>btn.addEventListener('click',()=>{schoolId=Number(btn.dataset.schoolId||0);month='';open();loadCalendar('');}));
  document.querySelectorAll('[data-icd-front-close-modal]').forEach(el=>el.addEventListener('click',close));
  document.getElementById('icd-front-calendar-prev')?.addEventListener('click',()=>loadCalendar(shiftMonth(month,-1)));
  document.getElementById('icd-front-calendar-next')?.addEventListener('click',()=>loadCalendar(shiftMonth(month,1)));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(modal.classList.contains('is-open'))close();if(summaryModal&&summaryModal.classList.contains('is-open'))closeSummary();}});
});

// 0.7.3 - geração AJAX robusta, download e compartilhamento de PDF sem abandonar o PWA.
(() => {
  const form=document.getElementById('icd-report-form');
  if(!form) return;
  const generate=document.getElementById('icd-report-generate');
  const status=document.getElementById('icd-report-status');
  const ready=document.getElementById('icd-report-ready');
  const share=document.getElementById('icd-report-share');
  const download=document.getElementById('icd-report-download');
  const view=document.getElementById('icd-report-view');
  let pdfBlob=null,pdfUrl='',pdfFilename='relatorio-visitas.pdf';

  const setStatus=(message,type='loading')=>{
    if(!status)return;
    status.hidden=false;
    status.className='icd-report-status is-'+type;
    status.textContent=message;
  };
  const clearPdf=()=>{
    if(pdfUrl){URL.revokeObjectURL(pdfUrl);pdfUrl='';}
    pdfBlob=null;
    if(ready)ready.hidden=true;
  };
  const base64ToBlob=(base64,type='application/pdf')=>{
    const binary=atob(base64),chunk=8192,parts=[];
    for(let offset=0;offset<binary.length;offset+=chunk){
      const slice=binary.slice(offset,offset+chunk),bytes=new Uint8Array(slice.length);
      for(let i=0;i<slice.length;i++)bytes[i]=slice.charCodeAt(i);
      parts.push(bytes);
    }
    return new Blob(parts,{type});
  };

  form.addEventListener('change',()=>{
    if(pdfBlob){clearPdf();setStatus('Os filtros foram alterados. Gere novamente o PDF.','loading');}
  });

  form.addEventListener('submit',async(e)=>{
    if(!window.fetch||!window.URL||!window.Blob||!window.atob)return;
    e.preventDefault();
    clearPdf();
    if(generate)generate.disabled=true;
    setStatus('Gerando o relatório…','loading');
    if(window.ICDLoading)window.ICDLoading.setMessage('Gerando relatório…');
    try{
      const fd=new FormData(form);
      fd.set('action','icd_generate_visits_pdf_ajax');
      const endpoint=(window.ICDVisitasPublic&&ICDVisitasPublic.ajaxurl)?ICDVisitasPublic.ajaxurl:form.action;
      const response=await fetch(endpoint,{method:'POST',body:fd,credentials:'same-origin',cache:'no-store'});
      const raw=await response.text();
      let payload=null;
      try{payload=JSON.parse(raw);}catch(parseError){
        throw new Error(response.ok?'O servidor retornou uma resposta inesperada ao gerar o PDF.':'O servidor recusou a geração do relatório (HTTP '+response.status+').');
      }
      if(!response.ok||!payload||payload.success!==true){
        const msg=payload&&payload.data&&payload.data.message?payload.data.message:'Não foi possível gerar o relatório.';
        throw new Error(msg);
      }
      const data=payload.data||{};
      if(!data.base64)throw new Error('O servidor não retornou os dados do PDF.');
      pdfBlob=base64ToBlob(data.base64,data.mime||'application/pdf');
      if(!pdfBlob||pdfBlob.size<100)throw new Error('O PDF retornado está vazio.');
      pdfFilename=data.filename||'relatorio-visitas.pdf';
      pdfUrl=URL.createObjectURL(pdfBlob);
      setStatus('PDF gerado com sucesso'+(Number.isFinite(Number(data.count))?' · '+Number(data.count)+' visita(s)':'')+'.','success');
      if(ready){ready.hidden=false;ready.scrollIntoView({behavior:'smooth',block:'nearest'});}
    }catch(err){
      clearPdf();
      setStatus(err&&err.message?err.message:'Não foi possível gerar o relatório.','error');
    }finally{
      if(generate)generate.disabled=false;
      if(window.ICDLoading)window.ICDLoading.setMessage('Processando…');
    }
  });

  if(view)view.addEventListener('click',()=>{
    if(!pdfUrl)return;
    const win=window.open(pdfUrl,'_blank','noopener,noreferrer');
    if(!win)window.location.href=pdfUrl;
  });

  if(download)download.addEventListener('click',()=>{
    if(!pdfUrl)return;
    const a=document.createElement('a');
    a.href=pdfUrl;a.download=pdfFilename;a.rel='noopener';
    document.body.appendChild(a);a.click();a.remove();
    setStatus('Download solicitado. Se o iPhone abrir o PDF em vez de baixar, use “Compartilhar / Salvar” para escolher “Salvar em Arquivos”.','success');
  });

  if(share)share.addEventListener('click',async()=>{
    if(!pdfBlob)return;
    try{
      const file=new File([pdfBlob],pdfFilename,{type:'application/pdf',lastModified:Date.now()});
      if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){
        await navigator.share({title:'Relatório de Visitas',text:'Relatório de visitas - Instituto Caminho de Davi',files:[file]});
        return;
      }
      setStatus('O compartilhamento de arquivos não é suportado neste navegador. Use “Baixar PDF”.','error');
    }catch(err){
      if(err&&err.name==='AbortError')return;
      setStatus('Não foi possível abrir o compartilhamento. Você ainda pode usar “Baixar PDF”.','error');
    }
  });

  window.addEventListener('pagehide',()=>{if(pdfUrl)URL.revokeObjectURL(pdfUrl);},{once:true});
})();

// 0.7.3 - loading global para navegação, formulários e chamadas ao servidor.
(() => {
  let overlay=null,label=null,pending=0,timer=null,message='Processando…';
  const ensure=()=>{
    if(overlay)return overlay;
    overlay=document.createElement('div');
    overlay.className='icd-global-loading';
    overlay.setAttribute('aria-hidden','true');
    overlay.innerHTML='<div class="icd-global-loading-card" role="status" aria-live="polite"><span class="icd-global-spinner" aria-hidden="true"></span><strong>Processando…</strong><small>Aguarde um instante</small></div>';
    document.body.appendChild(overlay);
    label=overlay.querySelector('strong');
    return overlay;
  };
  const activate=()=>{
    ensure();
    if(label)label.textContent=message;
    overlay.classList.add('is-active');
    overlay.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('icd-is-loading');
  };
  const deactivate=()=>{
    if(timer){clearTimeout(timer);timer=null;}
    if(overlay){overlay.classList.remove('is-active');overlay.setAttribute('aria-hidden','true');}
    document.documentElement.classList.remove('icd-is-loading');
  };
  const show=(custom)=>{
    if(custom)message=custom;
    pending++;
    if(pending===1){
      if(timer)clearTimeout(timer);
      timer=setTimeout(()=>{timer=null;if(pending>0)activate();},140);
    }
  };
  const hide=()=>{
    pending=Math.max(0,pending-1);
    if(pending===0)deactivate();
  };
  const force=(custom)=>{if(custom)message=custom;pending=Math.max(1,pending);activate();};
  const reset=()=>{pending=0;message='Processando…';deactivate();};
  const setMessage=(custom)=>{message=custom||'Processando…';if(label&&overlay&&overlay.classList.contains('is-active'))label.textContent=message;};
  window.ICDLoading={show,hide,force,reset,setMessage};

  if(window.fetch){
    const originalFetch=window.fetch.bind(window);
    window.fetch=(...args)=>{
      show();
      let result;
      try{result=originalFetch(...args);}catch(err){hide();throw err;}
      return Promise.resolve(result).finally(hide);
    };
  }

  document.addEventListener('submit',(event)=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement)||form.hasAttribute('data-icd-download'))return;
    // Submits AJAX que chamam preventDefault ficam cobertos pelo wrapper de fetch.
    setTimeout(()=>{if(!event.defaultPrevented)force('Salvando…');},0);
  });

  document.addEventListener('click',(event)=>{
    if(event.defaultPrevented||event.button>0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    const link=event.target.closest&&event.target.closest('a[href]');
    if(!link||link.hasAttribute('download')||link.target==='_blank')return;
    const href=(link.getAttribute('href')||'').trim();
    if(!href||href==='#'||href.startsWith('#')||href.startsWith('javascript:')||href.startsWith('mailto:')||href.startsWith('tel:'))return;
    // Links de navegação do app: mostra imediatamente e bloqueia cliques repetidos.
    force(link.classList.contains('icd-logout-btn')?'Saindo…':'Carregando…');
  },true);

  window.addEventListener('pageshow',reset);
  window.addEventListener('pagehide',()=>{ if(pending>0)activate(); });
})();
