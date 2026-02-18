/* ========= Helper DOM ========= */
const $ = (id) => document.getElementById(id);

/* ========= Hook UI dasar ========= */
const fAll = $('fileAll');
if (fAll) {
  $('pickAll').onclick = () => fAll.click();
  fAll.addEventListener('change', () => {
    const names = [...fAll.files].map(f => f.name).join(', ');
    $('nameAll').textContent = names || 'Belum ada file dipilih (bisa pilih beberapa sekaligus)';
  });
}

/* ========= Utils ========= */
const norm = s => (s || '').toString().toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
const fmt = n => {
  if (n === undefined || n === null || n === '') return '';
  const x = Number(String(n).replace(/[^\d.-]/g, ''));
  return Number.isFinite(x) ? new Intl.NumberFormat('id-ID').format(x) : n;
};
const readXlsx = f => new Promise((res, rej) => {
  const r = new FileReader();
  r.onload = e => {
    try {
      const wb = XLSX.read(e.target.result, { type: 'array' });
      const ws = wb.Sheets[wb.SheetNames[0]];
      res({ wb, ws });
    } catch (err) { rej(err); }
  };
  r.onerror = rej;
  r.readAsArrayBuffer(f);
});

/* Ambil header terbaik (baris yang paling banyak terisi) lalu buat rows */
function sheetToRows(ws) {
  const range = XLSX.utils.decode_range(ws['!ref']);
  const maxProbe = Math.min(8, (range.e.r - range.s.r + 1));
  let bestIdx = 0, bestScore = -1;
  for (let i = 0; i < maxProbe; i++) {
    let score = 0;
    for (let c = range.s.c; c <= range.e.c; c++) {
      const addr = XLSX.utils.encode_cell({ r: i, c: c });
      const v = ws[addr]?.v;
      if (v !== undefined && String(v).trim() !== '') score++;
    }
    if (score > bestScore) { bestScore = score; bestIdx = i; }
  }
  const hdr = XLSX.utils.sheet_to_json(ws, { header: 1, range: bestIdx })[0] || [];
  const data = XLSX.utils.sheet_to_json(ws, { defval: '', header: hdr, range: bestIdx + 1 });
  const cols = (hdr || []).map(h => String(h || '').trim());
  return { cols, data };
}

/* Deteksi tipe tabel */
function guessType(cols) {
  const n = cols.map(norm);
  const hitRing = ['cabang usaha', 'nelayan', 'armada', 'alat tangkap', 'ikan segar', 'nilai']
    .reduce((a, k) => a + (n.some(c => c.includes(k)) ? 1 : 0), 0);
  const hitProd = ['wilayah', 'komoditas', 'volume']
    .reduce((a, k) => a + (n.some(c => c.includes(k)) ? 1 : 0), 0);
  if (hitRing >= 2) return 'ringkasan';
  if (hitProd >= 2) return 'produksi';
  return 'unknown';
}

/* Mapper toleran variasi header */
const mapRing = row => {
  const m = {};
  for (const k in row) m[norm(k)] = row[k];
  return {
    usaha: m[norm('Cabang Usaha')] ?? row['Cabang Usaha'] ?? '',
    nelayan: m[norm('Nelayan (Orang)')] ?? m[norm('Nelayan')] ?? '',
    armada: m[norm('Armada Perikanan (Buah)')] ?? m[norm('Armada')] ?? '',
    alat: m[norm('Alat Tangkap (Unit)')] ?? m[norm('Alat Tangkap')] ?? '',
    segar: m[norm('Ikan Segar (Ton)')] ?? m[norm('Ikan Segar')] ?? '',
    nilai: m[norm('Nilai (Rp. 1.000,-)')] ?? m[norm('Nilai')] ?? ''
  };
};
const mapProd = row => {
  const m = {};
  for (const k in row) m[norm(k)] = row[k];
  return {
    wilayah: m[norm('Wilayah')] ?? row['Wilayah'] ?? '',
    komoditas: m[norm('Komoditas')] ?? row['Komoditas'] ?? '',
    volume: m[norm('Volume (Ton)')] ?? m[norm('Volume')] ?? row['Volume (Ton)'] ?? ''
  };
};

/* Renderer tabel */
function renderRingkasan(list) {
  if (!list.length) return;
  const tb = $('tbodyRingkasan');
  tb.innerHTML = list.map(r => `<tr>
    <td class="border px-3 py-2">${r.usaha || ''}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.nelayan)}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.armada)}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.alat)}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.segar)}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.nilai)}</td>
  </tr>`).join('');
  $('wrapRingkasan').classList.remove('hidden');
  $('emptyRingkasan').classList.add('hidden');
}
function renderProduksi(list) {
  if (!list.length) return;
  const tb = $('tbodyProduksi');
  tb.innerHTML = list.map(r => `<tr>
    <td class="border px-3 py-2">${r.wilayah || ''}</td>
    <td class="border px-3 py-2">${r.komoditas || ''}</td>
    <td class="border px-3 py-2 text-right">${fmt(r.volume)}</td>
  </tr>`).join('');
  $('wrapProduksi').classList.remove('hidden');
  $('emptyProduksi').classList.add('hidden');
}

/* ====== Tombol Upload ====== */
$('btnUpload')?.addEventListener('click', async () => {
  const status = $('status');
  const tahun = $('tahun').value;
  $('tahunRingkasan').textContent = tahun;
  $('tahunProduksi').textContent = tahun;

  if (!fAll?.files?.length) { status.textContent = 'Pilih minimal satu file.'; return; }
  status.textContent = 'Memproses file...';

  const aggRing = [];
  const aggProd = [];
  const unknowns = [];

  for (const f of fAll.files) {
    try {
      const { ws } = await readXlsx(f);
      const { cols, data } = sheetToRows(ws);
      const type = guessType(cols);
      const cleaned = data.filter(r => Object.values(r).some(v => String(v).trim() !== ''));

      if (type === 'ringkasan') aggRing.push(...cleaned.map(mapRing).filter(r => r.usaha));
      else if (type === 'produksi') aggProd.push(...cleaned.map(mapProd).filter(r => r.wilayah || r.komoditas));
      else unknowns.push(f.name);
    } catch (err) {
      console.error('Gagal baca:', f.name, err);
      unknowns.push(`${f.name} (gagal dibaca)`);
    }
  }

  if (aggRing.length) renderRingkasan(aggRing);
  if (aggProd.length) renderProduksi(aggProd);

  const parts = [];
  if (aggRing.length) parts.push(`Ringkasan: ${aggRing.length} baris`);
  if (aggProd.length) parts.push(`Produksi: ${aggProd.length} baris`);
  if (unknowns.length) parts.push(`Tidak terdeteksi: ${unknowns.length} file`);
  status.textContent = parts.join(' • ') || 'Tidak ada data yang dapat ditampilkan.';
});

/* ====== Tombol Reset ====== */
$('btnReset')?.addEventListener('click', () => {
  if (!fAll) return;
  fAll.value = '';
  $('nameAll').textContent = 'Belum ada file dipilih (bisa pilih beberapa sekaligus)';

  $('tahunRingkasan').textContent = '-';
  $('tahunProduksi').textContent = '-';

  $('tbodyRingkasan').innerHTML = '';
  $('tbodyProduksi').innerHTML = '';

  $('wrapRingkasan').classList.add('hidden');
  $('wrapProduksi').classList.add('hidden');

  $('status').textContent = '';
});
