(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  $$('[data-menu-toggle]').forEach((button) => {
    const id = button.getAttribute('aria-controls');
    const nav = id ? document.getElementById(id) : null;
    if (!nav) return;
    button.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(open));
    });
    $$('a', nav).forEach((link) => link.addEventListener('click', () => {
      nav.classList.remove('is-open'); button.setAttribute('aria-expanded', 'false');
    }));
  });

  $$('[data-template-refresh]').forEach((select) => select.addEventListener('change', () => select.form?.requestSubmit()));
  $$('[data-dismiss-flash]').forEach((button) => button.addEventListener('click', () => button.closest('[data-flash]')?.remove()));
  $$('[data-confirm]').forEach((node) => node.addEventListener('submit', (event) => {
    if (!window.confirm(node.dataset.confirm || 'هل تريد المتابعة؟')) event.preventDefault();
  }));

  const copyText = async (value) => {
    const text = value.startsWith('/') ? `${location.origin}${value}` : value;
    try { await navigator.clipboard.writeText(text); }
    catch {
      const box = document.createElement('textarea'); box.value = text; box.style.position = 'fixed'; box.style.opacity = '0'; document.body.append(box); box.select(); document.execCommand('copy'); box.remove();
    }
    return text;
  };
  $$('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    await copyText(button.dataset.copy || ''); const original = button.textContent; button.textContent = 'تم النسخ'; setTimeout(() => { button.textContent = original; }, 1800);
  }));
  $$('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copyTarget || ''); if (!target) return;
    await copyText(target.innerText); const original=button.textContent; button.textContent='تم النسخ'; setTimeout(()=>button.textContent=original,1800);
  }));
  $$('[data-share-page]').forEach((button) => button.addEventListener('click', async () => {
    const shareData={title:document.title,url:location.href};
    if (navigator.share) { try { await navigator.share(shareData); return; } catch {} }
    await copyText(location.href); const original=button.textContent; button.textContent='نُسخ الرابط'; setTimeout(()=>button.textContent=original,1800);
  }));
  const publicNav=document.querySelector('.public-sidebar-nav');
  if(publicNav){
    const links=[...publicNav.querySelectorAll('a[href^="#"]')]; const targets=links.map((link)=>document.querySelector(link.getAttribute('href'))).filter(Boolean);
    const observer=new IntersectionObserver((entries)=>{const visible=entries.filter((entry)=>entry.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(!visible)return;links.forEach((link)=>link.classList.toggle('is-current',link.getAttribute('href')==='#'+visible.target.id));},{rootMargin:'-18% 0px -65% 0px',threshold:[.1,.35,.7]});
    targets.forEach((target)=>observer.observe(target));
  }

  const editor = $('[data-editor]');
  if (editor) {
    const form = $('[data-editor-form]', editor);
    const sectionList = $('[data-section-list]', editor);
    const sectionTemplate = $('[data-section-template]', editor);
    const addButton = $('[data-add-section]', editor);
    $$('[data-theme-preset]', editor).forEach(button => button.addEventListener('click', () => {
      const preset = button.dataset.themePreset?.split('|') || [];
      const background = $('[data-theme-input="background"]', editor);
      const accent = $('[data-theme-input="accent"]', editor);
      if (background && preset[0]) background.value = preset[0];
      if (accent && preset[1]) accent.value = preset[1];
      [background, accent].filter(Boolean).forEach(input => input.dispatchEvent(new Event('input', {bubbles:true})));
      $$('[data-theme-preset]', editor).forEach(item => item.classList.toggle('is-active', item === button));
    }));
    const isTeacher=editor?.dataset.userRole==='teacher';
    let serial = sectionList ? sectionList.children.length : 0;
    const writeNames = () => {
      if (!sectionList) return;
      $$('[data-section]', sectionList).forEach((section, index) => {
        const setName=(selector, suffix)=>{ const field=$(selector,section); if(field) field.name=`sections[${index}][${suffix}]`; };
        setName('[data-section-id]','id'); setName('[data-section-client-key]','client_key'); setName('[data-section-enabled-hidden]','enabled');
        setName('[data-section-enabled]','enabled'); setName('[data-section-visibility-hidden]','visibility');
        setName('[data-section-visibility]','visibility'); setName('[data-section-title]','title'); setName('[data-section-body]','body');
        setName('[data-section-parent]','parent_id'); setName('[data-section-display-mode]','display_mode'); setName('[data-section-subject-theme]','subject_theme');
        const parentSelect=$('[data-section-parent]',section);
        if(parentSelect){
          const current=parentSelect.value;
          parentSelect.replaceChildren(new Option('قسم رئيسي مستقل','0'));
          $$('[data-section]',sectionList).forEach((candidate)=>{
            if(candidate===section) return;
            const candidateId=$('[data-section-id]',candidate)?.value;
            const candidateKey=$('[data-section-client-key]',candidate)?.value;
            const candidateParent=$('[data-section-parent]',candidate)?.value||'0';
            const candidateTitle=$('[data-section-title]',candidate)?.value.trim();
            const mainOnly=isTeacher && candidateParent!=='0';
            if(candidateTitle && !mainOnly && (candidateId && candidateId!=='0' || candidateKey)){
              const value=candidateId && candidateId!=='0'?candidateId:candidateKey;
              parentSelect.add(new Option('↳ '+candidateTitle,value));
            }
          });
          parentSelect.value=[...parentSelect.options].some((option)=>option.value===current)?current:'0';
        }
        const label=$('[data-section-index]',section); if(label) label.textContent=String(index+1).padStart(2,'0');
        const titleLabel=$('[data-section-title-label]',section); if(titleLabel) titleLabel.textContent=isTeacher?(parentSelect?.value && parentSelect.value!=='0'?'اسم الشاهد الفرعي':'العنصر الرئيسي'):'اسم القسم';
      });
    };
    const updatePreview=()=>{
      const title=$('[data-preview-title]',editor)?.value.trim()||'عنوان ملفك';
      const intro=$('[data-preview-intro]',editor)?.value.trim()||'ستظهر نبذتك التعريفية هنا.';
      const name=$('[data-theme-input="name"]',editor)?.value.trim()||'اسمك الظاهر';
      $$('[data-mini-title],[data-theme-title]').forEach((node)=>node.textContent=title);
      $$('[data-mini-intro],[data-theme-intro]').forEach((node)=>node.textContent=intro);
      $$('[data-mini-name],[data-theme-name]').forEach((node)=>node.textContent=name);
      const bg=$('[data-theme-input="background"]',editor)?.value; const accent=$('[data-theme-input="accent"]',editor)?.value; const font=$('[data-theme-input="font"]',editor)?.value; const cover=$('[data-theme-input="cover"]',editor)?.value;
      $$('[data-theme-preview],[data-main-preview]').forEach((node)=>{ if(bg) node.style.setProperty('--preview-bg',bg); if(accent) node.style.setProperty('--preview-accent',accent); if(font) node.style.setProperty('--preview-font',font); });
      $$('[data-main-preview]').forEach((node)=>{if(cover)node.dataset.coverStyle=cover;});
      const names=$$('[data-section-title]',sectionList||editor).map((input)=>input.value.trim()).filter(Boolean);
      const mini=$('[data-mini-sections]',editor); if(mini){mini.replaceChildren();names.forEach((name)=>{const li=document.createElement('li');li.textContent=name;mini.append(li);});}
      const live=$('[data-live-section-list]',editor); if(live){live.replaceChildren();names.slice(0,4).forEach((name)=>{const span=document.createElement('span');span.textContent=name;live.append(span);});}
      $$('[data-section]',sectionList||editor).forEach((section)=>{
        const title=$('[data-section-title]',section)?.value.trim()||'عنوان القسم'; const body=$('[data-section-body]',section)?.value.trim()||'ستظهر معاينة محتوى هذا القسم هنا.';
        const previewTitle=$('[data-section-preview-title]',section); const previewBody=$('[data-section-preview-body]',section);
        if(previewTitle) previewTitle.textContent=title; if(previewBody) previewBody.textContent=body;
        const vis=$('[data-section-visibility]',section); const hidden=$('[data-section-visibility-hidden]',section); if(vis&&hidden) hidden.value=vis.value;
      });
    };
    addButton?.addEventListener('click',()=>{
      if(!sectionList||!sectionTemplate) return;
      if(sectionList.children.length>=100){window.alert('يمكن أن يحتوي الملف على 100 عنصر كحد أقصى.');return;}
      const fragment=sectionTemplate.content.cloneNode(true); const section=$('[data-section]',fragment); if(!section)return;
      const id=$('[data-section-id]',section); if(id) id.value='0';
      const key=`new-${Date.now()}-${serial++}`; const keyField=$('[data-section-client-key]',section); if(keyField) keyField.value=key;
      section.dataset.clientKey=key; sectionList.append(section); writeNames(); updatePreview(); $('[data-section-title]',section)?.focus();
    });
    const subjectChoice=$('[data-subject-choice]',editor); const subjectForm=$('[data-subjects-form]',editor); const subjectInput=$('[data-subject-names]',editor);
    $('[data-subjects-yes]',editor)?.addEventListener('click',()=>{ if(subjectForm) subjectForm.hidden=false; subjectInput?.focus(); });
    $('[data-subjects-no]',editor)?.addEventListener('click',()=>{ subjectChoice?.remove(); });
    $('[data-subjects-create]',editor)?.addEventListener('click',()=>{
      const names=(subjectInput?.value||'').split(/[،,]/).map((name)=>name.trim()).filter(Boolean).slice(0,24);
      if(!names.length){ window.atharToast?.('اكتب مادة واحدة على الأقل ثم حاول مرة أخرى.','error'); return; }
      if(!sectionList || !sectionTemplate?.content){ window.atharToast?.('تعذر تجهيز بطاقة المادة. أعد تحميل صفحة المحرر وحاول مرة أخرى.','error'); return; }
      let added=0;
      names.forEach((name)=>{ if(sectionList.children.length<100){ const before=sectionList.children.length; addButton?.click(); const section=sectionList.lastElementChild; const title=section&&$('[data-section-title]',section); if(sectionList.children.length>before && title){title.value=name; title.dispatchEvent(new Event('input',{bubbles:true})); added++;} } });
      if(!added){ window.atharToast?.('وصلت إلى الحد الأقصى أو تعذر إنشاء البطاقات.','error'); return; }
      subjectChoice?.remove(); writeNames(); updatePreview(); window.atharToast?.(`تمت إضافة ${names.length} مواد إلى المحرر.`,'success');
    });
    sectionList?.addEventListener('click',(event)=>{
      const target=event.target; if(!(target instanceof Element)) return;
      const remove=target.closest('[data-remove-section]');
      if(remove){if(sectionList.children.length<=1){window.alert('يجب أن يبقى في الملف قسم واحد على الأقل.');return;}if(!window.confirm('سيُحذف القسم من ملفك. ستبقى الأدلة محفوظة لكنها ستصبح غير مرتبطة بقسم. متابعة؟'))return;remove.closest('[data-section]')?.remove();writeNames();updatePreview();return;}
      const up=target.closest('[data-move-up]'); const down=target.closest('[data-move-down]');
      const section=target.closest('[data-section]'); if(!section)return;
      if(up&&section.previousElementSibling)sectionList.insertBefore(section,section.previousElementSibling);
      if(down&&section.nextElementSibling)sectionList.insertBefore(section.nextElementSibling,section);
      writeNames();updatePreview();
    });
    editor.addEventListener('input',(event)=>{if(event.target instanceof Element)updatePreview();});
    editor.addEventListener('change',(event)=>{if(event.target instanceof Element){if(event.target.matches('[data-section-parent]'))writeNames();updatePreview();}});
    const directButton=$('[data-direct-edit]',editor); let directMode=false;
    const directMap={name:'[data-theme-input="name"]',title:'[data-preview-title]',intro:'[data-preview-intro]'};
    const setDirectMode=(enabled)=>{
      directMode=enabled; editor.classList.toggle('direct-edit-mode',enabled); if(directButton) directButton.textContent=enabled?'إنهاء التحرير المباشر':'تحرير مباشر';
      $$('[data-direct-target]',editor).forEach((node)=>{node.contentEditable=enabled?'true':'false';node.classList.toggle('is-direct-editable',enabled);node.setAttribute('aria-label','تحرير مباشر');});
    };
    directButton?.addEventListener('click',()=>setDirectMode(!directMode));
    editor.addEventListener('input',(event)=>{
      const target=event.target instanceof Element?event.target.closest('[data-direct-target]'):null; if(!target||!directMode)return;
      const key=target.dataset.directTarget; let field=$(directMap[key]||'',editor);
      if(key==='section-title') field=target.closest('[data-section]')?.querySelector('[data-section-title]');
      if(key==='section-body') field=target.closest('[data-section]')?.querySelector('[data-section-body]');
      if(field){field.value=target.textContent?.trim()||'';field.dispatchEvent(new Event('input',{bubbles:true}));}
    });
    $$('[data-direct-target]',editor).forEach((node)=>node.addEventListener('keydown',(event)=>{if(event.key==='Enter'&&node.dataset.directTarget!=='intro'){event.preventDefault();node.blur();}}));
    const evidenceList=$('[data-evidence-list]',editor); const reorderForm=$('[data-evidence-reorder]',editor); const orderFields=$('[data-evidence-order-fields]',editor); let dragged=null;
    const syncEvidenceOrder=()=>{if(!evidenceList||!orderFields)return;orderFields.replaceChildren();$$('[data-evidence-item]',evidenceList).forEach((item)=>{const input=document.createElement('input');input.type='hidden';input.name='evidence_order[]';input.value=item.dataset.evidenceId||'';orderFields.append(input);});};
    evidenceList?.addEventListener('dragstart',(event)=>{const item=event.target instanceof Element?event.target.closest('[data-evidence-item]'):null;if(!item)return;dragged=item;item.classList.add('is-dragging');event.dataTransfer?.setData('text/plain',item.dataset.evidenceId||'');});
    evidenceList?.addEventListener('dragend',()=>{dragged?.classList.remove('is-dragging');dragged=null;syncEvidenceOrder();});
    evidenceList?.addEventListener('dragover',(event)=>{event.preventDefault();const item=event.target instanceof Element?event.target.closest('[data-evidence-item]'):null;if(!item||!dragged||item===dragged)return;const rect=item.getBoundingClientRect();const after=(event.clientY-rect.top)>rect.height/2;if(after)item.after(dragged);else item.before(dragged);});
    syncEvidenceOrder();
    form?.addEventListener('submit',writeNames);
    writeNames();updatePreview();
  }

  $$('[data-file-preview]').forEach((input)=>input.addEventListener('change',()=>{
    const label=input.closest('label')?.querySelector('[data-file-name]'); const file=input.files?.[0]; if(label) label.textContent=file?`${file.name} · ${(file.size/1024/1024).toFixed(2)} ميغابايت`:'لم يُحدد ملف بعد';
  }));
  $$('[data-upload-form]').forEach((form)=>form.addEventListener('submit',(event)=>{
    const file=$('[data-file-preview]',form)?.files?.[0]; if(file&&file.size>8*1024*1024){event.preventDefault();window.alert('حجم الملف يتجاوز الحد الأقصى 8 ميغابايت.');}
  }));

  const loadScriptOnce = (src, ready) => new Promise((resolve, reject) => {
    if (ready()) return resolve();
    const existing = document.querySelector(`script[data-external-src="${src}"]`);
    if (existing) { existing.addEventListener('load', resolve, {once:true}); existing.addEventListener('error', reject, {once:true}); return; }
    const script = document.createElement('script'); script.src = src; script.async = true; script.crossOrigin = 'anonymous'; script.dataset.externalSrc = src;
    script.onload = resolve; script.onerror = () => reject(new Error('تعذر تحميل مكتبة OCR من CDN.')); document.head.append(script);
  });
  const tesseractUrl = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
  const tesseractWorkerUrl = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/worker.min.js';
  const tesseractCoreUrl = 'https://cdn.jsdelivr.net/npm/tesseract.js-core@5.1.1';
  const languageDataUrl = 'https://tessdata.projectnaptha.com/4.0.0';
  const pdfJsUrl = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
  const pdfWorkerUrl = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
  const recognizeBlob = async (blob, statusNode) => {
    await loadScriptOnce(tesseractUrl, () => !!window.Tesseract);
    const worker = await window.Tesseract.createWorker('ara+eng', 1, {
      workerPath: tesseractWorkerUrl, corePath: tesseractCoreUrl, langPath: languageDataUrl,
      logger: (message) => { if (message.status && statusNode) statusNode.textContent = `OCR: ${message.status}${message.progress ? ` (${Math.round(message.progress*100)}٪)` : ''}`; }
    });
    try { const result = await worker.recognize(blob); return result.data.text || ''; }
    finally { await worker.terminate(); }
  };
  const recognizePdf = async (blob, statusNode) => {
    await loadScriptOnce(pdfJsUrl, () => !!window.pdfjsLib);
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
    const pdf = await window.pdfjsLib.getDocument({data:new Uint8Array(await blob.arrayBuffer())}).promise;
    const total = Math.min(pdf.numPages, 5); const chunks = [];
    for (let pageNo = 1; pageNo <= total; pageNo++) {
      if (statusNode) statusNode.textContent = `قراءة الصفحة ${pageNo} من ${total}…`;
      const page = await pdf.getPage(pageNo); const base = page.getViewport({scale:1});
      const viewport = page.getViewport({scale:Math.min(1.5, 1500/base.width)});
      const canvas = document.createElement('canvas'); canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
      const context = canvas.getContext('2d', {willReadFrequently:true});
      await page.render({canvasContext:context, viewport}).promise;
      chunks.push(await recognizeBlob(canvas, statusNode)); canvas.width = 0; canvas.height = 0;
    }
    return chunks.join('\n\n').slice(0, 12000);
  };
  $$('[data-browser-ocr]').forEach((button) => button.addEventListener('click', async () => {
    const row=button.closest('.evidence-row'); const panel=row?.querySelector('[data-ocr-panel]'); const statusNode=panel?.querySelector('[data-ocr-status]'); const output=panel?.querySelector('[data-ocr-text]');
    if(!row||!panel||!statusNode||!output)return;
    panel.hidden=false; button.disabled=true; statusNode.textContent='جارٍ جلب الملف الخاص…'; output.value='';
    try {
      const response=await fetch(button.dataset.ocrUrl,{credentials:'same-origin',headers:{'Accept':'image/*,application/pdf'}});
      if(!response.ok)throw new Error('تعذر تحميل الملف؛ سجّل الدخول مجددًا ثم حاول.');
      const blob=await response.blob(); if(blob.size>8*1024*1024)throw new Error('الملف يتجاوز الحد المسموح به للمعالجة على هذا الجهاز.');
      if(button.dataset.ocrType==='application/pdf') output.value=await recognizePdf(blob,statusNode);
      else output.value=await recognizeBlob(blob,statusNode);
      statusNode.textContent=output.value.trim()?'اكتمل الاستخراج؛ راجع النص وصححه يدويًا. لم يُرسل النص لأي خدمة.':'لم يُستخرج نص واضح؛ جرّب صورة أوضح أو أدخل النص يدويًا.';
    } catch(error) { statusNode.textContent=error instanceof Error?error.message:'تعذر إكمال OCR. تحقق من الإنترنت وأدوات المتصفح.'; }
    finally { button.disabled=false; }
  }));
  $$('[data-ocr-copy]').forEach((button)=>button.addEventListener('click',async()=>{
    const text=button.closest('[data-ocr-panel]')?.querySelector('[data-ocr-text]')?.value||'';
    if(!text.trim())return; await copyText(text); const original=button.textContent; button.textContent='تم نسخ النص'; setTimeout(()=>button.textContent=original,1500);
  }));

  if (new URLSearchParams(location.search).has('print')) setTimeout(()=>window.print(),500);
})();

