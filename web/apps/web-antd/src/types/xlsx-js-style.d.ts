declare module 'xlsx-js-style' {
  const XLSX: any;

  export = XLSX;
}

declare module 'xlsx-js-style/dist/xlsx.min.js' {
  const XLSX: any;

  export default XLSX;
}

declare module 'xlsx-js-style/dist/xlsx.min.js?url' {
  const url: string;

  export default url;
}
