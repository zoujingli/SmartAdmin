const ATTACHMENT_LABEL_PATTERN = /^附件【\s*(.*?)\s*】$/u;

export function normalizeAttachmentLabel(value: string) {
  // 附件展示统一使用中文前缀；重复归一化时保留原始文件名，避免多次追加前缀。
  const text = String(value || '').replace(/\s+/gu, ' ').trim();
  const matched = text.match(ATTACHMENT_LABEL_PATTERN);
  const name = (matched?.[1] || text || '未命名附件').trim();

  return `附件【 ${name || '未命名附件'} 】`;
}

export function normalizeAttachmentLinkLabels(root: ParentNode) {
  root.querySelectorAll<HTMLAnchorElement>('a[data-project-file="1"],a[data-file-id]').forEach((link) => {
    const fileName = String(link.dataset.fileName || link.textContent || '').trim();
    link.textContent = normalizeAttachmentLabel(fileName);
  });
}
