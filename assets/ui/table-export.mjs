export function exportCell(node, fallback, csv = false) {
    const raw = node?.dataset?.export;
    if (raw !== undefined && /^-?\d+(?:\.\d+)?$/.test(raw)) return csv ? raw.replace('.', ',') : raw;
    const value = String(raw ?? node?.textContent ?? fallback).replace(/\s+/g, ' ').trim();
    return /^[=+\-@]/.test(value) ? `'${value}` : value;
}