// بعض إجراءات التأكيد مرتبطة بزر الإرسال لا بالنموذج نفسه.
document.querySelectorAll('button[data-confirm]').forEach((button) => button.addEventListener('click', (event) => {
  if (!window.confirm(button.dataset.confirm || 'هل تريد المتابعة؟')) event.preventDefault();
}));

// أدوات المستخدم اليومية: 19 تحسينًا خفيفًا تعمل دون خدمات خارجية.
(() => {
  const $ = (s, r=document) => r.querySelector(s);
  const loader = $('[data-site-loader]');
  let navigating = false, hideTimer = null;
  const queueLoaderHide = (delay=220) => {
    if (navigating || !loader || loader.classList.contains('is-hidden')) return;
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => { if (!navigating) loader.classList.add('is-hidden'); }, delay);
  };
  window.addEventListener('load', () => queueLoaderHide(180), {once:true});
  document.addEventListener('DOMContentLoaded', () => queueLoaderHide(420), {once:true});
  setTimeout(() => queueLoaderHide(2200), 2200); // لا تحجب الواجهة إذا تعثّر خط أو مورد خارجي.
  document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', () => { navigating=true; clearTimeout(hideTimer); loader?.classList.remove('is-hidden'); }));
  const editor = $('[data-editor]');
  if (!editor) return;
  const form = $('[data-editor-form]', editor); if (!form) return;
  const draftKey = 'athar-editor-draft-v1';
  const fields = () => [...form.querySelectorAll('input:not([type=hidden]):not([type=color]), textarea, select')];
  const status = document.createElement('span'); status.className='autosave-status'; status.textContent='جاهز'; form.querySelector('.editor-actions')?.prepend(status);
  const snapshot = () => JSON.stringify(fields().map(x => [x.name, x.value]));
  const restore = () => { try { const d=JSON.parse(localStorage.getItem(draftKey)||'null'); if(!d) return; const map=new Map(d); fields().forEach(x=>{if(map.has(x.name)&&map.get(x.name)!==x.value)x.value=map.get(x.name)}); status.textContent='تم استرجاع مسودة محلية'; } catch {} };
  const save = () => { try { localStorage.setItem(draftKey,snapshot()); status.textContent='حُفظت مسودة محلية'; } catch {} };
  const clear = () => { try { localStorage.removeItem(draftKey); } catch {} };
  restore(); let timer; form.addEventListener('input',()=>{ clearTimeout(timer); timer=setTimeout(save,900); }); form.addEventListener('submit',clear);
  const stats=document.createElement('div'); stats.className='editor-live-stats'; form.querySelector('.feature-editor-card')?.append(stats);
  const updateStats=()=>{const text=fields().filter(x=>x.tagName==='TEXTAREA').map(x=>x.value).join(' '); const words=text.trim()?text.trim().split(/\s+/).length:0; stats.textContent=`${words} كلمة · ${Math.ceil(words/180)||0} دقيقة قراءة · ${text.length} حرف`}; form.addEventListener('input',updateStats); updateStats();
  let undo=[], redo=[]; const remember=()=>{undo.push(snapshot()); if(undo.length>20)undo.shift(); redo=[]}; form.addEventListener('change',remember); const restoreSnap=(raw)=>{try{const map=new Map(JSON.parse(raw));fields().forEach(x=>{if(map.has(x.name))x.value=map.get(x.name)});form.dispatchEvent(new Event('input',{bubbles:true}))}catch{}};
  const history=document.createElement('div'); history.className='editor-history-actions'; history.innerHTML='<button type="button" class="button button-quiet" data-undo>تراجع</button><button type="button" class="button button-quiet" data-redo>إعادة</button><button type="button" class="button button-quiet" data-focus>وضع التركيز</button>'; form.querySelector('.editor-actions')?.prepend(history);
  $('[data-undo]',history)?.addEventListener('click',()=>{if(undo.length){redo.push(snapshot());restoreSnap(undo.pop())}}); $('[data-redo]',history)?.addEventListener('click',()=>{if(redo.length){undo.push(snapshot());restoreSnap(redo.pop())}}); $('[data-focus]',history)?.addEventListener('click',()=>document.body.classList.toggle('focus-editor'));
  document.addEventListener('keydown',(e)=>{if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();form.requestSubmit()} if((e.ctrlKey||e.metaKey)&&e.key==='z'){e.preventDefault(); $('[data-undo]',history)?.click()}});
  const search=document.querySelector('[data-evidence-list]'); if(search){const input=document.querySelector('.evidence-search input'); input?.addEventListener('input',()=>{const q=input.value.toLowerCase();search.querySelectorAll('[data-evidence-item]').forEach(x=>x.hidden=!x.innerText.toLowerCase().includes(q))})}
})();


