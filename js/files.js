/* files.js — clean navy header, tidy table, dblclick open (Office viewer), robust API base */
const API_BASE_URL = (()=>{
  try {
    const here = window.location.pathname;      // e.g. /samudera/files.php
    return here.replace(/\/[^\/]*$/, '') + '/api';
  } catch { return './api'; }
})();

const PAGE_SIZE = 10;
const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));

let applyFilters = false;
let currentData  = [];
let selectedIds  = new Set();
let selectMode   = false;
let page         = 1;

// -------- util --------
function fmtBytes(n){ if (n == null) return ''; const u=['B','KB','MB','GB','TB']; let i=0; let x=Number(n); while(x>=1024 && i<u.length-1){ x/=1024; i++; } return `${x.toFixed(2)} ${u[i]}`; }
function labelBidang(v){ if (!v) return '—'; const m={tangkap:'Perikanan Tangkap', budidaya:'Perikanan Budidaya', kpp:'KPP', pengolahan:'Pengolahan & Pemasaran', ekspor:'Ekspor Perikanan', investasi:'Investasi KP'}; return m[v] || v; }
function debounce(fn, ms=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
function getExt(name=''){ const p=name.toLowerCase().split('.'); return p.length>1?p.pop():''; }

// -------- data --------
async function fetchFiles(){
  const p = new URLSearchParams();
  if (applyFilters){
    const s = $('#search-input')?.value ?? '';
    const b = $('#bidang-filter')?.value || '';
    const t = $('#tahun-filter')?.value?.trim() || '';
    if (s.trim()) p.set('search', s);
    if (b) p.set('bidang', b);
    if (t) p.set('tahun', t);
  }
  p.set('_', Date.now());
  const r = await fetch(`${API_BASE_URL}/files.php?action=list&${p.toString()}`, {credentials:'same-origin'});
  const j = await r.json();
  if (!j.success) throw new Error(j.message || 'Gagal memuat');
  return j.data || [];
}

// -------- open / preview --------
async function openFile(id, filename){
  try{
    const res = await fetch(`${API_BASE_URL}/files.php?action=download&id=${encodeURIComponent(id)}`);
    const j = await res.json();
    if(!j.success) throw new Error(j.message||'Gagal mengambil URL');
    const fileUrl = new URL(j.url, location.origin).href;
    const name = filename || j.filename || '';
    const ext  = getExt(name);

    const office = ['doc','docx','xls','xlsx','ppt','pptx'];
    if (office.includes(ext)){
      window.open(`https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(fileUrl)}`, '_blank');
      return;
    }
    window.open(fileUrl, '_blank'); // pdf,img,txt,csv,dll
  }catch(e){ alert(e.message); }
}

// -------- render --------
function updateBulkBar(){
  const bar = $('#bulkbar');
  const count = selectedIds.size;
  $('#bulk-count').textContent = String(count);
  bar.classList.toggle('show', count>0);
}

function buildPager(total){
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  if (page > totalPages) page = totalPages;

  const wrap = $('#pager');
  const btn = (label, p, disabled=false, active=false, title='')=>{
    const b = document.createElement('button');
    b.className = 'page-btn' + (active?' active':'');
    b.textContent = label;
    if (title) b.title = title;
    b.disabled = disabled;
    b.onclick = ()=>{ page = p; render(currentData); };
    return b;
  };

  wrap.innerHTML = '';
  wrap.append(btn('<<', 1, page===1, false, 'Halaman pertama'),
              btn('<', Math.max(1,page-1), page===1, false, 'Sebelumnya'));

  const show = 5;
  let start = Math.max(1, page - Math.floor(show/2));
  let end   = Math.min(totalPages, start + show - 1);
  start = Math.max(1, end - show + 1);
  for(let i=start;i<=end;i++) wrap.append(btn(String(i), i, false, i===page));

  wrap.append(btn('>',  Math.min(totalPages,page+1), page===totalPages, false, 'Berikutnya'),
              btn('>>', totalPages, page===totalPages, false, 'Halaman terakhir'));
}

function render(rows){
  const tb = $('#files-table tbody');

  const total = rows.length;
  const start = (page-1)*PAGE_SIZE;
  const slice = rows.slice(start, start + PAGE_SIZE);

  tb.innerHTML = slice.map((r, idx)=>`
    <tr data-id="${r.id}" data-name="${r.name}">
      <td style="text-align:center; font-weight:800">${start+idx+1}</td>
      <td><div class="font-semibold">${r.name}</div></td>
      <td>${labelBidang(r.bidang)}</td>
      <td>${r.tahun || ''}</td>
      <td>${fmtBytes(r.size)}</td>
      <td>${new Date(r.modified).toLocaleString('id-ID')}</td>
      <td style="text-align:right; display:flex; gap:6px; justify-content:flex-end">
        <button class="btn btn-icon" data-dl="${r.id}" type="button" title="Download"><i class="fa-solid fa-download"></i></button>
        <button class="btn btn-icon btn-danger" data-del="${r.id}" type="button" title="Hapus"><i class="fa-solid fa-trash"></i></button>
      </td>
    </tr>
  `).join('');

  // selection & double click
  $$('#files-table tbody tr').forEach(tr=>{
    const id   = String(tr.dataset.id);
    const name = tr.dataset.name || '';

    tr.onclick = e=>{
      if (e.target.closest('[data-dl],[data-del]')) return;
      if (!selectMode) return;
      tr.classList.toggle('sel');
      if (tr.classList.contains('sel')) selectedIds.add(id); else selectedIds.delete(id);
      updateBulkBar();
    };
    tr.ondblclick = e=>{
      if (e.target.closest('[data-dl],[data-del]')) return;
      if (selectMode) return;
      openFile(id, name);
    };
  });

  // actions
  tb.querySelectorAll('[data-dl]').forEach(b=>b.onclick=()=>downloadFile(b.dataset.dl));
  tb.querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>deleteFile(b.dataset.del));

  // hint
  if (applyFilters){
    const b = $('#bidang-filter')?.value || '';
    const t = $('#tahun-filter')?.value?.trim();
    $('#active-hint').textContent = `Filter aktif: ${(b?labelBidang(b)+', ':'')}${t||'semua tahun'} — ${total} file`;
  } else {
    const years = rows.map(r=>parseInt(r.tahun||0)).filter(n=>n>0);
    $('#active-hint').textContent = (years.length? `Terbaru: ${Math.max(...years)}` : 'Semua data') + ` — ${total} file`;
  }

  buildPager(total);
  updateBulkBar();
}

