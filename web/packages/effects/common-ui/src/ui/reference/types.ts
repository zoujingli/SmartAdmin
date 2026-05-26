export type ReferencePrefix = '@' | '#';

export interface ReferenceItem {
  code: string;
  id: number;
  label?: string;
  prefix: ReferencePrefix;
  raw: string;
  type: string;
}

export type ReferenceSegment =
  | { text: string; type: 'text' }
  | { reference: ReferenceItem; type: 'reference' };

export interface ReferenceField {
  label: string;
  value?: number | string | null;
}

export interface ReferenceChainItem {
  code?: string;
  id?: number;
  label: string;
  prefix?: ReferencePrefix;
  raw?: string;
  type?: string;
  type_text?: string;
}

export interface ReferenceSection {
  content?: string;
  content_html?: string;
  fields?: ReferenceField[];
  title: string;
}

export interface ReferenceTag {
  color?: string;
  label: string;
}

export interface ReferenceDetail {
  available: boolean;
  chain?: ReferenceChainItem[];
  description?: string;
  fields?: ReferenceField[];
  id: number;
  message?: string;
  raw_type?: string;
  subtitle?: string;
  tags?: ReferenceTag[];
  title?: string;
  type: string;
  type_text?: string;
  sections?: ReferenceSection[];
}

export interface ReferenceProvider {
  describe?: (reference: ReferenceItem) => string;
  resolve: (reference: ReferenceItem) => Promise<ReferenceDetail>;
}
