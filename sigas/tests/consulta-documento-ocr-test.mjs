import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const require = createRequire(import.meta.url);
const ocr = require("../assets/js/cpf-ocr.js");
const root = resolve(import.meta.dirname, "..");
const php = readFileSync(resolve(root, "consulta-documento.php"), "utf8");
const consultaJs = readFileSync(resolve(root, "assets/js/consulta-documento.js"), "utf8");
const api = readFileSync(resolve(root, "api/comida-mesa/consultar-cpf.php"), "utf8");
const anexoService = readFileSync(resolve(root, "app/Integrations/Anexo/AnexoIntegrationService.php"), "utf8");

const makeCpf = (base) => {
    const digits = String(base).replace(/\D/g, "").padStart(9, "0").slice(0, 9);
    const calculate = (value, factor) => {
        let sum = 0;
        for (let index = 0; index < value.length; index += 1) sum += Number(value[index]) * (factor - index);
        const rest = (sum * 10) % 11;
        return rest === 10 ? 0 : rest;
    };
    const first = calculate(digits, 10);
    return `${digits}${first}${calculate(`${digits}${first}`, 11)}`;
};

const cpfA = makeCpf("123456789");
const cpfB = makeCpf("987654321");
const formattedA = ocr.formatCpf(cpfA);

assert.deepEqual(ocr.extractCpfFromNumericRegion(formattedA), [{ cpf: cpfA, formatted: formattedA }]);
assert.equal(ocr.extractCpfFromNumericRegion(cpfA)[0].cpf, cpfA);
assert.equal(ocr.extractCpfFromNumericRegion(`${cpfA.slice(0, 3)} ${cpfA.slice(3, 6)} ${cpfA.slice(6, 9)} ${cpfA.slice(9)}`)[0].cpf, cpfA);
assert.deepEqual(ocr.extractCpfFromNumericRegion("123.456.789-00"), []);
assert.deepEqual(ocr.extractCpfFromNumericRegion("000.000.000-00"), []);
assert.equal(ocr.extractCpfFromNumericRegion(`${formattedA} ${ocr.formatCpf(cpfB)}`).length, 2);
assert.deepEqual(ocr.extractCpfFromNumericRegion(`${cpfA}9`), []);
assert.equal(ocr.extractCpfFromNumericRegion(`linha ${cpfA}`)[0].cpf, cpfA);
assert.equal(ocr.selectCpfResult(`CPF: ${formattedA}`).state, "single");
assert.equal(ocr.onlyDigits("123.456.789-09"), "12345678909");
assert.equal(ocr.formatCpf(cpfA), formattedA);

assert.match(php, /tesseract\.js@7\.0\.0\/dist\/tesseract\.min\.js/);
assert.doesNotMatch(php, /tesseract\.js@latest|BarcodeDetector|QRCode/i);
assert.match(php, /id="cpfScanRegion"/);
assert.match(php, /class="cpf-scan-mask"/);
assert.match(php, /name="consulta_modo"\s+value="entrega_rapida"/);
assert.ok(php.indexOf("assets/css/style.css") < php.indexOf("assets/css/consulta-documento-ocr.css"));
assert.ok(php.indexOf("tesseract.js@7.0.0") < php.indexOf("assets/js/cpf-ocr.js"));
assert.ok(php.indexOf("assets/js/cpf-ocr.js") < php.indexOf("assets/js/consulta-documento.js"));

assert.match(consultaJs, /const CPF_ROI = Object\.freeze\(\{\s*x: 0\.06,\s*y: 0\.39,\s*width: 0\.88,\s*height: 0\.22\s*\}\)/);
assert.match(consultaJs, /tessedit_pageseg_mode:\s*"7"/);
assert.match(consultaJs, /tessedit_char_whitelist:\s*"0123456789\.- "/);
assert.doesNotMatch(consultaJs.match(/tessedit_char_whitelist:\s*"([^"]+)"/)?.[1] || "", /[A-Za-z]/);
assert.match(consultaJs, /classify_bln_numeric_mode:\s*"1"/);
assert.match(consultaJs, /function captureCpfRegionFromVideo|const captureCpfRegionFromVideo/);
assert.match(consultaJs, /sourceX[\s\S]*sourceY[\s\S]*sourceWidth[\s\S]*sourceHeight/);
assert.match(consultaJs, /drawImage\(videoEl,\s*sourceX,\s*sourceY,\s*sourceWidth,\s*sourceHeight,\s*0,\s*0/);
assert.doesNotMatch(consultaJs, /canvas\.width\s*=\s*video\.videoWidth|toDataURL|maxSide\s*>\s*2200|buildOcrCanvases/);
assert.doesNotMatch(consultaJs, /return \[canvas,\s*binary\]|for \(let index = 0; index < canvases\.length/);
assert.match(consultaJs, /liveScanBusy/);
assert.match(consultaJs, /lastConsultedCpf/);
assert.match(consultaJs, /scanTimer = setTimeout\(\(\) => runLiveScanAttempt\(runId\), 250\)/);
assert.match(consultaJs, /retry\.addEventListener\("click", startCamera\)/);
assert.match(consultaJs, /resetInterface[\s\S]*stopCamera\(\)[\s\S]*revokePreviewUrl/);
assert.doesNotMatch((consultaJs.match(/const resetInterface[\s\S]*?};/) || [""])[0], /destroyOcrWorker|terminate/);
assert.match(consultaJs, /pagehide", releaseLocalMedia/);
assert.match(consultaJs, /const releaseLocalMedia = \(\) => \{ resetInterface\(\); destroyOcrWorker\(\); \}/);
assert.doesNotMatch(consultaJs, /localStorage|sessionStorage|indexedDB|IndexedDB|console\.log/);
assert.doesNotMatch(consultaJs, /fetch\([^)]*(Blob|blob|image|preview|canvas)/s);
assert.doesNotMatch(consultaJs, /FormData\([^)]*(file|image|blob|canvas|preview)/i);

assert.match(api, /\$consultaModo/);
assert.match(api, /\['completa', 'entrega_rapida'\]/);
assert.match(api, /\(\$payload\['state'\] \?\? ''\) !== 'inscrito'/);
assert.match(api, /consultCpfBasic\(\$cpf\)/);

const basicBlock = anexoService.match(/public function consultCpfBasic[\s\S]*?public function summary/)?.[0] || "";
assert.match(basicBlock, /findSolicitanteByCpf/);
assert.doesNotMatch(basicBlock, /familiares\(|solicitacoes\(|entregasPorPessoa\(/);

console.log("consulta-documento OCR tests passed");
