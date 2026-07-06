import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const require = createRequire(import.meta.url);
const ocr = require("../assets/js/cpf-ocr.js");
const root = resolve(import.meta.dirname, "..");
const php = readFileSync(resolve(root, "consulta-documento.php"), "utf8");
const consultaJs = readFileSync(resolve(root, "assets/js/consulta-documento.js"), "utf8");

const makeCpf = (base) => {
    const digits = String(base).replace(/\D/g, "").padStart(9, "0").slice(0, 9);
    const calculate = (value, factor) => {
        let sum = 0;
        for (let index = 0; index < value.length; index += 1) {
            sum += Number(value[index]) * (factor - index);
        }
        const rest = (sum * 10) % 11;
        return rest === 10 ? 0 : rest;
    };
    const first = calculate(digits, 10);
    const second = calculate(`${digits}${first}`, 11);
    return `${digits}${first}${second}`;
};

const cpfA = makeCpf("123456789");
const cpfB = makeCpf("987654321");
const cpfC = makeCpf("102938475");

assert.equal(ocr.onlyDigits("123.456.789-09"), "12345678909");
assert.equal(ocr.formatCpf(cpfA), `${cpfA.slice(0, 3)}.${cpfA.slice(3, 6)}.${cpfA.slice(6, 9)}-${cpfA.slice(9)}`);

assert.equal(ocr.selectCpfResult(`CPF: ${ocr.formatCpf(cpfA)}`).state, "single");
assert.equal(ocr.selectCpfResult(`Documento ${cpfA}`).state, "single");
assert.equal(ocr.selectCpfResult(`CPF ${cpfA.slice(0, 3)} ${cpfA.slice(3, 6)} ${cpfA.slice(6, 9)} ${cpfA.slice(9)}`).state, "single");
assert.equal(ocr.selectCpfResult(`CPF: ${cpfA}`).candidates[0].cpf, cpfA);
assert.equal(ocr.selectCpfResult(`CPF/MF ${cpfA}`).candidates[0].cpf, cpfA);

const withZeros = makeCpf("123450789");
const withOnes = makeCpf("123451789");
assert.equal(ocr.selectCpfResult(`CPF: ${ocr.formatCpf(withZeros).replaceAll("0", "O")}`).candidates[0].cpf, withZeros);
assert.equal(ocr.selectCpfResult(`CPF: ${ocr.formatCpf(withOnes).replaceAll("1", "I").replace("I", "L")}`).candidates[0].cpf, withOnes);

assert.equal(ocr.isValidCpf("12345678900"), false);
assert.equal(ocr.isValidCpf("00000000000"), false);
assert.equal(ocr.selectCpfResult("RG 123456789").state, "not_found");
assert.equal(ocr.selectCpfResult("Nascimento 01/02/1980").state, "not_found");
assert.equal(ocr.selectCpfResult("Telefone 9291515710").state, "not_found");
assert.equal(ocr.selectCpfResult("CEP 69460000").state, "not_found");

const duplicate = ocr.extractCpfCandidates(`CPF ${cpfA}\nCPF/MF ${ocr.formatCpf(cpfA)}`);
assert.equal(duplicate.length, 1);

const multiple = ocr.selectCpfResult(`CPF ${ocr.formatCpf(cpfA)}\nCPF/MF ${ocr.formatCpf(cpfB)}`);
assert.equal(multiple.state, "multiple");
assert.deepEqual(ocr.selectCpfResult("documento sem cpf impresso"), { state: "not_found", candidates: [] });

const priority = ocr.extractCpfCandidates(`Outro numero ${cpfC}\nCPF ${cpfA}`);
assert.equal(priority[0].cpf, cpfA);
assert.ok(!priority.some((item) => /nome|filiacao|endereco|documento/i.test(item.context)));

assert.match(php, /tesseract\.js@7\.0\.0\/dist\/tesseract\.min\.js/);
assert.doesNotMatch(php, /tesseract\.js@latest|tesseract\.js\/dist/);
assert.ok(php.indexOf("bootstrap.bundle.min.js") < php.indexOf("assets/js/app.js"));
assert.ok(php.indexOf("tesseract.js@7.0.0") < php.indexOf("assets/js/cpf-ocr.js"));
assert.ok(php.indexOf("assets/js/cpf-ocr.js") < php.indexOf("assets/js/consulta-documento.js"));
assert.match(php, /id="ocrStatus"/);
assert.match(php, />\s*Ler CPF\s*</);
assert.match(php, /filemtime\(\s*__DIR__\s*\.\s*'\/assets\/js\/cpf-ocr\.js'/);
assert.match(php, /filemtime\(\s*__DIR__\s*\.\s*'\/assets\/css\/consulta-documento-ocr\.css'/);

assert.match(consultaJs, /await\s+consult\(\)/);
assert.doesNotMatch(consultaJs, /localStorage|sessionStorage|indexedDB|IndexedDB/);
assert.doesNotMatch(consultaJs, /console\.log/);
assert.doesNotMatch(consultaJs, /fetch\([^)]*(Blob|blob|image|preview|canvas)/s);
assert.doesNotMatch(consultaJs, /FormData\([^)]*(file|image|blob|canvas|preview)/i);

console.log("consulta-documento OCR tests passed");
