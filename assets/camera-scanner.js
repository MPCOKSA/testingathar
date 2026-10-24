(() => {
  const scanner = document.querySelector('[data-camera-scanner]');
  if (!scanner) return;
  const open = scanner.querySelector('[data-camera-open]');
  const close = scanner.querySelector('[data-camera-close]');
  const capture = scanner.querySelector('[data-camera-capture]');
  const enhance = scanner.querySelector('[data-camera-enhance]');
  const panel = scanner.querySelector('[data-camera-panel]');
  const video = scanner.querySelector('[data-camera-video]');
  const canvas = scanner.querySelector('[data-camera-canvas]');
  const status = scanner.querySelector('[data-camera-status]');
  const input = document.querySelector('[data-upload-form] input[type=file]');
  let stream = null;
  const stop = () => { stream?.getTracks().forEach((track) => track.stop()); stream = null; };
  const setFile = (blob) => {
    if (!input || !blob) return;
    const file = new File([blob], `شاهد-${new Date().toISOString().slice(0,10)}.jpg`, {type:'image/jpeg'});
    try { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; input.dispatchEvent(new Event('change', {bubbles:true})); status.textContent = 'تم تجهيز الصورة. أكمل بيانات الشاهد ثم اضغط رفع الدليل.'; } catch { status.textContent = 'تم الالتقاط؛ اختر الصورة من المعاينة أو أعد المحاولة.'; }
  };
  open?.addEventListener('click', async () => {
    if (!navigator.mediaDevices?.getUserMedia) { status.textContent = 'الكاميرا غير مدعومة في هذا المتصفح؛ استخدم اختيار ملف أو التقط الصورة من الهاتف.'; return; }
    try { stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1920},height:{ideal:1080}},audio:false}); video.srcObject = stream; panel.hidden = false; open.hidden = true; status.textContent = 'وجّه الكاميرا إلى الشاهد واجعل الحواف واضحة.'; } catch { status.textContent = 'تعذر فتح الكاميرا. تحقق من إذن الكاميرا واستخدم HTTPS عند النشر.'; }
  });
  capture?.addEventListener('click', () => {
    if (!video.videoWidth) { status.textContent = 'انتظر ظهور صورة الكاميرا ثم اضغط التقاط.'; return; }
    canvas.width = video.videoWidth; canvas.height = video.videoHeight; canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height); canvas.hidden = false;
    canvas.toBlob(setFile,'image/jpeg',.92); status.textContent = 'تم التقاط الشاهد. يمكنك تحسينه أو رفعه.';
  });
  enhance?.addEventListener('click', () => {
    if (!canvas.width) { status.textContent = 'التقط صورة أولًا.'; return; }
    const ctx=canvas.getContext('2d'); const image=ctx.getImageData(0,0,canvas.width,canvas.height); const data=image.data;
    for(let i=0;i<data.length;i+=4){ const gray=.299*data[i]+.587*data[i+1]+.114*data[i+2]; const contrast=Math.max(0,Math.min(255,(gray-128)*1.28+128)); data[i]=data[i+1]=data[i+2]=contrast; }
    ctx.putImageData(image,0,0); canvas.toBlob(setFile,'image/jpeg',.94); status.textContent='تم تحسين التباين وتحويل الشاهد إلى قراءة أوضح.';
  });
  close?.addEventListener('click', () => { stop(); panel.hidden=true; open.hidden=false; });
  window.addEventListener('pagehide', stop);
})();