// تحسينات السرعة وسهولة الوصول: تفضيلات محلية بلا طلبات شبكة إضافية.
(() => {
  const root = document.documentElement;
  const saved = localStorage.getItem('athar-theme');
  if (saved === 'dark') root.dataset.theme = 'dark';
  if (localStorage.getItem('athar-reduced-motion') === '1') root.dataset.motion = 'reduced';
  document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => {
    const dark = root.dataset.theme !== 'dark'; root.dataset.theme = dark ? 'dark' : 'light'; localStorage.setItem('athar-theme', dark ? 'dark' : 'light');
  }));
  const progress = document.querySelector('[data-scroll-progress]');
  const update = () => { if (!progress) return; const max = document.documentElement.scrollHeight - innerHeight; progress.style.transform = `scaleX(${max > 0 ? scrollY / max : 0})`; };
  addEventListener('scroll', update, {passive:true}); update();
  document.querySelectorAll('img:not([loading])').forEach((img) => { img.loading='lazy'; img.decoding='async'; });
  document.querySelectorAll('[data-reduced-motion], [data-motion-toggle]').forEach((button) => button.addEventListener('click', () => { const reduced=root.dataset.motion!=='reduced'; root.dataset.motion=reduced?'reduced':'full'; localStorage.setItem('athar-reduced-motion',reduced?'1':'0'); }));
  const adjustFontSize=(step)=>{const current=parseFloat(getComputedStyle(document.body).fontSize)||15;const next=Math.min(20,Math.max(12,current+step));document.body.style.fontSize=next+'px';localStorage.setItem('athar-font-size',String(next));};
  document.querySelectorAll('[data-font-plus]').forEach((button)=>button.addEventListener('click',()=>adjustFontSize(1)));
  document.querySelectorAll('[data-font-minus]').forEach((button)=>button.addEventListener('click',()=>adjustFontSize(-1)));
  const savedFont=localStorage.getItem('athar-font-size'); if(savedFont) document.body.style.fontSize=Math.min(20,Math.max(14,Number(savedFont)))+'px';
})();

