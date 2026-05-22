(function () {
    'use strict';

    var configured = false;
    var renderSeq = 0;

    function normalizeText(source) {
        return String(source || '').replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').trim();
    }

    function isMermaidText(source) {
        var text = normalizeText(source);

        if (text === '') {
            return false;
        }

        // 兼容标准 mermaid 代码块，也允许普通代码块直接以 flowchart LR / graph TB 开头。
        return /^(flowchart|graph)\s+(TB|TD|BT|RL|LR)\b/i.test(text)
            || /^(sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|mindmap|timeline|gitGraph)\b/i.test(text);
    }

    function ensureMermaid() {
        if (!window.mermaid) {
            return false;
        }

        if (!configured) {
            // 文档中的 Mermaid 图只来自仓库 Markdown；关闭自动启动，由 docsify 路由完成后统一渲染当前页面。
            window.mermaid.initialize({
                startOnLoad: false,
                securityLevel: 'strict',
                theme: 'base',
                flowchart: {
                    useMaxWidth: true,
                    htmlLabels: true,
                    curve: 'basis'
                },
                themeVariables: {
                    primaryColor: '#eefaff',
                    primaryTextColor: '#0f172a',
                    primaryBorderColor: '#06b6d4',
                    lineColor: '#2563eb',
                    secondaryColor: '#f5f3ff',
                    tertiaryColor: '#f8fcff',
                    fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
                }
            });
            configured = true;
        }

        return true;
    }

    function getCodeNode(pre) {
        return pre ? pre.querySelector('code') || pre : null;
    }

    function getLanguageHint(pre, code) {
        var hints = [];

        if (pre && pre.getAttribute('data-lang')) {
            hints.push(pre.getAttribute('data-lang'));
        }
        if (code && code.className) {
            hints.push(code.className);
        }

        return hints.join(' ').toLowerCase();
    }

    function isMermaidBlock(pre) {
        var code = getCodeNode(pre);
        var source = code ? code.textContent : '';
        var hint = getLanguageHint(pre, code);

        return /\b(lang|language)?-?mermaid\b/i.test(hint)
            || hint.indexOf('mermaid') >= 0
            || isMermaidText(source);
    }

    function titleFromSource(source) {
        var firstLine = normalizeText(source).split('\n')[0] || 'Mermaid';
        var match = firstLine.match(/^(flowchart|graph)\s+(TB|TD|BT|RL|LR)\b/i);

        if (match) {
            return 'Mermaid · ' + match[1].toLowerCase() + ' ' + match[2].toUpperCase();
        }

        return 'Mermaid 图表';
    }

    function createShell(source) {
        var wrapper = document.createElement('div');
        var title = document.createElement('div');
        var diagram = document.createElement('div');
        var fallback = document.createElement('pre');

        wrapper.className = 'devapi-mermaid-shell';
        wrapper.dataset.devapiMermaidSource = source;

        title.className = 'devapi-mermaid-title';
        title.textContent = titleFromSource(source);

        diagram.className = 'mermaid devapi-mermaid';
        diagram.id = 'devapi-mermaid-' + Date.now().toString(36) + '-' + (++renderSeq);
        diagram.textContent = source;

        fallback.className = 'devapi-mermaid-source';
        fallback.textContent = source;
        fallback.hidden = true;

        wrapper.appendChild(title);
        wrapper.appendChild(diagram);
        wrapper.appendChild(fallback);
        return {
            wrapper: wrapper,
            diagram: diagram,
            fallback: fallback
        };
    }

    function findMermaidBlocks() {
        var nodes = [];

        document.querySelectorAll('.markdown-section pre').forEach(function (pre) {
            var code;
            var source;
            var shell;

            if (!pre || pre.dataset.devapiMermaid === '1' || pre.closest('.devapi-mermaid-shell')) {
                return;
            }
            if (!isMermaidBlock(pre)) {
                return;
            }

            code = getCodeNode(pre);
            source = normalizeText(code ? code.textContent : '');
            if (source === '') {
                return;
            }

            shell = createShell(source);
            pre.dataset.devapiMermaid = '1';
            pre.replaceWith(shell.wrapper);
            nodes.push(shell.diagram);
        });

        return nodes;
    }

    function markRenderError(error, nodes) {
        var message = 'Mermaid 渲染失败，请检查图表语法：\n'
            + (error && error.message ? error.message : String(error || 'unknown error'));

        (nodes || Array.prototype.slice.call(document.querySelectorAll('.devapi-mermaid'))).forEach(function (node) {
            var block = node.closest('.devapi-mermaid-shell');
            var fallback = block ? block.querySelector('.devapi-mermaid-source') : null;
            var errorNode;

            if (!block || block.querySelector('.devapi-mermaid-error')) {
                return;
            }

            errorNode = document.createElement('pre');
            errorNode.className = 'devapi-mermaid-error';
            errorNode.textContent = message;
            block.appendChild(errorNode);
            if (fallback) {
                fallback.hidden = false;
            }
        });
    }

    function renderMermaidBlocks() {
        var nodes;
        var result;

        if (!ensureMermaid()) {
            return;
        }

        nodes = findMermaidBlocks();
        if (nodes.length === 0) {
            return;
        }

        try {
            result = window.mermaid.run({ nodes: nodes });
            if (result && typeof result.catch === 'function') {
                result.catch(function (error) {
                    markRenderError(error, nodes);
                });
            }
        } catch (error) {
            markRenderError(error, nodes);
        }
    }

    function install(hook) {
        hook.doneEach(function () {
            // 等 docsify 完成 DOM 替换后再渲染，确保 flowchart LR 等代码块已经进入正文区域。
            window.setTimeout(renderMermaidBlocks, 0);
        });
    }

    window.$docsify = window.$docsify || {};
    window.$docsify.plugins = [].concat(install, window.$docsify.plugins || []);
}());
