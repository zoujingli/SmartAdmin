#!/bin/bash

set -euo pipefail

cd "$(dirname "$0")"

# SmartAdmin Docker/1Panel 发布模板。实例连接信息保留在被 .gitignore 排除的 start_*.sh 中。
USER="root"
PASS="PASSWORD"
SERVER="10.10.10.1"

LOCAL_FILE="../system-linux-x64"
REMOTE_TEMP="/tmp/system-linux-x64"
REMOTE_FILE="/www/1panel/apps/smart/system-linux-x64"
CONTAINER_PATH="/app/system-linux-x64"
CONTAINER_NAME="smart-server"
HEALTH_URL="https://example.com/"

SSH_OPTS=(
    -o StrictHostKeyChecking=accept-new
    -o UserKnownHostsFile=/dev/null
    -o ConnectTimeout=10
    -o ServerAliveInterval=30
    -o ServerAliveCountMax=10
    -o LogLevel=ERROR
)

mkdir -p logs
LOG_FILE="logs/$(basename "$0" .sh).$(date +%Y%m%d%H%M%S).log"
exec > >(tee -a "$LOG_FILE") 2>&1

log() {
    printf '\033[32m[INFO]\033[0m %s\n' "$1"
}

die() {
    printf '\033[31m[ERROR]\033[0m %s\n' "$1" >&2
    exit 1
}

retry() {
    local attempt=1
    local max_attempts=3
    local delay=5
    while true; do
        "$@" && return 0
        if [ "$attempt" -ge "$max_attempts" ]; then
            return 1
        fi
        log "命令失败，${delay}s 后重试 (${attempt}/${max_attempts})"
        sleep "$delay"
        attempt=$((attempt + 1))
    done
}

step() {
    local desc="$1"
    shift
    log "$desc"
    retry "$@" || die "$desc 失败"
}

local_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    else
        shasum -a 256 "$1" | awk '{print $1}'
    fi
}

ssh_run() {
    sshpass -p "$PASS" ssh "${SSH_OPTS[@]}" "$USER@$SERVER" "$1"
}

upload() {
    sshpass -p "$PASS" scp "${SSH_OPTS[@]}" "$LOCAL_FILE" "$USER@$SERVER:$REMOTE_TEMP"
}

remote_upgrade() {
    local expected_sha="$1"
    sshpass -p "$PASS" ssh "${SSH_OPTS[@]}" "$USER@$SERVER" "bash -se" <<EOF_REMOTE
set -euo pipefail
timestamp="\$(date +%Y%m%d%H%M%S)"
binary_backup="$REMOTE_FILE.previous.\$timestamp"
preflight_report="/tmp/smartadmin-release-\$timestamp-preflight.json"
apply_report="/tmp/smartadmin-release-\$timestamp-apply.json"
verify_report="/tmp/smartadmin-release-\$timestamp-verify.json"
migration_report="/tmp/smartadmin-release-\$timestamp-project-migration.json"
closure_report="/tmp/smartadmin-release-\$timestamp-project-closure.json"

[ -f "$REMOTE_TEMP" ] || { echo "远端临时文件不存在: $REMOTE_TEMP" >&2; exit 1; }
actual_sha="\$(sha256sum "$REMOTE_TEMP" | awk '{print \$1}')"
[ "\$actual_sha" = "$expected_sha" ] || { echo "上传文件 SHA-256 不一致" >&2; exit 1; }
cp -p "$REMOTE_FILE" "\$binary_backup"
install -m 0755 "$REMOTE_TEMP" "$REMOTE_FILE.new"
docker stop "$CONTAINER_NAME" >/dev/null 2>&1 || true
mv -f "$REMOTE_FILE.new" "$REMOTE_FILE"
docker start "$CONTAINER_NAME" >/dev/null
docker exec "$CONTAINER_NAME" sh -lc "rm -rf '${CONTAINER_PATH%/*}/runtime/container'"
docker restart "$CONTAINER_NAME" >/dev/null
sleep 2

if ! docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:release:restore --install --dry-run --json >"\$preflight_report" 2>&1; then
    cat "\$preflight_report" >&2
    docker stop "$CONTAINER_NAME" >/dev/null 2>&1 || true
    install -m 0755 "\$binary_backup" "$REMOTE_FILE"
    docker start "$CONTAINER_NAME" >/dev/null
    docker exec "$CONTAINER_NAME" sh -lc "rm -rf '${CONTAINER_PATH%/*}/runtime/container'"
    docker restart "$CONTAINER_NAME" >/dev/null
    echo "发布预检失败，数据库尚未写入，已恢复旧二进制" >&2
    exit 1
fi
cat "\$preflight_report"

if ! docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:release:restore --install --force --json >"\$apply_report" 2>&1; then
    cat "\$apply_report" >&2
    echo "数据库升级已经开始，不自动回滚；请使用发布报告中的全量备份处理" >&2
    exit 1
fi
cat "\$apply_report"
grep -q '"pre_upgrade_backup": {' "\$apply_report" || { echo "发布报告缺少升级前全量备份" >&2; exit 1; }

docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:website:publish
docker restart "$CONTAINER_NAME" >/dev/null
sleep 3

docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:release:restore --install --dry-run --json >"\$verify_report"
docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:project:task-acceptance:migrate --all-tenants --dry-run --json >"\$migration_report"
docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:project:task-closure:repair --dry-run --limit=1000 --json >"\$closure_report"
cat "\$verify_report"
cat "\$migration_report"
cat "\$closure_report"
grep -q '"safe_sql": \[\]' "\$verify_report"
grep -q '"destructive_sql": \[\]' "\$verify_report"
grep -q '"code": "project.30.task_case_import_permission.v1"' "\$verify_report"
grep -q '"project_config_candidates": 0' "\$verify_report"
grep -q '"system_role_candidates": 0' "\$verify_report"
grep -q '"target_node_pending_sync": false' "\$verify_report"
! grep -q '"required": true' "\$verify_report"
grep -q '"required": false' "\$migration_report"
grep -q '"blocking": \[\]' "\$migration_report"
grep -q '"candidates": 0' "\$closure_report"

echo "binary_sha256=$expected_sha"
echo "binary_backup=\$binary_backup"
echo "preflight_report=\$preflight_report"
echo "apply_report=\$apply_report"
echo "verify_report=\$verify_report"
EOF_REMOTE
}

health_check() {
    local api_body
    curl -kfsS --max-time 15 "$HEALTH_URL" >/dev/null
    curl -kfsS --max-time 15 "${HEALTH_URL%/}/project/login" >/dev/null
    api_body="$(curl -kfsS --max-time 15 "${HEALTH_URL%/}/system/user/index")"
    printf '%s' "$api_body" | grep -Eq '"code"[[:space:]]*:[[:space:]]*401'
}

for cmd in sshpass ssh scp curl; do
    command -v "$cmd" >/dev/null 2>&1 || die "缺少依赖命令: $cmd"
done
[ -f "$LOCAL_FILE" ] || die "本地升级文件不存在: $LOCAL_FILE"

LOCAL_SHA256="$(local_sha256 "$LOCAL_FILE")"
log "发布二进制 SHA-256: $LOCAL_SHA256"
step "1/4 清理远端临时文件" ssh_run "rm -f '$REMOTE_TEMP'"
step "2/4 上传程序文件" upload
log "3/4 发布预检、全量备份、结构与数据升级"
remote_upgrade "$LOCAL_SHA256" || die "远端升级失败，已停止后续环境发布"
step "4/4 公网健康检查" health_check

log "升级完成，日志: $LOG_FILE"