// Save Slots: مسودات متعددة بأسماء واضحة + حفظ تلقائي محلي أثناء الكتابة.
(() => {
  const form = document.querySelector('[data-editor-form]');
  const panel = document.querySelector('[data-save-slots]');
  if (!form || !panel) return;
  const slotsKey = 'athar-save-slots-v1', autoKey = 'athar-auto-draft-v1';
  const nameInput = panel.querySelector('[data-slot-name]'), list = panel.querySelector('[data-slot-list]'), status = panel.querySelector('[data-slot-status]');
  const readForm = () => { const data={}; form.querySelectorAll('[name]').forEach(el=>{if(el.name==='csrf_token'||el.type==='file')return; data[el.name]=el.type==='checkbox'?el.checked:el.value;}); return data; };
  const writeForm = data => { form.querySelectorAll('[name]').forEach(el=>{if(el.name==='csrf_token'||!(el.name in data))return; if(el.type==='checkbox')el.checked=Boolean(data[el.name]);else el.value=data[el.name]; el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));}); };
  const getSlots=()=>{try{const v=JSON.parse(localStorage.getItem(slotsKey)||'[]');return Array.isArray(v)?v:[]}catch{return[];}};
  const setSlots=slots=>localStorage.setItem(slotsKey,JSON.stringify(slots.slice(0,12)));
  const stamp=iso=>{try{return new Intl.DateTimeFormat('ar-SA',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Riyadh'}).format(new Date(iso));}catch{return iso;}};
  const render=()=>{const slots=getSlots();list.innerHTML='';if(localStorage.getItem(autoKey)){const o=document.createElement('option');o.value='__auto__';o.textContent='الحفظ التلقائي الأخير';list.append(o);}if(!slots.length&&!localStorage.getItem(autoKey)){const o=document.createElement('option');o.value='';o.textContent='لا توجد نسخ محفوظة بعد';list.append(o);}slots.forEach(slot=>{const o=document.createElement('option');o.value=slot.id;o.textContent=`${slot.name} · ${stamp(slot.updatedAt)}`;list.append(o);});};
  const setStatus=text=>{if(status)status.textContent=text;};
  panel.querySelector('[data-slot-save]')?.addEventListener('click',()=>{const name=(nameInput.value||'').trim()||`مسودة ${new Date().toLocaleDateString('ar-SA',{timeZone:'Asia/Riyadh'})}`;const slots=getSlots();const slot={id:(crypto.randomUUID?crypto.randomUUID():String(Date.now())),name,updatedAt:new Date().toISOString(),data:readForm()};const same=slots.findIndex(x=>x.name.toLowerCase()===name.toLowerCase());if(same>=0)slots[same]=slot;else slots.unshift(slot);setSlots(slots);render();list.value=slot.id;nameInput.value='';setStatus(`تم حفظ «${name}»`);});
  panel.querySelector('[data-slot-load]')?.addEventListener('click',()=>{const id=list.value;let data=null;if(id==='__auto__'){try{data=JSON.parse(localStorage.getItem(autoKey)||'{}').data;}catch{}}else data=getSlots().find(x=>x.id===id)?.data;if(data){writeForm(data);setStatus('تم استرجاع النسخة');}});
  panel.querySelector('[data-slot-delete]')?.addEventListener('click',()=>{const id=list.value;if(!id||id==='__auto__')return;setSlots(getSlots().filter(x=>x.id!==id));render();setStatus('تم حذف النسخة');});
  let timer;const autoSave=()=>{localStorage.setItem(autoKey,JSON.stringify({updatedAt:new Date().toISOString(),data:readForm()}));setStatus('حفظ تلقائي · '+new Date().toLocaleTimeString('ar-SA',{hour:'2-digit',minute:'2-digit',timeZone:'Asia/Riyadh'}));};
  form.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(autoSave,900);});form.addEventListener('change',()=>{clearTimeout(timer);timer=setTimeout(autoSave,900);});render();
})();

