(function () {
    'use strict';

    var STORAGE_PREFIX = 'devapi:sidebar-scroll:';
    var EXPAND_STORAGE_PREFIX = 'devapi:sidebar-expand:v2:';
    var BRANCH_CLASS = 'docs-sidebar-branch';
    var HEADING_BRANCH_CLASS = 'docs-sidebar-heading-branch';
    var COLLAPSED_CLASS = 'docs-sidebar-collapsed';
    var EXPANDED_CLASS = 'docs-sidebar-expanded';
    var TOGGLE_CLASS = 'docs-sidebar-toggle';
    var TEXT_LABEL_CLASS = 'docs-sidebar-label';
    var ACTIONS_CLASS = 'docs-sidebar-actions';
    var ACTION_BUTTON_CLASS = 'docs-sidebar-action';
    var restoreTimerUntil = 0;
    var boundSidebar = null;
    var sidebarObserver = null;
    var sidebarPanelSeq = 0;
    var userSidebarIntentUntil = 0;

    function getStorageKey() {
        var pathname = window.location.pathname.replace(/\/index\.html$/, '/');
        // 每个文档部署路径独立保存左侧菜单位置，避免根路径、子路径和预览环境互相覆盖。
        return STORAGE_PREFIX + pathname.replace(/\/+$/, '/');
    }

    function getExpandStorageKey() {
        var pathname = window.location.pathname.replace(/\/index\.html$/, '/');
        // 折叠状态同样按模块隔离，避免后台、终端等不同菜单树相互污染。
        return EXPAND_STORAGE_PREFIX + pathname.replace(/\/+$/, '/');
    }

    function getSidebar() {
        return document.querySelector('.sidebar');
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

    function getDirectChildUl(li) {
        return getDirectChild(li, function (child) {
            return child.tagName === 'UL';
        });
    }

    function getDirectLink(li) {
        return getDirectChild(li, function (child) {
            return child.tagName === 'A';
        });
    }

    function getBranchLabel(li) {
        return getDirectChild(li, function (child) {
            if (child.classList && child.classList.contains(TOGGLE_CLASS)) {
                return false;
            }
            if (child.tagName === 'UL') {
                return false;
            }
            return ['A', 'P', 'STRONG', 'SPAN'].indexOf(child.tagName) !== -1;
        });
    }

    function ensureTextBranchLabel(li, childList) {
        var existingLabel = getDirectChild(li, function (child) {
            return child.classList && child.classList.contains(TEXT_LABEL_CLASS);
        });
        if (existingLabel) {
            return existingLabel;
        }

        var textNodes = [];
        var textParts = [];
        for (var node = li.firstChild; node; node = node.nextSibling) {
            if (node === childList) {
                break;
            }
            if (node.nodeType !== 3) {
                continue;
            }
            if (normalizeLabelText(node.textContent)) {
                textParts.push(node.textContent);
            }
            textNodes.push(node);
        }

        if (!textParts.length) {
            return null;
        }

        var label = document.createElement('span');
        label.className = TEXT_LABEL_CLASS;
        label.textContent = normalizeLabelText(textParts.join(' '));
        // Docsify 对纯文本分组会生成 LI 直连文本节点；包一层 span 后才能统一加按钮、缩进和键盘交互。
        li.insertBefore(label, textNodes[0] || childList);
        textNodes.forEach(function (node) {
            li.removeChild(node);
        });
        return label;
    }

    function normalizeLabelText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function getBranchDepth(li) {
        var depth = 0;
        var node = li;
        while (node) {
            if (node.tagName === 'LI') {
                depth += 1;
            }
            if (node.classList && node.classList.contains('sidebar-nav')) {
                break;
            }
            node = node.parentElement;
        }
        return depth;
    }

    function getBranchKey(li) {
        var childList = getDirectChildUl(li);
        var label = getBranchLabel(li);
        var ownLink = getDirectLink(li);
        var firstChildLink = childList ? childList.querySelector('a[href]') : null;
        return [
            getBranchDepth(li),
            normalizeLabelText(label ? label.textContent : ''),
            ownLink ? ownLink.getAttribute('href') || '' : '',
            firstChildLink ? firstChildLink.getAttribute('href') || '' : ''
        ].join('|');
    }

    function readExpandedMap() {
        try {
            var value = window.localStorage.getItem(getExpandStorageKey());
            var parsed = value ? JSON.parse(value) : {};
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function saveExpandedMap(map) {
        try {
            window.localStorage.setItem(getExpandStorageKey(), JSON.stringify(map || {}));
        } catch (error) {
            // localStorage 不可用时只影响用户偏好的展开记忆，不影响菜单点击展开。
        }
    }

    function saveExpandedState(key, expanded) {
        if (!key) {
            return;
        }
        try {
            var map = readExpandedMap();
            map[key] = Boolean(expanded);
            saveExpandedMap(map);
        } catch (error) {
            // localStorage 不可用时只影响用户偏好的展开记忆，不影响菜单点击展开。
        }
    }

    function readSavedTop() {
        try {
            var value = window.localStorage.getItem(getStorageKey());
            var top = Number(value);
            return Number.isFinite(top) && top > 0 ? top : 0;
        } catch (error) {
            return 0;
        }
    }

    function saveSidebarTop(sidebar) {
        if (!sidebar || Date.now() < restoreTimerUntil) {
            return;
        }
        try {
            window.localStorage.setItem(getStorageKey(), String(Math.max(0, Math.round(sidebar.scrollTop || 0))));
        } catch (error) {
            // localStorage 不可用时保持文档可阅读，不影响 Docsify 主流程。
        }
    }

    function getActiveSidebarLink() {
        return document.querySelector('.sidebar-nav li.active > a, .sidebar-nav a.active, .sidebar-nav .active > a');
    }

    function isActiveBranch(li) {
        return li.classList.contains('active') || Boolean(li.querySelector('li.active, a.active, .active'));
    }

    function ensurePanelId(childList) {
        if (!childList.id) {
            sidebarPanelSeq += 1;
            childList.id = 'docs-sidebar-panel-' + sidebarPanelSeq;
        }
        return childList.id;
    }

    function getToggleButton(li) {
        return getDirectChild(li, function (child) {
            return child.classList && child.classList.contains(TOGGLE_CLASS);
        });
    }

    function updateToggleA11y(li, expanded) {
        var button = getToggleButton(li);
        var childList = getDirectChildUl(li);
        var label = getBranchLabel(li) || ensureTextBranchLabel(li, childList);
        var labelText = normalizeLabelText(label ? label.textContent : '子菜单');
        var action = expanded ? '收起' : '展开';
        if (button) {
            button.setAttribute('aria-expanded', String(expanded));
            button.setAttribute('aria-label', action + '菜单：' + labelText);
            button.setAttribute('title', action + '菜单；Shift+点击同步子级');
            if (childList) {
                button.setAttribute('aria-controls', ensurePanelId(childList));
            }
        }
        if (label && label.getAttribute('role') === 'button') {
            label.setAttribute('aria-expanded', String(expanded));
            if (childList) {
                label.setAttribute('aria-controls', ensurePanelId(childList));
            }
        }
        if (childList) {
            childList.setAttribute('aria-hidden', String(!expanded));
        }
    }

    function setBranchExpanded(li, expanded, persist) {
        li.classList.toggle(EXPANDED_CLASS, expanded);
        li.classList.toggle(COLLAPSED_CLASS, !expanded);
        updateToggleA11y(li, expanded);
        if (persist) {
            saveExpandedState(li.dataset.docsSidebarKey || getBranchKey(li), expanded);
        }
    }

    function getSidebarBranches(sidebar) {
        var nav = sidebar ? sidebar.querySelector('.sidebar-nav') : null;
        if (!nav) {
            return [];
        }
        return Array.prototype.slice.call(nav.querySelectorAll('li.' + BRANCH_CLASS));
    }

    function persistAllBranchStates(sidebar) {
        var map = readExpandedMap();
        getSidebarBranches(sidebar).forEach(function (li) {
            var key = li.dataset.docsSidebarKey || getBranchKey(li);
            if (key) {
                map[key] = li.classList.contains(EXPANDED_CLASS);
            }
        });
        saveExpandedMap(map);
    }

    function setBranchTreeExpanded(li, expanded, persist) {
        var sidebar = getSidebar();
        var branches = [li].concat(Array.prototype.slice.call(li.querySelectorAll('li.' + BRANCH_CLASS)));
        branches.forEach(function (branch) {
            setBranchExpanded(branch, expanded, false);
        });
        if (persist) {
            persistAllBranchStates(sidebar);
        }
    }

    function toggleBranch(li, cascade) {
        if (cascade) {
            setBranchTreeExpanded(li, !li.classList.contains(EXPANDED_CLASS), true);
        } else {
            setBranchExpanded(li, !li.classList.contains(EXPANDED_CLASS), true);
        }
        saveSidebarTop(getSidebar());
    }

    function expandActivePath(sidebar) {
        var activeLink = getActiveSidebarLink();
        var li = activeLink ? activeLink.closest('li') : null;
        var expanded = false;
        while (li && sidebar && sidebar.contains(li)) {
            if (getDirectChildUl(li)) {
                setBranchExpanded(li, true, false);
                expanded = true;
            }
            li = li.parentElement ? li.parentElement.closest('li') : null;
        }
        return expanded;
    }

    function expandAllBranches() {
        var sidebar = getSidebar();
        enhanceSidebarTree(sidebar);
        getSidebarBranches(sidebar).forEach(function (li) {
            setBranchExpanded(li, true, false);
        });
        persistAllBranchStates(sidebar);
        saveSidebarTop(sidebar);
    }

    function collapseToActiveBranch() {
        var sidebar = getSidebar();
        enhanceSidebarTree(sidebar);
        getSidebarBranches(sidebar).forEach(function (li) {
            setBranchExpanded(li, false, false);
        });
        expandActivePath(sidebar);
        persistAllBranchStates(sidebar);
        saveSidebarTop(sidebar);
        scrollActiveSidebarIntoView(sidebar);
    }

    function locateActiveBranch() {
        var sidebar = getSidebar();
        enhanceSidebarTree(sidebar);
        expandActivePath(sidebar);
        persistAllBranchStates(sidebar);
        try {
            window.localStorage.removeItem(getStorageKey());
        } catch (error) {
            // localStorage 不可用时只跳过滚动位置重置。
        }
        scrollActiveSidebarIntoView(sidebar);
    }

    function createActionButton(text, title, onClick) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = ACTION_BUTTON_CLASS;
        button.textContent = text;
        button.setAttribute('title', title);
        button.setAttribute('aria-label', title);
        button.addEventListener('click', function (event) {
            event.preventDefault();
            onClick();
        });
        return button;
    }

    function ensureSidebarActions(sidebar) {
        var nav = sidebar ? sidebar.querySelector('.sidebar-nav') : null;
        var actions;
        if (!sidebar || !nav) {
            return;
        }
        actions = sidebar.querySelector('.' + ACTIONS_CLASS);
        if (actions) {
            return;
        }
        actions = document.createElement('div');
        actions.className = ACTIONS_CLASS;
        actions.setAttribute('role', 'toolbar');
        actions.setAttribute('aria-label', '菜单展开收缩工具');
        actions.appendChild(createActionButton('当前', '展开并定位当前菜单', locateActiveBranch));
        actions.appendChild(createActionButton('展开', '展开全部菜单', expandAllBranches));
        actions.appendChild(createActionButton('收起', '仅保留当前菜单链路', collapseToActiveBranch));
        // 大型接口目录需要快捷展开、收起和回到当前项，避免只靠单个小箭头逐层操作。
        sidebar.insertBefore(actions, nav);
    }

    function bindHeadingToggle(label, li) {
        if (!label || label.dataset.docsSidebarToggleBound === '1') {
            return;
        }

        label.dataset.docsSidebarToggleBound = '1';
        label.setAttribute('role', 'button');
        label.setAttribute('tabindex', '0');
        label.setAttribute('title', '展开/收起菜单；Shift+点击同步子级');
        label.addEventListener('click', function (event) {
            if (event.target.closest && event.target.closest('button')) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            toggleBranch(li, event.shiftKey);
        });
        label.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            toggleBranch(li, event.shiftKey);
        });
    }

    function prepareBranch(li, expandedMap) {
        var childList = getDirectChildUl(li);
        if (!childList) {
            return;
        }

        var label = getBranchLabel(li) || ensureTextBranchLabel(li, childList);
        var directLink = getDirectLink(li);
        var key = getBranchKey(li);
        var button = getToggleButton(li);
        var hasSavedState = Object.prototype.hasOwnProperty.call(expandedMap, key);
        // 约定“从第二级收起”：一级分组默认展开，二级及更深层有子级节点默认收起；当前页面所在链路始终展开。
        var expanded = isActiveBranch(li) || (hasSavedState ? expandedMap[key] === true : getBranchDepth(li) === 1);

        li.dataset.docsSidebarKey = key;
        li.classList.add(BRANCH_CLASS);
        li.classList.toggle(HEADING_BRANCH_CLASS, !directLink);
        ensurePanelId(childList);

        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.className = TOGGLE_CLASS;
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleBranch(li, event.shiftKey);
            });
            li.insertBefore(button, label || childList);
        }

        // 菜单分组统一按“目录”处理：无论标题是否是链接，点击标题都只展开/收起，不跳转页面。
        bindHeadingToggle(label, li);

        setBranchExpanded(li, expanded, false);
    }

    function enhanceSidebarTree(sidebar) {
        var nav = sidebar ? sidebar.querySelector('.sidebar-nav') : null;
        if (!nav) {
            return;
        }

        var expandedMap = readExpandedMap();
        Array.prototype.forEach.call(nav.querySelectorAll('li'), function (li) {
            prepareBranch(li, expandedMap);
        });
    }

    function scrollActiveSidebarIntoView(sidebar) {
        var activeLink = getActiveSidebarLink();
        if (!sidebar || !activeLink) {
            return;
        }

        var sidebarRect = sidebar.getBoundingClientRect();
        var activeRect = activeLink.getBoundingClientRect();
        var topSafeArea = 96;
        var bottomSafeArea = 54;
        var isVisible = activeRect.top >= sidebarRect.top + topSafeArea
            && activeRect.bottom <= sidebarRect.bottom - bottomSafeArea;
        if (isVisible) {
            return;
        }

        var maxTop = Math.max(0, sidebar.scrollHeight - sidebar.clientHeight);
        if (maxTop <= 0) {
            return;
        }

        // 直达深层接口或首次打开模块时，把当前 active 菜单定位到视区中上部，减少手动查找。
        var targetTop = sidebar.scrollTop + activeRect.top - sidebarRect.top - sidebar.clientHeight * 0.36;
        sidebar.scrollTop = Math.min(maxTop, Math.max(0, targetTop));
    }

    function restoreSidebarTop() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        if (Date.now() < userSidebarIntentUntil) {
            return;
        }

        enhanceSidebarTree(sidebar);

        var savedTop = readSavedTop();
        var maxTop = Math.max(0, sidebar.scrollHeight - sidebar.clientHeight);
        if (maxTop <= 0) {
            return;
        }

        if (savedTop) {
            // 菜单高度可能在 Docsify 渲染后才稳定，恢复时限制最大值，避免目录缩短后滚到空白区。
            sidebar.scrollTop = Math.min(savedTop, maxTop);
            return;
        }

        scrollActiveSidebarIntoView(sidebar);
    }

    function restoreSidebarSoon() {
        restoreTimerUntil = Date.now() + 1600;
        [0, 60, 180, 420, 900, 1400].forEach(function (delay) {
            window.setTimeout(restoreSidebarTop, delay);
        });
    }

    function onSidebarScroll(event) {
        saveSidebarTop(event.currentTarget);
    }

    function markSidebarUserIntent() {
        // 用户正在滚动或拖动侧栏时，取消切页后的延迟 scrollTop 恢复，避免菜单与用户滚动方向互相拉扯产生“飘动”。
        userSidebarIntentUntil = Date.now() + 1200;
        restoreTimerUntil = 0;
    }

    function bindSidebar() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        enhanceSidebarTree(sidebar);
        ensureSidebarActions(sidebar);
        if (sidebar === boundSidebar) {
            return;
        }

        if (boundSidebar) {
            boundSidebar.removeEventListener('scroll', onSidebarScroll);
            boundSidebar.removeEventListener('wheel', markSidebarUserIntent);
            boundSidebar.removeEventListener('touchstart', markSidebarUserIntent);
            boundSidebar.removeEventListener('pointerdown', markSidebarUserIntent);
        }
        boundSidebar = sidebar;
        boundSidebar.addEventListener('scroll', onSidebarScroll, { passive: true });
        boundSidebar.addEventListener('wheel', markSidebarUserIntent, { passive: true });
        boundSidebar.addEventListener('touchstart', markSidebarUserIntent, { passive: true });
        boundSidebar.addEventListener('pointerdown', markSidebarUserIntent, { passive: true });
        restoreSidebarSoon();
    }

    function watchSidebarRender() {
        if (sidebarObserver || !document.body || !window.MutationObserver) {
            return;
        }
        sidebarObserver = new MutationObserver(function () {
            bindSidebar();
        });
        sidebarObserver.observe(document.body, { childList: true, subtree: true });
    }

    function saveCurrentSidebar() {
        saveSidebarTop(getSidebar());
    }

    function boot() {
        bindSidebar();
        watchSidebarRender();
    }

    if (window.$docsify) {
        window.$docsify.plugins = window.$docsify.plugins || [];
        window.$docsify.plugins.push(function (hook) {
            if (hook.mounted) {
                hook.mounted(function () {
                    boot();
                    restoreSidebarSoon();
                });
            }
            if (hook.doneEach) {
                hook.doneEach(function () {
                    bindSidebar();
                    restoreSidebarSoon();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    window.addEventListener('hashchange', restoreSidebarSoon, { passive: true });
    window.addEventListener('beforeunload', saveCurrentSidebar);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            saveCurrentSidebar();
        }
    });
})();
