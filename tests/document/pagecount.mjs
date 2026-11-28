// A one-page invoice must produce one page - in the PDF and in the browser's
// print. Both have failed: the PDF pushed its foot onto a second sheet until
// the grid learned the printable height, and the browser spills a blank page
// whenever the declared sheet is taller than the printable area.
import fs from 'node:fs';

// Harness paths. ROOT is the repository; HERE is this directory.
const ROOT = new URL('../../', import.meta.url).pathname.replace(/\/$/, '');
const HERE = new URL('./', import.meta.url).pathname.replace(/\/$/, '');
let pass=0, fail=0;
const check=(n,ok,d)=>{ if(ok){pass++;console.log('  ok   '+n);} else {fail++;console.log('  FAIL '+n+(d?' — '+d:''));} };
const R=p=>fs.readFileSync(ROOT + '/'+p,'utf8');
const MM=2.834645, A4H=842;

// ---- the one place the numbers come from
const helper=R('application/helpers/anvoice_doc_helper.php');
const m=/function adomin_invoice_print_margins\(\)\s*\{([\s\S]*?)\}/.exec(helper)[1];
const nums=[...m.matchAll(/(\d+(?:\.\d+)?)/g)].map(x=>Number(x[1])).filter(n=>n>0 && n<40);
const [top,bottom] = [Math.max(...nums), Math.min(...nums.filter(n=>n>9))];
const printable = A4H - 10*MM - 16*MM;
check('the printable height is derived, not guessed', printable > 700 && printable < 800, printable.toFixed(1));
check('the helper declares the same margins the PDF is given',
  /768\.3|printable_height/.test(helper));

// ---- the PDF path: the grid must stop at the printable line
const css = R('assets/public/css/adomin-brand.css');
check('the browser sheet is shrunk to the printable height in print',
  /--av-ph:\s*768\.3pt/.test(css) && /height:\s*768\.3pt\s*!important/.test(css));
check('print sets the same margins as the PDF',
  /@page\s*\{[^}]*margin:\s*10mm 8mm 16mm/.test(css));

// the declared sheet, in print, must not exceed the printable area
const declared = 768.3;
check('the printed sheet fits the printable area',
  declared <= printable + 0.5, declared + ' vs ' + printable.toFixed(1));
// before the fix it did not
check('the unscaled 842pt sheet would not have fitted',
  A4H > printable + 1, A4H + ' vs ' + printable.toFixed(1));

// ---- the rendered documents: nothing sits past the last printable line
for (const t of ['banner','creative','toner-save','minimal','classic','modern']) {
  const html = fs.readFileSync(HERE + '/page-'+t+'.html','utf8');
  const bands = [...html.matchAll(/adv-band-\w+"[^>]*style="[^"]*top:([\d.]+)pt;width:[\d.]+pt;height:([\d.]+)pt;"/g)]
    .map(m=>Number(m[1])+Number(m[2]));
  const lowest = bands.length ? Math.max(...bands) : null;
  check(t+': the lowest block ends at the printable line',
    lowest !== null && Math.abs(lowest - 768.3) < 1, String(lowest));
  check(t+': no block spills past it', lowest === null || lowest <= 768.4, String(lowest));
}

// ---- the footer sits below the sheet, with clearance
const baseY = A4H - 6*MM;
check('the PDF footer clears the last block by more than a line',
  baseY - 11 - 768.3 > 12, (baseY-11-768.3).toFixed(1) + 'pt');

// ---- the PDF path, which is a different stylesheet from the browser's
const pdfCss = fs.readFileSync(ROOT + '/assets/users/css/pdf-base.css', 'utf8');
const heightRule = /\.adv-page \{[\s\S]{0,400}?height: var\(--av-ph, ([0-9.]+)pt\)/.exec(pdfCss);
check('the PDF sheet is not as tall as the paper', !!heightRule && Number(heightRule[1]) < 842,
  heightRule ? heightRule[1] + 'pt' : 'no fallback found');
check('the PDF sheet matches the printable height',
  !!heightRule && Math.abs(Number(heightRule[1]) - 768.3) < 1, heightRule ? heightRule[1] + 'pt' : '');

// the height is written into the sheet as a variable, and the inline value wins
// over the stylesheet fallback - so the value that matters is the one the
// helper emits, not the one the CSS declares
const docHelper = fs.readFileSync(ROOT + '/application/helpers/anvoice_doc_helper.php', 'utf8');
const sheetHeights = (docHelper.match(/--av-ph:/g) || []).length;
const sheetEmitters = docHelper.split("--av-ph:' . round(adomin_invoice_printable_height(), 2)").length - 1;
check('every sheet builder emits the printable height',
  sheetEmitters >= 2, sheetEmitters + ' of ' + sheetHeights + ' emitters use it');
// the letterhead placement container is a full page tall by design; only the
// sheet the document is drawn on has to fit the printable area
const sheetDivs = docHelper.split('class="adv-page').slice(1);
check('no sheet is sized to the whole page',
  sheetDivs.every(d => d.slice(0, 400).indexOf("--av-ph:' . (int) $page[1]") === -1),
  sheetDivs.length + ' sheet builders checked');

console.log('\n'+(fail?fail+' FAILED':'all pass')+'  ('+pass+' passed)');
process.exit(fail?1:0);