// حركة منظور خفيفة لمشهد الصفحة الرئيسية، تتوقف تلقائيًا على اللمس وتقليل الحركة.
const heroScene=document.querySelector('[data-hero-scene]');
if(heroScene && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && !('ontouchstart' in window)){
  heroScene.addEventListener('pointermove',(event)=>{const r=heroScene.getBoundingClientRect();const x=(event.clientX-r.left)/r.width-.5;const y=(event.clientY-r.top)/r.height-.5;heroScene.style.setProperty('--scene-rotate-y',`${x*5}deg`);heroScene.style.setProperty('--scene-rotate-x',`${y*-4}deg`);});
  heroScene.addEventListener('pointerleave',()=>{heroScene.style.setProperty('--scene-rotate-y','0deg');heroScene.style.setProperty('--scene-rotate-x','0deg');});
}

// مشهد قوالب ثلاثي الأبعاد: حركة مؤشر بسيطة مع دعم لوحة المفاتيح والحركة المخففة.
(() => {
  const cards=[...document.querySelectorAll('.template-card')];
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.motion==='reduced';
  const touch=window.matchMedia('(pointer: coarse)').matches;
  if(!cards.length || reduced || touch) return;
  cards.forEach(card=>{
    card.addEventListener('pointermove',event=>{
      const rect=card.getBoundingClientRect();
      const px=(event.clientX-rect.left)/rect.width, py=(event.clientY-rect.top)/rect.height;
      card.style.setProperty('--tilt-y',`${(px-.5)*8}deg`);
      card.style.setProperty('--tilt-x',`${(.5-py)*7}deg`);
      card.style.setProperty('--shine-x',`${px*100}%`);
      card.style.setProperty('--shine-y',`${py*100}%`);
    });
    card.addEventListener('pointerleave',()=>{
      card.style.setProperty('--tilt-x','0deg');card.style.setProperty('--tilt-y','0deg');
    });
    card.addEventListener('focusin',()=>card.classList.add('is-focused'));
    card.addEventListener('focusout',()=>card.classList.remove('is-focused'));
  });
})();


