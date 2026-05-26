import type { ReferenceItem, ReferenceSegment } from './types';

const REF_BOUNDARY_PATTERN = /(^|[^\p{L}\p{N}_])(([@#])([a-zA-Z]+)([1-9]\d*))(?:\[([^\]\r\n]{1,160})\])?(?=$|[^\p{L}\p{N}_])/gu;
const SECRET_ATTR_PATTERN = /\s(on[a-z]+)\s*=\s*(['"])[\s\S]*?\2/gi;
const UNSAFE_URL_ATTR_PATTERN = /\s(href|src)\s*=\s*(['"])\s*(javascript:|data:)[\s\S]*?\2/gi;

export function parseReferenceSegments(value: string): ReferenceSegment[] {
  const text = String(value || '');
  if (!text) return [];

  const segments: ReferenceSegment[] = [];
  let cursor = 0;
  for (const match of text.matchAll(REF_BOUNDARY_PATTERN)) {
    const leading = match[1] || '';
    const raw = match[2] || '';
    const prefix = match[3] as '@' | '#';
    const code = String(match[4] || '').toLowerCase();
    const id = Number(match[5] || 0);
    const label = normalizeReferenceLabel(match[6] || '');
    const tokenLength = raw.length + (match[6] === undefined ? 0 : String(match[6]).length + 2);
    const tokenStart = (match.index || 0) + leading.length;
    if (!raw || id <= 0 || tokenStart < cursor) continue;
    if (tokenStart > cursor) {
      segments.push({ type: 'text', text: text.slice(cursor, tokenStart) });
    }
    segments.push({ type: 'reference', reference: { code, id, label, prefix, raw, type: code } });
    cursor = tokenStart + tokenLength;
  }
  if (cursor < text.length) {
    segments.push({ type: 'text', text: text.slice(cursor) });
  }

  return segments.length > 0 ? segments : [{ type: 'text', text }];
}

export function hasReference(value: string) {
  return parseReferenceSegments(value).some((segment) => segment.type === 'reference');
}

export function referenceDisplayText(reference: ReferenceItem) {
  const label = normalizeReferenceLabel(reference.label || '');
  if (label) {
    return reference.prefix === '@' ? `@${label}` : `${referenceTokenText(reference)} ${label}`;
  }

  return referenceTokenText(reference);
}

export function referenceTokenText(reference: ReferenceItem) {
  const code = reference.prefix === '#' ? reference.code.toUpperCase() : reference.code.toLowerCase();

  return `${reference.prefix}${code}${reference.id}`;
}

export function referenceClickableText(reference: ReferenceItem) {
  return reference.prefix === '#' ? referenceTokenText(reference) : referenceDisplayText(reference);
}

export function referenceTrailingText(reference: ReferenceItem) {
  if (reference.prefix !== '#') return '';
  const label = normalizeReferenceLabel(reference.label || '');

  return label ? ` ${label}` : '';
}

export function sanitizeReferenceHtml(value: string) {
  return String(value || '')
    .replace(/<\s*(script|style|iframe|object|embed)[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(SECRET_ATTR_PATTERN, '')
    .replace(UNSAFE_URL_ATTR_PATTERN, '');
}

export function renderReferenceHtml(value: string) {
  const html = sanitizeReferenceHtml(value);
  if (typeof document === 'undefined' || typeof NodeFilter === 'undefined') {
    return html;
  }

  const template = document.createElement('template');
  template.innerHTML = html;
  const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      const parent = node.parentElement;
      if (!parent) return NodeFilter.FILTER_REJECT;
      const tag = parent.tagName.toLowerCase();
      if (['code', 'pre', 'script', 'style', 'textarea'].includes(tag)) return NodeFilter.FILTER_REJECT;
      return hasReference(node.nodeValue || '') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
    },
  });

  const nodes: Text[] = [];
  while (walker.nextNode()) {
    if (walker.currentNode instanceof Text) nodes.push(walker.currentNode);
  }

  for (const node of nodes) {
    const fragment = document.createDocumentFragment();
    for (const segment of parseReferenceSegments(node.nodeValue || '')) {
      if (segment.type === 'text') {
        fragment.append(document.createTextNode(segment.text));
        continue;
      }
      const span = document.createElement('span');
      span.className = 'reference-token';
      span.setAttribute('role', 'button');
      span.setAttribute('tabindex', '0');
      span.setAttribute('data-reference-token', '1');
      span.setAttribute('data-reference-prefix', segment.reference.prefix);
      span.setAttribute('data-reference-code', segment.reference.code);
      span.setAttribute('data-reference-id', String(segment.reference.id));
      if (segment.reference.label) span.setAttribute('data-reference-label', segment.reference.label);
      span.setAttribute('data-reference-raw', segment.reference.raw);
      span.textContent = referenceClickableText(segment.reference);
      fragment.append(span);
      const trailingText = referenceTrailingText(segment.reference);
      if (trailingText) fragment.append(document.createTextNode(trailingText));
    }
    node.parentNode?.replaceChild(fragment, node);
  }

  return template.innerHTML;
}

export function referenceFromDataset(element: HTMLElement): ReferenceItem | null {
  const code = String(element.dataset.referenceCode || '').toLowerCase();
  const id = Number(element.dataset.referenceId || 0);
  const prefix = element.dataset.referencePrefix === '@' ? '@' : '#';
  if (!code || id <= 0) return null;

  return {
    code,
    id,
    label: normalizeReferenceLabel(element.dataset.referenceLabel || ''),
    prefix,
    raw: String(element.dataset.referenceRaw || `${prefix}${code}${id}`),
    type: code,
  };
}

function normalizeReferenceLabel(value: string) {
  return String(value || '').replace(/\s+/gu, ' ').trim();
}
