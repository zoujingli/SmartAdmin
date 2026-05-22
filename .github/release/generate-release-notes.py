#!/usr/bin/env python3
"""Generate SmartAdmin repository release notes.

本脚本供 SmartAdminDeveloper 与导出的公开仓共用：根据 tag 范围收集提交、文件变更、
仓库定位、代码能力进度和下载使用量统计，生成可直接写入 GitHub Release 的 Markdown。
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from collections import OrderedDict
from dataclasses import dataclass
from pathlib import Path
from typing import Any

PRODUCT = 'SmartAdmin'
DEVELOPER_NAME = PRODUCT + 'Developer'
DEVELOPER_REPO = 'zoujingli/' + DEVELOPER_NAME

GROUPS = OrderedDict([
    ('feat', '新增功能'),
    ('fix', '问题修复'),
    ('security', '安全权限'),
    ('frontend', '前端界面'),
    ('plugin', '插件体系'),
    ('release', '构建发布'),
    ('docs', '文档更新'),
    ('test', '测试质量'),
    ('refactor', '架构重构'),
    ('perf', '性能优化'),
    ('chore', '工程维护'),
    ('other', '其他变更'),
])

PREFIX_GROUPS = {
    'feat': 'feat',
    'feature': 'feat',
    'fix': 'fix',
    'bugfix': 'fix',
    'security': 'security',
    'sec': 'security',
    'front': 'frontend',
    'frontend': 'frontend',
    'ui': 'frontend',
    'plugin': 'plugin',
    'release': 'release',
    'build': 'release',
    'ci': 'release',
    'docs': 'docs',
    'doc': 'docs',
    'test': 'test',
    'tests': 'test',
    'refactor': 'refactor',
    'perf': 'perf',
    'pref': 'perf',
    'chore': 'chore',
    '新增': 'feat',
    '功能': 'feat',
    '修复': 'fix',
    '问题': 'fix',
    '安全': 'security',
    '权限': 'security',
    '前端': 'frontend',
    '页面': 'frontend',
    '插件': 'plugin',
    '发布': 'release',
    '构建': 'release',
    '文档': 'docs',
    '测试': 'test',
    '质量': 'test',
    '架构': 'refactor',
    '重构': 'refactor',
    '性能': 'perf',
    '优化': 'perf',
    '工程': 'chore',
    '维护': 'chore',
    '系统': 'feat',
    '数据': 'feat',
}

PREFIX_RE = re.compile(r'^(?P<prefix>[A-Za-z]+|[\u4e00-\u9fa5]{1,8})(?:\((?P<scope>[^)]+)\))?[:：]\s*(?P<title>.+)$')


@dataclass(frozen=True)
class RepositoryProfile:
    title: str
    package: str | None
    visibility: str
    license: str
    positioning: str
    release_scope: str
    capabilities: tuple[str, ...]
    patterns: tuple[tuple[str, tuple[str, ...]], ...]


COMMON_PATTERNS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ('GitHub Actions 与发布自动化', ('.github/',)),
    ('Composer 依赖与工程配置', ('composer.json', 'phpunit.xml', 'phpstan.neon', '.php-cs-fixer.php')),
    ('测试质量保障', ('tests/',)),
    ('文档与开源协作', ('README.md', 'readme.md', 'docs/', 'LICENSE', 'NOTICE')),
)

PROFILES: dict[str, RepositoryProfile] = {
    DEVELOPER_REPO: RepositoryProfile(
        title=DEVELOPER_NAME,
        package=None,
        visibility='Private：全量开发源，不作为开源安装入口；开源部分按 Apache-2.0 由 TAG 自动同步到公开仓。',
        license='Apache-2.0（公开部分）；私有/商用插件按内部授权分发。',
        positioning='唯一手工打 TAG 的发布源和生态维护入口，集中维护开源核心、基础包源码、构建器源码、公开插件、私有/商用插件与发布流水线。',
        release_scope='同步 SmartAdminLibrary、SmartAdminBuilder、SmartAdmin，并生成全部可安装插件 ZIP；私有/商用插件只进入私有 Release 或内部存储。',
        capabilities=(
            '多仓库同步发布编排与 Tag 对齐',
            '源码期插件 ZIP 打包、安装、移除、备份与恢复闭环',
            '私有/商用插件源码、授权、ZIP 分发与内部交付能力',
            '开源核心、文档、测试、前端构建和发布升级验证入口',
        ),
        patterns=(
            ('基础库能力', ('plugin/Library/',)),
            ('构建器能力', ('plugin/Builder/',)),
            ('系统基础插件', ('plugin/System/',)),
            ('微信开放插件', ('plugin/WechatClient/', 'plugin/WechatService/')),
            ('项目管理商用插件', ('plugin/Project/',)),
            ('Smart 调用池商用插件', ('plugin/Smart/',)),
            ('Web 通用壳与前端宿主', ('web/',)),
            ('发布导出与 ZIP 打包链路', ('.github/tools/' + 'release/', '.github/workflows/release.yml')),
            *COMMON_PATTERNS,
        ),
    ),
    'zoujingli/SmartAdmin': RepositoryProfile(
        title=PRODUCT,
        package='zoujingli/smartadmin',
        visibility='Public：Apache-2.0 开源主项目，面向社区提供可运行后台框架与公开插件源码。',
        license='Apache-2.0',
        positioning='普通用户直接使用和二次开发的开源主仓，也是 SmartAdmin 生态的公开入口；基于 Hyperf、Swoole、Vue 与 TypeScript 提供高性能前后端一体化后台框架。',
        release_scope='发布开源核心、Web 通用壳、公开插件、文档、测试与开源插件 ZIP；不包含私有/商用插件源码。',
        capabilities=(
            'RBAC 权限、数据范围、租户隔离和统一响应约定',
            'System、WechatClient、WechatService 等开源插件源码与页面',
            '源码环境可通过 ZIP 安装或使用 --force 更新插件，再重新执行 Composer 更新和前端构建',
            'Web 通用壳、插件路由/鉴权/菜单消费与前端构建宿主',
            '基于 SmartAdminLibrary 与 SmartAdminBuilder 的安装、构建和发布基础能力',
        ),
        patterns=(
            ('系统管理插件', ('plugin/System/',)),
            ('微信客户端插件', ('plugin/WechatClient/',)),
            ('微信开放平台插件', ('plugin/WechatService/',)),
            ('Web 通用壳与前端运行库', ('web/',)),
            ('应用配置与插件迁移', ('config/', 'plugin/System/stc/migrations/', 'plugin/WechatClient/stc/migrations/', 'plugin/WechatService/stc/migrations/')),
            *COMMON_PATTERNS,
        ),
    ),
    'zoujingli/SmartAdminLibrary': RepositoryProfile(
        title='SmartAdminLibrary',
        package='zoujingli/smart-admin-library',
        visibility='Public：Apache-2.0 开源基础库 Composer 包，供 SmartAdmin 及插件复用。',
        license='Apache-2.0',
        positioning='SmartAdmin 基础库，封装后端 Core CRUD、权限、数据范围、租户、日志审计、发布升级和插件 ZIP 管理能力。',
        release_scope='发布独立 Composer 包，供正式环境直接 composer require；源码只由 SmartAdminDeveloper 自动导出同步。',
        capabilities=(
            'CoreController/CoreService/CoreMapper/CoreModel 标准 CRUD 基座',
            'Auth 注解、统一响应、数据范围、租户上下文与日志审计',
            'Release 数据库快照、升级、恢复和 dry-run 支撑',
            '源码期插件 ZIP 打包、安装、移除、备份与恢复命令',
        ),
        patterns=(
            ('认证与统一响应', ('Auth/', 'Middleware/', 'Exception/', 'CoreController.php')),
            ('CRUD、数据范围与租户基础', ('CoreService.php', 'CoreMapper.php', 'CoreModel.php', 'Service/', 'Traits/')),
            ('日志审计与事件能力', ('Logger/', 'Events/')),
            ('发布升级与插件管理命令', ('Command/', 'Support/PluginManager/', 'Support/Release', 'config/autoload/release.php')),
            ('缓存、翻译与通用支撑', ('Cache/', 'Translation/', 'Helper/', 'Support/')),
            *COMMON_PATTERNS,
        ),
    ),
    'zoujingli/SmartAdminBuilder': RepositoryProfile(
        title='SmartAdminBuilder',
        package='zoujingli/smart-admin-builder',
        visibility='Public：Apache-2.0 开源构建器 Composer 包，供 SmartAdmin 发布打包复用。',
        license='Apache-2.0',
        positioning='SmartAdmin Phar/SFX 构建器，负责配置 AST 改写、源码加固、前端资源归档和二进制产物生成。',
        release_scope='发布独立 Composer 包，供正式环境直接 composer require；源码只由 SmartAdminDeveloper 自动导出同步。',
        capabilities=(
            'Hyperf 项目 Phar/SFX 打包入口与构建命令',
            '配置 AST 改写和生产配置收敛',
            '一方源码压缩、字符串处理与源码加固运行时',
            '前端 dist.zip 归档与二进制构建流程衔接',
        ),
        patterns=(
            ('AST 配置改写', ('Ast/',)),
            ('构建、源码加固与前端归档', ('Support/',)),
            ('构建命令与 Provider 注册', ('Command.php', 'Provider.php')),
            *COMMON_PATTERNS,
        ),
    ),
}


def run_git(*args: str, allow_fail: bool = False) -> str:
    try:
        return subprocess.check_output(['git', *args], text=True, stderr=subprocess.DEVNULL).strip()
    except subprocess.CalledProcessError:
        if allow_fail:
            return ''
        raise


def git_lines(*args: str) -> list[str]:
    out = run_git(*args, allow_fail=True)
    return [line for line in out.splitlines() if line]


def resolve_current_tag(explicit: str | None) -> str:
    tag = explicit or os.environ.get('CURRENT_TAG') or os.environ.get('GITHUB_REF_NAME') or ''
    if tag:
        return tag
    tag = run_git('describe', '--tags', '--exact-match', allow_fail=True)
    if tag:
        return tag
    raise SystemExit('CURRENT_TAG or tag argument is required.')


def resolve_previous_tag(current_tag: str) -> str:
    explicit = os.environ.get('PREVIOUS_TAG', '')
    if explicit:
        return explicit
    current_commit = run_git('rev-list', '-n', '1', current_tag, allow_fail=True)
    if not current_commit:
        return ''
    return run_git('describe', '--tags', '--abbrev=0', '--match', 'v*', f'{current_commit}^', allow_fail=True)


def commit_rows(current_tag: str, previous_tag: str) -> list[tuple[str, str]]:
    revision = f'{previous_tag}..{current_tag}' if previous_tag else current_tag
    rows: list[tuple[str, str]] = []
    for line in git_lines('log', '--pretty=format:%H%x01%s', revision):
        if '\x01' not in line:
            continue
        sha, subject = line.split('\x01', 1)
        rows.append((sha, subject))
    return rows


def changed_files(current_tag: str, previous_tag: str) -> list[str]:
    if previous_tag:
        return sorted(set(git_lines('diff', '--name-only', previous_tag, current_tag)))
    # 首个发布标签没有上一个 tag，用 Git 空树对比当前 tag，展示完整仓库能力覆盖。
    empty_tree = '4b825dc642cb6eb9a060e54bf8d69288fbee4904'
    return sorted(set(git_lines('diff', '--name-only', empty_tree, current_tag)))


def normalize_commit(subject: str) -> tuple[str, str]:
    match = PREFIX_RE.match(subject)
    if not match:
        return 'other', subject
    prefix = match.group('prefix')
    scope = match.group('scope')
    title = match.group('title')
    group = PREFIX_GROUPS.get(prefix.lower(), PREFIX_GROUPS.get(prefix, 'other'))
    if scope:
        title = f'【{scope}】{title}'
    return group, title


def capability_progress(profile: RepositoryProfile, files: list[str]) -> list[str]:
    if not files:
        return ['- 本次发布未检测到文件差异，可能是仅同步 Tag 或补充 Release 信息。']
    lines: list[str] = []
    matched: set[str] = set()
    for label, prefixes in profile.patterns:
        hits = [path for path in files if any(path == prefix or path.startswith(prefix) for prefix in prefixes)]
        if not hits:
            continue
        matched.update(hits)
        samples = '、'.join(hits[:5])
        if len(hits) > 5:
            samples += f' 等 {len(hits)} 个文件'
        lines.append(f'- {label}：涉及 {len(hits)} 个文件（{samples}）。')
    other_count = len([path for path in files if path not in matched])
    if other_count:
        lines.append(f'- 其他源码与配置：涉及 {other_count} 个文件。')
    return lines or [f'- 本次发布涉及 {len(files)} 个文件，主要为仓库结构或同步类调整。']


def http_json(url: str, token: str | None = None) -> Any:
    headers = {
        'Accept': 'application/vnd.github+json' if 'api.github.com' in url else 'application/json',
        'User-Agent': 'SmartAdmin-Release-Notes',
    }
    if token and 'api.github.com' in url:
        headers['Authorization'] = f'Bearer {token}'
        headers['X-GitHub-Api-Version'] = '2022-11-28'
    request = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(request, timeout=12) as response:  # nosec - release workflow reads public APIs / current GitHub repo only.
        return json.loads(response.read().decode('utf-8'))


def github_release_downloads(repository: str, token: str | None, current_tag: str) -> dict[str, Any]:
    result: dict[str, Any] = {
        'repository': None,
        'release_asset_downloads': None,
        'release_asset_count': None,
        'current_asset_downloads': None,
        'error': None,
    }
    try:
        result['repository'] = http_json(f'https://api.github.com/repos/{repository}', token)
        total_downloads = 0
        asset_count = 0
        current_downloads = 0
        page = 1
        while page <= 10:
            releases = http_json(f'https://api.github.com/repos/{repository}/releases?per_page=100&page={page}', token)
            if not releases:
                break
            for release in releases:
                assets = release.get('assets') or []
                release_downloads = sum(int(asset.get('download_count') or 0) for asset in assets)
                total_downloads += release_downloads
                asset_count += len(assets)
                if release.get('tag_name') == current_tag:
                    current_downloads += release_downloads
            if len(releases) < 100:
                break
            page += 1
        result['release_asset_downloads'] = total_downloads
        result['release_asset_count'] = asset_count
        result['current_asset_downloads'] = current_downloads
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, json.JSONDecodeError) as exc:
        result['error'] = str(exc)
    return result


def packagist_stats(package: str) -> dict[str, Any]:
    result: dict[str, Any] = {'package': package, 'downloads': None, 'favers': None, 'error': None}
    try:
        quoted = urllib.parse.quote(package, safe='/')
        data = http_json(f'https://packagist.org/packages/{quoted}.json')
        info = data.get('package') or {}
        result['downloads'] = info.get('downloads') or {}
        result['favers'] = info.get('favers')
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, json.JSONDecodeError) as exc:
        result['error'] = str(exc)
    return result


def format_int(value: Any) -> str:
    if value is None:
        return '暂不可用'
    try:
        return f'{int(value):,}'
    except (TypeError, ValueError):
        return str(value)


def stats_lines(repository: str, profile: RepositoryProfile, current_tag: str) -> list[str]:
    token = os.environ.get('GITHUB_TOKEN') or os.environ.get('GH_TOKEN')
    github = github_release_downloads(repository, token, current_tag)
    lines = ['### GitHub 仓库']
    repo = github.get('repository') if isinstance(github.get('repository'), dict) else None
    if repo:
        lines.extend([
            f"- Stars：{format_int(repo.get('stargazers_count'))}",
            f"- Forks：{format_int(repo.get('forks_count'))}",
            f"- Watchers：{format_int(repo.get('subscribers_count'))}",
            f"- Open Issues：{format_int(repo.get('open_issues_count'))}",
            f"- Release 资产累计下载：{format_int(github.get('release_asset_downloads'))}（资产数：{format_int(github.get('release_asset_count'))}）",
            f"- 当前 Tag Release 资产下载：{format_int(github.get('current_asset_downloads'))}",
        ])
    else:
        lines.append(f"- GitHub 统计暂不可用：{github.get('error') or 'unknown error'}")

    lines.append('')
    lines.append('### Composer / Packagist')
    if not profile.package:
        lines.append('- 当前仓库不作为公开 Composer 安装包统计。')
    else:
        package = packagist_stats(profile.package)
        downloads = package.get('downloads') if isinstance(package.get('downloads'), dict) else None
        if downloads:
            lines.extend([
                f"- 包名：`{profile.package}`",
                f"- Composer 总下载：{format_int(downloads.get('total'))}",
                f"- Composer 月下载：{format_int(downloads.get('monthly'))}",
                f"- Composer 日下载：{format_int(downloads.get('daily'))}",
                f"- Packagist Favorites：{format_int(package.get('favers'))}",
            ])
        else:
            lines.append(f"- `{profile.package}` 统计暂不可用：{package.get('error') or 'not published'}")
    lines.append('')
    lines.append('> 说明：GitHub 不提供源码 ZIP/clone 总下载量统计；这里记录 GitHub Release 资产下载量与 Packagist Composer 下载量。')
    return lines


def build_notes(repository: str, current_tag: str, previous_tag: str) -> str:
    profile = PROFILES.get(repository, PROFILES['zoujingli/SmartAdmin'])
    rows = commit_rows(current_tag, previous_tag)
    files = changed_files(current_tag, previous_tag)
    grouped: dict[str, list[str]] = {key: [] for key in GROUPS}
    for sha, subject in rows:
        group, title = normalize_commit(subject)
        grouped[group].append(f'- {title} ({sha[:8]})')

    compare_url = f'https://github.com/{repository}/compare/{previous_tag}...{current_tag}' if previous_tag else ''
    lines: list[str] = [f'# {profile.title} {current_tag}', '']
    lines.extend([
        '## 仓库定位',
        '',
        f'- 仓库：`{repository}`',
        f'- 开源属性：{profile.visibility}',
        f'- 授权协议：{profile.license}',
        f"- Composer 包：`{profile.package}`" if profile.package else '- Composer 包：不适用',
        f'- 发布范围：{profile.release_scope}',
        f'- 定位说明：{profile.positioning}',
        '',
        '### 代码能力基线',
    ])
    lines.extend(f'- {item}' for item in profile.capabilities)
    lines.extend(['', '## 代码能力进度', ''])
    lines.extend(capability_progress(profile, files))

    lines.extend(['', '## 自动收集的变更记录', ''])
    if previous_tag:
        lines.append(f'- 对比范围：[`{previous_tag}...{current_tag}`]({compare_url})')
    else:
        lines.append('- 对比范围：首次发布标签')
    lines.append(f'- 提交数量：{len(rows)}')
    lines.append(f'- 变更文件：{len(files)}')
    lines.append('')
    summary = [f'{label} {len(items)} 项' for key, label in GROUPS.items() if (items := grouped[key])]
    lines.append('、'.join(summary) if summary else '- 本次发布没有检测到提交变更。')
    lines.append('')
    for key, label in GROUPS.items():
        items = grouped[key]
        if not items:
            continue
        lines.append(f'### {label}')
        lines.extend(items)
        lines.append('')

    lines.extend(['## 下载与使用量统计', ''])
    lines.extend(stats_lines(repository, profile, current_tag))

    lines.extend([
        '',
        '## 发布说明',
        '',
        '- Release 信息由 GitHub Actions 在 Tag 推送后自动生成或更新。',
        '- 统计数据在 Release 生成时采集，后续下载增长会在下一次生成 Release 信息时刷新。',
        '- 正式环境请优先使用已发布的 Composer 包；SmartAdminDeveloper 不作为安装入口，私有/商用插件仍只通过 ZIP 分发。',
        '',
    ])
    return '\n'.join(lines)


def main() -> None:
    parser = argparse.ArgumentParser(description='Generate SmartAdmin release notes.')
    parser.add_argument('--tag', default=None, help='Current release tag. Defaults to CURRENT_TAG/GITHUB_REF_NAME.')
    parser.add_argument('--repository', default=os.environ.get('GITHUB_REPOSITORY', 'zoujingli/SmartAdmin'))
    parser.add_argument('--output', default=None, help='Output markdown path. Defaults to log/<tag>.md.')
    args = parser.parse_args()

    current_tag = resolve_current_tag(args.tag)
    previous_tag = resolve_previous_tag(current_tag)
    notes = build_notes(args.repository, current_tag, previous_tag)
    output = Path(args.output) if args.output else Path('log') / f'{current_tag}.md'
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(notes, encoding='utf-8')
    print(output)


if __name__ == '__main__':
    main()