// تصنيف شاهد المعلم حسب القسم الرئيسي؛ الطالب يحتفظ بنموذج الرفع المعتاد.
(() => {
  document.querySelectorAll('[data-upload-form][data-user-role="teacher"]').forEach(form=>{
    const main=form.querySelector('[data-evidence-main-section]');
    const type=form.querySelector('[data-evidence-type-select]');
    const customWrap=form.querySelector('[data-evidence-custom-wrap]');
    const custom=customWrap?.querySelector('input');
    if(!main||!type)return;
    const refresh=()=>{
      const selected=type.value;
      [...type.options].forEach(option=>{
        if(!option.dataset.mainId)return;
        const show=option.dataset.mainId===main.value;
        option.hidden=!show;option.disabled=!show;
      });
      const stillValid=[...type.options].some(o=>o.value===selected && !o.disabled);
      if(!stillValid)type.value='';
      const isOther=type.value==='__other__';
      if(customWrap)customWrap.hidden=!isOther;
      if(custom){custom.required=isOther;if(!isOther)custom.value='';}
    };
    main.addEventListener('change',refresh);type.addEventListener('change',refresh);refresh();
  });
})();

// نافذة استعراض الشواهد والصور وملفات PDF داخل الملف العام.
(() => {
  const modal = document.querySelector('[data-evidence-modal]');
  if (!modal) return;
  const english = document.documentElement.lang === 'en';
  const copy = {title: english ? 'Evidence preview' : 'استعراض الشاهد', loading: english ? 'Loading evidence…' : 'جارٍ تحميل الشاهد…', imageError: english ? 'Unable to load the image. Use the open-in-new-window button.' : 'تعذر تحميل الصورة. استخدم زر الفتح في نافذة جديدة.', pdfError: english ? 'Unable to start the PDF viewer. Use open in a new window or download.' : 'تعذر تشغيل عارض PDF. استخدم زر «فتح في نافذة جديدة» أو التنزيل.', officeError: english ? 'If the file does not appear, use open in a new window or download.' : 'إذا لم يظهر الملف، استخدم زر «فتح في نافذة جديدة» أو التنزيل.', fallback: english ? 'You can open this file in a new window or download it.' : 'يمكن فتح هذا الملف في نافذة جديدة أو تنزيله.'};
  const body = modal.querySelector('[data-evidence-modal-body]');
  const title = modal.querySelector('[data-evidence-modal-title]');
  const download = modal.querySelector('[data-evidence-download]');
  if (modal.parentElement !== document.body) document.body.appendChild(modal);
  let lastFocus = null;
  const close = () => { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('evidence-modal-open'); body.replaceChildren(); lastFocus?.focus?.(); };
  document.addEventListener('click', event => {
    const trigger = event.target.closest?.('[data-evidence-preview]');
    if (trigger) {
      event.preventDefault();
      lastFocus = trigger;
      const url = trigger.dataset.evidenceUrl || '';
      const mime = trigger.dataset.evidenceMime || '';
      title.textContent = trigger.dataset.evidenceTitle || copy.title;
      download.href = new URL(url, window.location.href).href;
      download.target = '_blank';
      download.rel = 'noopener noreferrer';
      body.replaceChildren();
      const loading = document.createElement('p'); loading.className = 'evidence-preview-loading'; loading.textContent = copy.loading; body.append(loading);
      if (mime.startsWith('image/')) {
        const image = document.createElement('img'); image.src = url; image.alt = title.textContent; image.loading = 'eager'; image.decoding = 'async';
        image.addEventListener('load', () => loading.remove(), {once:true}); image.addEventListener('error', () => { loading.textContent=copy.imageError; loading.className='preview-load-error'; }, {once:true}); body.append(image);
      } else if (mime === 'application/pdf') {
        const pdfHost = document.createElement('div'); pdfHost.className = 'pdfjs-viewer'; body.append(pdfHost);
        const fallbackPdfFrame = () => { pdfHost.remove(); loading.remove(); const frame=document.createElement('iframe'); frame.src=url; frame.title=title.textContent; frame.loading='eager'; frame.setAttribute('allowfullscreen',''); frame.className='pdf-fallback-frame'; body.append(frame); };
        const pdfjs = window.pdfjsLib;
        if (!pdfjs) { fallbackPdfFrame(); }
        else {
          pdfjs.GlobalWorkerOptions.workerSrc = '/assets/pdfjs-v3/pdf.worker.min.js?v=1';
          pdfjs.getDocument({url, withCredentials:true}).promise.then(async pdf => {
            loading.remove();
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
              const page = await pdf.getPage(pageNumber); const viewport = page.getViewport({scale: Math.min(2, Math.max(1, window.devicePixelRatio || 1))});
              const canvas = document.createElement('canvas'); canvas.className='pdfjs-page'; canvas.width=viewport.width; canvas.height=viewport.height; canvas.setAttribute('aria-label',`PDF page ${pageNumber}`); pdfHost.append(canvas);
              await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
            }
          }).catch(() => fallbackPdfFrame());
        }
      } else if (mime === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' || mime === 'application/vnd.ms-powerpoint') {
        const pptHost=document.createElement('div'); pptHost.className='pptx-viewer-host'; const canvas=document.createElement('canvas'); canvas.className='pptx-slide-canvas'; const controls=document.createElement('div'); controls.className='pptx-controls'; controls.innerHTML=`<button type="button" data-pptx-prev>${english?'Previous':'السابق'}</button><span data-pptx-status>${english?'Loading slides…':'جارٍ تحميل الشرائح…'}</span><button type="button" data-pptx-next>${english?'Next':'التالي'}</button>`; pptHost.append(canvas,controls); body.append(pptHost);
        const fallbackPptx=()=>{pptHost.replaceChildren(); const note=document.createElement('p'); note.className='preview-load-error'; note.innerHTML=english?'This presentation cannot be rendered in this browser. Use the open or download button below.':'تعذر تحويل العرض إلى شرائح في هذا المتصفح. استخدم زر الفتح أو التنزيل أسفل النافذة.'; pptHost.append(note);};
        const loadScript=src=>new Promise((resolve,reject)=>{const script=document.createElement('script');script.src=src;script.onload=resolve;script.onerror=reject;document.head.append(script);});
        const ready=window.PptxViewJS?.PPTXViewer?Promise.resolve():loadScript('/assets/pptx/jszip.min.js?v=2').then(()=>loadScript('/assets/pptx/PptxViewJS.min.js?v=2'));
        ready.then(()=>{const engine=window.PptxViewJS?.PPTXViewer;if(!engine)throw new Error('PPTX viewer unavailable');return fetch(url,{credentials:'same-origin'}).then(response=>{if(!response.ok)throw new Error('PPTX download failed');return response.blob();}).then(blob=>{const viewer=new engine({canvas});const file=new File([blob],title.textContent+'.pptx',{type:mime});return Promise.resolve(viewer.loadFile(file)).then(()=>{loading.remove();let index=viewer.getCurrentSlideIndex?.()||0;const status=controls.querySelector('[data-pptx-status]');const paint=async()=>{await viewer.goToSlide?.(index);if(status)status.textContent=`${english?'Slide':'شريحة'} ${index+1} / ${viewer.getSlideCount?.()||1}`;};controls.querySelector('[data-pptx-prev]').addEventListener('click',async()=>{index=Math.max(0,index-1);await paint();});controls.querySelector('[data-pptx-next]').addEventListener('click',async()=>{index=Math.min((viewer.getSlideCount?.()||1)-1,index+1);await paint();});return viewer.render().then(paint);});});}).catch(fallbackPptx);
      } else if (mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || mime === 'application/msword') {
        const absoluteUrl = new URL(url, window.location.href).href;
        const officeHost=document.createElement('div'); officeHost.className='office-viewer-host'; const frame = document.createElement('iframe'); frame.src = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(absoluteUrl)}`; frame.title = title.textContent; frame.loading='eager'; frame.setAttribute('allowfullscreen',''); frame.setAttribute('allow','fullscreen'); officeHost.append(frame); body.append(officeHost);
        const showOfficeFallback=()=>{if(!loading.isConnected)return; loading.innerHTML=`<strong>${english?'This file cannot be previewed here.':'يتعذر عرض هذا الملف داخل النافذة.'}</strong><br><small>${english?'Use one of the buttons below to open or download it.':'استخدم أحد الزرين أسفل النافذة لفتحه أو تنزيله.'}</small>`;loading.className='preview-load-error office-fallback-message';};
        frame.addEventListener('load',()=>loading.remove(),{once:true}); setTimeout(showOfficeFallback,6500);
      } else { loading.textContent = copy.fallback; loading.className='preview-load-error'; }
      modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('evidence-modal-open'); modal.querySelector('.evidence-modal-close')?.focus();
      return;
    }
    if (event.target.closest?.('[data-evidence-close]')) close();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
})();

// فتح نموذج طلب الاشتراك مع تثبيت الخطة المختارة.
(() => {
  const panel = document.querySelector('[data-subscription-panel]');
  if (!panel) return;
  const planInput = panel.querySelector('[data-subscription-plan]');
  const planName = panel.querySelector('[data-subscription-name]');
  document.querySelectorAll('[data-subscription-open]').forEach(button => button.addEventListener('click', () => {
    planInput.value = button.dataset.subscriptionOpen || '';
    planName.textContent = button.dataset.planName || '';
    panel.hidden = false;
    panel.scrollIntoView({behavior:'smooth', block:'center'});
    panel.querySelector('textarea')?.focus();
  }));
  panel.querySelector('[data-subscription-close]')?.addEventListener('click', () => { panel.hidden = true; });
})();

// عدادات الحظر وانتهاء التجربة والاشتراك.
(() => {
  const english=document.documentElement.lang==='en'; const format = total => { const d=Math.floor(total/86400), h=Math.floor(total%86400/3600), m=Math.floor(total%3600/60), s=total%60; return english ? (d ? `${d}d, ${h}h` : `${h}h, ${m}m, ${s}s`) : (d ? `${d} يوم، ${h} ساعة` : `${h} ساعة، ${m} دقيقة، ${s} ثانية`); };
  const run = element => {
    const raw=element.dataset.banUntil || element.dataset.subscriptionCountdown || ''; if(!raw) return;
    const timestamp=/([zZ]|[+-]\d{2}:?\d{2})$/.test(raw)?raw:raw.replace(' ','T')+'Z'; const target=new Date(timestamp).getTime(); if(Number.isNaN(target)) return;
    const tick=()=>{ const remaining=Math.max(0,Math.floor((target-Date.now())/1000)); const output=element.querySelector('[data-ban-countdown]') || element.querySelector('[data-subscription-countdown-label]'); if(output) output.textContent=remaining?(english?`Time remaining: ${format(remaining)}`:`الوقت المتبقي: ${format(remaining)}`):(english?'The period has ended; refresh to check your status.':'انتهت المدة؛ حدّث الصفحة للتحقق من حالتك.'); if(remaining) setTimeout(tick,1000); };
    tick();
  };
  document.querySelectorAll('[data-ban-until],[data-subscription-countdown]').forEach(run);
})();

// تحديثات الموقع: تظهر نافذة التحديث مرة واحدة لكل رقم تحديث على هذا المتصفح.
(() => {
  const popup=document.querySelector('[data-update-popup]');
  if(!popup)return;
  const id=popup.dataset.updateId||'0'; const key=`athar-seen-update-${id}`;
  let seen=false; try{seen=localStorage.getItem(key)==='1';}catch{}
  const close=()=>{popup.hidden=true;document.body.classList.remove('update-popup-open');try{localStorage.setItem(key,'1');}catch{}};
  if(seen){popup.remove();return;}
  window.setTimeout(()=>{popup.hidden=false;document.body.classList.add('update-popup-open');},260);
  popup.querySelector('[data-update-close]')?.addEventListener('click',close);
  popup.addEventListener('click',event=>{if(event.target===popup)close();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!popup.hidden)close();});
})();

// معاينة أنماط صفحة المشاركة قبل اعتماد شكل الرابط.
(() => {
  const page=document.querySelector('.public-portfolio-page');
  const catalog=document.querySelector('[data-share-presentation-template]');
  if(!page||!catalog)return;
  const toolbar=page.querySelector('.public-site-actions, .subjects-directory-actions, .public-topbar > div');
  const pickers=[...page.querySelectorAll('.share-presentation-picker')];
  let picker=pickers[0]||null;
  if(!picker&&toolbar){
    picker=document.createElement('details');
    picker.className='share-presentation-picker';
    const summary=document.createElement('summary');
    summary.textContent='طرق العرض · 29';
    const menu=document.createElement('div');
    menu.className='share-presentation-menu';
    picker.append(summary,menu);
    toolbar.append(picker);
  }
  pickers.slice(1).forEach(duplicate=>duplicate.remove());
  if(!picker)return;
  const summary=picker.querySelector('summary');
  if(summary)summary.textContent='طرق العرض · 29';
  let menu=picker.querySelector('.share-presentation-menu');
  if(!menu){menu=document.createElement('div');menu.className='share-presentation-menu';picker.append(menu);}
  menu.innerHTML=catalog.innerHTML;

  const keys=[...menu.querySelectorAll('[data-presentation-preview]')].map(button=>button.dataset.presentationPreview).filter(Boolean);
  const currentClass=[...page.classList].find(name=>name.startsWith('share-style-'));
  let activeKey=currentClass?currentClass.replace('share-style-',''):'landing';
  if(!keys.includes(activeKey))activeKey='landing';
  let previewKey=activeKey;
  const setStyle=key=>{
    if(!keys.includes(key))return;
    page.classList.remove(...[...page.classList].filter(name=>name.startsWith('share-style-')));
    page.classList.add(`share-style-${key}`);
    menu.querySelectorAll('[data-presentation-select]').forEach(link=>{
      if(link.dataset.presentationSelect===key)link.setAttribute('aria-current','page');
      else link.removeAttribute('aria-current');
    });
  };

  const banner=document.createElement('aside');
  banner.className='share-preview-banner';
  banner.hidden=true;
  banner.setAttribute('role','region');
  banner.setAttribute('aria-label','معاينة طريقة العرض');
  banner.innerHTML='<div class="share-preview-copy"><strong data-preview-label>معاينة طريقة العرض</strong><span>المعاينة مؤقتة؛ اعتماد النمط يحدّث هذا الرابط، ويُحفظ النمط الافتراضي من إعدادات المشاركة.</span></div><div class="share-preview-actions"><button type="button" class="share-preview-apply" data-preview-apply>اعتماد العرض</button><button type="button" class="share-preview-cancel" data-preview-cancel>إلغاء المعاينة</button></div>';
  document.body.append(banner);
  const label=banner.querySelector('[data-preview-label]');

  menu.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-presentation-preview]');
    if(!trigger)return;
    previewKey=trigger.dataset.presentationPreview||'landing';
    const choice=menu.querySelector(`[data-presentation-select="${CSS.escape(previewKey)}"]`);
    if(label)label.textContent=`معاينة: ${choice?.textContent.trim()||'طريقة العرض'}`;
    setStyle(previewKey);
    banner.hidden=false;
    picker.open=false;
    banner.querySelector('[data-preview-apply]')?.focus({preventScroll:true});
  });
  banner.querySelector('[data-preview-cancel]')?.addEventListener('click',()=>{
    setStyle(activeKey);
    banner.hidden=true;
    menu.querySelector(`[data-presentation-preview="${CSS.escape(activeKey)}"]`)?.focus({preventScroll:true});
  });
  banner.querySelector('[data-preview-apply]')?.addEventListener('click',()=>{
    activeKey=previewKey;
    setStyle(activeKey);
    const url=new URL(window.location.href);
    url.searchParams.set('presentation',activeKey);
    window.history.replaceState(window.history.state,'',url);
    banner.hidden=true;
    window.scrollTo({top:0,behavior:'smooth'});
  });
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&!banner.hidden){
      setStyle(activeKey);
      banner.hidden=true;
    }
  });
})();

// افتح معاينة النمط المحدد من إعدادات المشاركة في تبويب مستقل.
(() => {
  document.querySelectorAll('[data-default-presentation-preview]').forEach(button=>button.addEventListener('click',()=>{
    const select=button.closest('form')?.querySelector('[data-default-presentation-select]');
    const base=button.dataset.previewUrl||'';
    if(!select||!base)return;
    const url=new URL(base,window.location.origin);
    url.searchParams.set('presentation',select.value||'landing');
    const link=document.createElement('a');
    link.href=url.href;
    link.target='_blank';
    link.rel='noopener noreferrer';
    link.hidden=true;
    document.body.append(link);
    link.click();
    link.remove();
  }));
})();
