import { describe, expect, it } from 'vitest';

import { parseReferenceSegments, referenceClickableText, referenceDisplayText, referenceTrailingText, renderReferenceHtml } from './reference-utils';

function referenceTokens(value: string) {
  return parseReferenceSegments(value)
    .filter((segment) => segment.type === 'reference')
    .map((segment) => segment.type === 'reference' ? segment.reference.raw : '');
}

describe('reference-utils', () => {
  it('parses mixed project reference tags with chinese punctuation boundaries', () => {
    expect(referenceTokens('#s1 任务，#t1 测试')).toEqual(['#s1', '#t1']);
    expect(referenceTokens('@u12 #p3 #v4 #f5 #m6')).toEqual(['@u12', '#p3', '#v4', '#f5', '#m6']);
  });

  it('parses hidden display labels while keeping project ids visible', () => {
    const segments = parseReferenceSegments('@u12[邹景立] 处理 #s8[测试任务]');
    const user = segments[0];
    const task = segments[2];
    if (user.type !== 'reference' || task.type !== 'reference') throw new Error('reference expected');

    expect(user.reference.raw).toBe('@u12');
    expect(user.reference.label).toBe('邹景立');
    expect(task.reference.raw).toBe('#s8');
    expect(referenceDisplayText(user.reference)).toBe('@邹景立');
    expect(referenceDisplayText(task.reference)).toBe('#S8 测试任务');
    expect(referenceClickableText(task.reference)).toBe('#S8');
    expect(referenceTrailingText(task.reference)).toBe(' 测试任务');
  });

  it('renders project product label with uppercase type and real id', () => {
    const segment = parseReferenceSegments('#p3[智慧厨房]')[0];
    if (segment.type !== 'reference') throw new Error('reference expected');

    expect(segment.reference.code).toBe('p');
    expect(segment.reference.id).toBe(3);
    expect(referenceDisplayText(segment.reference)).toBe('#P3 智慧厨房');
  });

  it('keeps chinese punctuation boundaries after hidden labels', () => {
    const segments = parseReferenceSegments('#s1[任务]，#t1[测试]');

    expect(segments.map((segment) => segment.type === 'reference' ? referenceDisplayText(segment.reference) : segment.text)).toEqual([
      '#S1 任务',
      '，',
      '#T1 测试',
    ]);
  });

  it('does not parse tags attached to letters or missing numeric ids', () => {
    expect(referenceTokens('abc#s1 #s 任务 #t0')).toEqual([]);
    expect(referenceTokens('请看 #s1，继续处理')).toEqual(['#s1']);
  });

  it('renders rich html text nodes as clickable reference tokens without touching code blocks', () => {
    const html = renderReferenceHtml('<p>#s1 任务，#t1 测试</p><pre>#s2</pre>');

    expect(html).toContain('data-reference-code="s"');
    expect(html).toContain('data-reference-id="1"');
    expect(html).toContain('data-reference-code="t"');
    expect(html).toContain('data-reference-id="1"');
    expect(html).toContain('#S1');
    expect(html).toContain('#T1');
    expect(html).toContain('<pre>#s2</pre>');
  });

  it('renders rich html label syntax with only the id token clickable', () => {
    const html = renderReferenceHtml('<p>@u12[邹景立] 处理 #s8[测试任务]</p>');

    expect(html).toContain('data-reference-id="12"');
    expect(html).toContain('data-reference-label="邹景立"');
    expect(html).toContain('@邹景立');
    expect(html).toContain('>#S8</span> 测试任务');
    expect(html).not.toContain('@u12[邹景立]');
  });

  it('renders references when dom walker is unavailable', () => {
    const originalCreateTreeWalker = document.createTreeWalker;
    Object.defineProperty(document, 'createTreeWalker', { configurable: true, value: undefined });

    try {
      const html = renderReferenceHtml('<p>#p3[智慧厨房] / #v4[1.0]</p><code>#s2</code>');

      expect(html).toContain('data-reference-code="p"');
      expect(html).toContain('data-reference-code="v"');
      expect(html).toContain('>#P3</span> 智慧厨房');
      expect(html).toContain('<code>#s2</code>');
    } finally {
      Object.defineProperty(document, 'createTreeWalker', { configurable: true, value: originalCreateTreeWalker });
    }
  });

  it('keeps user display lowercase while project display is uppercase', () => {
    const segment = parseReferenceSegments('@u12 处理 #m6')[0];
    if (segment.type !== 'reference') throw new Error('reference expected');

    expect(referenceDisplayText(segment.reference)).toBe('@u12');
  });
});
