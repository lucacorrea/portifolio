((root, factory) => {
    const api = factory();
    if (typeof module === "object" && module.exports) {
        module.exports = api;
    }
    root.SIGAS_CPF_OCR = api;
})(typeof globalThis !== "undefined" ? globalThis : window, () => {
    "use strict";

    const OCR_DIGIT_MAP = new Map([
        ["O", "0"], ["o", "0"], ["Q", "0"], ["q", "0"], ["D", "0"], ["d", "0"],
        ["I", "1"], ["i", "1"], ["L", "1"], ["l", "1"], ["|", "1"], ["!", "1"],
        ["Z", "2"], ["z", "2"],
        ["S", "5"], ["s", "5"],
        ["G", "6"], ["g", "6"],
        ["B", "8"], ["b", "8"],
    ]);
    const OCR_DIGIT_CHARS = "0-9OQoQDdIiLl|!ZzSsGgBb";
    const CPF_MARKER = /C\s*P\s*F(?:\s*\/?\s*M\s*F)?|CADASTRO\s+DE\s+PESSOA\s+F[ÍI]SICA/i;
    const CPF_MF_MARKER = /C\s*P\s*F\s*\/?\s*M\s*F/i;
    const FULL_LABEL_MARKER = /CADASTRO\s+DE\s+PESSOA\s+F[ÍI]SICA/i;

    const onlyDigits = (value) => String(value ?? "").replace(/\D+/g, "");

    const formatCpf = (value) => {
        const digits = onlyDigits(value).slice(0, 11);
        if (digits.length !== 11) return digits;
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    };

    const isValidCpf = (value) => {
        const cpf = onlyDigits(value);
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        const calculate = (factor) => {
            let sum = 0;
            for (let index = 0; index < factor - 1; index += 1) {
                sum += Number(cpf[index]) * (factor - index);
            }
            const rest = (sum * 10) % 11;
            return rest === 10 ? 0 : rest;
        };
        return calculate(10) === Number(cpf[9]) && calculate(11) === Number(cpf[10]);
    };

    const normalizeOcrCandidate = (value) => {
        const text = String(value ?? "");
        let normalized = "";
        for (let index = 0; index < text.length; index += 1) {
            const char = text[index];
            const replacement = OCR_DIGIT_MAP.get(char);
            if (!replacement) {
                normalized += char;
                continue;
            }
            const prev = text[index - 1] || "";
            const next = text[index + 1] || "";
            const nearNumber = /\d|[.\-:/\s]/.test(prev) || /\d|[.\-:/\s]/.test(next);
            normalized += nearNumber ? replacement : char;
        }
        return normalized;
    };

    const contextForSegment = (segment, fallback) => {
        if (CPF_MF_MARKER.test(segment)) return "CPF/MF";
        if (FULL_LABEL_MARKER.test(segment)) return "CPF";
        if (CPF_MARKER.test(segment)) return "CPF";
        return fallback;
    };

    const addCandidate = (items, seen, raw, priority, context, allowWindows) => {
        const normalized = normalizeOcrCandidate(raw);
        const digits = onlyDigits(normalized);
        const windows = [];
        if (digits.length === 11) {
            windows.push(digits);
        } else if (allowWindows && digits.length > 11 && digits.length <= 24) {
            for (let index = 0; index <= digits.length - 11; index += 1) {
                windows.push(digits.slice(index, index + 11));
            }
        }
        for (const cpf of windows) {
            if (!isValidCpf(cpf) || seen.has(cpf)) continue;
            seen.add(cpf);
            items.push({ cpf, formatted: formatCpf(cpf), priority, context });
        }
    };

    const scanSegment = (segment, priority, context, seen, allowWindows = false) => {
        const items = [];
        const charClass = `[${OCR_DIGIT_CHARS}]`;
        const formattedPattern = new RegExp(`${charClass}{3}\\s*[.]\\s*${charClass}{3}\\s*[.]\\s*${charClass}{3}\\s*[-]\\s*${charClass}{2}`, "g");
        const spacedPattern = new RegExp(`${charClass}{3}\\s+${charClass}{3}\\s+${charClass}{3}\\s+${charClass}{2}`, "g");
        const compactPattern = new RegExp(`${charClass}{11}`, "g");
        const longPattern = new RegExp(`${charClass}(?:[\\s.\\-:/]*${charClass}){10,23}`, "g");
        const patterns = [formattedPattern, spacedPattern, compactPattern];
        if (allowWindows) patterns.push(longPattern);
        for (const pattern of patterns) {
            for (const match of segment.matchAll(pattern)) {
                addCandidate(items, seen, match[0], priority, context, allowWindows);
            }
        }
        return items;
    };

    const extractCpfCandidates = (text) => {
        const source = String(text ?? "");
        const seen = new Set();
        const candidates = [];
        const lines = source.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

        for (let index = 0; index < lines.length; index += 1) {
            const line = lines[index];
            if (!CPF_MARKER.test(line)) continue;
            const segment = `${line} ${lines[index + 1] || ""}`.slice(0, 220);
            const context = contextForSegment(segment, "CPF");
            const priority = CPF_MF_MARKER.test(segment) ? 1 : FULL_LABEL_MARKER.test(segment) ? 2 : 1;
            candidates.push(...scanSegment(segment, priority, context, seen, true));
        }

        candidates.push(...scanSegment(source, 4, "FORMATTED_NUMBER", seen, false).filter((item) => /\./.test(item.formatted)));
        const compactMatches = scanSegment(source, 6, "NUMERIC_SEQUENCE", seen, false);
        candidates.push(...compactMatches);

        return candidates.sort((a, b) => a.priority - b.priority || a.cpf.localeCompare(b.cpf));
    };

    const selectCpfResult = (text) => {
        const candidates = extractCpfCandidates(text);
        if (candidates.length === 1) return { state: "single", candidates };
        if (candidates.length > 1) return { state: "multiple", candidates };
        return { state: "not_found", candidates: [] };
    };

    return {
        onlyDigits,
        formatCpf,
        isValidCpf,
        normalizeOcrCandidate,
        extractCpfCandidates,
        selectCpfResult,
    };
});