async function load(){
  try{ currentData = await fetchFiles(); page = 1; render(currentData); }
  catch(e){ console.error(e); alert('Gagal memuat daftar file.'); }
}

// -------- actions --------
async function downloadFile(id){
  try{
    const res = await fetch(`${API_BASE_URL}/files.php?action=download&id=${encodeURIComponent(id)}`);
    const j = await res.json();
    if(!j.success) throw new Error(j.message||'Download error');
    const a=document.createElement('a'); a.href=j.url; a.download=j.filename;
    document.body.appendChild(a); a.click(); a.remove();
  }catch(e){ alert(e.message); }
}
async function deleteFile(id){
  if(!confirm('Hapus file ini?')) return;
  try{
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    const res = await fetch(`${API_BASE_URL}/files.php`, {method:'POST', body:fd});
    const j = await res.json(); if(!j.success) throw new Error(j.message||'Gagal hapus');
    selectedIds.delete(String(id));
    await load();
  }catch(e){ alert(e.message); }
}
async function bulkDelete(){
  if (selectedIds.size === 0) return;
  if (!confirm(`Hapus ${selectedIds.size} file terpilih?`)) return;
  try{
    for (const id of Array.from(selectedIds)){
      const fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
      const res = await fetch(`${API_BASE_URL}/files.php`, {method:'POST', body:fd});
      const j = await res.json();
      if (!j.success) throw new Error(j.message||`Gagal hapus id ${id}`);
      selectedIds.delete(id);
    }
    await load();
  }catch(e){ alert(e.message); }
}

// -------- toolbar & filter --------
function setupToolbar(){
  $('#btn-toggle-select')?.addEventListener('click', ()=>{
    selectMode = !selectMode;
    $('#btn-toggle-select').classList.toggle('btn-ghost', selectMode);
    $('#btn-toggle-select').title = selectMode ? 'Mode pilih aktif (klik baris untuk memilih)' : 'Mode pilih';
    if (!selectMode){ selectedIds.clear(); $$('#files-table tbody tr.sel').forEach(tr=> tr.classList.remove('sel')); }
    updateBulkBar();
  });

  $('#btn-reset')?.addEventListener('click', ()=>{
    if($('#search-input')) $('#search-input').value='';
    if($('#bidang-filter')) $('#bidang-filter').value='';
    if($('#tahun-filter'))  $('#tahun-filter').value='';
    selectedIds.clear(); selectMode=false;
    applyFilters=false; page=1;
    $('#btn-toggle-select')?.classList.remove('btn-ghost');
    load();
  });

  $('#btn-bulk-delete')?.addEventListener('click', bulkDelete);
  $('#btn-bulk-cancel')?.addEventListener('click', ()=>{
    selectedIds.clear(); updateBulkBar();
    $$('#files-table tbody tr.sel').forEach(tr=> tr.classList.remove('sel'));
  });

  const onSearch = debounce(()=>{
    const q = $('#search-input')?.value ?? '';
    applyFilters = (q.trim().length>0) || ($('#bidang-filter')?.value || '') || ($('#tahun-filter')?.value?.trim() || '');
    page = 1;
    load();
  }, 220);
  $('#search-input')?.addEventListener('input', onSearch);

  $('#bidang-filter')?.addEventListener('change', ()=>{ applyFilters = true; page=1; load(); });
  $('#tahun-filter')?.addEventListener('input',  debounce(()=>{ applyFilters = true; page=1; load(); }, 220));
}

