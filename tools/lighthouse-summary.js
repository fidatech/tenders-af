// Turns Lighthouse JSON reports into a short text summary: scores, then every audit that is not passing.
const fs = require('fs');
for (const file of process.argv.slice(2)) {
  let r;
  try { r = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (e) { console.log(`== ${file}: no report (${e.message})`); continue; }
  console.log(`== ${file}  ${r.finalDisplayedUrl || r.finalUrl}`);
  if (r.runtimeError) console.log('runtime error:', r.runtimeError.message);
  for (const c of Object.values(r.categories)) console.log(`  ${c.title}: ${c.score === null ? '-' : Math.round(c.score * 100)}`);
  const m = r.audits;
  for (const k of ['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index']) {
    if (m[k]) console.log(`  ${m[k].title}: ${m[k].displayValue}`);
  }
  const lcpEl = m['largest-contentful-paint-element'];
  if (lcpEl && lcpEl.details && lcpEl.details.items) {
    const it = JSON.stringify(lcpEl.details.items).match(/"snippet":"(.*?)"/);
    if (it) console.log('  LCP element:', it[1].slice(0, 200));
  }
  for (const k of ['mainthread-work-breakdown', 'long-tasks', 'dom-size', 'bootup-time']) {
    const a = m[k]; if (!a) continue;
    const raw = a.details && a.details.items; const items = Array.isArray(raw) ? raw : [];
    console.log(`  ${k}${a.displayValue ? ' (' + a.displayValue + ')' : ''}: ` + items.slice(0, 5).map(i => JSON.stringify(i).slice(0, 160)).join(' | '));
  }
  console.log('  -- not passing:');
  for (const [id, a] of Object.entries(m)) {
    if (a.score === null || a.score >= 1 || ['informative', 'notApplicable', 'manual'].includes(a.scoreDisplayMode)) continue;
    console.log(`  [${a.score}] ${id}: ${a.title}${a.displayValue ? ' (' + a.displayValue + ')' : ''}`);
    const raw = a.details && a.details.items; const items = Array.isArray(raw) ? raw : (raw ? [raw] : []);
    for (const it of items.slice(0, 6)) {
      const s = JSON.stringify(it).replace(/\s+/g, ' ');
      console.log('      ' + s.slice(0, id.startsWith('cls') || id === 'layout-shifts' ? 1500 : 300));
    }
  }
  console.log('');
}
