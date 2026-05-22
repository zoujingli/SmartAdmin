(function () {
    'use strict';

    var NAV_CLASS = 'devapi-module-nav';
    var ACTIVE_CLASS = 'is-active';
    var DOWNLOAD_ACTION = 'download-current-md';
    var DOWNLOAD_LABEL = '下载';
    var DEFAULT_TITLE = 'SmartAdmin 文档';
    var DEFAULT_HOME_HREF = './#/快速开始/README';
    var DEFAULT_HOME_MARKDOWN = '快速开始/README.md';

    function getConfig() {
        return window.$docsify && window.$docsify.devapiModuleNav
            ? window.$docsify.devapiModuleNav
            : {};
    }

    function isExcludedLabel(label) {
        var excludes = getConfig().excludeLabels || ['文档总览'];

        return excludes.indexOf(label) >= 0;
    }

    function getDirectChild(element, checker) {
        if (!element) {
            return null;
        }
        for (var i = 0; i < element.children.length; i += 1) {
            if (checker(element.children[i])) {
                return element.children[i];
            }
        }
        return null;
    }

    function getDirectLabelElement(li) {
        return getDirectChild(li, function (child) {
            if (child.classList && child.classList.contains('docs-sidebar-toggle')) {
                return false;
            }
            if (child.tagName === 'UL') {
                return false;
            }
            return ['A', 'P', 'STRONG', 'SPAN'].indexOf(child.tagName) !== -1;
        });
    }

    function getDirectText(li) {
        var parts = [];
        for (var node = li.firstChild; node; node = node.nextSibling) {
            if (node.nodeType === 3 && node.nodeValue.trim()) {
                parts.push(node.nodeValue.trim());
            }
            if (node.nodeType === 1 && node.tagName === 'UL') {
                break;
            }
        }
        return parts.join(' ').replace(/\s+/g, ' ').trim();
    }

    function getTopLevelItems() {
        var root = document.querySelector('.sidebar-nav > ul');
        var items = [];
        var seen = {};

        if (!root) {
            return items;
        }

        Array.prototype.forEach.call(root.children, function (li, index) {
            var labelElement;
            var firstLink;
            var href;
            var label;
            var key;

            if (li.tagName !== 'LI') {
                return;
            }

            labelElement = getDirectLabelElement(li);
            firstLink = li.querySelector('a[href]');
            label = (labelElement ? labelElement.textContent : getDirectText(li)).replace(/\s+/g, ' ').trim();
            href = firstLink ? firstLink.getAttribute('href') : '';

            if (!label || !href || isExcludedLabel(label)) {
                return;
            }

            key = label + '|' + href;
            if (seen[key]) {
                return;
            }
            seen[key] = true;

            // 顶部导航只取当前文档左侧一级分组，避免再维护一份独立导航后出现入口漂移。
            items.push({
                key: 'group-' + index,
                label: label,
                href: href,
                active: li.classList.contains('active') || !!li.querySelector('a.active, .active > a')
            });
        });

        return items;
    }

    function createLink(item) {
        var link = document.createElement('a');
        link.className = 'devapi-module-nav__link';
        link.href = item.href;
        link.textContent = item.label;
        link.setAttribute('data-devapi-module', item.key);
        if (item.active) {
            link.classList.add(ACTIVE_CLASS);
            link.setAttribute('aria-current', 'page');
        }
        return link;
    }

    function safeDecode(value) {
        try {
            return decodeURIComponent(value);
        } catch (error) {
            return value;
        }
    }

    function stripRouteSuffix(route) {
        var queryIndex = route.indexOf('?');
        var hashIndex = route.indexOf('#');
        var end = route.length;

        if (queryIndex >= 0) {
            end = Math.min(end, queryIndex);
        }
        if (hashIndex >= 0) {
            end = Math.min(end, hashIndex);
        }

        return route.slice(0, end);
    }

    function normalizeMarkdownPathFromHash() {
        var hash = window.location.hash || '';
        var path = hash.replace(/^#!/, '#').replace(/^#\/?/, '');

        path = stripRouteSuffix(path).replace(/^\/+/, '');
        if (!path || path === '.' || path === './') {
            return getConfig().homeMarkdown || window.$docsify.homepage || DEFAULT_HOME_MARKDOWN;
        }

        if (/\/$/.test(path)) {
            path = path.replace(/\/+$/, '');
            return path ? path + '/README.md' : 'README.md';
        }

        path = path.replace(/\/+$/, '');
        if (!/\.md$/i.test(path)) {
            path += '.md';
        }

        return path;
    }

    function encodeMarkdownPath(path) {
        return path.split('/').map(function (segment) {
            return encodeURIComponent(safeDecode(segment));
        }).join('/');
    }

    function getCurrentMarkdownSource() {
        var path = normalizeMarkdownPathFromHash();
        var base = new URL('.', (window.location.href || '').split('#')[0]);
        var url = new URL(encodeMarkdownPath(path), base);
        var parts = path.split('/');
        var filename = safeDecode(parts[parts.length - 1] || 'README.md') || 'README.md';

        return {
            path: path,
            url: url.href,
            filename: filename
        };
    }

    function triggerDownload(text, filename) {
        var blob = new Blob([text], { type: 'text/markdown;charset=utf-8' });
        var objectUrl = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = objectUrl;
        link.download = filename || 'README.md';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        window.setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
        }, 1000);
    }

    function openMarkdownFallback(source) {
        var message = '无法读取当前 Markdown 源文件，是否在新窗口打开源文件？\n' + safeDecode(source.path);

        if (window.confirm(message)) {
            window.open(source.url, '_blank', 'noopener');
        }
    }

    function setDownloadButtonBusy(button, busy) {
        if (!button) {
            return;
        }

        button.disabled = busy;
        button.textContent = busy ? '正在下载...' : DOWNLOAD_LABEL;
    }

    function handleDownloadClick(event) {
        var button = event.currentTarget;
        var source = getCurrentMarkdownSource();

        event.preventDefault();

        // 下载当前文档只读取 Docsify 正在渲染的 Markdown 源文件，不引入后端接口或额外鉴权。
        if (typeof fetch !== 'function') {
            openMarkdownFallback(source);
            return;
        }

        setDownloadButtonBusy(button, true);
        fetch(source.url, { cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Markdown source status ' + response.status);
                }
                return response.text();
            })
            .then(function (text) {
                triggerDownload(text, source.filename);
                setDownloadButtonBusy(button, false);
            })
            .catch(function (error) {
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('[SmartAdmin Docs] download current markdown failed', error);
                }
                openMarkdownFallback(source);
                setDownloadButtonBusy(button, false);
            });
    }

    function ensureDownloadButton(nav) {
        var actions = nav.querySelector('.devapi-module-nav__actions');
        var button;

        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'devapi-module-nav__actions';
            nav.appendChild(actions);
        }

        button = actions.querySelector('[data-devapi-action="' + DOWNLOAD_ACTION + '"]');
        if (button) {
            return button;
        }

        button = document.createElement('button');
        button.type = 'button';
        button.className = 'devapi-module-nav__download';
        button.textContent = DOWNLOAD_LABEL;
        button.setAttribute('data-devapi-action', DOWNLOAD_ACTION);
        button.setAttribute('aria-label', '下载当前页面的 Markdown 源文件');
        button.title = '下载当前页面对应的 Markdown 源文件';
        button.addEventListener('click', handleDownloadClick);
        actions.appendChild(button);
        return button;
    }

    function syncDownloadTitle(nav) {
        var button = ensureDownloadButton(nav);
        var source = getCurrentMarkdownSource();

        button.title = '下载当前 Markdown：' + safeDecode(source.path);
    }

    function ensureNav() {
        var nav = document.querySelector('.' + NAV_CLASS);
        var title;
        var links;

        if (nav) {
            ensureDownloadButton(nav);
            return nav;
        }

        nav = document.createElement('nav');
        title = document.createElement('a');
        links = document.createElement('div');

        nav.className = NAV_CLASS;
        nav.setAttribute('aria-label', '当前模块导航');
        title.className = 'devapi-module-nav__title';
        title.href = getConfig().homeHref || DEFAULT_HOME_HREF;
        title.textContent = getConfig().title || DEFAULT_TITLE;
        links.className = 'devapi-module-nav__links';

        nav.appendChild(title);
        nav.appendChild(links);
        ensureDownloadButton(nav);
        return nav;
    }

    function renderNav(nav) {
        var items = getTopLevelItems();
        var links = nav.querySelector('.devapi-module-nav__links');

        if (!links || !items.length) {
            nav.hidden = true;
            return;
        }

        nav.hidden = false;
        links.innerHTML = '';
        items.forEach(function (item) {
            links.appendChild(createLink(item));
        });
        syncDownloadTitle(nav);
    }

    function mountNav() {
        var content = document.querySelector('section.content') || document.querySelector('.content');
        var markdown = content ? content.querySelector('.markdown-section') : null;
        var nav = ensureNav();

        if (!content || !markdown) {
            return;
        }

        renderNav(nav);

        if (nav.parentNode !== content || nav.nextSibling !== markdown) {
            content.insertBefore(nav, markdown);
        }
        syncNavToBody(nav, markdown);
        window.requestAnimationFrame(function () {
            syncNavToBody(nav, markdown);
        });
    }

    function syncNavToBody(nav, markdown) {
        var rect;
        if (!nav || !markdown) {
            return;
        }
        rect = markdown.getBoundingClientRect();
        if (!rect.width) {
            return;
        }
        // 固定导航条按正文内容区实际位置对齐，避免左侧菜单、缩放和窗口宽度变化后与正文卡片错位。
        nav.style.left = rect.left + 'px';
        nav.style.width = rect.width + 'px';
        nav.style.right = 'auto';
        // 正文间距交给 CSS 控制，避免页面滚动后用视口坐标反复计算导致顶部被撑出大空白。
        markdown.style.marginTop = '';
    }

    function registerDocsifyHook() {
        window.$docsify = window.$docsify || {};
        window.$docsify.plugins = window.$docsify.plugins || [];
        window.$docsify.plugins.push(function (hook) {
            hook.mounted(function () {
                mountNav();
                window.setTimeout(mountNav, 80);
            });
            hook.doneEach(function () {
                mountNav();
                window.setTimeout(mountNav, 80);
            });
        });
    }

    registerDocsifyHook();
    window.addEventListener('resize', mountNav);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountNav);
    } else {
        mountNav();
    }
}());
