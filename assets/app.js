(() => {
  const toast = (message, type = 'info') => {
    if (!message || !message.trim()) return;
    const stack = document.querySelector('[data-toast-stack]') || (() => { const node=document.createElement('div'); node.className='toast-stack'; node.dataset.toastStack='1'; document.body.append(node); return node; })();
    const item=document.createElement('div'); item.className=`athar-toast athar-toast-${type}`; item.setAttribute('role', type==='error'?'alert':'status');
    item.innerHTML=`<span class="athar-toast-icon">${type==='success'?'✓':type==='error'?'!':'i'}</span><span class="athar-toast-message"></span><button type="button" class="athar-toast-close" aria-label="إغلاق">×</button><i class="athar-toast-progress"></i>`;
    item.querySelector('.athar-toast-message').textContent=message; item.querySelector('.athar-toast-close').addEventListener('click',()=>item.remove()); stack.append(item);
    requestAnimationFrame(()=>item.classList.add('is-visible')); setTimeout(()=>{item.classList.remove('is-visible');setTimeout(()=>item.remove(),280)},5200);
  };
  window.atharToast = toast;
  document.querySelectorAll('[data-flash]').forEach((node)=>{ const type=node.className.includes('success')?'success':node.className.includes('error')?'error':'info'; toast(node.querySelector('span')?.textContent||node.textContent,type); node.remove(); });
  document.querySelectorAll('.form-alert').forEach((node)=>{ const message=[...node.querySelectorAll('p')].map((p)=>p.textContent.trim()).filter(Boolean).join(' '); toast(message,'error'); });
  window.addEventListener('error',()=>toast(document.documentElement.lang==='en'?'Something went wrong. Your changes were not lost. Try again.':'حدث خطأ غير متوقع. لم تُفقد تعديلاتك؛ حاول مرة أخرى.','error'));
  window.addEventListener('unhandledrejection',()=>toast(document.documentElement.lang==='en'?'A background action failed. Please try again.':'تعذّر تنفيذ إجراء في الخلفية. حاول مرة أخرى.','error'));
  if (document.body.classList.contains('app-shell')) { const poll=async()=>{try{const response=await fetch('/notifications/poll',{headers:{Accept:'application/json'},credentials:'same-origin'});if(!response.ok)return;const data=await response.json();(data.items||[]).forEach(item=>toast(`${item.title}: ${item.message}`,item.type||'info'));}catch{}}; poll(); setInterval(poll,30000); }
  const dismiss = document.querySelector('[data-dismiss-flash]');
  if (dismiss) dismiss.addEventListener('click', () => dismiss.closest('[data-flash]')?.remove());
  document.querySelectorAll('.role-option input').forEach((radio) => {
    radio.addEventListener('change', () => document.querySelectorAll('.role-option').forEach((option) => {
      option.classList.toggle('is-selected', option.querySelector('input')?.checked ?? false);
    }));
  });

  const root = document.querySelector('[data-tour-root]');
  if (!root) return;
  const steps = [...root.querySelectorAll('[data-tour-step]')];
  const panel = root.querySelector('[data-tour-panel]');
  const scrim = root.querySelector('[data-tour-scrim]');
  const title = root.querySelector('[data-tour-title]');
  const copy = root.querySelector('[data-tour-copy]');
  const progress = root.querySelector('[data-tour-progress-bar]');
  const next = root.querySelector('[data-tour-next]');
  const prev = root.querySelector('[data-tour-prev]');
  const toggle = root.querySelector('[data-tour-toggle]');
  const autoSwitch = root.querySelector('[data-tour-auto]');
  const close = root.querySelector('[data-tour-close]');
  const replay = root.querySelector('[data-tour-replay]');
  let current = 0;
  let timer = null;
  let paused = false;

  const copies = [
    'املأ بياناتك الأساسية أولًا؛ ستظهر هويتك في ملفك بالطريقة التي تختارها.',
    'هنا تكتب قصة إنجازك وتنظمها في أقسام واضحة.',
    'اربط كل إنجاز بدليل موثوق، وتذكر أن الأدلة خاصة حتى تسمح بعرضها.',
    'حوّل التعلم أو الخبرة إلى أهداف وتأملات تراكمية يمكنك الرجوع إليها.',
    'اختر مظهرًا يعكسك وشاهد المعاينة قبل اعتماد التغييرات.',
    'حدد الأقسام العامة وطريقة العرض قبل إرسال الرابط لأي شخص.'
  ];

  function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }
  function startTimer() {
    stopTimer();
    if (!paused && autoSwitch?.checked) timer = setInterval(() => setStep(current + 1), 5000);
  }
  function setStep(index) {
    current = (index + steps.length) % steps.length;
    steps.forEach((step, i) => step.classList.toggle('is-tour-active', i === current));
    const active = steps[current];
    active.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const label = active.dataset.tourLabel || active.querySelector('h2')?.textContent || 'خطوة جديدة';
    if (title) title.textContent = label;
    if (copy) copy.textContent = copies[current] || '';
    if (progress) progress.style.width = `${((current + 1) / steps.length) * 100}%`;
    if (next) next.textContent = current === steps.length - 1 ? 'إعادة الجولة' : 'التالي';
    if (prev) prev.disabled = current === 0;
    startTimer();
  }
  function openTour() {
    paused = false;
    if (panel) panel.hidden = false;
    if (scrim) scrim.hidden = false;
    root.classList.add('tour-is-open');
    if (autoSwitch) autoSwitch.checked = true;
    if (toggle) toggle.textContent = 'إيقاف الحركة';
    setStep(0);
  }
  function closeTour() {
    stopTimer();
    root.classList.remove('tour-is-open');
    steps.forEach((step) => step.classList.remove('is-tour-active'));
    if (panel) panel.hidden = true;
    if (scrim) scrim.hidden = true;
  }
  next?.addEventListener('click', () => setStep(current + 1));
  prev?.addEventListener('click', () => setStep(current - 1));
  replay?.addEventListener('click', openTour);
  close?.addEventListener('click', closeTour);
  toggle?.addEventListener('click', () => {
    paused = !paused;
    toggle.textContent = paused ? 'تشغيل الحركة' : 'إيقاف الحركة';
    if (paused) stopTimer(); else startTimer();
  });
  autoSwitch?.addEventListener('change', startTimer);
  openTour();
})();