// -------- init --------
document.addEventListener('DOMContentLoaded', ()=>{
  if($('#bidang-filter')) $('#bidang-filter').value='';
  if($('#tahun-filter'))  $('#tahun-filter').value='';
  setupToolbar();
  setupUpload(); // upload modal
  load();
});

/* ===== Upload modal (ringkas) ===== */
function setupUpload(){
  const modal     = $('#upload-modal'); if (!modal) return;
  const bidangSel = $('#modal-bidang');
  const tahunInp  = $('#modal-tahun');
  const pickerBtn = $('#btn-choose');
  const fileInput = $('#modal-files');
  const dropzone  = $('#dropzone');
  const pillList  = $('#pill-list');
  const submitBtn = $('#btn-upload-submit');
  const badgeTahun  = $('#badge-tahun');
  const badgeBidang = $('#badge-bidang');

  const files = [];
  const syncBadges = ()=>{
    badgeTahun.textContent  = tahunInp.value || '—';
    const txt = bidangSel.options[bidangSel.selectedIndex]?.text || '—';
    badgeBidang.textContent = txt;
  };
  bidangSel.addEventListener('change', syncBadges);
  tahunInp .addEventListener('input',  syncBadges);

  function drawList(){
    pillList.innerHTML = '';
    files.forEach((item, i)=>{
      const f = item.file;
      const row = document.createElement('div');
      row.className='filepill';
      row.innerHTML = `
        <div style="width:14px"></div>
        <div>
          <div class="nm">${f.name}</div>
          <div class="meta">${fmtBytes(f.size)}</div>
          <div class="progress"><div class="bar" style="width:${item.progress||0}%"></div></div>
        </div>
        <div class="act"><button class="btn btn-danger btn-sm" type="button" title="hapus"><i class="fa-solid fa-xmark"></i></button></div>
      `;
      row.querySelector('.btn-danger').onclick = ()=>{ files.splice(i,1); drawList(); };
      pillList.appendChild(row);
    });
  }
  function addFiles(list){ Array.from(list||[]).forEach(f=> files.push({file:f, progress:0})); drawList(); }

  pickerBtn.addEventListener('click', ()=> fileInput.click());
  fileInput.addEventListener('change', e=> addFiles(e.target.files));

  ;['dragenter','dragover'].forEach(ev=>{
    dropzone.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); dropzone.classList.add('drag'); });
  });
  ;['dragleave','drop'].forEach(ev=>{
    dropzone.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('drag'); });
  });
  dropzone.addEventListener('drop', e=> addFiles(e.dataTransfer.files));
  dropzone.addEventListener('click', e=>{ if (e.target === dropzone) fileInput.click(); });

  submitBtn.addEventListener('click', async ()=>{
    const bidang = bidangSel.value;
    const tahun  = tahunInp.value.trim();
    if (!bidang || !tahun){ alert('Pilih Bidang dan Tahun terlebih dulu.'); return; }
    if (!/^\d{4}$/.test(tahun)){ alert('Tahun harus 4 digit.'); return; }
    if (!files.length){ alert('Pilih minimal satu file.'); return; }

    for (let i=0;i<files.length;i++){
      const item = files[i];
      const f = item.file;
      await new Promise((resolve,reject)=>{
        const fd = new FormData();
        fd.append('bidang', bidang);
        fd.append('tahun',  tahun);
        fd.append('files',  f);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${API_BASE_URL}/upload.php`, true);
        xhr.responseType = 'json';
        xhr.upload.onprogress = e=>{
          if (e.lengthComputable){
            item.progress = Math.round(e.loaded/e.total*100);
            const bar = pillList.children[i]?.querySelector('.bar'); if (bar) bar.style.width = item.progress + '%';
          }
        };
        xhr.onload = ()=>{ const res = xhr.response || {}; if (!res.success) return reject(new Error(res.message || 'Upload gagal')); resolve(); };
        xhr.onerror = ()=> reject(new Error('Network error'));
        xhr.send(fd);
      }).catch(err=> alert(`${f.name}: ${err.message}`));
    }

    applyFilters = false;
    await load();
    $('#upload-modal').classList.remove('show');
    document.body.style.overflow='auto';
    files.length=0; drawList(); fileInput.value='';
  });
}
